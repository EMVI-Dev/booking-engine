<?php

use App\Models\Operator;
use App\Models\OperatorCouponRedemption;
use App\Models\PlatformAnnouncement;
use App\Models\PlatformCoupon;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@emvi.dev', 'is_admin' => true]);
    $this->operator = Operator::factory()->create();
    $this->otherOperator = Operator::factory()->create();
});

// ── Redemption Scope: unlimited ────────────────────────────────────────────────

test('unlimited coupon allows multiple redemptions by same operator', function () {
    $coupon = PlatformCoupon::factory()->create(['redemption_scope' => 'unlimited']);

    // Record several redemptions
    OperatorCouponRedemption::record($coupon, $this->operator);
    OperatorCouponRedemption::record($coupon, $this->operator);

    expect($coupon->meetsRedemptionRuleFor($this->operator))->toBeTrue();
});

// ── Redemption Scope: first_purchase_only ─────────────────────────────────────

test('first_purchase_only coupon is allowed before first redemption', function () {
    $coupon = PlatformCoupon::factory()->firstPurchaseOnly()->create();

    expect($coupon->meetsRedemptionRuleFor($this->operator))->toBeTrue();
});

test('first_purchase_only coupon is blocked after first redemption', function () {
    $coupon = PlatformCoupon::factory()->firstPurchaseOnly()->create();

    OperatorCouponRedemption::record($coupon, $this->operator);

    expect($coupon->meetsRedemptionRuleFor($this->operator))->toBeFalse();
});

test('first_purchase_only coupon allows different operators to redeem independently', function () {
    $coupon = PlatformCoupon::factory()->firstPurchaseOnly()->create();

    OperatorCouponRedemption::record($coupon, $this->operator);

    // Other operator has NOT redeemed yet
    expect($coupon->meetsRedemptionRuleFor($this->otherOperator))->toBeTrue();
});

// ── Redemption Scope: once_per_period ─────────────────────────────────────────

test('once_per_period coupon allows redemption in a fresh billing cycle', function () {
    $coupon = PlatformCoupon::factory()->oncePeriod()->create();

    expect($coupon->meetsRedemptionRuleFor($this->operator))->toBeTrue();
});

test('once_per_period coupon blocks second redemption in same billing cycle', function () {
    $coupon = PlatformCoupon::factory()->oncePeriod()->create();

    OperatorCouponRedemption::record($coupon, $this->operator);

    expect($coupon->meetsRedemptionRuleFor($this->operator))->toBeFalse();
});

test('once_per_period coupon allows redemption again in next billing cycle', function () {
    $coupon = PlatformCoupon::factory()->oncePeriod()->create();

    // Create a redemption dated last month
    OperatorCouponRedemption::create([
        'platform_coupon_id' => $coupon->id,
        'operator_id' => $this->operator->id,
        'billing_cycle' => now()->subMonth()->format('Y-m'),
        'redeemed_at' => now()->subMonth(),
    ]);

    // Current month: no redemption yet
    expect($coupon->meetsRedemptionRuleFor($this->operator))->toBeTrue();
});

// ── Eligibility Rule: min_monthly_transactions ────────────────────────────────

test('isEligibleFor returns true when operator meets transaction threshold', function () {
    $coupon = PlatformCoupon::factory()
        ->withEligibilityRule('min_monthly_transactions', 5)
        ->create();

    // Create 5 confirmed reservations for this operator this month
    Reservation::factory()->count(5)->create([
        'operator_id' => $this->operator->id,
        'status' => 'confirmed',
        'created_at' => now()->subDays(3),
    ]);

    expect($coupon->isEligibleFor($this->operator))->toBeTrue();
});

test('isEligibleFor returns false when operator is below transaction threshold', function () {
    $coupon = PlatformCoupon::factory()
        ->withEligibilityRule('min_monthly_transactions', 10)
        ->create();

    // Only 3 confirmed reservations
    Reservation::factory()->count(3)->create([
        'operator_id' => $this->operator->id,
        'status' => 'confirmed',
        'created_at' => now()->subDays(3),
    ]);

    expect($coupon->isEligibleFor($this->operator))->toBeFalse();
});

test('isEligibleFor only counts confirmed reservations not pending ones', function () {
    $coupon = PlatformCoupon::factory()
        ->withEligibilityRule('min_monthly_transactions', 3)
        ->create();

    // 5 total but only 2 confirmed
    Reservation::factory()->count(2)->create(['operator_id' => $this->operator->id, 'status' => 'confirmed']);
    Reservation::factory()->count(3)->create(['operator_id' => $this->operator->id, 'status' => 'pending_confirmation']);

    expect($coupon->isEligibleFor($this->operator))->toBeFalse();
});

// ── Admin UI: new fields are saved correctly ──────────────────────────────────

test('admin can create coupon with first_purchase_only redemption scope', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.coupons')
        ->call('openCreateModal')
        ->set('code', 'WELCOME25')
        ->set('discount_type', 'percentage')
        ->set('discount_value', 25)
        ->set('redemption_scope', 'first_purchase_only')
        ->set('min_spend', 0)
        ->call('saveCoupon')
        ->assertHasNoErrors();

    $coupon = PlatformCoupon::where('code', 'WELCOME25')->first();
    expect($coupon)->not->toBeNull()
        ->and($coupon->redemption_scope)->toBe('first_purchase_only');
});

test('admin can create coupon with eligibility rule', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.coupons')
        ->call('openCreateModal')
        ->set('code', 'STAR50TXN')
        ->set('discount_type', 'percentage')
        ->set('discount_value', 10)
        ->set('min_spend', 0)
        ->set('redemption_scope', 'unlimited')
        ->set('eligibility_rule_type', 'min_monthly_transactions')
        ->set('eligibility_threshold', 50)
        ->call('saveCoupon')
        ->assertHasNoErrors();

    $coupon = PlatformCoupon::where('code', 'STAR50TXN')->first();
    expect($coupon)->not->toBeNull()
        ->and($coupon->eligibility_rule)->toBe(['type' => 'min_monthly_transactions', 'threshold' => 50, 'lookback_months' => 1]);
});

// ── Broadcast Command ─────────────────────────────────────────────────────────

test('broadcast command dry-run reports eligible operators without creating announcements', function () {
    $coupon = PlatformCoupon::factory()
        ->withEligibilityRule('min_monthly_transactions', 3)
        ->create();

    // Operator meets threshold
    Reservation::factory()->count(5)->create([
        'operator_id' => $this->operator->id,
        'status' => 'confirmed',
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('coupons:broadcast', ['--dry-run' => true])
        ->assertSuccessful();

    // No announcements should have been created
    expect(PlatformAnnouncement::count())->toBe(0);
});
