<?php

namespace App\Services;

use App\Mail\GuestBookingConfirmedMail;
use App\Mail\GuestBookingCreatedMail;
use App\Mail\OperatorNewBookingNotificationMail;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Every guest, operator and vendor message triggered by a booking event.
 *
 * Delivery problems are reported but never break the booking flow that triggered them.
 */
class BookingNotificationService
{
    public function __construct(private VendorDispatchService $vendors) {}

    /**
     * Guest receives their hold + pay link.
     */
    public function holdCreated(Reservation $reservation): void
    {
        if (empty($reservation->guest_email)) {
            return;
        }

        $this->deliver(fn () => Mail::to($reservation->guest_email)->send(new GuestBookingCreatedMail($reservation)));
    }

    /**
     * Booking is confirmed: guest e-ticket, optional operator alert, and vendor dispatch.
     *
     * The operator alert is skipped when the operator confirmed the booking themselves.
     */
    public function bookingConfirmed(Reservation $reservation, bool $notifyOperator = true): void
    {
        if (! empty($reservation->guest_email)) {
            $this->deliver(fn () => Mail::to($reservation->guest_email)->send(new GuestBookingConfirmedMail($reservation)));
        }

        if ($notifyOperator) {
            $recipient = $reservation->operator?->bookingNotificationRecipient();

            if ($recipient !== null) {
                $this->deliver(fn () => Mail::to($recipient)->send(new OperatorNewBookingNotificationMail($reservation)));
            }
        }

        $this->deliver(fn () => $this->vendors->dispatchBookingConfirmation($reservation));
    }

    /**
     * Vendors involved in a paid booking are told it is off.
     */
    public function bookingCancelled(Reservation $reservation): void
    {
        $this->deliver(fn () => $this->vendors->dispatchBookingCancellation($reservation));
    }

    private function deliver(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
