<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case PaymentPending = 'payment_pending';
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Expired = 'expired';

    public function isPending(): bool
    {
        return $this === self::PaymentPending || $this === self::PendingConfirmation;
    }

    public function isPaymentPending(): bool
    {
        return $this === self::PaymentPending;
    }

    public function isPendingConfirmation(): bool
    {
        return $this === self::PendingConfirmation;
    }

    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function isExpired(): bool
    {
        return $this === self::Expired;
    }

    /**
     * Statuses an operator may move a booking to from this one.
     *
     * Expiry and payment confirmation are system-driven and are not listed here.
     *
     * @return list<self>
     */
    public function operatorTransitions(): array
    {
        return match ($this) {
            self::PaymentPending, self::PendingConfirmation => [self::Confirmed, self::Declined, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Declined, self::Cancelled, self::Completed, self::Expired => [],
        };
    }

    public function canOperatorTransitionTo(self $target): bool
    {
        return in_array($target, $this->operatorTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PaymentPending => 'Payment Pending',
            self::PendingConfirmation => 'Pending Confirmation',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::Expired => 'Expired',
        };
    }
}
