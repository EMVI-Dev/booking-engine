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
     * Payouts up to this amount are sent to the bank automatically; larger ones wait for an admin.
     */
    public const AUTO_DISBURSE_LIMIT = 10000000.00;

    /**
     * Credit the operator's wallet from a settled reservation payment.
     */
    public function creditBookingPayment(Payment $payment): ?WalletTransaction
    {
        return DB::transaction(function () use ($payment): ?WalletTransaction {
            /** @var Payment|null $lockedPayment */
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if (! $lockedPayment || $lockedPayment->status !== PaymentStatus::Paid) {
                return null;
            }

            $reservation = $lockedPayment->reservation;
            if (! $reservation || ! $reservation->operator) {
                return null;
            }

            // Idempotency: Prevent duplicate credits for the same reservation
            $existing = WalletTransaction::query()
                ->where('reservation_id', $reservation->id)
                ->where('type', WalletTransactionType::BookingEarning)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $operator = $reservation->operator;
            $splitDetails = $lockedPayment->split_details ?? [];

            $grossAmount = (float) $lockedPayment->amount;
            $platformCommission = (float) ($splitDetails['platform_commission'] ?? 0);
            $operatorAmount = (float) ($splitDetails['operator_amount'] ?? ($splitDetails['agent_amount'] ?? ($grossAmount - $platformCommission)));

            // No split recorded: the operator keeps the full charge (no operator commission, spec §5).
            if ($platformCommission <= 0 && $operatorAmount <= 0) {
                $operatorAmount = $grossAmount;
            }

            // Guard against splitDetails exceeding net collected funds (e.g. if raw subtotal was stored without coupon discount)
            if ($grossAmount > 0 && ($operatorAmount + $platformCommission) > $grossAmount) {
                $operatorAmount = max(0.0, round($grossAmount - $platformCommission, 2));
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
        });
    }

    /**
     * Operator requests a payout withdrawal to their registered bank account.
     *
     * @throws ValidationException
     */
    public function createPayoutRequest(Operator $operator, float $amount, ?string $notes = null): PayoutRequest
    {
        $operator->assertRealMoneyMovementAllowed();

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

        $payout = DB::transaction(function () use ($operator, $amount, $notes, $transferFee): PayoutRequest {
            /** @var Operator|null $lockedOperator */
            $lockedOperator = Operator::query()->whereKey($operator->id)->lockForUpdate()->first();

            $availableBalance = $lockedOperator ? $lockedOperator->getAvailableBalance() : 0.0;
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

            return $payout;
        });

        // The bank call runs after the ledger commits so no row lock is held across HTTP,
        // and a gateway failure can never roll back the recorded withdrawal.
        if ($amount <= self::AUTO_DISBURSE_LIMIT) {
            $this->disbursePayout($payout, 'DOKU BI-FAST API');
        }

        return $payout->fresh() ?? $payout;
    }

    /**
     * Send a pending payout to the operator's bank through DOKU.
     *
     * The payout is atomically claimed (Pending → Processing) first, so double clicks or
     * parallel workers can never send the same payout twice. On failure it returns to
     * Pending; the withdrawal stays on the ledger until an admin rejects it.
     *
     * @return array{success: bool, reference: string, message: string}
     */
    public function disbursePayout(PayoutRequest $payout, string $processedBy = 'DOKU BI-FAST API'): array
    {
        $claimed = PayoutRequest::query()
            ->whereKey($payout->id)
            ->where('status', PayoutStatus::Pending)
            ->update(['status' => PayoutStatus::Processing, 'updated_at' => now()]);

        if ($claimed === 0) {
            return [
                'success' => false,
                'reference' => (string) $payout->reference_number,
                'message' => __('This payout is already being processed or is finished.'),
            ];
        }

        $payout->refresh();

        try {
            $disbursement = app(DokuPaymentService::class)->disbursePayout($payout);
        } catch (\Throwable $e) {
            report($e);

            $disbursement = [
                'success' => false,
                'reference' => (string) $payout->reference_number,
                'message' => __('The bank transfer did not go through. The payout is still waiting.'),
            ];
        }

        $notes = $payout->notes ? $payout->notes.' | ' : '';

        if ($disbursement['success']) {
            $payout->update([
                'status' => PayoutStatus::Completed,
                'processed_by' => $processedBy,
                'processed_at' => now(),
                'notes' => $notes.$disbursement['message'].' [Ref: '.$disbursement['reference'].']',
            ]);
        } else {
            $payout->update([
                'status' => PayoutStatus::Pending,
                'notes' => $notes.$disbursement['message'],
            ]);
        }

        return $disbursement;
    }

    /**
     * Approve and mark a payout request as completed (manual bank transfer by an admin).
     *
     * @throws ValidationException
     */
    public function approvePayout(PayoutRequest $payout, ?string $proofPath = null, ?string $processedBy = null): PayoutRequest
    {
        return DB::transaction(function () use ($payout, $proofPath, $processedBy): PayoutRequest {
            $locked = $this->lockPayout($payout);

            if (! in_array($locked->status, [PayoutStatus::Pending, PayoutStatus::Processing], true)) {
                throw ValidationException::withMessages([
                    'payout' => __('Only waiting payouts can be marked as paid.'),
                ]);
            }

            $locked->update([
                'status' => PayoutStatus::Completed,
                'proof_document_path' => $proofPath ?? $locked->proof_document_path,
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * Reject a payout (or record a bank bounce) and restore funds to the operator's balance.
     *
     * Runs once per payout: a second call is refused so funds are never restored twice.
     *
     * @throws ValidationException
     */
    public function rejectPayout(PayoutRequest $payout, string $reason, ?string $processedBy = null): PayoutRequest
    {
        return DB::transaction(function () use ($payout, $reason, $processedBy): PayoutRequest {
            $payout = $this->lockPayout($payout);

            if ($payout->status === PayoutStatus::Rejected) {
                throw ValidationException::withMessages([
                    'payout' => __('This payout was already rejected and its money returned.'),
                ]);
            }

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
     * Re-read a payout under a row lock so status checks and writes cannot interleave.
     */
    protected function lockPayout(PayoutRequest $payout): PayoutRequest
    {
        /** @var PayoutRequest $locked */
        $locked = PayoutRequest::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

        return $locked;
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
     * Release pending escrow funds for a specific completed reservation.
     */
    public function releaseReservationEscrow(Reservation $reservation): int
    {
        return WalletTransaction::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', WalletTransactionStatus::PendingEscrow)
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
        return DB::transaction(function () use ($reservation, $reason): ?WalletTransaction {
            /** @var WalletTransaction|null $earningTx */
            $earningTx = WalletTransaction::query()
                ->where('reservation_id', $reservation->id)
                ->where('type', WalletTransactionType::BookingEarning)
                ->lockForUpdate()
                ->first();

            if (! $earningTx) {
                return null;
            }

            // Still in escrow: void it.
            if ($earningTx->status === WalletTransactionStatus::PendingEscrow) {
                $earningTx->update([
                    'status' => WalletTransactionStatus::Cancelled,
                    'description' => "{$earningTx->description} (Cancelled: {$reason})",
                ]);

                return $earningTx;
            }

            if ($earningTx->status !== WalletTransactionStatus::Cleared) {
                return $earningTx;
            }

            // Already paid out to the balance: debit it back exactly once.
            $existingReversal = WalletTransaction::query()
                ->where('reservation_id', $reservation->id)
                ->where('type', WalletTransactionType::ManualAdjustment)
                ->whereNull('payout_request_id')
                ->where('net_amount', '<', 0)
                ->first();

            if ($existingReversal) {
                return $existingReversal;
            }

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
        });
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
     * Open a card dispute on a paid booking: hold the charged amount plus the DOKU dispute fee.
     *
     * Used by both the DOKU webhook and the admin desk so the held amount is always the same.
     * Returns null when the booking has no paid charge or money is already held.
     */
    public function openCardDispute(Reservation $reservation, string $reason = 'Card payment disputed'): ?WalletTransaction
    {
        $payment = $reservation->latestPayment()->first();

        if ($payment === null || ! $payment->isPaid() || $this->outstandingDisputeHold($reservation) > 0) {
            return null;
        }

        return $this->holdDispute($reservation, (float) $payment->amount + self::DISPUTE_ADMIN_FEE, $reason);
    }

    /**
     * The card dispute was lost: the held amount becomes a permanent deduction (spec Policy 3).
     *
     * Writes a release that closes the hold and a refund deduction of the same amount, so the
     * balance does not move again but the ledger shows the final outcome.
     *
     * @throws ValidationException
     */
    public function settleLostDispute(Reservation $reservation, string $reason = 'Card dispute lost'): WalletTransaction
    {
        return DB::transaction(function () use ($reservation, $reason): WalletTransaction {
            Operator::query()->whereKey($reservation->operator_id)->lockForUpdate()->first();

            $held = $this->outstandingDisputeHold($reservation);

            if ($held <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('There is no held money on this booking.'),
                ]);
            }

            $this->releaseDispute($reservation, $held, 'Hold closed: '.$reason);

            return WalletTransaction::query()->create([
                'operator_id' => $reservation->operator_id,
                'reservation_id' => $reservation->id,
                'type' => WalletTransactionType::RefundDeduction,
                'gross_amount' => -1 * $held,
                'fee_amount' => 0,
                'net_amount' => -1 * $held,
                'status' => WalletTransactionStatus::Cleared,
                'description' => "Chargeback for booking #{$reservation->code}: {$reason}",
            ]);
        });
    }

    /**
     * Platform finance correction on an operator wallet (positive credits, negative debits).
     *
     * @throws ValidationException
     */
    public function recordManualAdjustment(Operator $operator, float $amount, string $reason, ?string $actor = null): WalletTransaction
    {
        $reason = trim($reason);

        if (abs($amount) < 1 || mb_strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'adjustment' => __('Enter an amount and a reason of at least 5 characters.'),
            ]);
        }

        return WalletTransaction::query()->create([
            'operator_id' => $operator->id,
            'type' => WalletTransactionType::ManualAdjustment,
            'gross_amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => $amount,
            'status' => WalletTransactionStatus::Cleared,
            'description' => 'Platform adjustment: '.$reason.($actor ? " (by {$actor})" : ''),
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
