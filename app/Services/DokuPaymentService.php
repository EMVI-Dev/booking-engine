<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Mail\AgentNewBookingNotificationMail;
use App\Mail\GuestBookingConfirmedMail;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Reservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DokuPaymentService
{
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
        $platform = PlatformSetting::current();
        $mode = config('doku.default_mode', 'sandbox');

        $clientId = (string) ($platform->settings['doku'][$mode]['client_id'] ?? config("doku.{$mode}.client_id", ''));
        $secretKey = (string) ($platform->settings['doku'][$mode]['secret_key'] ?? config("doku.{$mode}.secret_key", ''));
        $baseUrl = (string) config("doku.{$mode}.base_url", 'https://api-sandbox.doku.com');

        if (trim($clientId) === '' || trim($secretKey) === '') {
            return null;
        }

        $targetPath = '/checkout/v1/payment';
        $requestId = (string) Str::uuid();
        $requestTimestamp = gmdate('Y-m-d\TH:i:s\Z');

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
        $digest = base64_encode(hash('sha256', $jsonBody, true));

        $signatureComponent = "Client-Id:{$clientId}\n"
            ."Request-Id:{$requestId}\n"
            ."Request-Timestamp:{$requestTimestamp}\n"
            ."Request-Target:{$targetPath}\n"
            ."Digest:{$digest}";

        $signature = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $signatureComponent, $secretKey, true));

        try {
            $response = Http::withHeaders([
                'Client-Id' => $clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $requestTimestamp,
                'Signature' => $signature,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$baseUrl}{$targetPath}", $body);

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
        $platform = PlatformSetting::current();
        $mode = config('doku.default_mode', 'sandbox');

        $clientId = (string) ($platform->settings['doku'][$mode]['client_id'] ?? config("doku.{$mode}.client_id", ''));
        $secretKey = (string) ($platform->settings['doku'][$mode]['secret_key'] ?? config("doku.{$mode}.secret_key", ''));
        $baseUrl = (string) config("doku.{$mode}.base_url", 'https://api-sandbox.doku.com');

        if (trim($clientId) === '' || trim($secretKey) === '' || trim($invoiceNumber) === '') {
            return null;
        }

        $targetPath = "/orders/v1/status/{$invoiceNumber}";
        $requestId = (string) Str::uuid();
        $requestTimestamp = gmdate('Y-m-d\TH:i:s\Z');

        $signatureComponent = "Client-Id:{$clientId}\n"
            ."Request-Id:{$requestId}\n"
            ."Request-Timestamp:{$requestTimestamp}\n"
            ."Request-Target:{$targetPath}";

        $signature = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $signatureComponent, $secretKey, true));

        try {
            $response = Http::withHeaders([
                'Client-Id' => $clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $requestTimestamp,
                'Signature' => $signature,
            ])->timeout(10)->get("{$baseUrl}{$targetPath}");

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
        Log::info('DOKU Webhook Notification Received', $payload);

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
            return false;
        }

        if (strtoupper($transactionStatus) === 'SUCCESS' || strtoupper($transactionStatus) === 'PAID' || strtoupper($transactionStatus) === '00') {
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

        if (strtoupper($transactionStatus) === 'FAILED' || strtoupper($transactionStatus) === 'EXPIRED') {
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
}
