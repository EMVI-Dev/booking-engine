<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionProrationService;
use Livewire\Livewire;

beforeEach(function () {
    Plan::seedDefaultPlans();

    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'plan_id' => null, // Starter
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);

    $this->starterPlan = Plan::where('slug', 'starter')->first();
    $this->proPlan = Plan::where('slug', 'growth')->first();
    $this->ultimatePlan = Plan::where('slug', 'enterprise')->first();
});

test('proration service calculates accurate net difference when upgrading from Starter to Pro', function () {
    $service = app(SubscriptionProrationService::class);

    $proration = $service->calculateSwitch($this->operator, $this->proPlan, 'monthly');

    expect($proration['is_upgrade'])->toBeTrue()
        ->and($proration['is_downgrade'])->toBeFalse()
        ->and($proration['unused_credit'])->toEqual(0.0)
        ->and($proration['net_amount_due'])->toEqual((float) $this->proPlan->price_monthly);
});

test('proration service calculates prorated credit and charge when upgrading mid-cycle from Pro to Ultimate', function () {
    // Set operator to Pro with 15 days remaining out of a 30-day period
    $this->operator->update([
        'plan_id' => $this->proPlan->id,
        'subscription_interval' => 'monthly',
        'subscribed_at' => now()->subDays(15),
        'plan_expires_at' => now()->addDays(15),
    ]);

    $service = app(SubscriptionProrationService::class);
    $proration = $service->calculateSwitch($this->operator, $this->ultimatePlan, 'monthly');

    expect($proration['is_upgrade'])->toBeTrue()
        ->and($proration['days_remaining'])->toBeGreaterThanOrEqual(14)
        ->and($proration['unused_credit'])->toBeGreaterThan(0)
        ->and($proration['prorated_target_cost'])->toBeGreaterThan($proration['unused_credit'])
        ->and($proration['net_amount_due'])->toEqual(round($proration['prorated_target_cost'] - $proration['unused_credit'], 2));
});

test('operator can initiate upgrade, redirect to checkout, and complete card payment', function () {
    $this->actingAs($this->user);

    $component = Livewire::test('pages::settings.plan')
        ->assertSee('Starter Essential')
        ->call('initiatePlanSwitch', $this->proPlan->id)
        ->assertSet('show_switch_modal', true)
        ->assertSet('target_plan_id', $this->proPlan->id)
        ->set('auto_renew', true)
        ->set('auto_renew_consent', true)
        ->set('payment_method', 'cc')
        ->call('confirmPlanSwitch')
        ->assertSet('show_switch_modal', false)
        ->assertHasNoErrors();

    $payment = SubscriptionPayment::where('operator_id', $this->operator->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->plan_id)->toBe($this->proPlan->id)
        ->and($payment->status)->toBe('pending')
        ->and((float) $payment->net_amount_paid)->toEqual((float) $this->proPlan->price_monthly);

    $component->assertRedirect(route('settings.plan.checkout', $payment->id));

    // Now test the Plan Checkout Livewire component
    Livewire::test('pages::settings.plan-checkout', ['payment' => $payment])
        ->assertSee('Pro Operator')
        ->assertSee('Card Information')
        ->set('card_holder', 'John Operator')
        ->set('card_number', '4000 1234 5678 9010')
        ->set('card_expiry', '12/28')
        ->set('card_cvv', '888')
        ->set('auto_renew_consent', true)
        ->call('processCreditCardPayment')
        ->assertRedirect(route('settings.plan'))
        ->assertHasNoErrors();

    $this->operator->refresh();
    $payment->refresh();

    expect($this->operator->plan_id)->toBe($this->proPlan->id)
        ->and($this->operator->subscription_auto_renew)->toBeTrue()
        ->and($this->operator->plan_expires_at)->not->toBeNull()
        ->and($payment->status)->toBe('completed');
});

test('operator requires consent and confirmation when toggling recurring auto-renew status', function () {
    $this->actingAs($this->user);

    $this->operator->update([
        'plan_id' => $this->proPlan->id,
        'subscription_auto_renew' => true,
        'plan_expires_at' => now()->addMonth(),
    ]);

    // Test prompt to turn off auto-renew
    Livewire::test('pages::settings.plan')
        ->assertSee('Auto-Renew: On')
        ->call('promptToggleAutoRenew')
        ->assertSet('show_auto_renew_modal', true)
        ->assertSet('target_auto_renew_state', false)
        ->call('confirmToggleAutoRenew')
        ->assertSet('show_auto_renew_modal', false)
        ->assertHasNoErrors();

    $this->operator->refresh();
    expect($this->operator->subscription_auto_renew)->toBeFalse();

    // Test prompt to turn on auto-renew (fails without consent checkbox)
    Livewire::test('pages::settings.plan')
        ->assertSee('One-Time: Manual')
        ->call('promptToggleAutoRenew')
        ->assertSet('show_auto_renew_modal', true)
        ->assertSet('target_auto_renew_state', true)
        ->call('confirmToggleAutoRenew')
        ->assertHasErrors(['modal_consent_checkbox'])
        ->set('modal_consent_checkbox', true)
        ->call('confirmToggleAutoRenew')
        ->assertSet('show_auto_renew_modal', false)
        ->assertHasNoErrors();

    $this->operator->refresh();
    expect($this->operator->subscription_auto_renew)->toBeTrue();
});

test('operator can schedule a downgrade to end of billing cycle and cancel it', function () {
    $this->actingAs($this->user);

    $this->operator->update([
        'plan_id' => $this->ultimatePlan->id,
        'subscription_interval' => 'monthly',
        'subscribed_at' => now(),
        'plan_expires_at' => now()->addDays(20),
    ]);

    Livewire::test('pages::settings.plan')
        ->call('initiatePlanSwitch', $this->proPlan->id)
        ->set('downgrade_mode', 'end_of_cycle')
        ->call('confirmPlanSwitch')
        ->assertHasNoErrors()
        ->assertSee('Scheduled Plan Downgrade to Pro Operator');

    $this->operator->refresh();

    // The active plan is STILL Ultimate until cycle ends
    expect($this->operator->plan_id)->toBe($this->ultimatePlan->id)
        ->and($this->operator->pending_plan_id)->toBe($this->proPlan->id)
        ->and($this->operator->hasPendingPlanChange())->toBeTrue();

    // Cancel the scheduled downgrade with confirmation modal
    Livewire::test('pages::settings.plan')
        ->call('promptCancelScheduledDowngrade')
        ->assertSet('show_cancel_modal', true)
        ->assertSee('Keep Your Agency Ultimate Subscription?')
        ->call('confirmCancelScheduledDowngrade')
        ->assertSet('show_cancel_modal', false)
        ->assertHasNoErrors();

    $this->operator->refresh();
    expect($this->operator->pending_plan_id)->toBeNull()
        ->and($this->operator->hasPendingPlanChange())->toBeFalse();
});

test('scheduled plan changes console command executes pending downgrades when action date arrives', function () {
    $this->operator->update([
        'plan_id' => $this->ultimatePlan->id,
        'pending_plan_id' => $this->proPlan->id,
        'pending_plan_action_at' => now()->subMinute(),
    ]);

    $this->artisan('subscriptions:process-scheduled-changes')
        ->assertSuccessful();

    $this->operator->refresh();

    expect($this->operator->plan_id)->toBe($this->proPlan->id)
        ->and($this->operator->pending_plan_id)->toBeNull()
        ->and($this->operator->hasPendingPlanChange())->toBeFalse();
});

test('operator can view billing invoices page, view receipts, and update billing contact', function () {
    $this->actingAs($this->user);

    $payment = SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $this->proPlan->id,
        'invoice_number' => 'INV-SUB-2026-TEST',
        'type' => 'upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 499000,
        'prorated_credit' => 0,
        'net_amount_paid' => 499000,
        'status' => 'completed',
        'gateway' => 'credit_card',
        'gateway_ref' => 'CC-TEST-1234',
        'paid_at' => now(),
    ]);

    Livewire::test('pages::settings.billing')
        ->assertSee('Billing & Invoices')
        ->assertSee('INV-SUB-2026-TEST')
        ->assertSee('Rp 499.000')
        ->call('viewInvoice', $payment->id)
        ->assertSet('show_invoice_modal', true)
        ->assertSee('Official Subscription Receipt')
        ->call('closeInvoiceModal')
        ->assertSet('show_invoice_modal', false)
        ->set('company_legal_name', 'PT Test Travel Nusantara')
        ->set('billing_email', 'finance@testtravel.com')
        ->set('tax_id', '01.234.567.8-901.000')
        ->call('updateBillingInfo')
        ->assertHasNoErrors();

    $this->operator->refresh();
    expect($this->operator->billing_email)->toBe('finance@testtravel.com')
        ->and($this->operator->settings['legal_name'])->toBe('PT Test Travel Nusantara')
        ->and($this->operator->settings['tax_id'])->toBe('01.234.567.8-901.000');
});

test('operator can apply platform subscription coupon during checkout to receive discount', function () {
    $this->actingAs($this->user);

    PlatformCoupon::create([
        'code' => 'PLATFORM50',
        'operator_id' => null, // Platform Master Coupon
        'discount_type' => 'percentage',
        'discount_value' => 50.0,
        'is_active' => true,
    ]);

    $payment = SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $this->proPlan->id,
        'invoice_number' => 'INV-SUB-DISC-TEST',
        'type' => 'upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 300000,
        'prorated_credit' => 0,
        'net_amount_paid' => 300000,
        'status' => 'pending',
        'gateway' => 'credit_card',
    ]);

    Livewire::test('pages::settings.plan-checkout', ['payment' => $payment])
        ->set('couponCode', 'PLATFORM50')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', 'PLATFORM50')
        ->assertSet('discountAmount', 150000.0)
        ->assertSet('couponValid', true);

    $payment->refresh();
    expect((float) $payment->net_amount_paid)->toBe(150000.0)
        ->and((float) $payment->breakdown['discount_amount'])->toBe(150000.0);
});

test('operator can apply platform subscription coupon directly in plan upgrade modal', function () {
    $this->actingAs($this->user);

    PlatformCoupon::create([
        'code' => 'UPGRADE25',
        'operator_id' => null, // Platform Master Coupon
        'discount_type' => 'percentage',
        'discount_value' => 25.0,
        'is_active' => true,
    ]);

    Livewire::test('pages::settings.plan')
        ->call('initiatePlanSwitch', $this->proPlan->id)
        ->assertSet('show_switch_modal', true)
        ->set('couponCode', 'UPGRADE25')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', 'UPGRADE25')
        ->assertSet('couponValid', true)
        ->assertSee('UPGRADE25')
        ->set('auto_renew_consent', true)
        ->call('confirmPlanSwitch')
        ->assertHasNoErrors();
});

test('operator invoice receipt displays coupon promo code discount breakdown', function () {
    $this->actingAs($this->user);

    $payment = SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $this->proPlan->id,
        'invoice_number' => 'INV-SUB-PROMO-100',
        'type' => 'upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 500000,
        'prorated_credit' => 0,
        'net_amount_paid' => 350000,
        'status' => 'completed',
        'gateway' => 'credit_card',
        'gateway_ref' => 'CC-PROMO-TEST',
        'breakdown' => [
            'coupon_code' => 'SAVE150',
            'discount_amount' => 150000,
        ],
        'paid_at' => now(),
    ]);

    Livewire::test('pages::settings.billing')
        ->assertSee('INV-SUB-PROMO-100')
        ->assertSee('SAVE150')
        ->call('openInvoice', $payment->id)
        ->assertSee('Official Subscription Receipt')
        ->assertSee('Promo Code Discount (SAVE150)')
        ->assertSee('150.000')
        ->assertSee('350.000')
        ->call('closeInvoiceModal')
        ->call('viewInvoice', $payment->id)
        ->assertSee('Official Subscription Receipt');
});
