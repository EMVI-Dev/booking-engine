<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Mail\GuestBookingConfirmedMail;
use App\Mail\OperatorNewBookingNotificationMail;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\PlatformSetting;
use App\Models\Reservation;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DokuPaymentService
{
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
        $platform = PlatformSetting::current();
        $mode = $platform->getDokuMode()->value;

        $clientId = $mode === 'live' ? $platform->getDokuLiveClientId() : $platform->getDokuSandboxClientId();
        $secretKey = $mode === 'live' ? $platform->getDokuLiveSecretKey() : $platform->getDokuSandboxSecretKey();

        return ! empty($clientId) && ! empty($secretKey);
    }

    /**
     * Checkout, payouts, refunds, and webhooks all use the admin-selected DOKU mode.
     */
    protected function activeMode(?Operator $operator = null): string
    {
        if ($operator?->isDemo()) {
            return 'sandbox';
        }

        return PlatformSetting::current()->getDokuMode()->value;
    }

    /**
     * Resolve the active gateway credentials for the configured DOKU mode.
     *
     * @return array{client_id: string, secret_key: string, base_url: string}
     */
    protected function gatewayCredentials(?Operator $operator = null): array
    {
        $platform = PlatformSetting::current();
        $mode = $this->activeMode($operator);

        return [
            'client_id' => $mode === 'live' ? $platform->getDokuLiveClientId() : $platform->getDokuSandboxClientId(),
            'secret_key' => $mode === 'live' ? $platform->getDokuLiveSecretKey() : $platform->getDokuSandboxSecretKey(),
            'base_url' => (string) config("doku.{$mode}.base_url", 'https://api-sandbox.doku.com'),
        ];
    }

    /**
     * HMAC-SHA256 headers required by DOKU Jokul APIs.
     *
     * @return array<string, string>
     */
    protected function signedHeaders(string $targetPath, ?string $jsonBody = null): array
    {
        $credentials = $this->gatewayCredentials();
        $requestId = (string) Str::uuid();
        $requestTimestamp = gmdate('Y-m-d\TH:i:s\Z');

        $signatureComponent = "Client-Id:{$credentials['client_id']}\n"
            ."Request-Id:{$requestId}\n"
            ."Request-Timestamp:{$requestTimestamp}\n"
            ."Request-Target:{$targetPath}";

        if ($jsonBody !== null) {
            $signatureComponent .= "\nDigest:".base64_encode(hash('sha256', $jsonBody, true));
        }

        return [
            'Client-Id' => $credentials['client_id'],
            'Request-Id' => $requestId,
            'Request-Timestamp' => $requestTimestamp,
            'Signature' => 'HMACSHA256='.base64_encode(hash_hmac('sha256', $signatureComponent, $credentials['secret_key'], true)),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Verify the HMAC-SHA256 signature DOKU attaches to notification callbacks.
     *
     * Returns false whenever the payload cannot be proven to originate from DOKU. When
     * no secret is configured the request is only trusted on simulator deployments.
     */
    public function verifyNotificationSignature(Request $request): bool
    {
        $credentials = $this->gatewayCredentials();
        $clientId = trim($credentials['client_id']);
        $secretKey = trim($credentials['secret_key']);

        if ($secretKey === '' || $clientId === '') {
            return static::simulatorEnabled();
        }

        $providedSignature = (string) $request->header('Signature', '');
        $requestId = (string) $request->header('Request-Id', '');
        $requestTimestamp = (string) $request->header('Request-Timestamp', '');
        $requestClientId = (string) $request->header('Client-Id', '');

        if ($providedSignature === '' || $requestId === '' || $requestTimestamp === '') {
            return false;
        }

        if (! hash_equals($clientId, $requestClientId)) {
            return false;
        }

        $digest = base64_encode(hash('sha256', $request->getContent(), true));

        $signatureComponent = "Client-Id:{$clientId}\n"
            ."Request-Id:{$requestId}\n"
            ."Request-Timestamp:{$requestTimestamp}\n"
            .'Request-Target:'.config('doku.notification_path', '/api/v1/payments/doku/notify')."\n"
            ."Digest:{$digest}";

        $expectedSignature = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $signatureComponent, $secretKey, true));

        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Create a pending Payment record and initiate a DOKU payment session for a reservation.
     *
     * @return array{payment: Payment, checkout_url: string, invoice_number: string}
     */
    public function createPaymentSession(Reservation $reservation, float $totalAmount): array
    {
        $agent = $reservation->operator ?? $reservation->agent;
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
            // Fallback for direct bookings or legacy reservations
            $commissionRate = $agent ? $agent->getEffectiveCommissionRate() : $platform->getCommissionRate();
            $platformCommission = round($totalAmount * $commissionRate, 2);
            $agentShare = round($totalAmount - $platformCommission, 2);
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
     * Request a hosted payment page URL from DOKU Jokul Checkout API.
     */
    public function createJokulCheckoutSession(Payment $payment, Reservation $reservation, string $invoiceNumber): ?string
    {
        $operator = $reservation->operator ?? $reservation->agent;
        $credentials = $this->gatewayCredentials($operator);

        if (trim($credentials['client_id']) === '' || trim($credentials['secret_key']) === '') {
            return null;
        }

        $targetPath = '/checkout/v1/payment';

        $body = [
            'order' => [
                'amount' => (int) round((float) $payment->amount),
                'invoice_number' => $invoiceNumber,
                'currency' => 'IDR',
                'callback_url' => route('storefront.reservation.receipt', $reservation),
                'auto_redirect' => true,
            ],
            'payment' => [
                'payment_due_date' => 30,
            ],
            'customer' => [
                'name' => $reservation->guest_name,
                'email' => $reservation->guest_email ?: 'guest@travelengine.id',
                'phone' => $reservation->guest_contact ?: '081234567890',
            ],
        ];

        $jsonBody = (string) json_encode($body);

        try {
            $response = Http::withHeaders($this->signedHeaders($targetPath, $jsonBody))
                ->timeout(10)
                ->connectTimeout(3)
                ->post($credentials['base_url'].$targetPath, $body);

            if ($response->successful()) {
                $paymentUrl = $response->json('response.payment.url');
                if (! empty($paymentUrl) && is_string($paymentUrl)) {
                    return $paymentUrl;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * Request a hosted payment page URL from DOKU Jokul Checkout API for an operator subscription invoice.
     */
    public function createSubscriptionCheckoutSession(SubscriptionPayment $payment): ?string
    {
        $operator = $payment->operator;
        $credentials = $this->gatewayCredentials($operator);

        if (trim($credentials['client_id']) === '' || trim($credentials['secret_key']) === '') {
            return null;
        }

        $targetPath = '/checkout/v1/payment';
        $owner = $operator?->users()->first();

        $body = [
            'order' => [
                'amount' => (int) round((float) $payment->net_amount_paid),
                'invoice_number' => (string) $payment->invoice_number,
                'currency' => 'IDR',
                'callback_url' => route('settings.plan'),
                'auto_redirect' => true,
            ],
            'payment' => [
                'payment_due_date' => 60,
            ],
            'customer' => [
                'name' => $owner?->name ?: ($operator?->name ?: 'Tour Operator'),
                'email' => $operator?->billing_email ?: ($owner?->email ?: 'billing@travelengine.id'),
                'phone' => $operator?->contact_whatsapp ?: '081234567890',
            ],
        ];

        $jsonBody = (string) json_encode($body);

        try {
            $response = Http::withHeaders($this->signedHeaders($targetPath, $jsonBody))
                ->timeout(10)
                ->connectTimeout(3)
                ->post($credentials['base_url'].$targetPath, $body);

            if ($response->successful()) {
                $paymentUrl = $response->json('response.payment.url');
                if (! empty($paymentUrl) && is_string($paymentUrl)) {
                    return $paymentUrl;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * Query live payment transaction status from DOKU API.
     */
    public function queryPaymentStatus(string $invoiceNumber): ?string
    {
        $credentials = $this->gatewayCredentials();

        if (trim($credentials['client_id']) === '' || trim($credentials['secret_key']) === '' || trim($invoiceNumber) === '') {
            return null;
        }

        $targetPath = "/orders/v1/status/{$invoiceNumber}";

        try {
            $response = Http::withHeaders($this->signedHeaders($targetPath))
                ->timeout(10)
                ->connectTimeout(3)
                ->get($credentials['base_url'].$targetPath);

            if ($response->successful()) {
                $status = $response->json('transaction.status') ?? $response->json('response.transaction.status') ?? $response->json('status');
                if (! empty($status) && is_string($status)) {
                    return strtoupper($status);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * Synchronize a payment status directly against DOKU servers.
     */
    public function syncPaymentStatus(Payment $payment): bool
    {
        if (empty($payment->gateway_ref)) {
            return false;
        }

        $status = $this->queryPaymentStatus($payment->gateway_ref);

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
        if ($payment->status !== 'pending') {
            return false;
        }

        $invoiceNumber = (string) ($payment->invoice_number ?: $payment->gateway_ref);

        if ($invoiceNumber === '') {
            return false;
        }

        $status = $this->queryPaymentStatus($invoiceNumber);

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
     * Whether real gateway credentials are configured for the active mode.
     *
     * When true, refund/payout must not fall back to the offline simulator after an API failure.
     */
    protected function hasGatewayCredentials(?Operator $operator = null): bool
    {
        $credentials = $this->gatewayCredentials($operator);

        return trim($credentials['client_id']) !== '' && trim($credentials['secret_key']) !== '';
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
                    if ($paidAmount !== null && $paidAmount > 0 && $paidAmount < (float) $subscriptionPayment->net_amount_paid) {
                        Log::warning('DOKU notification rejected: paid amount is less than subscription invoice net amount', [
                            'invoice_number' => $invoiceNumber,
                            'expected' => (float) $subscriptionPayment->net_amount_paid,
                            'received' => $paidAmount,
                        ]);

                        return false;
                    }

                    app(SubscriptionProrationService::class)->completePendingPayment($subscriptionPayment, $invoiceNumber, 'doku');

                    return true;
                }

                if ($subscriptionPayment->status === 'pending' && in_array($normalizedSubscriptionStatus, ['FAILED', 'EXPIRED', 'CANCELLED', 'DENIED'], true)) {
                    $subscriptionPayment->update([
                        'status' => 'failed',
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

            if ($reservation && app(WalletService::class)->outstandingDisputeHold($reservation) <= 0) {
                app(WalletService::class)->holdDispute(
                    $reservation,
                    (float) $payment->amount + WalletService::DISPUTE_ADMIN_FEE,
                    'Card payment disputed'
                );
            }

            return true;
        }

        if (in_array($normalizedStatus, ['REFUND', 'REFUNDED', 'SUCCESS_REFUND'], true)) {
            $this->applyExternalRefund($payment);

            return true;
        }

        if ($normalizedStatus === 'SUCCESS' || $normalizedStatus === 'PAID' || $normalizedStatus === '00') {
            if ($paidAmount !== null && $paidAmount > 0 && $paidAmount < (float) $payment->amount) {
                Log::warning('DOKU notification rejected: paid amount is less than booking payment amount', [
                    'invoice_number' => $invoiceNumber,
                    'expected' => (float) $payment->amount,
                    'received' => $paidAmount,
                ]);

                return false;
            }

            $confirmedReservation = null;
            $shouldNotify = false;

            DB::transaction(function () use ($payment, &$confirmedReservation, &$shouldNotify): void {
                /** @var Payment|null $lockedPayment */
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

                if (! $lockedPayment || $lockedPayment->status === PaymentStatus::Paid) {
                    return;
                }

                $lockedPayment->update([
                    'status' => PaymentStatus::Paid,
                ]);

                $reservation = $lockedPayment->reservation;
                $agent = $reservation?->agent;
                $isManual = $agent?->isManualConfirmationEnabled() ?? false;

                $reservation?->update([
                    'status' => $isManual ? ReservationStatus::PendingConfirmation : ReservationStatus::Confirmed,
                    'hold_expires_at' => null,
                ]);

                // Credit operator wallet ledger
                app(WalletService::class)->creditBookingPayment($lockedPayment);

                $confirmedReservation = $reservation;
                $shouldNotify = true;
            });

            if (! $shouldNotify) {
                Log::info('DOKU notification ignored for already-settled payment', ['invoice_number' => $invoiceNumber]);

                return true;
            }

            // Dispatch confirmation and alert emails
            if ($confirmedReservation) {
                if (! empty($confirmedReservation->guest_email)) {
                    try {
                        Mail::to($confirmedReservation->guest_email)
                            ->send(new GuestBookingConfirmedMail($confirmedReservation));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $agent = $confirmedReservation->agent;
                $agentEmail = $agent ? ($agent->booking_notification_email ?: $agent->users()->first()?->email) : null;
                if (! empty($agentEmail)) {
                    try {
                        Mail::to($agentEmail)
                            ->send(new OperatorNewBookingNotificationMail($confirmedReservation));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                // Dispatch vendor booking notifications
                try {
                    app(VendorDispatchService::class)->dispatchBookingConfirmation($confirmedReservation);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return true;
        }

        if ($normalizedStatus === 'FAILED' || $normalizedStatus === 'EXPIRED') {
            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);

            // Only mark reservation as Declined if hold is expired; otherwise keep PaymentPending so guest can retry payment
            if ($payment->reservation && $payment->reservation->hold_expires_at && $payment->reservation->hold_expires_at->isPast()) {
                $payment->reservation->update([
                    'status' => ReservationStatus::Declined,
                ]);
            } else {
                $payment->reservation?->update([
                    'status' => ReservationStatus::PaymentPending,
                ]);
            }

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

        $credentials = $this->gatewayCredentials();
        $invoiceNumber = (string) $payment->gateway_ref;
        $hasCredentials = $this->hasGatewayCredentials();

        if ($hasCredentials && $invoiceNumber !== '') {
            $targetPath = '/orders/v1/refund';
            $body = [
                'order' => [
                    'invoice_number' => $invoiceNumber,
                    'amount' => (int) round((float) $payment->amount),
                ],
            ];
            $jsonBody = (string) json_encode($body);

            try {
                $response = Http::withHeaders($this->signedHeaders($targetPath, $jsonBody))
                    ->timeout(10)
                    ->connectTimeout(3)
                    ->post($credentials['base_url'].$targetPath, $body);

                if ($response->successful()) {
                    $this->markPaymentRefunded($payment);

                    return true;
                }

                Log::warning('DOKU refund was rejected', [
                    'invoice_number' => $invoiceNumber,
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }

            return false;
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
        if ($payment->status === PaymentStatus::Refunded || $payment->refund_status === 'refunded') {
            return;
        }

        $this->markPaymentRefunded($payment);

        $reservation = $payment->reservation;

        if (! $reservation) {
            return;
        }

        if (! in_array($reservation->status, [
            ReservationStatus::Confirmed,
            ReservationStatus::PendingConfirmation,
        ], true)) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'hold_expires_at' => null,
        ]);

        app(WalletService::class)->cancelBookingEarning(
            $reservation,
            'Refunded via DOKU notification'
        );

        try {
            app(VendorDispatchService::class)->dispatchBookingCancellation($reservation);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Disburse an automated bank transfer via DOKU Jokul Payout / Disbursement API (BI-FAST).
     *
     * @return array{success: bool, reference: string, message: string}
     */
    public function disbursePayout(PayoutRequest $payoutRequest): array
    {
        $credentials = $this->gatewayCredentials();
        $reference = 'PO-DISB-'.strtoupper(Str::random(6)).'-'.time();

        if (trim($credentials['client_id']) !== '' && trim($credentials['secret_key']) !== '') {
            $targetPath = '/disbursement/v1/transfer';
            $body = [
                'partner_reference_no' => $reference,
                'amount' => [
                    'value' => number_format((float) $payoutRequest->amount, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'beneficiary_bank_code' => strtoupper((string) $payoutRequest->bank_provider),
                'beneficiary_account_number' => (string) $payoutRequest->bank_account_number,
                'beneficiary_name' => (string) $payoutRequest->bank_account_name,
                'remark' => 'Payout for '.($payoutRequest->operator?->name ?? 'Merchant'),
            ];
            $jsonBody = (string) json_encode($body);

            try {
                $response = Http::withHeaders($this->signedHeaders($targetPath, $jsonBody))
                    ->timeout(10)
                    ->connectTimeout(3)
                    ->post($credentials['base_url'].$targetPath, $body);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'reference' => $reference,
                        'message' => __('Disbursed instantly via DOKU BI-FAST API'),
                    ];
                }

                Log::warning('DOKU payout was rejected', [
                    'reference' => $reference,
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }

            // Credentials are set — fail closed even if the offline simulator is enabled.
            return [
                'success' => false,
                'reference' => $reference,
                'message' => __('The bank transfer did not go through. The payout is still waiting.'),
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
