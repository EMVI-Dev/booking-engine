<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Validation\ValidationException;

class GuestCancellationService
{
    public function __construct(private WalletService $walletService) {}

    public function cancel(Reservation $reservation): Reservation
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            throw ValidationException::withMessages([
                'reservation' => __('This booking is already cancelled.'),
            ]);
        }

        if (! in_array($reservation->status, [
            ReservationStatus::Confirmed,
            ReservationStatus::PendingConfirmation,
            ReservationStatus::PaymentPending,
        ], true)) {
            throw ValidationException::withMessages([
                'reservation' => __('This booking can no longer be cancelled here. Please message the operator.'),
            ]);
        }

        if ($reservation->status !== ReservationStatus::PaymentPending && ! $reservation->isEligibleForFreeCancellation()) {
            throw ValidationException::withMessages([
                'reservation' => __('The free-cancel window has closed. Message the operator if you still need to change this trip.'),
            ]);
        }

        $reservation->loadMissing('latestPayment');
        $payment = $reservation->latestPayment;

        if ($payment?->isPaid() && ! app(DokuPaymentService::class)->refundPayment($payment)) {
            throw ValidationException::withMessages([
                'reservation' => __('We could not send the refund to your original payment. Message the team and we will sort it out.'),
            ]);
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'hold_expires_at' => null,
        ]);

        $this->walletService->cancelBookingEarning($reservation, 'Guest cancelled before the free-cancel cutoff');

        return $reservation->fresh() ?? $reservation;
    }
}
