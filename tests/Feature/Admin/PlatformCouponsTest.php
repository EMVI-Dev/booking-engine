<?php

use App\Models\Operator;
use App\Models\Package;
use App\Models\PlatformCoupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'email' => 'admin@emvi.dev',
        'is_admin' => true,
    ]);

    $this->operator = Operator::factory()->create([
        'name' => 'Lombok Trekking Co',
        'slug' => 'lombok-trekking',
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 1000000,
    ]);
});

test('admin can view coupons page and create a new promo code', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.coupons')
        ->assertOk()
        ->assertSee('Platform Subscription Promo Codes')
        ->call('openCreateModal')
        ->set('code', 'TREK15')
        ->set('description', '15% Off Trekking Launch')
        ->set('discount_type', 'percentage')
        ->set('discount_value', 15)
        ->set('min_spend', 500000)
        ->call('saveCoupon')
        ->assertHasNoErrors();

    expect(PlatformCoupon::where('code', 'TREK15')->exists())->toBeTrue();
    $coupon = PlatformCoupon::where('code', 'TREK15')->first();
    expect((float) $coupon->discount_value)->toBe(15.0)
        ->and((float) $coupon->min_spend)->toBe(500000.0)
        ->and($coupon->operator_id)->toBeNull();
});

test('admin can toggle coupon active status and delete coupon', function () {
    $coupon = PlatformCoupon::factory()->create([
        'code' => 'DISCOUNT50K',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.coupons')
        ->call('toggleActive', $coupon->id)
        ->assertHasNoErrors();

    $coupon->refresh();
    expect($coupon->is_active)->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.coupons')
        ->call('deleteCoupon', $coupon->id)
        ->assertHasNoErrors();

    expect(PlatformCoupon::find($coupon->id))->toBeNull();
});

test('guest can apply platform coupon in storefront booking box and receive discount', function () {
    PlatformCoupon::create([
        'code' => 'BALISAVE20',
        'operator_id' => $this->operator->id,
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'min_spend' => 0,
        'is_active' => true,
    ]);

    Livewire::test('storefront.booking-box', [
        'bookable' => $this->package,
        'operator' => $this->operator,
    ])
        ->assertSet('discountAmount', 0.0)
        ->set('couponCode', 'balisave20')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', 'BALISAVE20')
        ->assertSet('discountAmount', 200000.0)
        ->assertSet('couponValid', true);
});

test('coupon rejects when minimum spend is not met', function () {
    PlatformCoupon::create([
        'code' => 'VIP500',
        'operator_id' => $this->operator->id,
        'discount_type' => 'fixed',
        'discount_value' => 500000,
        'min_spend' => 5000000, // Requires 5M subtotal
        'is_active' => true,
    ]);

    Livewire::test('storefront.booking-box', [
        'bookable' => $this->package, // price 1M, 1 pax = 1M
        'operator' => $this->operator,
    ])
        ->set('couponCode', 'VIP500')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', null)
        ->assertSet('couponValid', false)
        ->assertSee('Minimum booking spend');
});
