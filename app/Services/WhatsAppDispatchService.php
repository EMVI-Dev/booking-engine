<?php

namespace App\Services;

use App\Contracts\Bookable;
use App\Models\Reservation;

class WhatsAppDispatchService
{
    /**
     * Normalize a phone number to standard international WhatsApp format without leading + or 0.
     * Converts Indonesian 08xxx to 628xxx.
     */
    public function normalizePhoneNumber(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        // Remove all non-numeric characters
        $cleaned = (string) preg_replace('/[^0-9]/', '', $phone);

        // Convert Indonesian leading 0 to 62
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62'.substr($cleaned, 1);
        }

        return $cleaned;
    }

    /**
     * Build WhatsApp Web / App direct click URL with pre-filled message.
     */
    public function buildWhatsAppUrl(string $phone, string $message): string
    {
        $cleanPhone = $this->normalizePhoneNumber($phone);

        return 'https://wa.me/'.$cleanPhone.'?text='.urlencode($message);
    }

    /**
     * Generate 1-Click Voucher & Booking Confirmation WhatsApp message URL.
     */
    public function getConfirmationUrl(Reservation $reservation): string
    {
        $agentName = $reservation->agent->name ?? config('app.name', 'Tour Operator');
        $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
        $dateFormatted = $reservation->requested_date->format('l, d F Y');
        $pax = $reservation->pax_count;

        $bookableTitle = $reservation->bookable instanceof Bookable
            ? $reservation->bookable->getTitle()
            : 'Tour Experience';

        $receiptUrl = route('storefront.reservation.receipt', $reservation);

        $latestPayment = $reservation->latestPayment;
        $isPaid = $latestPayment && $latestPayment->isPaid();
        $paymentText = $isPaid
            ? 'Paid & Confirmed (Rp '.number_format((float) $latestPayment->amount, 0, ',', '.').')'
            : 'Pending Payment Confirmation';

        $msg = "Hello {$reservation->guest_name}!\n\n"
            ."Your booking with *{$agentName}* is confirmed.\n\n"
            ."*Booking Code:* #{$code}\n"
            ."*Departure Date:* {$dateFormatted}\n"
            ."*Guests:* {$pax} Pax\n"
            ."*Experience:* {$bookableTitle}\n"
            ."*Payment:* {$paymentText}\n\n"
            ."*View your digital e-ticket & voucher here:*\n"
            ."{$receiptUrl}\n\n"
            .'If you have any questions or special requests, please reply directly to this message. See you soon!';

        return $this->buildWhatsAppUrl($reservation->guest_contact, $msg);
    }

    /**
     * Generate 24h Departure & Packing Reminder WhatsApp message URL.
     */
    public function getReminderUrl(Reservation $reservation): string
    {
        $agentName = $reservation->agent->name ?? config('app.name', 'Tour Operator');
        $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
        $dateFormatted = $reservation->requested_date->format('l, d F Y');
        $pax = $reservation->pax_count;

        $bookableTitle = $reservation->bookable instanceof Bookable
            ? $reservation->bookable->getTitle()
            : 'Tour Experience';

        $receiptUrl = route('storefront.reservation.receipt', $reservation);

        $msg = "*Trip Reminder: Your expedition with {$agentName} is tomorrow!*\n\n"
            ."*Booking Code:* #{$code}\n"
            ."*Date:* {$dateFormatted}\n"
            ."*Guests:* {$pax} Pax\n"
            ."*Tour:* {$bookableTitle}\n\n"
            ."*Departure Tips:*\n"
            ."• Please arrive 15 minutes before departure at the harbor / meeting point.\n"
            ."• Don't forget sunscreen, sunglasses, swimwear, and comfortable sandals.\n"
            ."• Show your booking code (#{$code}) to our crew on site.\n\n"
            ."*Your Voucher & Details:*\n"
            ."{$receiptUrl}\n\n"
            .'Wishing you an unforgettable adventure with us tomorrow!';

        return $this->buildWhatsAppUrl($reservation->guest_contact, $msg);
    }

    /**
     * Generate Meeting Point & Harbor Instructions WhatsApp message URL.
     */
    public function getMeetingPointUrl(Reservation $reservation): string
    {
        $agentName = $reservation->agent->name ?? config('app.name', 'Tour Operator');
        $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
        $dateFormatted = $reservation->requested_date->format('l, d F Y');

        $receiptUrl = route('storefront.reservation.receipt', $reservation);

        $msg = "*Meeting Point & Check-in Instructions for {$agentName}*\n\n"
            ."Hello {$reservation->guest_name},\n"
            ."Here are the check-in details for your booking (#{$code}) on {$dateFormatted}:\n\n"
            ."• *Check-in Desk:* Please look for the {$agentName} booth/crew wearing official uniform.\n"
            ."• *What to bring:* ID / Passport copy and your digital voucher.\n"
            ."• *Digital Voucher Link:* {$receiptUrl}\n\n"
            .'Let us know if you need assistance with transportation or directions.';

        return $this->buildWhatsAppUrl($reservation->guest_contact, $msg);
    }

    /**
     * Generate Payment Hold / Direct Checkout WhatsApp reminder URL.
     */
    public function getPaymentHoldLinkUrl(Reservation $reservation): string
    {
        $agentName = $reservation->agent->name ?? config('app.name', 'Tour Operator');
        $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
        $dateFormatted = $reservation->requested_date->format('l, d F Y');
        $pax = $reservation->pax_count;

        $bookableTitle = $reservation->bookable instanceof Bookable
            ? $reservation->bookable->getTitle()
            : 'Tour Experience';

        $payUrl = route('storefront.reservation.pay', $reservation);

        $latestPayment = $reservation->latestPayment;
        $amountFormatted = $latestPayment
            ? 'Rp '.number_format((float) $latestPayment->amount, 0, ',', '.')
            : 'Rp '.number_format($pax * ($reservation->bookable->price ?? 0), 0, ',', '.');

        $msg = "*Payment Required: Reservation #{$code} on Hold*\n\n"
            ."Hello {$reservation->guest_name},\n\n"
            ."Your reservation with *{$agentName}* is currently on hold. Please complete your payment to secure and confirm your trip spot.\n\n"
            ."*Booking Code:* #{$code}\n"
            ."*Trip Date:* {$dateFormatted}\n"
            ."*Guests:* {$pax} Pax\n"
            ."*Experience:* {$bookableTitle}\n"
            ."*Amount Due:* {$amountFormatted}\n\n"
            ."*Click here to complete payment online:*\n"
            ."{$payUrl}\n\n"
            .'_Please complete payment before your hold window expires. Reply directly if you have any questions!_';

        return $this->buildWhatsAppUrl($reservation->guest_contact, $msg);
    }

    /**
     * Generate Direct Chat WhatsApp message URL.
     */
    public function getDirectChatUrl(Reservation $reservation): string
    {
        $agentName = $reservation->agent->name ?? config('app.name', 'Tour Operator');
        $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));

        $msg = "Hello {$reservation->guest_name}, reaching out from {$agentName} regarding your reservation #{$code}.";

        return $this->buildWhatsAppUrl($reservation->guest_contact, $msg);
    }
}
