<?php

namespace App\Services;

use App\Contracts\Bookable;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\CapacityUnavailableException;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The only way a booking hold is created: storefront checkout and operator booking links both
 * go through createHold(), so price, blackout, capacity, coupon and payment rules stay identical.
 */
class ReservationBookingService
{
    public function __construct(
        private BookingPricingService $pricing,
        private CapacityService $capacity,
        private DokuPaymentService $payments,
        private BookingNotificationService $notifications,
    ) {}

    /**
     * Hold seats for 30 minutes (platform setting) and open a payment session.
     *
     * Validation errors use the keys `requested_date`, `coupon_code`, `bookable` and `payment`
     * (the payment page could not be opened; the hold is released).
     *
     * @param  Bookable&Model  $bookable
     * @param  array{name: string, contact: string, email?: ?string, notes?: ?string}  $guest
     * @return array{reservation: Reservation, quote: BookingQuote, checkout_url: string}
     *
     * @throws ValidationException
     * @throws CapacityUnavailableException
     */
    public function createHold(
        Bookable $bookable,
        Operator $operator,
        string $requestedDate,
        int $pax,
        array $guest,
        ?string $couponCode = null,
        bool $notifyGuest = true,
        bool $enforceAdvanceBooking = true,
    ): array {
        $operator->assertCheckoutAllowed();

        $earliest = $enforceAdvanceBooking ? $bookable->earliestBookableDate() : now()->startOfDay();
        if (Carbon::parse($requestedDate)->startOfDay()->lt($earliest)) {
            throw ValidationException::withMessages([
                'requested_date' => $enforceAdvanceBooking && $bookable->getAdvanceBookingHours() > 0
                    ? __('Please choose a date at least :hours hours in advance.', ['hours' => $bookable->getAdvanceBookingHours()])
                    : __('Please choose today or a later date.'),
            ]);
        }

        if ($bookable->getOperatorId() !== (string) $operator->id || ! $bookable->isPublished()) {
            throw ValidationException::withMessages([
                'bookable' => __('This experience is not open for booking right now.'),
            ]);
        }

        if ($bookable->isBlackedOutOn($requestedDate)) {
            throw ValidationException::withMessages([
                'requested_date' => __('The selected date (:date) is unavailable for booking due to scheduled maintenance or operator blackout.', ['date' => $requestedDate]),
            ]);
        }

        $quote = $this->pricing->quote($bookable, $pax, $operator, $couponCode);

        if ($quote->hasCouponError()) {
            throw ValidationException::withMessages([
                'coupon_code' => $quote->couponError,
            ]);
        }

        $termsSnapshot = array_merge($bookable->generateTermsSnapshot(), $quote->toSnapshot());
        $holdMinutes = PlatformSetting::current()->getBookingHoldMinutes();

        /** @var Reservation $reservation */
        $reservation = $this->capacity->reserve(
            $bookable,
            $requestedDate,
            $quote->pax,
            fn (): Reservation => Reservation::query()->create([
                'bookable_type' => $bookable->getMorphClass(),
                'bookable_id' => $bookable->getId(),
                'operator_id' => $operator->id,
                'guest_name' => $guest['name'],
                'guest_contact' => $guest['contact'],
                'guest_email' => filled($guest['email'] ?? null) ? $guest['email'] : null,
                'requested_date' => $requestedDate,
                'pax_count' => $quote->pax,
                'notes' => filled($guest['notes'] ?? null) ? $guest['notes'] : null,
                'terms_snapshot' => $termsSnapshot,
                'status' => ReservationStatus::PaymentPending,
                'hold_expires_at' => now()->addMinutes($holdMinutes),
            ]),
        );

        // The coupon is counted when the booking is paid (ReservationLifecycleService).
        try {
            $session = $this->payments->createPaymentSession($reservation, $quote->total);
        } catch (Throwable $e) {
            report($e);
            $this->releaseUnpayableHold($reservation);

            throw ValidationException::withMessages([
                'payment' => __('We could not open the payment page right now. Nothing was charged. Please try again in a few minutes.'),
            ]);
        }

        if ($notifyGuest) {
            $this->notifications->holdCreated($reservation);
        }

        return [
            'reservation' => $reservation,
            'quote' => $quote,
            'checkout_url' => $session['checkout_url'],
        ];
    }

    /**
     * The payment page could not be opened: free the seats straight away instead of keeping
     * them blocked until the hold runs out.
     */
    private function releaseUnpayableHold(Reservation $reservation): void
    {
        Reservation::query()
            ->whereKey($reservation->id)
            ->where('status', ReservationStatus::PaymentPending)
            ->update(['status' => ReservationStatus::Expired, 'hold_expires_at' => null, 'updated_at' => now()]);

        Payment::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Failed, 'updated_at' => now()]);
    }
}
