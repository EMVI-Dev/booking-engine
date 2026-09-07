<?php

use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\DokuPaymentService;
use App\Services\LapsedSubscriptionService;
use App\Services\OperatorOnboardingService;
use App\Services\SubscriptionProrationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function fakeOperatorSlackWebhook(): string
{
    $url = 'https://hooks.slack.com/services/T000/B000/testing';

    config(['services.slack.operator_webhook_url' => $url]);

    Http::preventStrayRequests();
    Http::fake([
        'https://hooks.slack.com/*' => Http::response('ok', 200),
    ]);

    return $url;
}

test('registration posts an operator activity alert to slack', function () {
    fakeOperatorSlackWebhook();

    $result = app(OperatorOnboardingService::class)->registerOperator([
        'name' => 'Wayan Sudarma',
        'email' => 'wayan@example.com',
        'password' => 'password',
        'operator_name' => 'Nusa Penida Charters',
        'slug' => 'nusa-penida-charters',
    ]);

    Http::assertSent(function (Request $request) use ($result): bool {
        return str_contains($request->url(), 'hooks.slack.com')
            && $request['text'] === 'New operator registered: '.$result['operator']->name
            && str_contains((string) json_encode($request->data()), $result['user']->email);
    });
});

test('slack stays silent when the webhook is not configured', function () {
    config(['services.slack.operator_webhook_url' => '']);
    Http::preventStrayRequests();
    Http::fake();

    app(OperatorOnboardingService::class)->registerOperator([
        'name' => 'Made Rai',
        'email' => 'made@example.com',
        'password' => 'password',
        'operator_name' => 'Ubud Walks',
    ]);

    Http::assertNothingSent();
});

test('a slack failure does not block operator registration', function () {
    config(['services.slack.operator_webhook_url' => 'https://hooks.slack.com/services/T000/B000/testing']);
    Http::preventStrayRequests();
    Http::fake([
        'https://hooks.slack.com/*' => Http::response('no_service', 500),
    ]);

    $result = app(OperatorOnboardingService::class)->registerOperator([
        'name' => 'Ketut Sari',
        'email' => 'ketut@example.com',
        'password' => 'password',
        'operator_name' => 'Sanur Boat',
    ]);

    expect($result['operator']->name)->toBe('Sanur Boat')
        ->and($result['user']->email)->toBe('ketut@example.com');
});

test('plan upgrades and downgrades post slack alerts', function () {
    fakeOperatorSlackWebhook();
    Plan::seedDefaultPlans();

    $operator = Operator::factory()->create([
        'name' => 'Komodo Diving Co',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::query()->where('slug', 'starter')->value('id'),
    ]);

    $growth = Plan::query()->where('slug', 'growth')->first();
    $starter = Plan::query()->where('slug', 'starter')->first();

    app(SubscriptionProrationService::class)->executeUpgrade($operator, $growth, 'monthly');

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Subscription paid')
        && str_contains((string) $request['text'], 'Komodo Diving Co')
        && str_contains((string) json_encode($request->data()), 'Rp '));

    Http::fake([
        'https://hooks.slack.com/*' => Http::response('ok', 200),
    ]);

    app(SubscriptionProrationService::class)->executeImmediateDowngrade($operator->fresh(), $starter, 'monthly');

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Subscription downgraded')
        && str_contains((string) $request['text'], 'Komodo Diving Co'));
});

test('completing a pending subscription payment posts amount and invoice to slack', function () {
    fakeOperatorSlackWebhook();
    Plan::seedDefaultPlans();

    $operator = Operator::factory()->create([
        'name' => 'Gili Boat Club',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::query()->where('slug', 'starter')->value('id'),
    ]);
    $growth = Plan::query()->where('slug', 'growth')->first();

    $payment = SubscriptionPayment::create([
        'operator_id' => $operator->id,
        'plan_id' => $growth->id,
        'previous_plan_id' => $operator->plan_id,
        'invoice_number' => 'SUB-BILL-TEST-1',
        'type' => 'subscription_upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 299000,
        'prorated_credit' => 0,
        'net_amount_paid' => 299000,
        'status' => 'pending',
        'gateway' => 'credit_card',
    ]);

    app(SubscriptionProrationService::class)->completePendingPayment($payment, 'CC-TEST', 'credit_card');

    Http::assertSent(function (Request $request): bool {
        $body = (string) json_encode($request->data());

        return str_contains((string) $request['text'], 'Subscription paid')
            && str_contains((string) $request['text'], 'Gili Boat Club')
            && str_contains($body, 'SUB-BILL-TEST-1')
            && str_contains($body, 'Rp 299.000');
    });
});

test('a failed doku subscription charge posts a slack alert', function () {
    fakeOperatorSlackWebhook();
    Plan::seedDefaultPlans();

    $operator = Operator::factory()->create([
        'name' => 'Ubud Walks',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::query()->where('slug', 'starter')->value('id'),
    ]);
    $growth = Plan::query()->where('slug', 'growth')->first();

    $payment = SubscriptionPayment::create([
        'operator_id' => $operator->id,
        'plan_id' => $growth->id,
        'invoice_number' => 'SUB-FAIL-1',
        'type' => 'subscription_upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 299000,
        'prorated_credit' => 0,
        'net_amount_paid' => 299000,
        'status' => 'pending',
        'gateway' => 'doku',
    ]);

    $processed = app(DokuPaymentService::class)->processNotification([
        'order' => ['invoice_number' => 'SUB-FAIL-1'],
        'transaction' => ['status' => 'FAILED'],
    ]);

    expect($processed)->toBeTrue()
        ->and($payment->fresh()->status)->toBe('failed');

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Subscription payment failed')
        && str_contains((string) $request['text'], 'Ubud Walks'));
});

test('a lapsed paid plan posts a slack alert', function () {
    fakeOperatorSlackWebhook();

    $starter = Plan::factory()->starter()->create();
    $growth = Plan::factory()->growth()->create();

    $operator = Operator::factory()->create([
        'name' => 'Lombok Trekking Co',
        'plan_id' => $growth->id,
        'plan_expires_at' => now()->subDays(4),
    ]);

    app(LapsedSubscriptionService::class)->revertToFreePlan($operator);

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Subscription lapsed')
        && str_contains((string) $request['text'], $starter->name));
});

test('auto-renew and admin billing actions post slack alerts', function () {
    fakeOperatorSlackWebhook();
    Plan::seedDefaultPlans();

    $admin = User::factory()->create(['is_admin' => true]);
    $growth = Plan::query()->where('slug', 'growth')->first();
    $operator = Operator::factory()->create([
        'name' => 'Raja Ampat Liveaboard',
        'status' => OperatorStatus::Approved,
        'plan_id' => $growth->id,
        'plan_expires_at' => now()->addDays(5),
        'subscription_auto_renew' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.plans')
        ->call('extendSubscription', $operator->id, 30);

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Subscription extended')
        && str_contains((string) $request['text'], 'Raja Ampat Liveaboard'));

    Http::fake([
        'https://hooks.slack.com/*' => Http::response('ok', 200),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.plans')
        ->call('toggleAutoRenew', $operator->id);

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Auto-renew turned on')
        && str_contains((string) $request['text'], 'Raja Ampat Liveaboard'));
});

test('renewal reminder emails also post a slack alert', function () {
    fakeOperatorSlackWebhook();
    Mail::fake();
    Plan::seedDefaultPlans();

    $growth = Plan::query()->where('slug', 'growth')->first();
    $operator = Operator::factory()->create([
        'name' => 'Komodo Yacht Club',
        'status' => OperatorStatus::Approved,
        'plan_id' => $growth->id,
        'billing_email' => 'billing@komodo.test',
        'plan_expires_at' => now()->addDays(3)->startOfDay(),
    ]);
    $operator->users()->attach(User::factory()->create()->id, ['role' => 'owner']);

    $this->artisan('subscriptions:send-renewal-reminders')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Renewal reminder sent')
        && str_contains((string) $request['text'], 'Komodo Yacht Club'));
});

test('admin plan and status changes post slack alerts', function () {
    fakeOperatorSlackWebhook();
    Plan::seedDefaultPlans();

    $admin = User::factory()->create(['is_admin' => true]);
    $operator = Operator::factory()->create([
        'name' => 'Bali Sea Explorers',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::query()->where('slug', 'starter')->value('id'),
    ]);
    $agency = Plan::query()->where('slug', 'agency')->first();

    Livewire::actingAs($admin)
        ->test('pages::admin.operators.show', ['operator' => $operator])
        ->call('assignPlan', $agency->id);

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Complimentary plan granted')
        && str_contains((string) $request['text'], 'Bali Sea Explorers'));

    Http::fake([
        'https://hooks.slack.com/*' => Http::response('ok', 200),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.operators.show', ['operator' => $operator->fresh()])
        ->call('updateStatus', 'suspended');

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Operator status')
        && str_contains((string) $request['text'], 'Suspended'));
});

test('inviting a teammate posts a slack alert', function () {
    fakeOperatorSlackWebhook();
    Notification::fake();
    Plan::seedDefaultPlans();

    $owner = User::factory()->create();
    $operator = Operator::factory()->create([
        'name' => 'Plain Language Tours',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::query()->where('slug', 'growth')->value('id'),
    ]);
    $operator->users()->attach($owner->id, ['role' => OperatorUserRole::Owner]);

    $this->actingAs($owner);

    Livewire::test('pages::settings.team')
        ->set('invite_name', 'Ayu Bookings')
        ->set('invite_email', 'ayu@example.com')
        ->set('invite_role', OperatorUserRole::Reservation->value)
        ->call('inviteTeammate')
        ->assertHasNoErrors();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Team member invited')
        && str_contains((string) $request['text'], 'ayu@example.com'));
});
