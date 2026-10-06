<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The numbers and lists on the operator desk dashboard, in one place.
 *
 * Earnings come from the wallet ledger, so they are what the operator actually earns:
 * the guest platform fee is never included, voided earnings drop out, and refunds and
 * booking reversals are subtracted. Dates follow the app timezone (Bali time).
 */
class OperatorDashboardService
{
    /** Bookings that hold a seat and are expected to travel. */
    private const TRAVELLING = [
        ReservationStatus::Confirmed,
        ReservationStatus::PendingConfirmation,
    ];

    /**
     * @return array{
     *     earnings_this_month: float,
     *     earnings_change_pct: float|null,
     *     upcoming_guests_7d: int,
     *     upcoming_trips_7d: int,
     *     awaiting_action: int,
     *     available_balance: float,
     *     pending_confirmation_count: int,
     *     open_pay_links_count: int,
     *     today_departures: Collection<int, Reservation>,
     *     upcoming_departures: Collection<int, Reservation>,
     *     recent_bookings: Collection<int, Reservation>,
     *     seven_day_schedule: list<array{date: CarbonInterface, isToday: bool, pax: int, trips: int}>,
     * }
     */
    public function summary(Operator $operator): array
    {
        $now = now();
        $earningsThisMonth = $this->earningsBetween($operator, $now->copy()->startOfMonth(), $now);
        $earningsSamePeriodLastMonth = $this->earningsBetween(
            $operator,
            $now->copy()->subMonthNoOverflow()->startOfMonth(),
            $now->copy()->subMonthNoOverflow(),
        );

        $pendingConfirmation = $operator->reservations()
            ->where('status', ReservationStatus::PendingConfirmation)
            ->count();

        $openPayLinks = $operator->reservations()
            ->where('status', ReservationStatus::PaymentPending)
            ->where('hold_expires_at', '>', $now)
            ->count();

        $upcoming = $this->travelling($operator)
            ->whereDate('requested_date', '>=', $now->toDateString())
            ->whereDate('requested_date', '<=', $now->copy()->addDays(7)->toDateString());

        return [
            'earnings_this_month' => $earningsThisMonth,
            'earnings_change_pct' => $earningsSamePeriodLastMonth > 0
                ? round(($earningsThisMonth - $earningsSamePeriodLastMonth) / $earningsSamePeriodLastMonth * 100, 1)
                : null,
            'upcoming_guests_7d' => (int) (clone $upcoming)->sum('pax_count'),
            'upcoming_trips_7d' => (clone $upcoming)->count(),
            'awaiting_action' => $pendingConfirmation + $openPayLinks,
            'available_balance' => $operator->getAvailableBalance(),
            'pending_confirmation_count' => $pendingConfirmation,
            'open_pay_links_count' => $openPayLinks,
            'today_departures' => $this->travelling($operator)
                ->with(['bookable', 'latestPayment'])
                ->whereDate('requested_date', $now->toDateString())
                ->get(),
            'upcoming_departures' => (clone $upcoming)
                ->with(['bookable', 'latestPayment'])
                ->orderBy('requested_date')
                ->take(5)
                ->get(),
            'recent_bookings' => $operator->reservations()
                ->with(['bookable', 'latestPayment'])
                ->latest('created_at')
                ->take(6)
                ->get(),
            'seven_day_schedule' => $this->sevenDaySchedule($operator, $now),
        ];
    }

    /**
     * Travelling guests and bookings for today and the next six days, one row per day.
     *
     * @return list<array{date: CarbonInterface, isToday: bool, pax: int, trips: int}>
     */
    public function sevenDaySchedule(Operator $operator, CarbonInterface $from): array
    {
        $days = collect(range(0, 6))->map(fn (int $offset): CarbonInterface => $from->copy()->startOfDay()->addDays($offset));

        $byDate = $this->travelling($operator)
            ->whereDate('requested_date', '>=', $days->first()->toDateString())
            ->whereDate('requested_date', '<=', $days->last()->toDateString())
            ->get(['requested_date', 'pax_count'])
            ->groupBy(fn (Reservation $reservation): string => $reservation->requested_date->toDateString());

        return array_values($days->map(fn (CarbonInterface $day, int $offset): array => [
            'date' => $day,
            'isToday' => $offset === 0,
            'pax' => (int) ($byDate->get($day->toDateString())?->sum('pax_count') ?? 0),
            'trips' => $byDate->get($day->toDateString())?->count() ?? 0,
        ])->all());
    }

    /**
     * Net booking income recorded in the ledger between two moments (inclusive).
     */
    public function earningsBetween(Operator $operator, CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) $operator->walletTransactions()
            ->whereBetween('created_at', [$from, $to])
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('type', WalletTransactionType::BookingEarning)
                        ->where('status', '!=', WalletTransactionStatus::Cancelled);
                })
                    ->orWhere('type', WalletTransactionType::RefundDeduction)
                    ->orWhere(function (Builder $query): void {
                        // Reversal of an already-cleared earning when a booking is cancelled.
                        $query->where('type', WalletTransactionType::ManualAdjustment)
                            ->whereNotNull('reservation_id');
                    });
            })
            ->sum('net_amount');
    }

    /**
     * @return HasMany<Reservation, Operator>
     */
    private function travelling(Operator $operator)
    {
        return $operator->reservations()->whereIn('status', self::TRAVELLING);
    }
}
