<?php

namespace App\Services;

use App\Mail\GuestBookingCancelledMail;
use App\Mail\GuestBookingConfirmedMail;
use App\Mail\GuestBookingCreatedMail;
use App\Mail\OperatorBookingCancelledMail;
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

        $this->deliver(fn () => Mail::to($reservation->guest_email)->queue(new GuestBookingCreatedMail($reservation)));
    }

    /**
     * Booking is confirmed: guest e-ticket, optional operator alert, and vendor dispatch.
     *
     * The operator alert is skipped when the operator confirmed the booking themselves.
     */
    public function bookingConfirmed(Reservation $reservation, bool $notifyOperator = true): void
    {
        if (! empty($reservation->guest_email)) {
            $this->deliver(fn () => Mail::to($reservation->guest_email)->queue(new GuestBookingConfirmedMail($reservation)));
        }

        if ($notifyOperator) {
            $recipient = $reservation->operator?->bookingNotificationRecipient();

            if ($recipient !== null) {
                $this->deliver(fn () => Mail::to($recipient)->queue(new OperatorNewBookingNotificationMail($reservation)));
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

    /**
     * A paid or confirmed booking was cancelled: the guest hears what happens to their money, and
     * the operator is told when the guest cancelled it themselves (an operator already knows).
     */
    public function cancellationConfirmed(Reservation $reservation, bool $cancelledByGuest, float $refundAmount): void
    {
        if (! empty($reservation->guest_email)) {
            $this->deliver(fn () => Mail::to($reservation->guest_email)->queue(new GuestBookingCancelledMail($reservation, $cancelledByGuest, $refundAmount)));
        }

        if (! $cancelledByGuest) {
            return;
        }

        $recipient = $reservation->operator?->bookingNotificationRecipient();

        if ($recipient !== null) {
            $this->deliver(fn () => Mail::to($recipient)->queue(new OperatorBookingCancelledMail($reservation, $refundAmount)));
        }
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
