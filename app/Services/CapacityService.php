<?php

namespace App\Services;

use App\Contracts\Bookable;
use App\Enums\ReservationStatus;
use App\Exceptions\CapacityUnavailableException;
use App\Models\Product;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves how much daily capacity remains for a bookable item on a given date.
 *
 * A package consumes the capacity of every activity it bundles, multiplied by the
 * quantity that package requires, so selling a package draws down the same daily
 * pool as selling those activities standalone.
 */
class CapacityService
{
    /**
     * Reservation statuses that hold inventory. Unpaid holds only count while the
     * 30-minute window is still open; expired, declined and cancelled bookings free
     * their seats back up immediately.
     */
    protected function applyBlockingStatuses(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn('status', [
                ReservationStatus::PendingConfirmation,
                ReservationStatus::Confirmed,
                ReservationStatus::Completed,
            ])->orWhere(function (Builder $query): void {
                $query->where('status', ReservationStatus::PaymentPending)
                    ->where(function (Builder $query): void {
                        $query->whereNull('hold_expires_at')
                            ->orWhere('hold_expires_at', '>', now());
                    });
            });
        });
    }

    /**
     * Total pax already committed per bookable for an operator on a single date.
     *
     * @return array<string, int> Keyed by "{type}:{id}"
     */
    public function committedPaxByBookable(string $operatorId, Carbon|string $date, ?string $excludeReservationId = null): array
    {
        $query = Reservation::query()
            ->where('operator_id', $operatorId)
            ->whereDate('requested_date', Carbon::parse($date)->toDateString());

        if ($excludeReservationId !== null) {
            $query->whereKeyNot($excludeReservationId);
        }

        return $this->applyBlockingStatuses($query)
            ->selectRaw('bookable_type, bookable_id, SUM(pax_count) as committed_pax')
            ->groupBy('bookable_type', 'bookable_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $row->bookable_type.':'.$row->bookable_id => (int) $row->committed_pax,
            ])
            ->all();
    }

    /**
     * Units of a single activity already consumed on a date, counting both standalone
     * bookings and every package that bundles it.
     *
     * @param  array<string, int>  $committedPax
     */
    public function consumedUnitsForProduct(Product $product, array $committedPax): int
    {
        $consumed = $committedPax['product:'.$product->id] ?? 0;

        foreach ($product->packages as $package) {
            $paxOnPackage = $committedPax['package:'.$package->id] ?? 0;
            $consumed += $paxOnPackage * max(1, (int) ($package->pivot->quantity_required ?? 1));
        }

        return $consumed;
    }

    /**
     * Remaining pax that can still be booked for a bookable on a date.
     *
     * Returns null when the item has no underlying activities and is therefore
     * not capacity constrained.
     */
    public function remainingCapacity(Bookable $bookable, Carbon|string $date, ?string $excludeReservationId = null): ?int
    {
        $requirements = $bookable->getRequiredProducts();

        if ($requirements->isEmpty()) {
            return null;
        }

        $committedPax = $this->committedPaxByBookable(
            $bookable->getOperatorId(),
            $date,
            $excludeReservationId
        );

        $remaining = null;

        foreach ($requirements as $requirement) {
            /** @var Product $product */
            $product = $requirement['product'];
            $quantityPerPax = max(1, (int) $requirement['quantity']);
            $dailyCapacity = (int) $product->capacity_per_day;

            if ($dailyCapacity <= 0) {
                continue;
            }

            $availableUnits = max(0, $dailyCapacity - $this->consumedUnitsForProduct($product, $committedPax));
            $availablePax = intdiv($availableUnits, $quantityPerPax);

            $remaining = $remaining === null ? $availablePax : min($remaining, $availablePax);
        }

        return $remaining;
    }

    /**
     * Which bundled activity is the binding constraint, for guest-facing messaging.
     */
    public function limitingProductTitle(Bookable $bookable, Carbon|string $date, ?string $excludeReservationId = null): ?string
    {
        $requirements = $bookable->getRequiredProducts();
        $committedPax = $this->committedPaxByBookable($bookable->getOperatorId(), $date, $excludeReservationId);

        $limitingTitle = null;
        $lowest = null;

        foreach ($requirements as $requirement) {
            /** @var Product $product */
            $product = $requirement['product'];
            $dailyCapacity = (int) $product->capacity_per_day;

            if ($dailyCapacity <= 0) {
                continue;
            }

            $availablePax = intdiv(
                max(0, $dailyCapacity - $this->consumedUnitsForProduct($product, $committedPax)),
                max(1, (int) $requirement['quantity'])
            );

            if ($lowest === null || $availablePax < $lowest) {
                $lowest = $availablePax;
                $limitingTitle = $product->getTitle();
            }
        }

        return $limitingTitle;
    }

    /**
     * Reserve capacity and run the callback, or throw if the date cannot take the party.
     *
     * The underlying activity rows are locked for the duration of the transaction so
     * two guests racing for the last seats cannot both pass the availability check.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws CapacityUnavailableException
     */
    public function reserve(Bookable $bookable, Carbon|string $date, int $paxCount, callable $callback, ?string $excludeReservationId = null): mixed
    {
        return DB::transaction(function () use ($bookable, $date, $paxCount, $callback, $excludeReservationId) {
            $productIds = $bookable->getRequiredProducts()
                ->map(fn (array $requirement): string => $requirement['product']->id)
                ->all();

            if ($productIds !== []) {
                Product::query()->whereIn('id', $productIds)->lockForUpdate()->get();
            }

            $this->assertCanAccommodate($bookable, $date, $paxCount, $excludeReservationId);

            return $callback();
        });
    }

    /**
     * @throws CapacityUnavailableException
     */
    public function assertCanAccommodate(Bookable $bookable, Carbon|string $date, int $paxCount, ?string $excludeReservationId = null): void
    {
        $remaining = $this->remainingCapacity($bookable, $date, $excludeReservationId);

        if ($remaining === null || $remaining >= $paxCount) {
            return;
        }

        $readableDate = Carbon::parse($date)->format('j M Y');

        throw new CapacityUnavailableException(
            $remaining <= 0
                ? __('Sorry, :title is fully booked on :date. Please choose another date.', [
                    'title' => $bookable->getTitle(),
                    'date' => $readableDate,
                ])
                : trans_choice('Only :count spot left for :date. Please reduce your party size or choose another date.|Only :count spots left for :date. Please reduce your party size or choose another date.', $remaining, [
                    'count' => $remaining,
                    'date' => $readableDate,
                ]),
            $remaining,
            $this->limitingProductTitle($bookable, $date, $excludeReservationId),
        );
    }
}
