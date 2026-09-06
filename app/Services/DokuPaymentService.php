<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Mail\AgentNewBookingNotificationMail;
use App\Mail\GuestBookingConfirmedMail;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\PlatformSetting;
use App\Models\Reservation;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
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
     * Checkout, payouts, refunds, and webhooks all use the admin-selected DOKU mode.
     */
    protected function activeMode(): string
    {
        return PlatformSetting::current()->getDokuMode()->value;
    }

    /**
     * Resolve the active gateway credentials for the configured DOKU mode.
     *
     * @return array{client_id: string, secret_key: string, base_url: string}
     */
    protected function gatewayCredentials(): array
    {
        $platform = PlatformSetting::current();
        $mode = $this->activeMode();

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
        $platform = PlatformSetting::current();
        $agent = $reservation->agent;

        $termsSnapshot = $reservation->terms_snapshot ?? [];
        $subtotal = isset($termsSnapshot['subtotal']) ? (float) $termsSnapshot['subtotal'] : null;
        $serviceFee = isset($termsSnapshot['service_fee']) ? (float) $termsSnapshot['service_fee'] : null;
        $serviceFeeRate = isset($termsSnapshot['service_fee_rate']) ? (float) $termsSnapshot['service_fee_rate'] : null;

        if ($subtotal !== null && $serviceFee !== null) {
            $agentShare = round($subtotal, 2); // 100% of operator's listed price
            $platformCommission = round($serviceFee, 2); // Guest service fee
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
                'guest_service_fee' => $serviceFee ?? $platformCommission,
                'commission_rate' => $commissionRate,
                'platform_commission' => $platformCommission,
                'agent_amount' => $agentShare,
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
        $credentials = $this->gatewayCredentials();

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
                'email' => $reservation->guest_email ?: 'guest@emvi.dev',
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
            ?? ($payload['latestTransactionStatus'] === '00' ? 'SUCCESS' : ($payload['latestTransactionStatus'] ?? 'SUCCESS'))
        );

        if ($invoiceNumber === '') {
            return false;
        }

        $payment = Payment::query()
            ->where('gateway_ref', $invoiceNumber)
            ->first();

        if (! $payment) {
            $subscriptionPayment = SubscriptionPayment::query()
                ->where('invoice_number', $invoiceNumber)
                ->orWhere('gateway_ref', $invoiceNumber)
                ->first();

            if ($subscriptionPayment && (strtoupper($transactionStatus) === 'SUCCESS' || strtoupper($transactionStatus) === 'PAID' || strtoupper($transactionStatus) === '00')) {
                app(SubscriptionProrationService::class)->completePendingPayment($subscriptionPayment, $invoiceNumber, 'doku');

                return true;
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
            $payment->update([
                'status' => PaymentStatus::Refunded,
                'refund_status' => 'refunded',
                'refunded_at' => now(),
            ]);

            return true;
        }

        if ($normalizedStatus === 'SUCCESS' || $normalizedStatus === 'PAID' || $normalizedStatus === '00') {
            // Gateways retry notifications; replaying a settled payment must not
            // re-confirm the reservation or re-send the guest e-voucher.
            if ($payment->status === PaymentStatus::Paid) {
                Log::info('DOKU notification ignored for already-settled payment', ['invoice_number' => $invoiceNumber]);

                return true;
            }

            $payment->update([
                'status' => PaymentStatus::Paid,
            ]);

            $agent = $payment->reservation?->agent;
            $isManual = $agent?->isManualConfirmationEnabled() ?? false;

            $payment->reservation?->update([
                'status' => $isManual ? ReservationStatus::PendingConfirmation : ReservationStatus::Confirmed,
                'hold_expires_at' => null,
            ]);

            // Credit operator wallet ledger
            app(WalletService::class)->creditBookingPayment($payment);

            // Dispatch confirmation and alert emails
            $reservation = $payment->reservation;
            if ($reservation) {
                if (! empty($reservation->guest_email)) {
                    try {
                        Mail::to($reservation->guest_email)
                            ->send(new GuestBookingConfirmedMail($reservation));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $agentEmail = $agent ? ($agent->booking_notification_email ?: $agent->users()->first()?->email) : null;
                if (! empty($agentEmail)) {
                    try {
                        Mail::to($agentEmail)
                            ->send(new AgentNewBookingNotificationMail($reservation));
                    } catch (\Throwable $e) {
                        report($e);
                    }
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

        if (trim($credentials['client_id']) !== '' && trim($credentials['secret_key']) !== '' && $invoiceNumber !== '') {
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

            if (! static::simulatorEnabled()) {
                return false;
            }
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

            if (! static::simulatorEnabled()) {
                return [
                    'success' => false,
                    'reference' => $reference,
                    'message' => __('The bank transfer did not go through. The payout is still waiting.'),
                ];
            }
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
