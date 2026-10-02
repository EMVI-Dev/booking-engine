<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\PlatformSetting;
use App\Models\Reservation;
use App\Models\SubscriptionPayment;
use App\Services\Integrations\DokuClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Guest checkout, subscription checkout, webhooks, refunds and payouts: the money rules.
 * Every call to DOKU itself goes through DokuClient.
 */
class DokuPaymentService
{
    public function __construct(
        protected DokuClient $doku,
    ) {}

    /**
     * Whether the offline payment simulator may be used on this deployment.
     *
     * The simulator confirms reservations without a real payment, so it defaults to
     * local/testing only and must be opted into explicitly anywhere else.
     */
    public static function simulatorEnabled(): bool
    {
        $configured = config('doku.simulator_enabled');

        if ($configured !== null) {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return app()->environment('local', 'testing');
    }

    /**
     * Whether valid DOKU gateway credentials are configured for the active mode.
     */
    public static function isConfigured(): bool
    {
        return DokuClient::isConfigured();
    }

    /**
     * Whether a DOKU notification can be trusted. Unsigned requests are only accepted
     * when no credentials exist and the offline simulator is on.
     */
    public function verifyNotificationSignature(Request $request): bool
    {
        return $this->doku->verifyNotificationSignature($request) ?? static::simulatorEnabled();
    }

    /**
     * Create a pending Payment record and initiate a DOKU payment session for a reservation.
     *
     * @return array{payment: Payment, checkout_url: string, invoice_number: string}
     */
    public function createPaymentSession(Reservation $reservation, float $totalAmount): array
    {
        $agent = $reservation->operator;
        $agent?->assertCheckoutAllowed();

        $platform = PlatformSetting::current();

        $termsSnapshot = $reservation->terms_snapshot ?? [];
        $subtotal = isset($termsSnapshot['subtotal']) ? (float) $termsSnapshot['subtotal'] : null;
        $serviceFee = isset($termsSnapshot['service_fee']) ? (float) $termsSnapshot['service_fee'] : null;
        $serviceFeeRate = isset($termsSnapshot['service_fee_rate']) ? (float) $termsSnapshot['service_fee_rate'] : null;
        $discountAmount = isset($termsSnapshot['discount_amount']) ? (float) $termsSnapshot['discount_amount'] : 0.0;
        $couponCode = isset($termsSnapshot['coupon_code']) ? (string) $termsSnapshot['coupon_code'] : null;

        if ($subtotal !== null && $serviceFee !== null) {
            $discountedSubtotal = max(0.0, $subtotal - $discountAmount);
            $platformCommission = round($serviceFee, 2); // Guest service fee
            // Operator earning is discounted subtotal, capped at net collected funds
            $agentShare = min(round($discountedSubtotal, 2), max(0.0, round($totalAmount - $platformCommission, 2)));
            $commissionRate = (float) ($serviceFeeRate ?? 0.05);
        } else {
            // Legacy reservations without a price snapshot: operators keep 100% (no operator commission, spec §5).
            $commissionRate = 0.0;
            $platformCommission = 0.0;
            $agentShare = round($totalAmount, 2);
        }

        $invoiceNumber = 'INV-'.strtoupper(Str::random(6)).'-'.time();

        /** @var Payment $payment */
        $payment = Payment::query()->create([
            'reservation_id' => $reservation->id,
            'amount' => $totalAmount,
            'gateway' => 'doku',
            'gateway_ref' => $invoiceNumber,
            'split_details' => [
                'subtotal' => $subtotal ?? $agentShare,
                'coupon_code' => $couponCode,
                'discount_amount' => $discountAmount,
                'discounted_subtotal' => $discountedSubtotal ?? $agentShare,
                'guest_service_fee' => $serviceFee ?? $platformCommission,
                'commission_rate' => $commissionRate,
                'platform_commission' => $platformCommission,
                'agent_amount' => $agentShare,
                'operator_amount' => $agentShare,
                'agent_bank_provider' => $agent->bank_provider ?? 'BCA',
                'agent_bank_account' => $agent->bank_account_number ?? '',
                'agent_bank_account_name' => $agent->bank_account_name ?? '',
            ],
            'status' => PaymentStatus::Pending,
        ]);

        $checkoutUrl = $this->buildCheckoutUrl($payment, $reservation, $invoiceNumber);

        return [
            'payment' => $payment,
            'checkout_url' => $checkoutUrl,
            'invoice_number' => $invoiceNumber,
        ];
    }

    /**
     * Build the DOKU Checkout URL or simulated sandbox checkout redirect.
     */
    protected function buildCheckoutUrl(Payment $payment, Reservation $reservation, string $invoiceNumber): string
    {
        // Attempt to create a live DOKU Jokul Checkout session if credentials exist
        $jokulUrl = $this->createJokulCheckoutSession($payment, $reservation, $invoiceNumber);

        if ($jokulUrl) {
            return $jokulUrl;
        }

        if (! static::simulatorEnabled()) {
            throw new \RuntimeException('No DOKU credentials are configured and the offline payment simulator is disabled, so no checkout session could be created.');
        }

        // Fallback to internal simulation sandbox route
        return route('storefront.payment.simulate', [
            'reservation' => $reservation->id,
            'payment' => $payment->id,
            'ref' => $invoiceNumber,
        ]);
    }

    /**
     * Request a hosted payment page URL from DOKU Jokul Checkout for a guest booking.
     */
    public function createJokulCheckoutSession(Payment $payment, Reservation $reservation, string $invoiceNumber): ?string
    {
        return $this->doku->createCheckout(
            invoiceNumber: $invoiceNumber,
            amount: (float) $payment->amount,
            callbackUrl: route('storefront.reservation.receipt', $reservation),
            dueMinutes: 30,
            customer: [
                'name' => $reservation->guest_name,
                'email' => $reservation->guest_email ?: 'guest@travelengine.id',
                'phone' => $reservation->guest_contact,
            ],
            operator: $reservation->operator,
        );
    }

    /**
     * Request a hosted payment page URL from DOKU Jokul Checkout for an operator subscription invoice.
     */
    public function createSubscriptionCheckoutSession(SubscriptionPayment $payment): ?string
    {
        $operator = $payment->operator;
        $owner = $operator?->users()->first();

        return $this->doku->createCheckout(
            invoiceNumber: (string) $payment->invoice_number,
            amount: (float) $payment->net_amount_paid,
            callbackUrl: route('settings.plan'),
            dueMinutes: 60,
            customer: [
                'name' => $owner?->name ?: ($operator?->name ?: 'Tour Operator'),
                'email' => $operator?->billing_email ?: ($owner?->email ?: 'billing@travelengine.id'),
                'phone' => $operator?->contact_whatsapp,
            ],
            operator: $operator,
        );
    }

    /**
     * Query live payment transaction status from DOKU API.
     */
    public function queryPaymentStatus(string $invoiceNumber, ?Operator $operator = null): ?string
    {
        return $this->doku->orderStatus($invoiceNumber, $operator);
    }

    /**
     * Synchronize a payment status directly against DOKU servers.
     */
    public function syncPaymentStatus(Payment $payment): bool
    {
        if (empty($payment->gateway_ref)) {
            return false;
        }

        $status = $this->queryPaymentStatus($payment->gateway_ref, $payment->reservation?->operator);

        if ($status) {
            return $this->processNotification([
                'order' => ['invoice_number' => $payment->gateway_ref],
                'transaction' => ['status' => $status],
            ]);
        }

        return false;
    }

    /**
     * Synchronize a pending subscription invoice against DOKU (return-path safety net).
     */
    public function syncSubscriptionPaymentStatus(SubscriptionPayment $payment): bool
    {
        if ($payment->status !== SubscriptionPayment::STATUS_PENDING) {
            return false;
        }

        $invoiceNumber = (string) ($payment->invoice_number ?: $payment->gateway_ref);

        if ($invoiceNumber === '') {
            return false;
        }

        $status = $this->queryPaymentStatus($invoiceNumber, $payment->operator);

        if ($status) {
            return $this->processNotification([
                'order' => [
                    'invoice_number' => $invoiceNumber,
                    'amount' => (float) $payment->net_amount_paid,
                ],
                'transaction' => ['status' => $status],
            ]);
        }

        return false;
    }

    /**
     * Process an incoming notification / webhook callback from DOKU.
     *
     * @param  array<string, mixed>  $payload
     */
    public function processNotification(array $payload): bool
    {
        Log::info('DOKU payment notification received', [
            'invoice_number' => (string) (
                $payload['order']['invoice_number']
                ?? $payload['invoice_number']
                ?? $payload['originalPartnerReferenceNo']
                ?? $payload['partnerReferenceNo']
                ?? $payload['trx_id']
                ?? ''
            ),
            'status' => (string) (
                $payload['transaction']['status']
                ?? $payload['status']
                ?? $payload['transactionStatus']
                ?? ''
            ),
        ]);

        $invoiceNumber = (string) (
            $payload['order']['invoice_number']
            ?? $payload['invoice_number']
            ?? $payload['originalPartnerReferenceNo']
            ?? $payload['partnerReferenceNo']
            ?? $payload['trx_id']
            ?? ''
        );

        $transactionStatus = (string) (
            $payload['transaction']['status']
            ?? $payload['status']
            ?? $payload['transactionStatus']
            ?? ''
        );
        $rawAmount = $payload['order']['amount']
            ?? $payload['amount']['value']
            ?? $payload['amount']
            ?? null;
        $paidAmount = is_numeric($rawAmount) ? (float) $rawAmount : null;
        $channelId = (string) (
            $payload['channel']['id']
            ?? $payload['payment']['payment_method_type']
            ?? $payload['service']['id']
            ?? $payload['additional_info']['channel']
            ?? ''
        );

        if ($invoiceNumber === '') {
            return false;
        }

        $payment = Payment::query()
            ->where('gateway_ref', $invoiceNumber)
            ->first();

        if (! $payment) {
            $subscriptionPayment = SubscriptionPayment::query()
                ->where(function ($query) use ($invoiceNumber): void {
                    $query->where('invoice_number', $invoiceNumber)
                        ->orWhere('gateway_ref', $invoiceNumber);
                })
                ->first();

            if ($subscriptionPayment) {
                $normalizedSubscriptionStatus = strtoupper($transactionStatus);

                if (in_array($normalizedSubscriptionStatus, ['SUCCESS', 'PAID', '00'], true)) {
                    $expectedAmount = (float) $subscriptionPayment->net_amount_paid;

                    if ($paidAmount !== null && $expectedAmount > 0 && $paidAmount < $expectedAmount) {
                        Log::warning('DOKU notification rejected: paid amount is less than subscription invoice net amount', [
                            'invoice_number' => $invoiceNumber,
                            'expected' => $expectedAmount,
                            'received' => $paidAmount,
                        ]);

                        return false;
                    }

                    if ($channelId !== '') {
                        $breakdown = $subscriptionPayment->breakdown ?? [];
                        $breakdown['channel'] = $channelId;
                        $subscriptionPayment->update(['breakdown' => $breakdown]);
                    }

                    app(SubscriptionProrationService::class)->completePendingPayment($subscriptionPayment, $invoiceNumber, 'doku');

                    return true;
                }

                if ($subscriptionPayment->status === SubscriptionPayment::STATUS_PENDING && in_array($normalizedSubscriptionStatus, ['FAILED', 'EXPIRED', 'CANCELLED', 'DENIED'], true)) {
                    $subscriptionPayment->update([
                        'status' => SubscriptionPayment::STATUS_FAILED,
                        'gateway_ref' => $invoiceNumber,
                    ]);

                    $operator = $subscriptionPayment->operator;

                    if ($operator) {
                        app(OperatorActivitySlackNotifier::class)->subscriptionPaymentFailed(
                            $operator,
                            $subscriptionPayment->fresh() ?? $subscriptionPayment,
                        );
                    }

                    return true;
                }
            }

            return false;
        }

        $normalizedStatus = strtoupper($transactionStatus);

        if (in_array($normalizedStatus, ['DISPUTE', 'DISPUTE_OPENED', 'CHARGEBACK'], true)) {
            $reservation = $payment->reservation;

            if ($reservation) {
                app(WalletService::class)->openCardDispute($reservation);
            }

            return true;
        }

        if (in_array($normalizedStatus, ['REFUND', 'REFUNDED', 'SUCCESS_REFUND'], true)) {
            $this->applyExternalRefund($payment);

            return true;
        }

        if ($normalizedStatus === 'SUCCESS' || $normalizedStatus === 'PAID' || $normalizedStatus === '00') {
            $expectedAmount = (float) $payment->amount;

            if ($paidAmount !== null && $expectedAmount > 0 && $paidAmount < $expectedAmount) {
                Log::warning('DOKU notification rejected: paid amount is less than booking payment amount', [
                    'invoice_number' => $invoiceNumber,
                    'expected' => $expectedAmount,
                    'received' => $paidAmount,
                ]);

                return false;
            }

            $confirmedReservation = null;
            $shouldNotify = false;
            $mustRefund = false;
            $lifecycle = app(ReservationLifecycleService::class);

            DB::transaction(function () use ($payment, $channelId, $lifecycle, &$confirmedReservation, &$shouldNotify, &$mustRefund): void {
                /** @var Payment|null $lockedPayment */
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

                // Only an open (or previously failed) invoice can become paid. A replayed
                // success must never re-open a refunded payment.
                if (! $lockedPayment || ! in_array($lockedPayment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
                    return;
                }

                $updates = ['status' => PaymentStatus::Paid];
                if ($channelId !== '') {
                    $splitDetails = $lockedPayment->split_details ?? [];
                    $splitDetails['channel'] = $channelId;
                    $updates['split_details'] = $splitDetails;
                }

                $lockedPayment->update($updates);

                $confirmedReservation = $lifecycle->applyPaidPayment($lockedPayment);
                $shouldNotify = $confirmedReservation !== null;
                $mustRefund = $confirmedReservation === null;
            });

            if ($mustRefund) {
                $this->refundPayment($payment->fresh() ?? $payment);

                return true;
            }

            if (! $shouldNotify) {
                Log::info('DOKU notification ignored for already-settled payment', ['invoice_number' => $invoiceNumber]);

                return true;
            }

            $lifecycle->announcePaidBooking($confirmedReservation);

            return true;
        }

        if ($normalizedStatus === 'FAILED' || $normalizedStatus === 'EXPIRED') {
            app(ReservationLifecycleService::class)->applyFailedPayment($payment);

            return true;
        }

        return false;
    }

    /**
     * Send the guest's money back through DOKU, or locally when the simulator is on.
     *
     * When real gateway credentials are configured, API failures fail closed — the
     * offline simulator must not mark money as refunded after a live/sandbox rejection.
     */
    public function refundPayment(Payment $payment): bool
    {
        if ($payment->status === PaymentStatus::Refunded || $payment->refund_status === 'refunded') {
            return true;
        }

        if ($payment->status !== PaymentStatus::Paid) {
            return true;
        }

        $operator = $payment->reservation?->operator;

        // Demo bookings are sample data; never send them to a real gateway.
        if ($operator?->isDemo()) {
            $this->markPaymentRefunded($payment);

            return true;
        }

        $invoiceNumber = (string) $payment->gateway_ref;

        // Credentials are set: DOKU's answer is final, never fall back to the simulator.
        if ($this->doku->hasCredentials($operator) && $invoiceNumber !== '') {
            if (! $this->doku->refund($invoiceNumber, (float) $payment->amount, $operator)) {
                return false;
            }

            $this->markPaymentRefunded($payment);

            return true;
        }

        if (! static::simulatorEnabled()) {
            return false;
        }

        $this->markPaymentRefunded($payment);

        return true;
    }

    protected function markPaymentRefunded(Payment $payment): void
    {
        $payment->update([
            'status' => PaymentStatus::Refunded,
            'refund_status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }

    /**
     * Apply a refund reported by DOKU (webhook) and keep reservation/wallet in sync.
     */
    protected function applyExternalRefund(Payment $payment): void
    {
        app(ReservationLifecycleService::class)->applyGatewayRefund(
            $payment,
            fn (Payment $refunded) => $this->markPaymentRefunded($refunded),
        );
    }

    /**
     * Disburse an automated bank transfer via DOKU Jokul Payout / Disbursement API (BI-FAST).
     *
     * @return array{success: bool, reference: string, message: string}
     */
    public function disbursePayout(PayoutRequest $payoutRequest): array
    {
        $operator = $payoutRequest->operator;

        // Stable per payout so a retried call can be de-duplicated by DOKU instead of paying twice.
        $reference = 'PO-DISB-'.$payoutRequest->reference_number;

        if ($operator?->isDemo()) {
            return [
                'success' => false,
                'reference' => $reference,
                'message' => __('Payouts are off on the sample shop.'),
            ];
        }

        if ($this->doku->hasCredentials($operator)) {
            $sent = $this->doku->transfer(
                reference: $reference,
                amount: (float) $payoutRequest->amount,
                bank: (string) $payoutRequest->bank_provider,
                accountNumber: (string) $payoutRequest->bank_account_number,
                accountName: (string) $payoutRequest->bank_account_name,
                remark: 'Payout for '.($operator?->name ?? 'Merchant'),
                operator: $operator,
            );

            // Credentials are set: fail closed even if the offline simulator is enabled.
            return [
                'success' => $sent,
                'reference' => $reference,
                'message' => $sent
                    ? __('Disbursed instantly via DOKU BI-FAST API')
                    : __('The bank transfer did not go through. The payout is still waiting.'),
            ];
        }

        if (! static::simulatorEnabled()) {
            return [
                'success' => false,
                'reference' => $reference,
                'message' => __('Bank payouts are not configured yet. The payout is still waiting.'),
            ];
        }

        return [
            'success' => true,
            'reference' => 'SIM-BIFAST-'.strtoupper(Str::random(8)),
            'message' => __('Disbursed via DOKU Simulated BI-FAST Gateway'),
        ];
    }
}
