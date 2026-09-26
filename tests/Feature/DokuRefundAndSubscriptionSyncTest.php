<?php

use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DokuPaymentService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Plan::seedDefaultPlans();

    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);

    $this->growthPlan = Plan::where('slug', 'growth')->firstOrFail();
});

test('syncSubscriptionPaymentStatus completes pending upgrade when DOKU reports SUCCESS', function () {
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');
    config()->set('doku.sandbox.base_url', 'https://api-sandbox.doku.com');

    $payment = SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $this->growthPlan->id,
        'previous_plan_id' => $this->operator->plan_id,
        'invoice_number' => 'SUB-SYNC-TEST-1',
        'type' => 'subscription_upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 299000,
        'prorated_credit' => 0,
        'net_amount_paid' => 299000,
        'status' => 'pending',
        'gateway' => 'doku',
        'gateway_ref' => 'SUB-SYNC-TEST-1',
        'breakdown' => ['auto_renew' => true, 'target_plan_name' => 'Growth'],
    ]);

    Http::fake([
        'https://api-sandbox.doku.com/orders/v1/status/SUB-SYNC-TEST-1' => Http::response([
            'transaction' => ['status' => 'SUCCESS'],
        ], 200),
    ]);

    $synced = app(DokuPaymentService::class)->syncSubscriptionPaymentStatus($payment);

    expect($synced)->toBeTrue()
        ->and($payment->fresh()->status)->toBe('completed')
        ->and($this->operator->fresh()->plan_id)->toBe($this->growthPlan->id);
});

test('plan page mount reconciles pending subscription invoice via DOKU status sync', function () {
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');
    config()->set('doku.sandbox.base_url', 'https://api-sandbox.doku.com');

    SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $this->growthPlan->id,
        'previous_plan_id' => $this->operator->plan_id,
        'invoice_number' => 'SUB-SYNC-MOUNT-1',
        'type' => 'subscription_upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 299000,
        'prorated_credit' => 0,
        'net_amount_paid' => 299000,
        'status' => 'pending',
        'gateway' => 'doku',
        'gateway_ref' => 'SUB-SYNC-MOUNT-1',
        'breakdown' => ['auto_renew' => true],
    ]);

    Http::fake([
        'https://api-sandbox.doku.com/orders/v1/status/SUB-SYNC-MOUNT-1' => Http::response([
            'transaction' => ['status' => 'SUCCESS'],
        ], 200),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::settings.plan')
        ->assertHasNoErrors();

    expect($this->operator->fresh()->plan_id)->toBe($this->growthPlan->id);
});

test('refundPayment posts to DOKU and marks payment refunded on success', function () {
    config()->set('doku.simulator_enabled', true);
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');
    config()->set('doku.sandbox.base_url', 'https://api-sandbox.doku.com');

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);
    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
    ]);
    $payment = Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 500000,
        'gateway_ref' => 'INV-REFUND-OK-1',
    ]);

    Http::fake([
        'https://api-sandbox.doku.com/orders/v1/refund' => Http::response(['status' => 'SUCCESS'], 200),
    ]);

    $result = app(DokuPaymentService::class)->refundPayment($payment);

    expect($result)->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/orders/v1/refund')
        && $request['order']['invoice_number'] === 'INV-REFUND-OK-1');
});

test('refundPayment fails closed when DOKU rejects even if simulator is enabled', function () {
    config()->set('doku.simulator_enabled', true);
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');
    config()->set('doku.sandbox.base_url', 'https://api-sandbox.doku.com');

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);
    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
    ]);
    $payment = Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 500000,
        'gateway_ref' => 'INV-REFUND-FAIL-1',
    ]);

    Http::fake([
        'https://api-sandbox.doku.com/orders/v1/refund' => Http::response(['message' => 'rejected'], 400),
    ]);

    $result = app(DokuPaymentService::class)->refundPayment($payment);

    expect($result)->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('refund webhook cancels confirmed reservation and reverses wallet earning', function () {
    $package = Package::factory()->create(['operator_id' => $this->operator->id]);
    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
    ]);
    $payment = Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 750000,
        'gateway_ref' => 'INV-REFUND-HOOK-1',
    ]);

    $trx = WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 750000,
        'fee_amount' => 0,
        'net_amount' => 750000,
        'status' => WalletTransactionStatus::PendingEscrow,
        'description' => 'Pending trip',
    ]);

    $processed = app(DokuPaymentService::class)->processNotification([
        'order' => ['invoice_number' => 'INV-REFUND-HOOK-1'],
        'transaction' => ['status' => 'REFUNDED'],
    ]);

    expect($processed)->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($trx->fresh()->status)->toBe(WalletTransactionStatus::Cancelled);
});
