<?php

use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Mail\VendorBookingNotificationMail;
use App\Models\Operator;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'bio' => 'Verified tour operator providing curated experiences.',
        'contact_whatsapp' => '+628123456789',
        'bank_account_ref' => 'BCA-1234567890',
        'terms_and_conditions' => 'Standard terms and conditions.',
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($this->user);
});

test('operator can view vendors index and manage vendors', function () {
    $vendor = Vendor::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Nusa Dive Center',
        'reservation_email' => 'booking@nusadive.com',
    ]);

    $this->get(route('vendors.index'))
        ->assertOk()
        ->assertSee('Nusa Dive Center')
        ->assertSee('booking@nusadive.com');

    // Test creating a vendor via Livewire
    Livewire::test('pages::vendors.index')
        ->set('name', 'Bali ATV Adventures')
        ->set('contact_person', 'Ketut')
        ->set('reservation_email', 'atv@balitrips.com')
        ->set('phone', '+628111222333')
        ->set('bank_name', 'BCA')
        ->set('bank_account_number', '9876543210')
        ->set('bank_account_holder', 'PT Bali ATV')
        ->call('save')
        ->assertHasNoErrors();

    expect(Vendor::where('name', 'Bali ATV Adventures')->exists())->toBeTrue();
    $newVendor = Vendor::where('name', 'Bali ATV Adventures')->first();
    expect($newVendor->reservation_email)->toBe('atv@balitrips.com')
        ->and($newVendor->payout_details['bank_name'])->toBe('BCA');

    // Test editing vendor
    Livewire::test('pages::vendors.index')
        ->call('editVendor', $vendor->id)
        ->set('name', 'Nusa Premium Dive Center')
        ->call('save')
        ->assertHasNoErrors();

    expect($vendor->fresh()->name)->toBe('Nusa Premium Dive Center');

    // Test deleting vendor
    Livewire::test('pages::vendors.index')
        ->call('confirmDelete', $vendor->id)
        ->call('deleteVendor')
        ->assertHasNoErrors();

    expect(Vendor::find($vendor->id))->toBeNull();
});

test('operator can create vendor inline during activity creation', function () {
    Livewire::test('pages::products.create')
        ->set('new_vendor_name', 'Ubud Rafting Co')
        ->set('new_vendor_email', 'dispatch@ubudrafting.com')
        ->set('new_vendor_phone', '+628999888777')
        ->set('new_vendor_contact', 'Made')
        ->call('quickCreateVendor')
        ->assertHasNoErrors();

    $vendor = Vendor::where('name', 'Ubud Rafting Co')->first();
    expect($vendor)->not->toBeNull()
        ->and($vendor->reservation_email)->toBe('dispatch@ubudrafting.com');

    // Complete creating product with this vendor
    Livewire::test('pages::products.create')
        ->set('name', 'Ayung River Rafting')
        ->set('category', 'Water Sports')
        ->set('capacity_per_day', 15)
        ->set('sellable_standalone', true)
        ->set('price', 350000)
        ->set('vendor_id', $vendor->id)
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Ayung River Rafting')->first();
    expect($product)->not->toBeNull()
        ->and($product->vendor_id)->toBe($vendor->id);
});

test('operator can assign and remove vendor when editing activity', function () {
    $vendor = Vendor::factory()->create(['operator_id' => $this->operator->id]);
    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'vendor_id' => null,
    ]);

    Livewire::test('pages::products.edit', ['product' => $product])
        ->set('vendor_id', $vendor->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh()->vendor_id)->toBe($vendor->id);

    Livewire::test('pages::products.edit', ['product' => $product])
        ->set('vendor_id', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh()->vendor_id)->toBeNull();
});

test('vendor dispatch service can send test notification', function () {
    Mail::fake();

    $vendor = Vendor::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Sanur Watersports',
        'reservation_email' => 'booking@sanurwatersports.com',
    ]);

    app(VendorDispatchService::class)->sendTestNotification($vendor, $this->operator);

    Mail::assertQueued(VendorBookingNotificationMail::class, function ($mail) use ($vendor) {
        return $mail->hasTo('booking@sanurwatersports.com')
            && $mail->vendor->id === $vendor->id
            && $mail->isTest === true;
    });
});
