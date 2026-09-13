<?php

use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Services\MediaStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'bio' => 'Verified tour operator providing curated experiences and services.',
        'contact_whatsapp' => '+628123456789',
        'bank_account_ref' => 'BCA-1234567890',
        'terms_and_conditions' => 'Standard tour operator cancellation and safety policy.',
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($this->user);
});

test('operator can view products and packages list, create and edit pages', function () {
    $product = Product::factory()->create(['operator_id' => $this->operator->id]);
    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $this->get(route('products.index'))->assertOk();

    $this->get(route('products.create'))
        ->assertOk()
        ->assertSee('op-back-link', false)
        ->assertSee(__('Back to activities'));

    $this->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('op-back-link', false)
        ->assertSee(__('Back to activities'))
        ->assertSee($product->name);

    $this->get(route('packages.index'))->assertOk();

    $this->get(route('packages.create'))
        ->assertOk()
        ->assertSee('op-back-link', false)
        ->assertSee(__('Back to packages'));

    $this->get(route('packages.edit', $package))
        ->assertOk()
        ->assertSee('op-back-link', false)
        ->assertSee(__('Back to packages'))
        ->assertSee($package->title);
});

test('product creation is blocked if profile and terms are incomplete', function () {
    $this->operator->update(['terms_and_conditions' => null]);

    Livewire::test('pages::products.create')
        ->set('name', 'Equipment Unit')
        ->set('capacity_per_day', 10)
        ->call('save')
        ->assertHasErrors(['profile']);

    expect(Product::where('name', 'Equipment Unit')->exists())->toBeFalse();
});

test('operator with completed profile can create an inventory product with cover and gallery images', function () {
    Storage::fake(MediaStore::diskName());

    $cover = UploadedFile::fake()->image('cover.jpg', 600, 400);
    $gallery1 = UploadedFile::fake()->image('gallery1.jpg', 600, 400);
    $gallery2 = UploadedFile::fake()->image('gallery2.jpg', 600, 400);

    Livewire::test('pages::products.create')
        ->set('name', 'Speedboat Transfer Slot')
        ->set('category', 'Transportation')
        ->set('capacity_per_day', 15)
        ->set('sellable_standalone', true)
        ->set('price', 250000)
        ->set('free_cancellation_hours', 24)
        ->set('advance_booking_hours', 12)
        ->set('status', 'published')
        ->set('coverPhoto', $cover)
        ->set('galleryFiles', [$gallery1, $gallery2])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.index'));

    $product = Product::where('operator_id', $this->operator->id)->where('name', 'Speedboat Transfer Slot')->first();
    expect($product)->not->toBeNull()
        ->and($product->capacity_per_day)->toBe(15)
        ->and((float) $product->price)->toBe(250000.0)
        ->and($product->sellable_standalone)->toBeTrue()
        ->and($product->status)->toBe(ListingStatus::Published)
        ->and($product->cover_photo)->toStartWith('operators/'.$this->operator->id.'/products/covers/')
        ->and($product->cover_photo)->toEndWith('.webp')
        ->and(count($product->gallery))->toBe(2);

    Storage::disk(MediaStore::diskName())->assertExists($product->cover_photo);
    Storage::disk(MediaStore::diskName())->assertExists($product->gallery[0]);
    expect($product->gallery[0])->toStartWith('operators/'.$this->operator->id.'/products/gallery/')
        ->and($product->gallery[0])->toEndWith('.webp');
});

test('operator can edit product and delete gallery photos', function () {
    Storage::fake(MediaStore::diskName());

    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Original Kayak',
        'cover_photo' => 'products/covers/sample.jpg',
        'gallery' => ['products/gallery/img1.jpg', 'products/gallery/img2.jpg'],
    ]);

    Storage::disk(MediaStore::diskName())->put('products/covers/sample.jpg', 'fake image content');
    Storage::disk(MediaStore::diskName())->put('products/gallery/img1.jpg', 'fake image content 1');
    Storage::disk(MediaStore::diskName())->put('products/gallery/img2.jpg', 'fake image content 2');

    Livewire::test('pages::products.edit', ['product' => $product])
        ->assertSet('name', 'Original Kayak')
        ->set('name', 'Updated Clear Kayak')
        ->call('removeExistingGalleryImage', 0)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: __('Activity item ":name" updated successfully.', ['name' => 'Updated Clear Kayak']), type: 'success');

    $product->refresh();
    expect($product->name)->toBe('Updated Clear Kayak')
        ->and(count($product->gallery))->toBe(1)
        ->and($product->gallery[0])->toBe('products/gallery/img2.jpg');

    Storage::disk(MediaStore::diskName())->assertMissing('products/gallery/img1.jpg');
});

test('package creation is blocked if profile and terms are incomplete', function () {
    $this->operator->update(['terms_and_conditions' => null]);

    Livewire::test('pages::packages.create')
        ->set('title', 'Island Excursion Package')
        ->set('price', 500000)
        ->call('save')
        ->assertHasErrors(['profile']);

    expect(Package::where('title', 'Island Excursion Package')->exists())->toBeFalse();
});

test('package create warns when price is not cheaper than linked activities separately', function () {
    $activityA = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Snorkel Session',
        'price' => 300000,
        'status' => ListingStatus::Published,
    ]);

    $activityB = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Temple Walk',
        'price' => 200000,
        'status' => ListingStatus::Published,
    ]);

    $warningSnippet = 'Guests may prefer booking these activities one by one';
    $dealSnippet = 'Smart price suggestion';

    Livewire::test('pages::packages.create')
        ->set('selectedProducts', [
            $activityA->id => 1,
            $activityB->id => 1,
        ])
        ->set('price', 499000)
        ->assertSee($dealSnippet)
        ->assertSee('Guest saves')
        ->assertDontSee($warningSnippet)
        ->set('price', 500000)
        ->assertSee($warningSnippet)
        ->assertSee('Price looks high')
        ->assertSee('Rp 500.000')
        ->set('price', 650000)
        ->assertSee($warningSnippet)
        ->assertSee('Rp 650.000')
        ->set('title', 'Overpriced Combo')
        ->set('category', 'Day Tour')
        ->set('location', 'Bali')
        ->set('status', 'published')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('packages.index'));

    expect(Package::query()->where('title', 'Overpriced Combo')->exists())->toBeTrue();
});

test('package edit shows and clears the bundle price warning', function () {
    $activity = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Reef Dive',
        'price' => 400000,
        'status' => ListingStatus::Published,
    ]);

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Dive Bundle',
        'price' => 350000,
    ]);
    $package->products()->attach($activity->id, ['quantity_required' => 1]);

    $warningSnippet = 'Guests may prefer booking these activities one by one';
    $dealSnippet = 'Smart price suggestion';

    Livewire::test('pages::packages.edit', ['package' => $package])
        ->assertSee($dealSnippet)
        ->assertDontSee($warningSnippet)
        ->set('price', 400000)
        ->assertSee($warningSnippet)
        ->set('price', 399000)
        ->assertSee($dealSnippet)
        ->assertDontSee($warningSnippet);
});

test('package create suggests a 15 percent price inside a 10–20 percent band', function () {
    $activityA = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Boat Transfer',
        'price' => 600000,
        'status' => ListingStatus::Published,
    ]);

    $activityB = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Guide Hire',
        'price' => 400000,
        'status' => ListingStatus::Published,
    ]);

    // SeparateTotal = 1.000.000 → Low 800k (20%), Mid 850k (15%), High 900k (10%)
    Livewire::test('pages::packages.create')
        ->set('selectedProducts', [
            $activityA->id => 1,
            $activityB->id => 1,
        ])
        ->set('price', 1000000)
        ->assertSee('Price looks high')
        ->assertSee('Rp 800.000')
        ->assertSee('Rp 900.000')
        ->assertSee('Suggested: Rp 850.000')
        ->assertDontSee('15%')
        ->assertDontSee('10–20%')
        ->call('applySuggestedBundlePrice')
        ->assertSet('price', 850000.0)
        ->assertSet('bundlePriceSuggestionDismissed', true)
        ->assertDispatched('toast', message: __('Package price set to the suggested amount.'), type: 'success')
        ->assertDontSee('Smart price suggestion')
        ->assertDontSee('Price looks high');
});

test('suggested package price rounds down to the nearest Rp 10.000', function () {
    $activity = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Odd Price Trek',
        'price' => 733000,
        'status' => ListingStatus::Published,
    ]);

    // 733000 × 0.85 = 623050 → floor 620000; ×0.80 → 580000; ×0.90 → 650000
    Livewire::test('pages::packages.create')
        ->set('selectedProducts', [$activity->id => 1])
        ->assertSee('Rp 580.000')
        ->assertSee('Rp 650.000')
        ->assertSee('Suggested: Rp 620.000')
        ->call('applySuggestedBundlePrice')
        ->assertSet('price', 620000.0)
        ->assertDontSee('Smart price suggestion');
});

test('package create can fill gallery from selected activity photos', function () {
    Storage::fake(MediaStore::diskName());

    $galleryA = [
        'operators/'.$this->operator->id.'/products/gallery/a1.webp',
        'operators/'.$this->operator->id.'/products/gallery/a2.webp',
        'operators/'.$this->operator->id.'/products/gallery/a3.webp',
    ];
    $galleryB = [
        'operators/'.$this->operator->id.'/products/gallery/b1.webp',
    ];

    foreach ([...$galleryA, ...$galleryB] as $path) {
        Storage::disk(MediaStore::diskName())->put($path, 'fake-image');
    }

    $activityA = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Snorkel Run',
        'gallery' => $galleryA,
        'status' => ListingStatus::Published,
    ]);

    $activityB = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Temple Stop',
        'gallery' => $galleryB,
        'status' => ListingStatus::Published,
    ]);

    $component = Livewire::test('pages::packages.create')
        ->set('selectedProducts', [
            $activityA->id => 1,
            $activityB->id => 1,
        ])
        ->call('fillGalleryFromActivities')
        ->assertDispatched('toast', message: __('Added :count photos from selected activities.', ['count' => 3]), type: 'success');

    expect($component->get('existingGallery'))->toHaveCount(3);

    foreach ($component->get('existingGallery') as $path) {
        expect($path)->toStartWith('operators/'.$this->operator->id.'/packages/gallery/')
            ->and(Storage::disk(MediaStore::diskName())->exists($path))->toBeTrue();
    }

    $component
        ->call('fillGalleryFromActivities')
        ->assertDispatched('toast', message: __('Those activity photos are already in the gallery.'), type: 'info');

    expect($component->get('existingGallery'))->toHaveCount(3);
});

test('package create can set cover from a selected activity cover', function () {
    Storage::fake(MediaStore::diskName());

    $coverPath = 'operators/'.$this->operator->id.'/products/covers/activity-cover.webp';
    Storage::disk(MediaStore::diskName())->put($coverPath, 'fake-cover');

    $activity = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Cover Source Activity',
        'cover_photo' => $coverPath,
        'status' => ListingStatus::Published,
    ]);

    $component = Livewire::test('pages::packages.create')
        ->set('selectedProducts', [
            $activity->id => 1,
        ])
        ->call('applyActivityCover', $activity->id)
        ->assertDispatched('toast', message: __('Cover photo set from the activity.'), type: 'success')
        ->assertDispatched('close-modal', 'choose-activity-cover');

    $packageCover = $component->get('existingCoverPhoto');

    expect($packageCover)->toStartWith('operators/'.$this->operator->id.'/packages/covers/')
        ->and(Storage::disk(MediaStore::diskName())->exists($packageCover))->toBeTrue()
        ->and($component->get('coverPhoto'))->toBeNull();
});

test('operator can create a package with product composition and cover image', function () {
    Storage::fake(MediaStore::diskName());

    $productA = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Speedboat Seat',
        'capacity_per_day' => 20,
    ]);

    $productB = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Certified Guide Slot',
        'capacity_per_day' => 8,
    ]);

    $cover = UploadedFile::fake()->image('package-cover.jpg', 800, 500);

    Livewire::test('pages::packages.create')
        ->set('title', 'All-Inclusive Island Safari')
        ->set('category', 'Experience')
        ->set('location', 'Island Destination')
        ->set('price', 650000)
        ->set('selectedProducts', [
            $productA->id => 2,
            $productB->id => 1,
        ])
        ->set('coverPhoto', $cover)
        ->set('free_cancellation_hours', 48)
        ->set('advance_booking_hours', 24)
        ->set('status', 'published')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('packages.index'));

    $package = Package::where('operator_id', $this->operator->id)->where('title', 'All-Inclusive Island Safari')->first();
    expect($package)->not->toBeNull()
        ->and((float) $package->price)->toBe(650000.0)
        ->and($package->products()->count())->toBe(2)
        ->and($package->cover_photo)->toStartWith('operators/'.$this->operator->id.'/packages/covers/')
        ->and($package->cover_photo)->toEndWith('.webp');

    Storage::disk(MediaStore::diskName())->assertExists($package->cover_photo);
});

test('operator can edit a package and manage inventory composition', function () {
    $product = Product::factory()->create(['operator_id' => $this->operator->id]);
    $package = Package::factory()->create(['operator_id' => $this->operator->id, 'title' => 'Old Title']);
    $package->products()->attach($product->id, ['quantity_required' => 1]);

    Livewire::test('pages::packages.edit', ['package' => $package])
        ->assertSet('title', 'Old Title')
        ->set('title', 'New Expedition Title')
        ->set('price', 990000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: __('Tour package ":title" updated successfully.', ['title' => 'New Expedition Title']), type: 'success');

    $package->refresh();
    expect($package->title)->toBe('New Expedition Title')
        ->and((float) $package->price)->toBe(990000.0);
});

test('editing a package persists changes to its activity composition', function () {
    $original = Product::factory()->create(['operator_id' => $this->operator->id]);
    $added = Product::factory()->create(['operator_id' => $this->operator->id]);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);
    $package->products()->attach($original->id, ['quantity_required' => 1]);

    Livewire::test('pages::packages.edit', ['package' => $package])
        ->set('selectedProducts', [$added->id => 3])
        ->call('save')
        ->assertHasNoErrors();

    $package->refresh()->load('products');

    expect($package->products)->toHaveCount(1)
        ->and($package->products->first()->id)->toBe($added->id)
        ->and((int) $package->products->first()->pivot->quantity_required)->toBe(3);
});

test('a large activity catalog is searched instead of listed on package edit', function () {
    Product::factory()->count(9)->create(['operator_id' => $this->operator->id]);
    $hidden = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Zebra Night Dive Unique',
    ]);
    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $component = Livewire::test('pages::packages.edit', ['package' => $package])
        ->assertSee('Search activities to add')
        ->assertSee('10 more activities in your catalog')
        ->assertDontSee('Zebra Night Dive Unique');

    $component->set('bundleSearch', 'Zebra Night')
        ->assertSee('Zebra Night Dive Unique')
        ->call('toggleProductSelection', $hidden->id)
        ->assertSet('bundleSearch', '')
        ->assertSee('In this package (1)');
});

test('a small activity catalog still lists activities on package create', function () {
    $activity = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Guided Reef Snorkel',
    ]);

    Livewire::test('pages::packages.create')
        ->assertSee('Guided Reef Snorkel')
        ->assertDontSee('more activities in your catalog')
        ->call('toggleProductSelection', $activity->id)
        ->assertSee('In this package (1)');
});

test('operator can delete product from inside edit page and index modal', function () {
    Storage::fake(MediaStore::diskName());

    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'To Delete Product',
        'cover_photo' => 'products/covers/sample.jpg',
    ]);
    Storage::disk(MediaStore::diskName())->put('products/covers/sample.jpg', 'content');

    Livewire::test('pages::products.edit', ['product' => $product])
        ->call('delete')
        ->assertRedirect(route('products.index'));

    expect(Product::where('id', $product->id)->exists())->toBeFalse();
    Storage::disk(MediaStore::diskName())->assertMissing('products/covers/sample.jpg');

    // Test index modal deletion
    $product2 = Product::factory()->create(['operator_id' => $this->operator->id, 'name' => 'Index Delete Product']);
    Livewire::test('pages::products.index')
        ->call('confirmDelete', $product2->id, $product2->name)
        ->assertSet('deletingProductId', $product2->id)
        ->call('deleteConfirmed');

    expect(Product::where('id', $product2->id)->exists())->toBeFalse();
});

test('operator can delete package from inside edit page and index modal', function () {
    Storage::fake(MediaStore::diskName());

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'To Delete Package',
        'cover_photo' => 'packages/covers/sample.jpg',
    ]);
    Storage::disk(MediaStore::diskName())->put('packages/covers/sample.jpg', 'content');

    Livewire::test('pages::packages.edit', ['package' => $package])
        ->call('delete')
        ->assertRedirect(route('packages.index'));

    expect(Package::where('id', $package->id)->exists())->toBeFalse();
    Storage::disk(MediaStore::diskName())->assertMissing('packages/covers/sample.jpg');

    // Test index modal deletion
    $package2 = Package::factory()->create(['operator_id' => $this->operator->id, 'title' => 'Index Delete Package']);
    Livewire::test('pages::packages.index')
        ->call('confirmDelete', $package2->id, $package2->title)
        ->assertSet('deletingPackageId', $package2->id)
        ->call('deleteConfirmed');

    expect(Package::where('id', $package2->id)->exists())->toBeFalse();
});
