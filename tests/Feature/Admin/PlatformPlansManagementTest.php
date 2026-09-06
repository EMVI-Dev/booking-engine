<?php

use App\Enums\OperatorStatus;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'master-admin@emvi.dev',
    ]);
});

test('admin can view subscription plans management page', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.plans.index'));

    $response->assertOk()
        ->assertSee('Plans')
        ->assertSee('Essential')
        ->assertSee('Pro')
        ->assertSee('Agency')
        ->assertDontSee('Enterprise');
});

test('admin can update plan pricing, commission rate, and feature flags', function () {
    $growthPlan = Plan::where('slug', 'growth')->first();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('editPlan', $growthPlan->id)
        ->set('price_monthly', 349000.00)
        ->set('commission_percentage', 6.5)
        ->set('features.custom_domain', true)
        ->call('savePlan')
        ->assertHasNoErrors();

    $growthPlan->refresh();
    expect((float) $growthPlan->price_monthly)->toBe(349000.00)
        ->and($growthPlan->commission_rate)->toBe(0.0650)
        ->and($growthPlan->hasFeature('custom_domain'))->toBeTrue()
        ->and($growthPlan->hasFeature('byo_gateway'))->toBeFalse();
});

test('plan editor cannot turn on a private payment account', function () {
    $growthPlan = Plan::where('slug', 'growth')->first();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('editPlan', $growthPlan->id)
        ->assertDontSee('Private payment account')
        ->set('features.byo_gateway', true)
        ->call('savePlan')
        ->assertHasNoErrors();

    expect($growthPlan->fresh()->hasFeature('byo_gateway'))->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('admin.plans.index'))
        ->assertOk()
        ->assertDontSee('Private payment account')
        ->assertDontSee('Payment Gateway Credentials');
});

test('admin can assign subscription plan to an operator from operator details page', function () {
    $operator = Operator::factory()->create([
        'name' => 'Lombok Coral Explorer',
        'slug' => 'lombok-coral',
        'status' => OperatorStatus::Approved,
        'plan_id' => null,
    ]);

    $agencyPlan = Plan::where('slug', 'agency')->first();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.operators.show', ['operator' => $operator])
        ->call('assignPlan', $agencyPlan->id);

    $operator->refresh();
    expect($operator->plan_id)->toBe($agencyPlan->id)
        ->and($operator->getEffectiveCommissionRate())->toBe(0.0000)
        ->and($operator->hasFeature('custom_domain'))->toBeTrue();
});

test('admin can view renewals tab and send renewal reminder email', function () {
    Mail::fake();

    $growthPlan = Plan::where('slug', 'growth')->first();

    $operator = Operator::factory()->create([
        'name' => 'Komodo Yacht Club',
        'slug' => 'komodo-yacht',
        'status' => OperatorStatus::Approved,
        'plan_id' => $growthPlan->id,
        'billing_email' => 'billing@komodoyacht.com',
        'plan_expires_at' => now()->addDays(3),
        'subscription_auto_renew' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->set('tab', 'renewals')
        ->assertSee('Komodo Yacht Club')
        ->assertSee('billing@komodoyacht.com')
        ->call('sendRenewalReminder', $operator->id)
        ->assertHasNoErrors();

    Mail::assertSent(SubscriptionRenewalReminderMail::class, function ($mail) use ($operator) {
        return $mail->hasTo('billing@komodoyacht.com') && $mail->operator->id === $operator->id;
    });
});

test('admin can extend operator subscription duration and toggle auto renew', function () {
    $growthPlan = Plan::where('slug', 'growth')->first();

    $operator = Operator::factory()->create([
        'name' => 'Raja Ampat Liveaboard',
        'slug' => 'raja-ampat',
        'status' => OperatorStatus::Approved,
        'plan_id' => $growthPlan->id,
        'plan_expires_at' => now()->addDays(5),
        'subscription_auto_renew' => false,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('extendSubscription', $operator->id, 30)
        ->call('toggleAutoRenew', $operator->id)
        ->assertHasNoErrors();

    $operator->refresh();
    expect(now()->diffInDays($operator->plan_expires_at))->toBeGreaterThanOrEqual(34)
        ->and($operator->subscription_auto_renew)->toBeTrue();
});
