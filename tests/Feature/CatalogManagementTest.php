<?php

use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
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
    $this->get(route('products.create'))->assertOk();
    $this->get(route('products.edit', $product))->assertOk();

    $this->get(route('packages.index'))->assertOk();
    $this->get(route('packages.create'))->assertOk();
    $this->get(route('packages.edit', $package))->assertOk();
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
    Storage::fake('public');

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
        ->and($product->cover_photo)->not->toBeNull()
        ->and(count($product->gallery))->toBe(2);

    Storage::disk('public')->assertExists($product->cover_photo);
    Storage::disk('public')->assertExists($product->gallery[0]);
});

test('operator can edit product and delete gallery photos', function () {
    Storage::fake('public');

    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Original Kayak',
        'cover_photo' => 'products/covers/sample.jpg',
        'gallery' => ['products/gallery/img1.jpg', 'products/gallery/img2.jpg'],
    ]);

    Storage::disk('public')->put('products/covers/sample.jpg', 'fake image content');
    Storage::disk('public')->put('products/gallery/img1.jpg', 'fake image content 1');
    Storage::disk('public')->put('products/gallery/img2.jpg', 'fake image content 2');

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

    Storage::disk('public')->assertMissing('products/gallery/img1.jpg');
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

test('operator can create a package with product composition and cover image', function () {
    Storage::fake('public');

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
        ->and($package->cover_photo)->not->toBeNull();

    Storage::disk('public')->assertExists($package->cover_photo);
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

test('operator can delete product from inside edit page and index modal', function () {
    Storage::fake('public');

    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'To Delete Product',
        'cover_photo' => 'products/covers/sample.jpg',
    ]);
    Storage::disk('public')->put('products/covers/sample.jpg', 'content');

    Livewire::test('pages::products.edit', ['product' => $product])
        ->call('delete')
        ->assertRedirect(route('products.index'));

    expect(Product::where('id', $product->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('products/covers/sample.jpg');

    // Test index modal deletion
    $product2 = Product::factory()->create(['operator_id' => $this->operator->id, 'name' => 'Index Delete Product']);
    Livewire::test('pages::products.index')
        ->call('confirmDelete', $product2->id, $product2->name)
        ->assertSet('deletingProductId', $product2->id)
        ->call('deleteConfirmed');

    expect(Product::where('id', $product2->id)->exists())->toBeFalse();
});

test('operator can delete package from inside edit page and index modal', function () {
    Storage::fake('public');

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'To Delete Package',
        'cover_photo' => 'packages/covers/sample.jpg',
    ]);
    Storage::disk('public')->put('packages/covers/sample.jpg', 'content');

    Livewire::test('pages::packages.edit', ['package' => $package])
        ->call('delete')
        ->assertRedirect(route('packages.index'));

    expect(Package::where('id', $package->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('packages/covers/sample.jpg');

    // Test index modal deletion
    $package2 = Package::factory()->create(['operator_id' => $this->operator->id, 'title' => 'Index Delete Package']);
    Livewire::test('pages::packages.index')
        ->call('confirmDelete', $package2->id, $package2->title)
        ->assertSet('deletingPackageId', $package2->id)
        ->call('deleteConfirmed');

    expect(Package::where('id', $package2->id)->exists())->toBeFalse();
});
