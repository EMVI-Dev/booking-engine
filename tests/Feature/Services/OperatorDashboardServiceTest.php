<?php

use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\WalletTransaction;
use App\Services\OperatorDashboardService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->operator = Operator::factory()->create();
    $this->package = Package::factory()->create(['operator_id' => $this->operator->id]);
});

function ledgerEntry(Operator $operator, WalletTransactionType $type, float $net, WalletTransactionStatus $status, ?string $reservationId = null): void
{
    WalletTransaction::create([
        'operator_id' => $operator->id,
        'reservation_id' => $reservationId,
        'type' => $type,
        'gross_amount' => abs($net),
        'fee_amount' => 0,
        'net_amount' => $net,
        'status' => $status,
        'description' => 'test',
    ]);
}

function bookingFor(Operator $operator, Package $package, array $attributes): Reservation
{
    return Reservation::factory()->create(array_merge([
        'operator_id' => $operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
    ], $attributes));
}

test('earnings come from the ledger, net of refunds and voided bookings', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00'));

    $reservation = bookingFor($this->operator, $this->package, ['status' => ReservationStatus::Cancelled, 'hold_expires_at' => null]);

    ledgerEntry($this->operator, WalletTransactionType::BookingEarning, 1000000, WalletTransactionStatus::PendingEscrow);
    ledgerEntry($this->operator, WalletTransactionType::BookingEarning, 500000, WalletTransactionStatus::Cleared);
    ledgerEntry($this->operator, WalletTransactionType::BookingEarning, 700000, WalletTransactionStatus::Cancelled);
    ledgerEntry($this->operator, WalletTransactionType::RefundDeduction, -200000, WalletTransactionStatus::Cleared);
    ledgerEntry($this->operator, WalletTransactionType::ManualAdjustment, -100000, WalletTransactionStatus::Cleared, $reservation->id);
    // Payouts move money out of the wallet; they are not lost earnings.
    ledgerEntry($this->operator, WalletTransactionType::PayoutWithdrawal, -300000, WalletTransactionStatus::Cleared);

    $summary = app(OperatorDashboardService::class)->summary($this->operator);

    expect($summary['earnings_this_month'])->toBe(1200000.0);
});

test('earnings change compares with the same days of last month', function () {
    $this->travelTo(Carbon::parse('2026-09-10 10:00'));
    ledgerEntry($this->operator, WalletTransactionType::BookingEarning, 400000, WalletTransactionStatus::Cleared);

    // Later in September: outside the "same period" comparison.
    $this->travelTo(Carbon::parse('2026-09-25 10:00'));
    ledgerEntry($this->operator, WalletTransactionType::BookingEarning, 900000, WalletTransactionStatus::Cleared);

    $this->travelTo(Carbon::parse('2026-10-12 10:00'));
    ledgerEntry($this->operator, WalletTransactionType::BookingEarning, 600000, WalletTransactionStatus::Cleared);

    $summary = app(OperatorDashboardService::class)->summary($this->operator);

    expect($summary['earnings_this_month'])->toBe(600000.0)
        ->and($summary['earnings_change_pct'])->toBe(50.0);
});

test('earnings change is null without last month earnings', function () {
    expect(app(OperatorDashboardService::class)->summary($this->operator)['earnings_change_pct'])->toBeNull();
});

test('guests, actions and lists count only the right bookings', function () {
    $this->travelTo(Carbon::parse('2026-10-15 09:00'));

    bookingFor($this->operator, $this->package, ['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null, 'requested_date' => '2026-10-15', 'pax_count' => 3]);
    bookingFor($this->operator, $this->package, ['status' => ReservationStatus::PendingConfirmation, 'hold_expires_at' => null, 'requested_date' => '2026-10-20', 'pax_count' => 2]);
    bookingFor($this->operator, $this->package, ['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null, 'requested_date' => '2026-10-30', 'pax_count' => 9]);
    bookingFor($this->operator, $this->package, ['status' => ReservationStatus::Cancelled, 'hold_expires_at' => null, 'requested_date' => '2026-10-16', 'pax_count' => 9]);
    bookingFor($this->operator, $this->package, ['status' => ReservationStatus::PaymentPending, 'hold_expires_at' => now()->addMinutes(20), 'requested_date' => '2026-10-18', 'pax_count' => 1]);
    bookingFor($this->operator, $this->package, ['status' => ReservationStatus::PaymentPending, 'hold_expires_at' => now()->subMinute(), 'requested_date' => '2026-10-18', 'pax_count' => 1]);

    ledgerEntry($this->operator, WalletTransactionType::BookingEarning, 250000, WalletTransactionStatus::Cleared);

    $summary = app(OperatorDashboardService::class)->summary($this->operator);

    expect($summary['upcoming_guests_7d'])->toBe(5)
        ->and($summary['pending_confirmation_count'])->toBe(1)
        ->and($summary['open_pay_links_count'])->toBe(1)
        ->and($summary['awaiting_action'])->toBe(2)
        ->and($summary['available_balance'])->toBe(250000.0)
        ->and($summary['today_departures'])->toHaveCount(1)
        ->and($summary['upcoming_departures'])->toHaveCount(2)
        ->and($summary['recent_bookings'])->toHaveCount(6)
        ->and($summary['upcoming_trips_7d'])->toBe(2)
        ->and($summary['seven_day_schedule'])->toHaveCount(7)
        ->and($summary['seven_day_schedule'][0])->toMatchArray(['isToday' => true, 'pax' => 3, 'trips' => 1])
        ->and($summary['seven_day_schedule'][5])->toMatchArray(['isToday' => false, 'pax' => 2, 'trips' => 1])
        ->and($summary['seven_day_schedule'][1])->toMatchArray(['pax' => 0, 'trips' => 0]);
});
