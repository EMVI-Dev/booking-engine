<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Reservation;
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

        // Calculate automated platform commission split (e.g. 10% take-rate)
        $commissionRate = $platform->getCommissionRate();
        $platformCommission = round($totalAmount * $commissionRate, 2);
        $agentShare = round($totalAmount - $platformCommission, 2);

        $invoiceNumber = 'INV-'.strtoupper(Str::random(6)).'-'.time();

        /** @var Payment $payment */
        $payment = Payment::query()->create([
            'reservation_id' => $reservation->id,
            'amount' => $totalAmount,
            'gateway' => 'doku',
            'gateway_ref' => $invoiceNumber,
            'split_details' => [
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
        $mode = config('doku.default_mode', 'sandbox');
        $baseUrl = (string) config("doku.{$mode}.checkout_url", 'https://jokul-sandbox.doku.com/checkout');

        // Return payment confirmation route with reference
        return route('storefront.payment.simulate', [
            'reservation' => $reservation->id,
            'payment' => $payment->id,
            'ref' => $invoiceNumber,
        ]);
    }

    /**
     * Process an incoming notification / webhook callback from DOKU.
     *
     * @param  array<string, mixed>  $payload
     */
    public function processNotification(array $payload): bool
    {
        $invoiceNumber = (string) ($payload['order']['invoice_number'] ?? ($payload['invoice_number'] ?? ''));
        $transactionStatus = (string) ($payload['transaction']['status'] ?? ($payload['status'] ?? 'SUCCESS'));

        if ($invoiceNumber === '') {
            return false;
        }

        $payment = Payment::query()
            ->where('gateway_ref', $invoiceNumber)
            ->first();

        if (! $payment) {
            return false;
        }

        if (strtoupper($transactionStatus) === 'SUCCESS' || strtoupper($transactionStatus) === 'PAID') {
            $payment->update([
                'status' => PaymentStatus::Paid,
            ]);

            $agent = $payment->reservation?->agent;
            $isManual = $agent?->isManualConfirmationEnabled() ?? false;

            $payment->reservation?->update([
                'status' => $isManual ? ReservationStatus::PendingConfirmation : ReservationStatus::Confirmed,
                'hold_expires_at' => null,
            ]);

            return true;
        }

        if (strtoupper($transactionStatus) === 'FAILED' || strtoupper($transactionStatus) === 'EXPIRED') {
            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);

            $payment->reservation?->update([
                'status' => ReservationStatus::Expired,
            ]);

            return true;
        }

        return true;
    }
}
