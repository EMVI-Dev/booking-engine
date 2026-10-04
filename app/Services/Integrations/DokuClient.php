<?php

namespace App\Services\Integrations;

use App\Enums\DokuMode;
use App\Models\Operator;
use App\Services\PhoneNumber;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The only class that talks to the DOKU Jokul API.
 *
 * It knows credentials, the active mode, request signing and DOKU's endpoints and payload
 * shapes. It knows nothing about bookings, wallets or invoices: DokuPaymentService decides
 * what to charge, refund or pay out and calls this client to do it.
 */
class DokuClient
{
    public const CHECKOUT_PATH = '/checkout/v1/payment';

    public const REFUND_PATH = '/orders/v1/refund';

    public const TRANSFER_PATH = '/disbursement/v1/transfer';

    private const FALLBACK_PHONE = '081234567890';

    /**
     * The configured mode (DOKU_MODE): sandbox or live.
     */
    public static function mode(): DokuMode
    {
        return DokuMode::tryFrom((string) config('doku.mode')) ?? DokuMode::Sandbox;
    }

    /**
     * Whether credentials are configured for the configured mode (ignores demo routing).
     */
    public static function isConfigured(): bool
    {
        $mode = self::mode()->value;

        return filled(config("doku.{$mode}.client_id")) && filled(config("doku.{$mode}.secret_key"));
    }

    /**
     * Demo shops always use sandbox; everyone else follows DOKU_MODE.
     */
    public function modeFor(?Operator $operator = null): string
    {
        return $operator?->isDemo() ? DokuMode::Sandbox->value : self::mode()->value;
    }

    /**
     * @return array{client_id: string, secret_key: string, base_url: string}
     */
    public function credentials(?Operator $operator = null): array
    {
        $mode = $this->modeFor($operator);

        return [
            'client_id' => trim((string) config("doku.{$mode}.client_id")),
            'secret_key' => trim((string) config("doku.{$mode}.secret_key")),
            'base_url' => rtrim((string) config("doku.{$mode}.base_url"), '/'),
        ];
    }

    public function hasCredentials(?Operator $operator = null): bool
    {
        $credentials = $this->credentials($operator);

        return $credentials['client_id'] !== '' && $credentials['secret_key'] !== '';
    }

    /**
     * Ask DOKU for a hosted checkout page. Returns the payment URL, or null when DOKU is not
     * configured or refuses the request.
     *
     * @param  array{name: string, email: string, phone: ?string}  $customer
     */
    public function createCheckout(
        string $invoiceNumber,
        float $amount,
        string $callbackUrl,
        int $dueMinutes,
        array $customer,
        ?Operator $operator = null,
    ): ?string {
        if (! $this->hasCredentials($operator)) {
            return null;
        }

        $response = $this->send('post', self::CHECKOUT_PATH, [
            'order' => [
                'amount' => (int) round($amount),
                'invoice_number' => $invoiceNumber,
                'currency' => 'IDR',
                'callback_url' => $callbackUrl,
                'auto_redirect' => true,
            ],
            'payment' => [
                'payment_due_date' => $dueMinutes,
            ],
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $this->checkoutPhone($customer['phone']),
            ],
        ], $operator);

        if ($response === null) {
            return null;
        }

        if (! $response->successful()) {
            Log::error('DOKU checkout session creation failed', [
                'status' => $response->status(),
                'invoice_number' => $invoiceNumber,
                'error' => $this->errorSummary($response),
            ]);

            return null;
        }

        $paymentUrl = $response->json('response.payment.url');

        return is_string($paymentUrl) && $paymentUrl !== '' ? $paymentUrl : null;
    }

    /**
     * Current transaction status for an invoice (e.g. SUCCESS, FAILED), or null if unknown.
     */
    public function orderStatus(string $invoiceNumber, ?Operator $operator = null): ?string
    {
        if (trim($invoiceNumber) === '' || ! $this->hasCredentials($operator)) {
            return null;
        }

        $response = $this->send('get', "/orders/v1/status/{$invoiceNumber}", null, $operator);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $status = $response->json('transaction.status') ?? $response->json('response.transaction.status') ?? $response->json('status');

        return is_string($status) && $status !== '' ? strtoupper($status) : null;
    }

    /**
     * Refund a paid invoice in full. True only when DOKU accepted the refund.
     */
    public function refund(string $invoiceNumber, float $amount, ?Operator $operator = null): bool
    {
        $response = $this->send('post', self::REFUND_PATH, [
            'order' => [
                'invoice_number' => $invoiceNumber,
                'amount' => (int) round($amount),
            ],
        ], $operator);

        if ($response?->successful()) {
            return true;
        }

        if ($response !== null) {
            Log::warning('DOKU refund was rejected', [
                'invoice_number' => $invoiceNumber,
                'status' => $response->status(),
                'error' => $this->errorSummary($response),
            ]);
        }

        return false;
    }

    /**
     * BI-FAST bank transfer. The reference is stable per payout so DOKU can de-duplicate retries.
     */
    public function transfer(
        string $reference,
        float $amount,
        string $bank,
        string $accountNumber,
        string $accountName,
        string $remark,
        ?Operator $operator = null,
    ): bool {
        $response = $this->send('post', self::TRANSFER_PATH, [
            'partner_reference_no' => $reference,
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'beneficiary_bank_code' => $this->bankCode($bank),
            'beneficiary_account_number' => $accountNumber,
            'beneficiary_name' => $accountName,
            'remark' => $remark,
        ], $operator);

        if ($response?->successful()) {
            return true;
        }

        if ($response !== null) {
            Log::warning('DOKU payout was rejected', [
                'reference' => $reference,
                'status' => $response->status(),
                'error' => $this->errorSummary($response),
            ]);
        }

        return false;
    }

    /**
     * Verify the HMAC-SHA256 signature DOKU attaches to notification callbacks.
     *
     * Returns null when no credentials are configured, so the caller decides whether an
     * unsigned request may be trusted (simulator only).
     */
    public function verifyNotificationSignature(Request $request): ?bool
    {
        $credentials = $this->credentials();

        if ($credentials['client_id'] === '' || $credentials['secret_key'] === '') {
            return null;
        }

        $providedSignature = (string) $request->header('Signature', '');
        $requestId = (string) $request->header('Request-Id', '');
        $requestTimestamp = (string) $request->header('Request-Timestamp', '');
        $requestClientId = (string) $request->header('Client-Id', '');

        if ($providedSignature === '' || $requestId === '' || $requestTimestamp === '') {
            return false;
        }

        if (! hash_equals($credentials['client_id'], $requestClientId)) {
            return false;
        }

        $signatureComponent = "Client-Id:{$credentials['client_id']}\n"
            ."Request-Id:{$requestId}\n"
            ."Request-Timestamp:{$requestTimestamp}\n"
            .'Request-Target:'.config('doku.notification_path', '/api/v1/payments/doku/notify')."\n"
            .'Digest:'.base64_encode(hash('sha256', $request->getContent(), true));

        $expected = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $signatureComponent, $credentials['secret_key'], true));

        return hash_equals($expected, $providedSignature);
    }

    /**
     * Digits only for DOKU checkout; DOKU rejects short or empty numbers, so use a placeholder.
     */
    public function checkoutPhone(?string $phone): string
    {
        $digits = PhoneNumber::digits($phone);

        return strlen($digits) < 9 ? self::FALLBACK_PHONE : $digits;
    }

    /**
     * Map what operators type as their bank to a DOKU BI-FAST bank code.
     */
    public function bankCode(string $bank): string
    {
        $cleaned = strtoupper(trim($bank));

        return match (true) {
            str_contains($cleaned, 'BCA') => 'BCA',
            str_contains($cleaned, 'MANDIRI') => 'MANDIRI',
            str_contains($cleaned, 'BRI') => 'BRI',
            str_contains($cleaned, 'BNI') => 'BNI',
            str_contains($cleaned, 'BSI') || str_contains($cleaned, 'SYARIAH INDONESIA') => 'BSI',
            str_contains($cleaned, 'CIMB') => 'CIMB',
            str_contains($cleaned, 'PERMATA') => 'PERMATA',
            str_contains($cleaned, 'DANAMON') => 'DANAMON',
            str_contains($cleaned, 'JAGO') => 'JAGO',
            str_contains($cleaned, 'BTN') => 'BTN',
            str_contains($cleaned, 'SEABANK') => 'SEABANK',
            str_contains($cleaned, 'BNC') || str_contains($cleaned, 'NEO') => 'BNC',
            default => preg_replace('/[^A-Z0-9]/', '', $cleaned) ?: 'BCA',
        };
    }

    /**
     * HMAC-SHA256 headers required by DOKU Jokul APIs.
     *
     * @return array<string, string>
     */
    public function signedHeaders(string $targetPath, ?string $jsonBody = null, ?Operator $operator = null): array
    {
        $credentials = $this->credentials($operator);
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
     * Signed request to DOKU. Null when the request could not be sent at all (network error).
     *
     * @param  array<string, mixed>|null  $body
     */
    private function send(string $method, string $targetPath, ?array $body, ?Operator $operator): ?Response
    {
        $jsonBody = $body === null ? null : (string) json_encode($body);
        $url = $this->credentials($operator)['base_url'].$targetPath;

        try {
            $request = Http::withHeaders($this->signedHeaders($targetPath, $jsonBody, $operator))
                ->timeout(10)
                ->connectTimeout(3);

            return $method === 'get' ? $request->get($url) : $request->post($url, $body ?? []);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Only DOKU's error code and message, never the whole body: DOKU can echo the request
     * back, which carries guest names, phone numbers and bank accounts.
     *
     * @return array{code: mixed, message: mixed}
     */
    private function errorSummary(Response $response): array
    {
        return [
            'code' => $response->json('error.code') ?? $response->json('response_code') ?? $response->json('code'),
            'message' => Str::limit((string) json_encode($response->json('error.message') ?? $response->json('message') ?? $response->json('response_message')), 200),
        ];
    }
}
