<?php

namespace App\Services;

use App\Contracts\Bookable;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Reservation;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public const DISPUTE_ADMIN_FEE = 150000.00;

    public const PAYOUT_TRANSFER_FEE = 2500.00;

    public const PAYOUT_FEE_WAIVER_AMOUNT = 500000.00;

    /**
     * Credit the operator's wallet from a settled reservation payment.
     */
    public function creditBookingPayment(Payment $payment): ?WalletTransaction
    {
        if ($payment->status !== PaymentStatus::Paid) {
            return null;
        }

        $reservation = $payment->reservation;
        if (! $reservation || ! $reservation->operator) {
            return null;
        }

        // Idempotency: Prevent duplicate credits for the same reservation
        $existing = WalletTransaction::query()
            ->where('reservation_id', $reservation->id)
            ->where('type', WalletTransactionType::BookingEarning)
            ->first();

        if ($existing) {
            return $existing;
        }

        $operator = $reservation->operator;
        $splitDetails = $payment->split_details ?? [];

        $grossAmount = (float) $payment->amount;
        $platformCommission = (float) ($splitDetails['platform_commission'] ?? 0);
        $operatorAmount = (float) ($splitDetails['operator_amount'] ?? ($splitDetails['agent_amount'] ?? ($grossAmount - $platformCommission)));

        if ($platformCommission <= 0 && $operatorAmount <= 0) {
            $commissionRate = $operator->getEffectiveCommissionRate();
            $platformCommission = round($grossAmount * $commissionRate, 2);
            $operatorAmount = round($grossAmount - $platformCommission, 2);
        }

        // Check if departure date is already in the past or today
        $departureDate = Carbon::parse($reservation->requested_date)->startOfDay();
        $isDeparturePassed = $departureDate->isPast() || $departureDate->isToday();

        $status = $isDeparturePassed
            ? WalletTransactionStatus::Cleared
            : WalletTransactionStatus::PendingEscrow;

        $bookableTitle = $reservation->bookable instanceof Bookable
            ? $reservation->bookable->getTitle()
            : 'Tour Experience';

        $earning = WalletTransaction::query()->create([
            'operator_id' => $operator->id,
            'reservation_id' => $reservation->id,
            'type' => WalletTransactionType::BookingEarning,
            'gross_amount' => $grossAmount,
            'fee_amount' => $platformCommission,
            'net_amount' => $operatorAmount,
            'status' => $status,
            'available_at' => $departureDate,
            'description' => "Booking #{$reservation->code} ({$bookableTitle}) - {$reservation->guest_name}",
        ]);

        if ($platformCommission > 0) {
            WalletTransaction::query()->create([
                'operator_id' => $operator->id,
                'reservation_id' => $reservation->id,
                'type' => WalletTransactionType::PlatformCommission,
                'gross_amount' => 0,
                'fee_amount' => $platformCommission,
                'net_amount' => 0,
                'status' => $status,
                'available_at' => $departureDate,
                'description' => "Guest service fee for booking #{$reservation->code}",
            ]);
        }

        return $earning;
    }

    /**
     * Operator requests a payout withdrawal to their registered bank account.
     *
     * @throws ValidationException
     */
    public function createPayoutRequest(Operator $operator, float $amount, ?string $notes = null): PayoutRequest
    {
        if (! $operator->hasValidBankAccount()) {
            throw ValidationException::withMessages([
                'bank' => __('Please configure your bank name, account number, and account holder name in Settings before requesting a payout.'),
            ]);
        }

        $minThreshold = 50000.00; // Rp 50.000
        if ($amount < $minThreshold) {
            throw ValidationException::withMessages([
                'amount' => __('The minimum payout request amount is Rp :min.', ['min' => number_format($minThreshold, 0, ',', '.')]),
            ]);
        }

        $transferFee = $amount < self::PAYOUT_FEE_WAIVER_AMOUNT ? self::PAYOUT_TRANSFER_FEE : 0.0;
        $availableBalance = $operator->getAvailableBalance();
        if (($amount + $transferFee) > $availableBalance) {
            throw ValidationException::withMessages([
                'amount' => $transferFee > 0
                    ? __('Payouts under Rp 500.000 include a Rp 2.500 transfer fee. You need Rp :needed available.', [
                        'needed' => number_format($amount + $transferFee, 0, ',', '.'),
                    ])
                    : __('Requested amount exceeds your available balance of Rp :balance.', [
                        'balance' => number_format($availableBalance, 0, ',', '.'),
                    ]),
            ]);
        }

        return DB::transaction(function () use ($operator, $amount, $notes, $transferFee): PayoutRequest {
            /** @var PayoutRequest $payout */
            $payout = PayoutRequest::query()->create([
                'operator_id' => $operator->id,
                'amount' => $amount,
                'bank_provider' => (string) $operator->bank_provider,
                'bank_account_name' => (string) $operator->bank_account_name,
                'bank_account_number' => (string) $operator->bank_account_number,
                'status' => PayoutStatus::Pending,
                'notes' => $notes,
            ]);

            WalletTransaction::query()->create([
                'operator_id' => $operator->id,
                'payout_request_id' => $payout->id,
                'type' => WalletTransactionType::PayoutWithdrawal,
                'gross_amount' => 0,
                'fee_amount' => $transferFee,
                'net_amount' => -1 * abs($amount + $transferFee),
                'status' => WalletTransactionStatus::Cleared,
                'description' => $transferFee > 0
                    ? "Payout Request #{$payout->reference_number} ({$operator->bank_provider} - {$operator->bank_account_number}) plus Rp 2.500 transfer fee"
                    : "Payout Request #{$payout->reference_number} ({$operator->bank_provider} - {$operator->bank_account_number})",
            ]);

            // Auto-Disburse via DOKU API if amount <= Rp 10.000.000 (Industry Standard)
            if ($amount <= 10000000.00) {
                try {
                    $disbursement = app(DokuPaymentService::class)->disbursePayout($payout);
                    if ($disbursement['success']) {
                        $payout->update([
                            'status' => PayoutStatus::Completed,
                            'processed_by' => 'DOKU BI-FAST API',
                            'processed_at' => now(),
                            'notes' => ($notes ? $notes.' | ' : '').$disbursement['message'].' [Ref: '.$disbursement['reference'].']',
                        ]);
                    } else {
                        $payout->update([
                            'notes' => ($notes ? $notes.' | ' : '').$disbursement['message'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $payout;
        });
    }

    /**
     * Approve and mark a payout request as completed.
     */
    public function approvePayout(PayoutRequest $payout, ?string $proofPath = null, ?string $processedBy = null): PayoutRequest
    {
        $payout->update([
            'status' => PayoutStatus::Completed,
            'proof_document_path' => $proofPath ?? $payout->proof_document_path,
            'processed_by' => $processedBy,
            'processed_at' => now(),
        ]);

        return $payout;
    }

    /**
     * Reject a payout request and restore funds to the operator's available balance.
     */
    public function rejectPayout(PayoutRequest $payout, string $reason, ?string $processedBy = null): PayoutRequest
    {
        return DB::transaction(function () use ($payout, $reason, $processedBy): PayoutRequest {
            $payout->update([
                'status' => PayoutStatus::Rejected,
                'rejection_reason' => $reason,
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);

            $withdrawal = WalletTransaction::query()
                ->where('payout_request_id', $payout->id)
                ->where('type', WalletTransactionType::PayoutWithdrawal)
                ->first();

            WalletTransaction::query()->create([
                'operator_id' => $payout->operator_id,
                'payout_request_id' => $payout->id,
                'type' => WalletTransactionType::ManualAdjustment,
                'gross_amount' => 0,
                'fee_amount' => 0,
                'net_amount' => $withdrawal
                    ? abs((float) $withdrawal->net_amount)
                    : abs((float) $payout->amount),
                'status' => WalletTransactionStatus::Cleared,
                'description' => "Reversal of Rejected Payout #{$payout->reference_number}: {$reason}",
            ]);

            return $payout;
        });
    }

    /**
     * Release all matured escrows whose departure date has arrived/passed.
     */
    public function releaseMaturedEscrows(): int
    {
        return WalletTransaction::query()
            ->where('status', WalletTransactionStatus::PendingEscrow)
            ->where('available_at', '<=', now())
            ->update([
                'status' => WalletTransactionStatus::Cleared,
                'updated_at' => now(),
            ]);
    }

    /**
     * Cancel or reverse wallet earnings/escrows when a booking is cancelled.
     */
    public function cancelBookingEarning(Reservation $reservation, string $reason = 'Booking Cancelled'): ?WalletTransaction
    {
        /** @var WalletTransaction|null $earningTx */
        $earningTx = WalletTransaction::query()
            ->where('reservation_id', $reservation->id)
            ->where('type', WalletTransactionType::BookingEarning)
            ->first();

        if (! $earningTx) {
            return null;
        }

        // If pending in escrow, cancel transaction
        if ($earningTx->status === WalletTransactionStatus::PendingEscrow) {
            $earningTx->update([
                'status' => WalletTransactionStatus::Cancelled,
                'description' => "{$earningTx->description} (Cancelled: {$reason})",
            ]);

            return $earningTx;
        }

        // If already cleared, issue a debit reversal transaction
        if ($earningTx->status === WalletTransactionStatus::Cleared) {
            return WalletTransaction::query()->create([
                'operator_id' => $earningTx->operator_id,
                'reservation_id' => $reservation->id,
                'type' => WalletTransactionType::ManualAdjustment,
                'gross_amount' => -1 * abs((float) $earningTx->gross_amount),
                'fee_amount' => 0,
                'net_amount' => -1 * abs((float) $earningTx->net_amount),
                'status' => WalletTransactionStatus::Cleared,
                'description' => "Reversal for Cancelled Booking #{$reservation->code}: {$reason}",
            ]);
        }

        return $earningTx;
    }

    /**
     * Process a partial refund debit against an operator's wallet.
     */
    public function processPartialRefund(Reservation $reservation, float $refundAmount, string $reason = 'Partial refund issued'): WalletTransaction
    {
        $operator = $reservation->operator;

        return WalletTransaction::query()->create([
            'operator_id' => $operator->id,
            'reservation_id' => $reservation->id,
            'type' => WalletTransactionType::RefundDeduction,
            'gross_amount' => -1 * abs($refundAmount),
            'fee_amount' => 0,
            'net_amount' => -1 * abs($refundAmount),
            'status' => WalletTransactionStatus::Cleared,
            'description' => "Partial Refund for Booking #{$reservation->code}: {$reason}",
        ]);
    }

    /**
     * Money still held on a booking after a card fight.
     */
    public function outstandingDisputeHold(Reservation $reservation): float
    {
        $net = $reservation->relationLoaded('walletTransactions')
            ? (float) $reservation->walletTransactions
                ->whereIn('type', [WalletTransactionType::DisputeHold, WalletTransactionType::DisputeRelease])
                ->sum('net_amount')
            : (float) WalletTransaction::query()
                ->where('reservation_id', $reservation->id)
                ->whereIn('type', [WalletTransactionType::DisputeHold, WalletTransactionType::DisputeRelease])
                ->sum('net_amount');

        return max(0.0, -1 * $net);
    }

    /**
     * Hold operator funds while a card payment is being disputed.
     */
    public function holdDispute(Reservation $reservation, float $amount, string $reason = 'Card payment disputed'): WalletTransaction
    {
        $amount = abs($amount);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Enter an amount to hold.'),
            ]);
        }

        if ($this->outstandingDisputeHold($reservation) > 0) {
            throw ValidationException::withMessages([
                'amount' => __('Money is already held on this booking.'),
            ]);
        }

        $operator = $reservation->operator;

        return WalletTransaction::query()->create([
            'operator_id' => $operator->id,
            'reservation_id' => $reservation->id,
            'type' => WalletTransactionType::DisputeHold,
            'gross_amount' => -1 * $amount,
            'fee_amount' => 0,
            'net_amount' => -1 * $amount,
            'status' => WalletTransactionStatus::Cleared,
            'description' => "Money held for a card dispute on booking #{$reservation->code}: {$reason}",
        ]);
    }

    /**
     * Return held money to the operator wallet after a card fight ends.
     */
    public function releaseDispute(Reservation $reservation, ?float $amount = null, string $reason = 'Card dispute closed'): WalletTransaction
    {
        $outstanding = $this->outstandingDisputeHold($reservation);

        if ($outstanding <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('There is no held money to give back on this booking.'),
            ]);
        }

        $releaseAmount = $amount === null ? $outstanding : abs($amount);

        if ($releaseAmount <= 0 || $releaseAmount > $outstanding) {
            throw ValidationException::withMessages([
                'amount' => __('You can only give back the amount still held.'),
            ]);
        }

        $operator = $reservation->operator;

        return WalletTransaction::query()->create([
            'operator_id' => $operator->id,
            'reservation_id' => $reservation->id,
            'type' => WalletTransactionType::DisputeRelease,
            'gross_amount' => $releaseAmount,
            'fee_amount' => 0,
            'net_amount' => $releaseAmount,
            'status' => WalletTransactionStatus::Cleared,
            'description' => "Held money given back for booking #{$reservation->code}: {$reason}",
        ]);
    }
}
