<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\Plan;
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

test('operator can upgrade plan via Livewire and record payment', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::settings.plan')
        ->assertSee('Starter Essential')
        ->call('initiatePlanSwitch', $this->proPlan->id)
        ->assertSet('show_switch_modal', true)
        ->assertSet('target_plan_id', $this->proPlan->id)
        ->call('confirmPlanSwitch')
        ->assertSet('show_switch_modal', false)
        ->assertHasNoErrors();

    $this->operator->refresh();

    expect($this->operator->plan_id)->toBe($this->proPlan->id)
        ->and($this->operator->plan_expires_at)->not->toBeNull();

    $payment = SubscriptionPayment::where('operator_id', $this->operator->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->plan_id)->toBe($this->proPlan->id)
        ->and($payment->status)->toBe('completed')
        ->and((float) $payment->net_amount_paid)->toEqual((float) $this->proPlan->price_monthly);
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
