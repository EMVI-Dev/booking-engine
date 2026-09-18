<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Enums\ReservationStatus;
use App\Mail\VendorBookingCancelledMail;
use App\Mail\VendorBookingNotificationMail;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class VendorDispatchService
{
    /**
     * Dispatch booking confirmation emails to all involved vendors.
     */
    public function dispatchBookingConfirmation(Reservation $reservation): void
    {
        $vendorsWithActivities = $this->resolveVendorsForReservation($reservation);

        foreach ($vendorsWithActivities as $item) {
            /** @var Vendor $vendor */
            $vendor = $item['vendor'];
            /** @var array<int, string> $activities */
            $activities = $item['activities'];

            if (! empty($vendor->reservation_email)) {
                try {
                    Mail::to($vendor->reservation_email)
                        ->send(new VendorBookingNotificationMail($reservation, $vendor, $activities));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /**
     * Dispatch booking cancellation alerts to all involved vendors.
     */
    public function dispatchBookingCancellation(Reservation $reservation): void
    {
        $vendorsWithActivities = $this->resolveVendorsForReservation($reservation);

        foreach ($vendorsWithActivities as $item) {
            /** @var Vendor $vendor */
            $vendor = $item['vendor'];
            /** @var array<int, string> $activities */
            $activities = $item['activities'];

            if (! empty($vendor->reservation_email)) {
                try {
                    Mail::to($vendor->reservation_email)
                        ->send(new VendorBookingCancelledMail($reservation, $vendor, $activities));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /**
     * Send a test booking dispatch notification copy to the vendor's reservation email.
     */
    public function sendTestNotification(Vendor $vendor, Operator $operator): void
    {
        if (empty($vendor->reservation_email)) {
            return;
        }

        $product = $vendor->products()->first() ?? $operator->products()->first();
        if (! $product) {
            $product = $operator->products()->create([
                'name' => $vendor->name.' Activity',
                'slug' => Str::slug($vendor->name.'-activity-'.Str::random(6)),
                'category' => 'Tour',
                'capacity_per_day' => 10,
                'sellable_standalone' => true,
                'price' => 150000,
                'status' => ListingStatus::Published,
                'vendor_id' => $vendor->id,
            ]);
        }

        $activities = [$product->name];

        $sampleReservation = Reservation::where('operator_id', $operator->id)
            ->where('code', 'LIKE', 'DEMO-VND-%')
            ->first();

        if (! $sampleReservation) {
            $sampleReservation = new Reservation([
                'operator_id' => $operator->id,
                'bookable_type' => $product->getMorphClass(),
                'bookable_id' => $product->id,
                'code' => 'DEMO-VND-'.rand(1000, 9999),
                'public_token' => (string) Str::random(48),
                'guest_name' => 'Jane Doe (Sample Guest)',
                'guest_email' => 'sample.guest@example.com',
                'guest_contact' => '+62 812-3456-7890',
                'requested_date' => now()->addDays(3),
                'pax_count' => 2,
                'notes' => __('Sample booking dispatch notification sent to verify email delivery.'),
                'terms_snapshot' => [
                    'terms' => $operator->terms_and_conditions ?? 'Standard terms apply.',
                    'cancellation_policy' => 'Standard cancellation policy.',
                ],
                'status' => ReservationStatus::Confirmed,
            ]);
            $sampleReservation->save();
        } else {
            $sampleReservation->update([
                'bookable_type' => $product->getMorphClass(),
                'bookable_id' => $product->id,
            ]);
        }

        $sampleReservation->loadMissing(['operator', 'bookable']);

        Mail::to($vendor->reservation_email)
            ->send(new VendorBookingNotificationMail($sampleReservation, $vendor, $activities, isTest: true));
    }

    /**
     * Resolve all unique vendors and their corresponding activity names for a reservation.
     *
     * @return Collection<string, array{vendor: Vendor, activities: array<int, string>}>
     */
    public function resolveVendorsForReservation(Reservation $reservation): Collection
    {
        $reservation->loadMissing(['operator', 'bookable']);
        $bookable = $reservation->bookable;

        $results = collect();

        if ($bookable instanceof Product) {
            $bookable->loadMissing('vendor');
            if ($bookable->vendor && $bookable->vendor->is_active) {
                $results->put($bookable->vendor->id, [
                    'vendor' => $bookable->vendor,
                    'activities' => [$bookable->name],
                ]);
            }
        } elseif ($bookable instanceof Package) {
            $products = $bookable->products()->with('vendor')->get();

            /** @var Collection<string, Collection<int, Product>> $grouped */
            $grouped = $products->groupBy('vendor_id');

            foreach ($grouped as $vendorId => $vendorProducts) {
                if (empty($vendorId)) {
                    continue;
                }

                /** @var Product|null $firstProduct */
                $firstProduct = $vendorProducts->first();
                $vendor = $firstProduct?->vendor;

                if ($vendor && $vendor->is_active) {
                    $results->put($vendor->id, [
                        'vendor' => $vendor,
                        'activities' => $vendorProducts->pluck('name')->values()->all(),
                    ]);
                }
            }
        }

        return $results;
    }
}
