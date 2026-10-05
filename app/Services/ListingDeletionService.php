<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Package;
use App\Models\Product;
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
        $count = $this->upcomingBookingCount($listing);

        if ($count === 0) {
            return null;
        }

        return trans_choice(
            'This listing has :count upcoming booking, so it cannot be deleted. Unpublish it to stop new bookings instead.|This listing has :count upcoming bookings, so it cannot be deleted. Unpublish it to stop new bookings instead.',
            $count,
            ['count' => $count],
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
        $packageIds = $listing instanceof Product
            ? $listing->packages()->pluck('packages.id')->all()
            : [];

        return $listing->operator->reservations()
            ->whereIn('status', [
                ReservationStatus::PaymentPending,
                ReservationStatus::PendingConfirmation,
                ReservationStatus::Confirmed,
            ])
            ->whereDate('requested_date', '>=', today())
            ->where(function ($query) use ($listing, $packageIds): void {
                $query->where(function ($query) use ($listing): void {
                    $query->where('bookable_type', $listing->getMorphClass())
                        ->where('bookable_id', $listing->id);
                });

                if ($packageIds !== []) {
                    $query->orWhere(function ($query) use ($packageIds): void {
                        $query->where('bookable_type', (new Package)->getMorphClass())
                            ->whereIn('bookable_id', $packageIds);
                    });
                }
            })
            ->count();
    }
}
