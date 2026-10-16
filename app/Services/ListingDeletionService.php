<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Package;
use App\Models\Product;
use App\Models\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting an activity or package from the operator desk.
 *
 * A listing with upcoming open bookings cannot be deleted: the booking would lose its trip,
 * and (because package_products cascades) package bookings would stop counting against the
 * activity's daily capacity, so the date could be oversold. The operator unpublishes instead.
 * Deleting an activity is also blocked by upcoming bookings of any package that bundles it.
 */
class ListingDeletionService
{
    public function __construct(
        private CapacityService $capacity,
        private MediaStore $media,
    ) {}

    /**
     * Why this listing cannot be deleted right now, or null when it can.
     */
    public function blockReason(Product|Package $listing): ?string
    {
        return $this->blockReasons(collect([$listing]))[$listing->id];
    }

    /**
     * Why each listing cannot be deleted right now (null when it can), keyed by listing id.
     *
     * Runs a fixed number of queries however many listings there are, so desk lists can call it once.
     *
     * @param  Collection<int, Product|Package>  $listings
     * @return array<string, string|null>
     */
    public function blockReasons(Collection $listings): array
    {
        return array_map(
            fn (int $count): ?string => $count === 0 ? null : trans_choice(
                'This listing has :count upcoming booking, so it cannot be deleted. Unpublish it to stop new bookings instead.|This listing has :count upcoming bookings, so it cannot be deleted. Unpublish it to stop new bookings instead.',
                $count,
                ['count' => $count],
            ),
            $this->upcomingBookingCounts($listings),
        );
    }

    /**
     * Delete the listing and its photos, unless upcoming bookings depend on it.
     *
     * @throws ValidationException
     */
    public function delete(Product|Package $listing): void
    {
        // The activity lock makes a booking for the same activities wait until the delete is done.
        DB::transaction(function () use ($listing): void {
            $this->capacity->lockActivities($listing);

            if ($reason = $this->blockReason($listing)) {
                throw ValidationException::withMessages(['listing' => $reason]);
            }

            $listing->delete();
        });

        $media = $this->media->scopedTo($listing->operator_id);
        $media->delete($listing->cover_photo);
        $media->deleteMany(array_values($listing->gallery ?? []));
    }

    /**
     * Open bookings dated today or later for this listing (and, for an activity, its packages).
     */
    public function upcomingBookingCount(Product|Package $listing): int
    {
        return $this->upcomingBookingCounts(collect([$listing]))[$listing->id];
    }

    /**
     * Open bookings dated today or later per listing (an activity also counts its packages' bookings), keyed by listing id.
     *
     * @param  Collection<int, Product|Package>  $listings
     * @return array<string, int>
     */
    public function upcomingBookingCounts(Collection $listings): array
    {
        if ($listings->isEmpty()) {
            return [];
        }

        $productType = (new Product)->getMorphClass();
        $packageType = (new Package)->getMorphClass();
        $productIds = $listings->filter(fn (Product|Package $listing): bool => $listing instanceof Product)->pluck('id')->all();

        /** @var array<string, list<string>> $packageIdsByProduct */
        $packageIdsByProduct = $productIds === [] ? [] : DB::table('package_products')
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'package_id'])
            ->groupBy('product_id')
            ->map(fn ($rows): array => $rows->pluck('package_id')->all())
            ->all();

        $packageIds = array_values(array_unique(array_merge(
            $listings->filter(fn (Product|Package $listing): bool => $listing instanceof Package)->pluck('id')->all(),
            ...array_values($packageIdsByProduct),
        )));

        $bookingCounts = Reservation::query()
            ->whereIn('operator_id', $listings->pluck('operator_id')->unique()->values())
            ->whereIn('status', [
                ReservationStatus::PaymentPending,
                ReservationStatus::PendingConfirmation,
                ReservationStatus::Confirmed,
            ])
            ->whereDate('requested_date', '>=', today())
            ->where(function ($query) use ($productType, $productIds, $packageType, $packageIds): void {
                foreach ([$productType => $productIds, $packageType => $packageIds] as $type => $ids) {
                    if ($ids !== []) {
                        $query->orWhere(fn ($query) => $query->where('bookable_type', $type)->whereIn('bookable_id', $ids));
                    }
                }
            })
            ->groupBy('bookable_type', 'bookable_id')
            ->selectRaw('bookable_type, bookable_id, count(*) as aggregate')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->bookable_type.':'.$row->bookable_id => (int) $row->aggregate]);

        return $listings->mapWithKeys(fn (Product|Package $listing): array => [
            $listing->id => $bookingCounts->get($listing->getMorphClass().':'.$listing->id, 0)
                + collect($packageIdsByProduct[$listing->id] ?? [])
                    ->sum(fn (string $packageId): int => $bookingCounts->get($packageType.':'.$packageId, 0)),
        ])->all();
    }
}
