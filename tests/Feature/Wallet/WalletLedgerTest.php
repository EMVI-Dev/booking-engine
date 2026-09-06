<?php

use App\Console\Commands\ReleaseMaturedEscrowsCommand;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DokuPaymentService;
use App\Services\WalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'name' => 'Komodo Expeditions',
        'slug' => 'komodo-expeditions',
        'status' => OperatorStatus::Approved,
        'bank_provider' => 'BCA',
        'bank_account_name' => 'John Komodo',
        'bank_account_number' => '8881234567',
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Komodo 3D2N Liveaboard',
        'price' => 5000000.00,
    ]);
});

test('successful reservation payment automatically creates wallet transaction ledger credit', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'requested_date' => now()->addDays(5)->format('Y-m-d'),
        'pax_count' => 1,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $dokuService = app(DokuPaymentService::class);
    $session = $dokuService->createPaymentSession($reservation, 5000000.00);

    // Simulate DOKU webhook notification
    $dokuService->processNotification([
        'order' => ['invoice_number' => $session['invoice_number']],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    expect($session['payment']->fresh()->status)->toBe(PaymentStatus::Paid);

    // Verify wallet transaction ledger record was created
    $transaction = WalletTransaction::where('reservation_id', $reservation->id)->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe(WalletTransactionType::BookingEarning)
        ->and((float) $transaction->gross_amount)->toBe(5000000.00)
        ->and((float) $transaction->fee_amount)->toBe(0.00) // 0% commission deducted from operator
        ->and((float) $transaction->net_amount)->toBe(5000000.00) // 100% net to operator
        ->and($transaction->status)->toBe(WalletTransactionStatus::PendingEscrow);

    // Escrow balance should reflect full 5.000.000, available should be 0 until departure
    expect($this->operator->getPendingEscrowBalance())->toBe(5000000.00)
        ->and($this->operator->getAvailableBalance())->toBe(0.0)
        ->and($this->operator->getTotalGrossSales())->toBe(5000000.00);
});

test('funds unlock to available balance when departure date has passed', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'requested_date' => now()->subDay()->format('Y-m-d'), // Yesterday
        'status' => ReservationStatus::PaymentPending,
    ]);

    $dokuService = app(DokuPaymentService::class);
    $session = $dokuService->createPaymentSession($reservation, 2000000.00);

    $dokuService->processNotification([
        'order' => ['invoice_number' => $session['invoice_number']],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    // Because departure is in past, it should be immediately cleared (100% net)
    $transaction = WalletTransaction::where('reservation_id', $reservation->id)->first();
    expect($transaction->status)->toBe(WalletTransactionStatus::Cleared)
        ->and($this->operator->getAvailableBalance())->toBe(2000000.00);
});

test('release escrows command transitions matured pending transactions to cleared', function () {
    $walletService = app(WalletService::class);

    // Create a pending escrow with available_at in the past
    $trx = WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1000000.00,
        'fee_amount' => 100000.00,
        'net_amount' => 900000.00,
        'status' => WalletTransactionStatus::PendingEscrow,
        'available_at' => now()->subHour(),
        'description' => 'Tour departed',
    ]);

    expect($this->operator->getAvailableBalance())->toBe(0.0);

    $this->artisan(ReleaseMaturedEscrowsCommand::class)
        ->assertSuccessful();

    expect($trx->fresh()->status)->toBe(WalletTransactionStatus::Cleared)
        ->and($this->operator->getAvailableBalance())->toBe(900000.00);
});

test('operator can submit payout request within available balance', function () {
    $walletService = app(WalletService::class);

    // Credit cleared balance
    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 2000000.00,
        'fee_amount' => 200000.00,
        'net_amount' => 1800000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Cleared earnings',
    ]);

    expect($this->operator->getAvailableBalance())->toBe(1800000.00);

    $payout = $walletService->createPayoutRequest($this->operator, 1000000.00, 'Monthly payout');

    expect($payout)->toBeInstanceOf(PayoutRequest::class)
        ->and($payout->status)->toBe(PayoutStatus::Completed)
        ->and((float) $payout->amount)->toBe(1000000.00)
        ->and($payout->bank_provider)->toBe('BCA')
        ->and($this->operator->getAvailableBalance())->toBe(800000.00); // 1.800.000 - 1.000.000
});

test('operator cannot request payout exceeding available balance or below minimum', function () {
    $walletService = app(WalletService::class);

    expect(fn () => $walletService->createPayoutRequest($this->operator, 100000.00))
        ->toThrow(ValidationException::class); // Available balance is 0

    // Try below minimum Rp 50.000
    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 2000000.00,
        'fee_amount' => 20000.00,
        'net_amount' => 180000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Cleared earnings',
    ]);

    expect(fn () => $walletService->createPayoutRequest($this->operator, 25000.00))
        ->toThrow(ValidationException::class);
});

test('rejecting payout restores funds back to operator wallet', function () {
    $walletService = app(WalletService::class);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1000000.00,
        'fee_amount' => 100000.00,
        'net_amount' => 900000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Cleared earnings',
    ]);

    $payout = $walletService->createPayoutRequest($this->operator, 500000.00);
    expect($this->operator->getAvailableBalance())->toBe(400000.00);

    $walletService->rejectPayout($payout, 'Bank account name does not match');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Rejected)
        ->and($payout->fresh()->rejection_reason)->toBe('Bank account name does not match')
        ->and($this->operator->getAvailableBalance())->toBe(900000.00); // Restored!
});

test('operator wallet page renders metrics and handles payout requests via livewire', function () {
    $this->actingAs($this->user);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 2000000.00,
        'fee_amount' => 200000.00,
        'net_amount' => 1800000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Direct booking payout test',
    ]);

    Livewire::test('pages::wallet.index')
        ->assertOk()
        ->assertSee('Wallet & Payouts')
        ->assertSee('Rp 1.800.000')
        ->call('openPayoutModal')
        ->assertSet('showPayoutModal', true)
        ->set('payoutAmount', '500000')
        ->call('submitPayoutRequest')
        ->assertHasNoErrors()
        ->assertSet('showPayoutModal', false);

    expect($this->operator->payoutRequests()->count())->toBe(1)
        ->and($this->operator->getAvailableBalance())->toBe(1300000.00);
});

test('cancelling reservation in pending escrow cancels wallet transaction', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'requested_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => ReservationStatus::Confirmed,
    ]);

    $trx = WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1000000.00,
        'fee_amount' => 0.00,
        'net_amount' => 1000000.00,
        'status' => WalletTransactionStatus::PendingEscrow,
        'available_at' => now()->addDays(5),
        'description' => 'Pending trip',
    ]);

    $walletService = app(WalletService::class);
    $walletService->cancelBookingEarning($reservation, 'Guest request');

    expect($trx->fresh()->status)->toBe(WalletTransactionStatus::Cancelled)
        ->and($this->operator->getPendingEscrowBalance())->toBe(0.0);
});

test('cancelling cleared reservation creates debit reversal transaction in wallet', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'requested_date' => now()->subDay()->format('Y-m-d'),
        'status' => ReservationStatus::Confirmed,
    ]);

    $trx = WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1000000.00,
        'fee_amount' => 0.00,
        'net_amount' => 1000000.00,
        'status' => WalletTransactionStatus::Cleared,
        'available_at' => now()->subDay(),
        'description' => 'Cleared trip',
    ]);

    expect($this->operator->getAvailableBalance())->toBe(1000000.00);

    $walletService = app(WalletService::class);
    $reversal = $walletService->cancelBookingEarning($reservation, 'Refund issued');

    expect($reversal)->not->toBeNull()
        ->and($reversal->type)->toBe(WalletTransactionType::ManualAdjustment)
        ->and((float) $reversal->net_amount)->toBe(-1000000.00)
        ->and($this->operator->getAvailableBalance())->toBe(0.0);
});

test('reservation free cancellation eligibility correctly respects cutoff hours', function () {
    $requestedDate = now()->addDays(3)->format('Y-m-d'); // 72 hours away
    $reservation = Reservation::factory()->create([
        'requested_date' => $requestedDate,
        'terms_snapshot' => [
            'free_cancellation_hours' => 24,
        ],
    ]);

    expect($reservation->getFrozenFreeCancellationHours())->toBe(24)
        ->and($reservation->isEligibleForFreeCancellation(now()))->toBeTrue();

    // 12 hours before departure (past 24h cutoff)
    $departureDate = Carbon::parse($requestedDate)->startOfDay();
    $tooLate = $departureDate->copy()->subHours(12);

    expect($reservation->isEligibleForFreeCancellation($tooLate))->toBeFalse();
});

test('processing partial refund debits operator wallet balance accurately', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'requested_date' => now()->subDay()->format('Y-m-d'),
        'status' => ReservationStatus::Confirmed,
    ]);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 2000000.00,
        'fee_amount' => 0.00,
        'net_amount' => 2000000.00,
        'status' => WalletTransactionStatus::Cleared,
        'available_at' => now()->subDay(),
        'description' => 'Full trip earning',
    ]);

    expect($this->operator->getAvailableBalance())->toBe(2000000.00);

    $walletService = app(WalletService::class);
    $partialTx = $walletService->processPartialRefund($reservation, 500000.00, '50% pax cancellation');

    expect($partialTx->type)->toBe(WalletTransactionType::RefundDeduction)
        ->and((float) $partialTx->net_amount)->toBe(-500000.00)
        ->and($this->operator->getAvailableBalance())->toBe(1500000.00);
});

test('refund after payout results in negative wallet balance and blocks payout requests', function () {
    $walletService = app(WalletService::class);

    // Operator starts with 1.000.000 cleared
    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1000000.00,
        'fee_amount' => 0.00,
        'net_amount' => 1000000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Initial cleared earning',
    ]);

    // Operator withdraws all 1.000.000
    $payout = $walletService->createPayoutRequest($this->operator, 1000000.00);
    expect($this->operator->getAvailableBalance())->toBe(0.0);

    // Later, a refund debit of 400.000 is issued
    $reservation = Reservation::factory()->create(['operator_id' => $this->operator->id]);
    $walletService->processPartialRefund($reservation, 400000.00, 'Post-payout refund');

    // Balance is now negative (-400.000)
    expect($this->operator->getAvailableBalance())->toBe(-400000.00);

    // Payout request must be blocked
    expect(fn () => $walletService->createPayoutRequest($this->operator, 50000.00))
        ->toThrow(ValidationException::class);
});

test('payouts under five hundred thousand include the bank transfer fee', function () {
    $walletService = app(WalletService::class);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 300000.00,
        'fee_amount' => 0,
        'net_amount' => 300000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Cleared earnings',
    ]);

    $payout = $walletService->createPayoutRequest($this->operator, 100000.00);

    expect((float) $payout->amount)->toBe(100000.00)
        ->and($this->operator->getAvailableBalance())->toBe(197500.00);
});

test('failed live payouts stay waiting instead of looking paid', function () {
    config(['doku.simulator_enabled' => false]);

    $walletService = app(WalletService::class);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1000000.00,
        'fee_amount' => 0,
        'net_amount' => 1000000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Cleared earnings',
    ]);

    $payout = $walletService->createPayoutRequest($this->operator, 500000.00);

    expect($payout->fresh()->status)->toBe(PayoutStatus::Pending)
        ->and($this->operator->getAvailableBalance())->toBe(500000.00);

    config(['doku.simulator_enabled' => null]);
});

test('new earnings bring a negative wallet back toward zero', function () {
    $walletService = app(WalletService::class);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'type' => WalletTransactionType::RefundDeduction,
        'gross_amount' => -400000.00,
        'fee_amount' => 0,
        'net_amount' => -400000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Post-payout refund',
    ]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'requested_date' => now()->subDay()->format('Y-m-d'),
        'status' => ReservationStatus::Confirmed,
    ]);

    $payment = Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 500000.00,
        'split_details' => [
            'agent_amount' => 500000.00,
            'platform_commission' => 25000.00,
        ],
    ]);

    $walletService->creditBookingPayment($payment);

    expect($this->operator->getAvailableBalance())->toBe(100000.00)
        ->and(WalletTransaction::query()->where('reservation_id', $reservation->id)->where('type', WalletTransactionType::PlatformCommission)->count())->toBe(1);
});

test('holding money for a card fight reduces available balance and can be given back', function () {
    $walletService = app(WalletService::class);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
    ]);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1500000.00,
        'fee_amount' => 0.00,
        'net_amount' => 1500000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Cleared earnings',
    ]);

    expect($this->operator->getAvailableBalance())->toBe(1500000.00);

    $hold = $walletService->holdDispute($reservation, 1500000.00, 'Guest bank opened a card fight');

    expect($hold->type)->toBe(WalletTransactionType::DisputeHold)
        ->and((float) $hold->net_amount)->toBe(-1500000.00)
        ->and($walletService->outstandingDisputeHold($reservation))->toBe(1500000.00)
        ->and($this->operator->getAvailableBalance())->toBe(0.0);

    expect(fn () => $walletService->holdDispute($reservation, 100000.00))
        ->toThrow(ValidationException::class);

    $release = $walletService->releaseDispute($reservation);

    expect($release->type)->toBe(WalletTransactionType::DisputeRelease)
        ->and((float) $release->net_amount)->toBe(1500000.00)
        ->and($walletService->outstandingDisputeHold($reservation->fresh()))->toBe(0.0)
        ->and($this->operator->getAvailableBalance())->toBe(1500000.00);
});
