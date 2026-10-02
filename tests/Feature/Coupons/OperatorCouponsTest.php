<?php

use App\Enums\PaymentStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PlatformCoupon;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('operator can view coupons management page', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $user->operators()->attach($operator->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get(route('coupons.index'))
        ->assertOk()
        ->assertSee('Coupons & Promo Codes');
});

test('operator can create a storefront promo code', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $user->operators()->attach($operator->id, ['role' => 'owner']);

    Livewire::actingAs($user)
        ->test('pages::coupons.index')
        ->set('code', 'SUMMER26')
        ->set('description', 'Summer promo 15% off')
        ->set('discount_type', 'percentage')
        ->set('discount_value', 15.0)
        ->set('min_spend', 500000.0)
        ->set('max_discount_amount', 100000.0)
        ->set('max_uses', 50)
        ->set('is_active', true)
        ->call('saveCoupon')
        ->assertHasNoErrors();

    expect(PlatformCoupon::where('operator_id', $operator->id)->where('code', 'SUMMER26')->exists())->toBeTrue();
});

test('operator can open edit modal and update an existing coupon', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $user->operators()->attach($operator->id, ['role' => 'owner']);

    $coupon = PlatformCoupon::create([
        'operator_id' => $operator->id,
        'code' => 'EDITME10',
        'discount_type' => 'percentage',
        'discount_value' => 10.0,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::coupons.index')
        ->call('editCoupon', $coupon->id)
        ->assertSet('editing_id', $coupon->id)
        ->assertSet('code', 'EDITME10')
        ->assertSet('show_modal', true)
        ->set('discount_value', 25.0)
        ->call('saveCoupon')
        ->assertHasNoErrors();

    expect($coupon->fresh()->discount_value)->toEqual(25.0);

    // Also verify openModal compatibility
    Livewire::actingAs($user)
        ->test('pages::coupons.index')
        ->call('openModal', $coupon->id)
        ->assertSet('editing_id', $coupon->id)
        ->assertSet('show_modal', true)
        ->call('openModal')
        ->assertSet('editing_id', null)
        ->assertSet('show_modal', true);
});

test('operator cannot see or edit another operators coupons', function () {
    $user1 = User::factory()->create();
    $operator1 = Operator::factory()->create();
    $user1->operators()->attach($operator1->id, ['role' => 'owner']);

    $user2 = User::factory()->create();
    $operator2 = Operator::factory()->create();
    $user2->operators()->attach($operator2->id, ['role' => 'owner']);

    $coupon2 = PlatformCoupon::create([
        'code' => 'OTHEROP',
        'operator_id' => $operator2->id,
        'discount_type' => 'percentage',
        'discount_value' => 20.0,
        'is_active' => true,
    ]);

    Livewire::actingAs($user1)
        ->test('pages::coupons.index')
        ->assertDontSee('OTHEROP')
        ->call('deleteCoupon', $coupon2->id);

    // Coupon 2 should still exist
    expect(PlatformCoupon::where('id', $coupon2->id)->exists())->toBeTrue();
});

test('operator can prompt and confirm delete coupon with modal', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $user->operators()->attach($operator->id, ['role' => 'owner']);

    $coupon = PlatformCoupon::create([
        'code' => 'DELETEME',
        'operator_id' => $operator->id,
        'discount_type' => 'percentage',
        'discount_value' => 10.0,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::coupons.index')
        ->call('promptDelete', $coupon->id, 'DELETEME')
        ->assertSet('confirming_delete_id', $coupon->id)
        ->assertSet('confirming_delete_code', 'DELETEME')
        ->assertSee('Delete Promo Code "DELETEME"?')
        ->call('confirmDelete')
        ->assertSet('confirming_delete_id', null);

    expect(PlatformCoupon::where('id', $coupon->id)->exists())->toBeFalse();
});

test('operator can prompt and confirm toggle active coupon status with modal', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $user->operators()->attach($operator->id, ['role' => 'owner']);

    $coupon = PlatformCoupon::create([
        'code' => 'TOGGLEME',
        'operator_id' => $operator->id,
        'discount_type' => 'percentage',
        'discount_value' => 10.0,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::coupons.index')
        ->call('promptToggleActive', $coupon->id, 'TOGGLEME', true)
        ->assertSet('confirming_toggle_id', $coupon->id)
        ->assertSee('Deactivate Promo Code "TOGGLEME"?')
        ->call('confirmToggleActive')
        ->assertSet('confirming_toggle_id', null);

    $coupon->refresh();
    expect($coupon->is_active)->toBeFalse();
});

test('guest booking box displays promo code section and allows applying codes', function () {
    $operator = Operator::factory()->create();
    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'price' => 1000000.0,
    ]);

    Livewire::test('storefront.booking-box', [
        'bookable' => $package,
        'operator' => $operator,
    ])->assertSee('Have a promo code?');
});

test('guest can apply valid operator promo code and get discount', function () {
    $operator = Operator::factory()->create();
    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'price' => 1000000.0,
    ]);

    PlatformCoupon::create([
        'code' => 'DISC10',
        'operator_id' => $operator->id,
        'discount_type' => 'percentage',
        'discount_value' => 10.0,
        'is_active' => true,
    ]);

    Livewire::test('storefront.booking-box', [
        'bookable' => $package,
        'operator' => $operator,
    ])
        ->set('couponCode', 'DISC10')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', 'DISC10')
        ->assertSet('discountAmount', 100000.0);
});

test('guest cannot apply a promo code from a different operator', function () {
    $operator1 = Operator::factory()->create();
    $operator2 = Operator::factory()->create();

    $package1 = Package::factory()->create([
        'operator_id' => $operator1->id,
        'price' => 1000000.0,
    ]);

    // Coupon created for operator 2 only
    PlatformCoupon::create([
        'code' => 'OP2ONLY',
        'operator_id' => $operator2->id,
        'discount_type' => 'percentage',
        'discount_value' => 20.0,
        'is_active' => true,
    ]);

    Livewire::test('storefront.booking-box', [
        'bookable' => $package1,
        'operator' => $operator1,
    ])
        ->set('couponCode', 'OP2ONLY')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', null)
        ->assertSet('discountAmount', 0.0);
});

test('platform subscription coupons are never displayed in operator coupon list', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $operator->users()->attach($user, ['role' => 'owner']);

    // Platform subscription coupon targeted to this operator
    PlatformCoupon::create([
        'code' => 'PLATFORMSUB50',
        'description' => 'Platform billing discount for operator subscription',
        'scope' => 'subscription',
        'operator_id' => $operator->id,
        'discount_type' => 'percentage',
        'discount_value' => 50.0,
        'is_active' => true,
    ]);

    // Storefront guest promo code
    PlatformCoupon::create([
        'code' => 'GUESTPROMO10',
        'description' => 'Guest storefront checkout promo',
        'scope' => 'guest',
        'operator_id' => $operator->id,
        'discount_type' => 'percentage',
        'discount_value' => 10.0,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::coupons.index')
        ->assertSee('GUESTPROMO10')
        ->assertDontSee('PLATFORMSUB50');
});

test('operator can access dedicated coupon report page and view performance metrics and transactions', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $operator->users()->attach($user, ['role' => 'owner']);

    $coupon = PlatformCoupon::create([
        'code' => 'REPORTTEST',
        'scope' => 'guest',
        'operator_id' => $operator->id,
        'discount_type' => 'percentage',
        'discount_value' => 15.0,
        'used_count' => 1,
        'is_active' => true,
    ]);

    Model::preventLazyLoading();

    $res = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'code' => 'RSV-COUPON-REPORT-1',
        'guest_name' => 'Sarah Traveler',
        'terms_snapshot' => [
            'subtotal' => 1000000.0,
            'coupon_code' => 'REPORTTEST',
            'discount_amount' => 150000.0,
            'total_price' => 850000.0,
        ],
    ]);

    Payment::factory()->create([
        'reservation_id' => $res->id,
        'amount' => 850000.0,
        'status' => PaymentStatus::Paid,
    ]);

    // Operator index displays report link
    Livewire::actingAs($user)
        ->test('pages::coupons.index')
        ->assertSee('REPORTTEST')
        ->assertSee(route('coupons.report', $coupon));

    // Operator visits dedicated report page
    $this->actingAs($user)
        ->get(route('coupons.report', $coupon))
        ->assertOk()
        ->assertSee('REPORTTEST')
        ->assertSee('15% OFF')
        ->assertSee('RSV-COUPON-REPORT-1')
        ->assertSee('Sarah Traveler');

    // Livewire component test on dedicated report
    Livewire::actingAs($user)
        ->test('pages::coupons.report', ['coupon' => $coupon])
        ->assertSee('REPORTTEST')
        ->assertSee('RSV-COUPON-REPORT-1')
        ->assertSee('Sarah Traveler')
        ->assertSee('150.000')
        ->assertSee('Newest First')
        ->set('sort', 'discount_high')
        ->assertSet('sort', 'discount_high')
        ->set('status', 'confirmed')
        ->assertSet('status', 'confirmed')
        ->call('exportCsv')
        ->assertFileDownloaded('coupon-report-reporttest-'.now()->format('Y-m-d').'.csv');
});

test('operator cannot access report of subscription coupons or other operators coupons', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $operator->users()->attach($user, ['role' => 'owner']);

    $otherOperator = Operator::factory()->create();

    // Subscription coupon
    $subCoupon = PlatformCoupon::create([
        'code' => 'SUBCOUPON',
        'scope' => 'subscription',
        'operator_id' => $operator->id,
        'discount_type' => 'percentage',
        'discount_value' => 20.0,
        'is_active' => true,
    ]);

    // Other operator coupon
    $otherCoupon = PlatformCoupon::create([
        'code' => 'OTHEROPCOUPON',
        'scope' => 'guest',
        'operator_id' => $otherOperator->id,
        'discount_type' => 'percentage',
        'discount_value' => 20.0,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('coupons.report', $subCoupon))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('coupons.report', $otherCoupon))
        ->assertNotFound();
});
