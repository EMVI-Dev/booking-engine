<?php

namespace App\Services;

use App\Contracts\Bookable;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\CapacityUnavailableException;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The single owner of reservation status changes and their side effects (refunds, wallet,
 * escrow, guest/operator/vendor messages). Storefront, operator desk, DOKU webhooks and
 * scheduled commands all call into here, so a status always changes the same way.
 */
class ReservationLifecycleService
{
    public function __construct(
        private WalletService $wallet,
        private BookingNotificationService $notifications,
        private CapacityService $capacity,
    ) {}

    // ── Gateway events ────────────────────────────────────────────────────────

    /**
     * Apply a confirmed gateway payment. Must be called inside the transaction that locked
     * and marked the payment paid. Returns the reservation to notify once committed, or null
     * when the booking can no longer be honoured (the caller refunds it after commit).
     */
    public function applyPaidPayment(Payment $lockedPayment): ?Reservation
    {
        $reservation = $lockedPayment->reservation;

        if (! $reservation) {
            return null;
        }

        if (! $this->canStillHonour($reservation)) {
            Log::warning('Payment arrived for a booking that can no longer be honoured; refunding.', [
                'reservation' => $reservation->code,
                'status' => $reservation->status->value,
            ]);

            return null;
        }

        $reservation->update([
            'status' => $reservation->operator?->isManualConfirmationEnabled()
                ? ReservationStatus::PendingConfirmation
                : ReservationStatus::Confirmed,
            'hold_expires_at' => null,
        ]);

        $this->wallet->creditBookingPayment($lockedPayment);

        return $reservation;
    }

    /**
     * Side effects of a newly paid booking, run after the payment transaction commits.
     */
    public function announcePaidBooking(Reservation $reservation): void
    {
        $this->notifications->bookingConfirmed($reservation, notifyOperator: true);
    }

    /**
     * A pending invoice failed or expired at the gateway. Settled payments are never touched.
     */
    public function applyFailedPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            /** @var Payment|null $lockedPayment */
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if (! $lockedPayment || $lockedPayment->status !== PaymentStatus::Pending) {
                return;
            }

            $lockedPayment->update(['status' => PaymentStatus::Failed]);

            $reservation = $lockedPayment->reservation;

            if (! $reservation || $reservation->status !== ReservationStatus::PaymentPending) {
                return;
            }

            // Hold already over: release the seats. Otherwise keep the hold so the guest can retry.
            if ($reservation->hold_expires_at && $reservation->hold_expires_at->isPast()) {
                $reservation->update(['status' => ReservationStatus::Declined]);
            }
        });
    }

    /**
     * The gateway reports the payment was refunded outside our own cancel flow.
     *
     * @param  callable(Payment): void  $markRefunded
     */
    public function applyGatewayRefund(Payment $payment, callable $markRefunded): void
    {
        if ($payment->isRefunded()) {
            return;
        }

        $markRefunded($payment);

        $reservation = $payment->reservation;

        if (! $reservation || ! in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::PendingConfirmation], true)) {
            return;
        }

        $this->closeAsCancelled($reservation, 'Refunded via DOKU notification', notifyVendors: true);
    }

    // ── Operator desk ─────────────────────────────────────────────────────────

    /**
     * Why an operator cannot move the booking to the target status now, or null when allowed.
     */
    public function operatorBlockReason(Reservation $reservation, ReservationStatus $target): ?string
    {
        $reservation->refresh()->loadMissing('latestPayment');
        $current = $reservation->status;

        if ($target === ReservationStatus::Declined && $reservation->latestPayment?->isPaid()) {
            return __('Cannot decline a paid reservation. Please cancel and refund instead.');
        }

        if ($target === ReservationStatus::Completed) {
            if ($current !== ReservationStatus::Confirmed) {
                return __('Only confirmed reservations can be marked as completed.');
            }

            if ($reservation->requested_date->isFuture() && ! $reservation->requested_date->isToday()) {
                return __('Cannot mark a reservation completed before its scheduled trip date.');
            }
        }

        if (! $current->canOperatorTransitionTo($target)) {
            return __('A :from booking cannot be changed to :to.', ['from' => $current->label(), 'to' => $target->label()]);
        }

        return null;
    }

    /**
     * Move a booking to a new status on the operator's request.
     *
     * @return array{refunded: bool, refund_amount: float}
     *
     * @throws ValidationException
     */
    public function transitionByOperator(Reservation $reservation, ReservationStatus $target): array
    {
        if ($reason = $this->operatorBlockReason($reservation, $target)) {
            throw ValidationException::withMessages(['status' => $reason]);
        }

        return match ($target) {
            ReservationStatus::Cancelled => $this->cancel($reservation, 'Cancelled and refunded by operator'),
            ReservationStatus::Confirmed => $this->confirmByOperator($reservation),
            ReservationStatus::Completed => $this->complete($reservation),
            default => $this->setStatus($reservation, $target),
        };
    }

    // ── Guest self-service ────────────────────────────────────────────────────

    /**
     * Guest cancels from the receipt page: unpaid holds any time, paid bookings inside the free window.
     *
     * @throws ValidationException
     */
    public function cancelByGuest(Reservation $reservation): Reservation
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

        $this->cancel($reservation, 'Guest cancelled before the free-cancel cutoff', errorKey: 'reservation');

        return $reservation->fresh() ?? $reservation;
    }

    // ── Shared transitions ────────────────────────────────────────────────────

    /**
     * Cancel a booking: refund the guest if paid, reverse the operator's earning, tell vendors.
     *
     * Serialised per booking so two clicks can never send two refunds.
     *
     * @return array{refunded: bool, refund_amount: float}
     *
     * @throws ValidationException
     */
    public function cancel(Reservation $reservation, string $reason, string $errorKey = 'status'): array
    {
        $lock = Cache::lock('reservation-cancel:'.$reservation->id, 60);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                $errorKey => __('This booking is already being cancelled. Please refresh in a moment.'),
            ]);
        }

        try {
            $reservation->refresh();

            if ($reservation->status === ReservationStatus::Cancelled) {
                return ['refunded' => false, 'refund_amount' => 0.0];
            }

            $wasActive = in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::PendingConfirmation], true);
            $payment = $reservation->latestPayment()->first();
            $wasPaid = (bool) $payment?->isPaid();
            $refundAmount = 0.0;

            if ($wasPaid) {
                $refundAmount = (float) $payment->amount;

                if (! app(DokuPaymentService::class)->refundPayment($payment)) {
                    throw ValidationException::withMessages([
                        $errorKey => $errorKey === 'reservation'
                            ? __('We could not send the refund to your original payment. Message the team and we will sort it out.')
                            : __('Could not process automated refund with the payment gateway. Please check gateway connection or refund manually.'),
                    ]);
                }
            }

            $this->closeAsCancelled($reservation, $reason, notifyVendors: $wasPaid || $wasActive);

            return ['refunded' => $wasPaid, 'refund_amount' => $refundAmount];
        } finally {
            $lock->release();
        }
    }

    /**
     * Trip day has arrived or passed: close the booking and release its escrow.
     *
     * @return array{refunded: bool, refund_amount: float}
     */
    public function complete(Reservation $reservation): array
    {
        $reservation->update(['status' => ReservationStatus::Completed, 'hold_expires_at' => null]);
        $this->wallet->releaseReservationEscrow($reservation);

        return ['refunded' => false, 'refund_amount' => 0.0];
    }

    // ── Platform finance ──────────────────────────────────────────────────────

    /**
     * Close a card dispute. Won: held money goes back to the operator. Lost: the hold becomes a
     * permanent deduction, the payment is marked charged back and the booking is cancelled.
     *
     * @throws ValidationException
     */
    public function resolveCardDispute(Reservation $reservation, bool $won): void
    {
        if ($won) {
            $this->wallet->releaseDispute($reservation, null, 'Card dispute won');

            return;
        }

        $this->wallet->settleLostDispute($reservation);

        $payment = $reservation->latestPayment()->first();
        $payment?->update([
            'status' => PaymentStatus::Refunded,
            'refund_status' => 'chargeback',
            'refunded_at' => now(),
        ]);

        if ($reservation->status !== ReservationStatus::Cancelled) {
            $reservation->update(['status' => ReservationStatus::Cancelled, 'hold_expires_at' => null]);
            $this->notifications->bookingCancelled($reservation);
        }
    }

    // ── Scheduled housekeeping ────────────────────────────────────────────────

    /**
     * Expire unpaid holds whose window has closed. Rows are updated only while still unpaid,
     * so a payment landing at the same moment is never overwritten.
     *
     * @return list<string> Codes of expired bookings
     */
    public function expireStaleHolds(): array
    {
        $codes = [];

        Reservation::query()
            ->where('status', ReservationStatus::PaymentPending)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->select(['id', 'code'])
            ->chunkById(200, function ($reservations) use (&$codes): void {
                foreach ($reservations as $reservation) {
                    $updated = Reservation::query()
                        ->whereKey($reservation->id)
                        ->where('status', ReservationStatus::PaymentPending)
                        ->update(['status' => ReservationStatus::Expired, 'updated_at' => now()]);

                    if ($updated > 0) {
                        $codes[] = (string) $reservation->code;
                    }
                }
            });

        return $codes;
    }

    /**
     * Close confirmed trips whose date has passed.
     *
     * @return list<string> Codes of completed bookings
     */
    public function completeFinishedTrips(): array
    {
        $codes = [];

        Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->whereDate('requested_date', '<', today())
            ->chunkById(200, function ($reservations) use (&$codes): void {
                foreach ($reservations as $reservation) {
                    $this->complete($reservation);
                    $codes[] = (string) $reservation->code;
                }
            });

        return $codes;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * @return array{refunded: bool, refund_amount: float}
     */
    private function confirmByOperator(Reservation $reservation): array
    {
        $this->setStatus($reservation, ReservationStatus::Confirmed);
        $this->notifications->bookingConfirmed($reservation, notifyOperator: false);

        return ['refunded' => false, 'refund_amount' => 0.0];
    }

    /**
     * @return array{refunded: bool, refund_amount: float}
     */
    private function setStatus(Reservation $reservation, ReservationStatus $status): array
    {
        $reservation->update(['status' => $status, 'hold_expires_at' => null]);

        return ['refunded' => false, 'refund_amount' => 0.0];
    }

    private function closeAsCancelled(Reservation $reservation, string $reason, bool $notifyVendors): void
    {
        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'hold_expires_at' => null,
        ]);

        $this->wallet->cancelBookingEarning($reservation, $reason);

        if ($notifyVendors) {
            $this->notifications->bookingCancelled($reservation);
        }
    }

    /**
     * A late payment can still be honoured while the hold is live, or when the seats are still free.
     */
    private function canStillHonour(Reservation $reservation): bool
    {
        if (in_array($reservation->status, [
            ReservationStatus::PaymentPending,
            ReservationStatus::PendingConfirmation,
            ReservationStatus::Confirmed,
        ], true)) {
            return true;
        }

        if (! in_array($reservation->status, [ReservationStatus::Expired, ReservationStatus::Declined], true)) {
            return false;
        }

        if (! $reservation->bookable instanceof Bookable) {
            return false;
        }

        try {
            $this->capacity->assertCanAccommodate($reservation->bookable, $reservation->requested_date, $reservation->pax_count, $reservation->id);
        } catch (CapacityUnavailableException) {
            return false;
        }

        return ! $reservation->bookable->isBlackedOutOn($reservation->requested_date);
    }
}
