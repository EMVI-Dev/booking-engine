<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\WalletTransaction;

class PaymentMatchService
{
    /**
     * Find paid guest charges that never landed in an operator wallet.
     *
     * @return list<array{payment_id: string, invoice: string, amount: float, reason: string, found_at: string}>
     */
    public function unmatchedPaidCharges(): array
    {
        $earningReservationIds = WalletTransaction::query()
            ->where('type', WalletTransactionType::BookingEarning)
            ->whereNotNull('reservation_id')
            ->select('reservation_id');

        $exceptions = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereNotNull('gateway_ref')
            ->whereNotIn('reservation_id', $earningReservationIds)
            ->get(['id', 'reservation_id', 'gateway_ref', 'amount'])
            ->map(fn (Payment $payment): array => [
                'payment_id' => (string) $payment->id,
                'invoice' => (string) $payment->gateway_ref,
                'amount' => (float) $payment->amount,
                'reason' => 'A guest paid, but the money is not in the operator wallet yet.',
                'found_at' => now()->toIso8601String(),
            ])
            ->values()
            ->all();

        PlatformSetting::current()->storeUnmatchedPayments($exceptions);

        return $exceptions;
    }
}
