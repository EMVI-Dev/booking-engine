<?php

use App\Models\Operator;
use App\Models\Package;
use App\Models\PlatformCoupon;
use App\Models\User;
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
