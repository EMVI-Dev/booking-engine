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
        $paid = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereNotNull('gateway_ref')
            ->get();

        $exceptions = [];

        foreach ($paid as $payment) {
            $hasEarning = WalletTransaction::query()
                ->where('reservation_id', $payment->reservation_id)
                ->where('type', WalletTransactionType::BookingEarning)
                ->exists();

            if ($hasEarning) {
                continue;
            }

            $exceptions[] = [
                'payment_id' => (string) $payment->id,
                'invoice' => (string) $payment->gateway_ref,
                'amount' => (float) $payment->amount,
                'reason' => 'A guest paid, but the money is not in the operator wallet yet.',
                'found_at' => now()->toIso8601String(),
            ];
        }

        PlatformSetting::current()->storeUnmatchedPayments($exceptions);

        return $exceptions;
    }
}
