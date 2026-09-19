<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DokuPaymentService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->user = User::factory()->create(['name' => 'Audit Admin']);

    $this->operator = Operator::factory()->create([
        'name' => 'Lombok Marine Expeditions',
        'slug' => 'lombok-marine',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'lombok-marine.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 1000000.00,
        'status' => ListingStatus::Published,
    ]);

    $this->reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_email' => 'traveler@example.com',
        'pax_count' => 1,
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(30),
    ]);

    $this->payment = Payment::factory()->create([
        'reservation_id' => $this->reservation->id,
        'amount' => 1000000.00,
        'gateway' => 'doku',
        'gateway_ref' => 'INV-AUDIT-GUEST-1',
        'status' => PaymentStatus::Pending,
    ]);
});

test('doku webhook strictly rejects underpaid guest payment payloads', function () {
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');

    $dokuService = app(DokuPaymentService::class);

    // Tampered payload with amount 500.000 instead of 1.000.000
    $tamperedPayload = [
        'order' => [
            'invoice_number' => 'INV-AUDIT-GUEST-1',
            'amount' => 500000.00,
        ],
        'transaction' => [
            'status' => 'SUCCESS',
        ],
    ];

    $processed = $dokuService->processNotification($tamperedPayload);

    expect($processed)->toBeFalse()
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->reservation->fresh()->status)->toBe(ReservationStatus::PaymentPending);
});

test('doku webhook confirms guest payment when full amount is paid', function () {
    $dokuService = app(DokuPaymentService::class);

    $validPayload = [
        'order' => [
            'invoice_number' => 'INV-AUDIT-GUEST-1',
            'amount' => 1000000.00,
        ],
        'transaction' => [
            'status' => 'SUCCESS',
        ],
    ];

    $processed = $dokuService->processNotification($validPayload);

    expect($processed)->toBeTrue()
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('doku webhook accepts and upgrades operator subscription when paid in full', function () {
    $growthPlan = Plan::where('slug', 'growth')->firstOrFail();

    $subscriptionPayment = SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $growthPlan->id,
        'invoice_number' => 'INV-SUB-AUDIT-1',
        'billing_interval' => 'monthly',
        'gross_amount' => 299000.00,
        'net_amount_paid' => 299000.00,
        'status' => 'pending',
        'gateway' => 'doku',
        'breakdown' => ['auto_renew' => true],
    ]);

    $dokuService = app(DokuPaymentService::class);

    $validPayload = [
        'order' => [
            'invoice_number' => 'INV-SUB-AUDIT-1',
            'amount' => 299000.00,
        ],
        'transaction' => [
            'status' => 'SUCCESS',
        ],
    ];

    $processed = $dokuService->processNotification($validPayload);

    expect($processed)->toBeTrue()
        ->and($subscriptionPayment->fresh()->status)->toBe('completed')
        ->and($this->operator->fresh()->plan_id)->toBe($growthPlan->id);

    // Idempotent replay
    $replayed = $dokuService->processNotification($validPayload);
    expect($replayed)->toBeTrue();
});

test('doku webhook rejects subscription payment when underpaid', function () {
    $growthPlan = Plan::where('slug', 'growth')->firstOrFail();

    $subscriptionPayment = SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $growthPlan->id,
        'invoice_number' => 'INV-SUB-AUDIT-2',
        'billing_interval' => 'monthly',
        'gross_amount' => 299000.00,
        'net_amount_paid' => 299000.00,
        'status' => 'pending',
        'gateway' => 'doku',
        'breakdown' => ['auto_renew' => true],
    ]);

    $dokuService = app(DokuPaymentService::class);

    $underpaidPayload = [
        'order' => [
            'invoice_number' => 'INV-SUB-AUDIT-2',
            'amount' => 100000.00,
        ],
        'transaction' => [
            'status' => 'SUCCESS',
        ],
    ];

    $processed = $dokuService->processNotification($underpaidPayload);

    expect($processed)->toBeFalse()
        ->and($subscriptionPayment->fresh()->status)->toBe('pending');
});

test('wallet service creditBookingPayment is atomic and idempotent against duplicate calls', function () {
    $walletService = app(WalletService::class);

    // Payment must be Paid to credit wallet
    $this->payment->update(['status' => PaymentStatus::Paid]);

    // Call credit twice
    $tx1 = $walletService->creditBookingPayment($this->payment);
    $tx2 = $walletService->creditBookingPayment($this->payment);

    expect($tx1)->not->toBeNull()
        ->and($tx2)->not->toBeNull()
        ->and($tx1->id)->toBe($tx2->id);

    // Check ledger
    $count = WalletTransaction::where('reservation_id', $this->reservation->id)
        ->where('type', WalletTransactionType::BookingEarning)
        ->count();

    expect($count)->toBe(1);
});

test('payout requests evaluate balance under lock and reject race condition double spends', function () {
    $walletService = app(WalletService::class);

    // Configure bank details
    $this->operator->update([
        'bank_name' => 'BCA',
        'bank_account_number' => '12345678',
        'bank_account_name' => 'Lombok Marine',
    ]);

    // Credit operator with Rp 500.000 cleared balance
    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => null,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 500000.00,
        'fee_amount' => 0,
        'net_amount' => 500000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Test funds',
    ]);

    expect($this->operator->getAvailableBalance())->toBe(500000.0);

    // First payout request of Rp 200.000 succeeds (amount 200.000 + transfer fee 4.000 = 204.000 deducted)
    $payout1 = $walletService->createPayoutRequest(
        $this->operator,
        200000.00,
        'First batch'
    );

    expect($payout1)->not->toBeNull();
    $remaining = $this->operator->getAvailableBalance();
    expect($remaining)->toBe(297500.0); // 500.000 - (200.000 + 2.500 BI-FAST fee)

    // Second concurrent payout request of Rp 300.000 (exceeding remaining 297.500) must fail with validation exception
    expect(fn () => $walletService->createPayoutRequest(
        $this->operator,
        300000.00,
        'Second batch'
    ))->toThrow(ValidationException::class);

    // Balance remains intact and positive, never negative
    expect($this->operator->getAvailableBalance())->toBe(297500.0);
});

test('subscription checkout forbids simulation when simulator is disabled', function () {
    config()->set('doku.simulator_enabled', false);

    $growthPlan = Plan::where('slug', 'growth')->firstOrFail();

    $subscriptionPayment = SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $growthPlan->id,
        'invoice_number' => 'INV-SUB-AUDIT-3',
        'billing_interval' => 'monthly',
        'gross_amount' => 299000.00,
        'net_amount_paid' => 299000.00,
        'status' => 'pending',
        'gateway' => 'doku',
        'breakdown' => ['auto_renew' => true],
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::settings.plan-checkout', ['payment' => $subscriptionPayment])
        ->call('processSimulatedPayment')
        ->assertForbidden();

    expect($subscriptionPayment->fresh()->status)->toBe('pending');
});
