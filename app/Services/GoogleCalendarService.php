<?php

namespace App\Services;

use App\Contracts\Bookable;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Reservation;

class GoogleCalendarService
{
    /**
     * Build a 1-click direct Google Calendar event creation URL.
     */
    public function buildGoogleCalendarUrl(Reservation $reservation): string
    {
        $operatorName = $reservation->operator->name ?? config('app.name', 'Tour Operator');
        $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
        $dateStr = $reservation->requested_date->format('Ymd');
        $endDateStr = $reservation->requested_date->copy()->addDay()->format('Ymd');

        $bookableTitle = $reservation->bookable instanceof Bookable
            ? $reservation->bookable->getTitle()
            : 'Tour Experience';

        $title = "{$operatorName} - {$bookableTitle} (#{$code} • {$reservation->guest_name})";

        $receiptUrl = route('storefront.reservation.receipt', $reservation);

        $details = "Booking Code: #{$code}\n"
            ."Guests: {$reservation->pax_count} Pax\n"
            ."Lead Guest: {$reservation->guest_name}\n"
            ."Contact: {$reservation->guest_contact}\n"
            .'Email: '.($reservation->guest_email ?? 'N/A')."\n"
            ."Experience: {$bookableTitle}\n"
            ."Status: {$reservation->status->label()}\n"
            ."Voucher Link: {$receiptUrl}";

        $params = [
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => "{$dateStr}/{$endDateStr}",
            'details' => $details,
            'location' => $operatorName,
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }

    /**
     * Build a 1-click Google Calendar subscription web URL for an iCal feed.
     */
    public function buildGoogleCalendarSubscriptionUrl(string $feedUrl): string
    {
        $webcalUrl = preg_replace('/^https?:\/\//i', 'webcal://', $feedUrl);

        return 'https://calendar.google.com/calendar/r?cid='.urlencode($webcalUrl);
    }

    /**
     * Generate an RFC 5545 standard iCal calendar feed string for an operator.
     */
    public function generateIcalFeed(Operator $operator): string
    {
        $platformDomain = app(DomainResolverService::class)->getPlatformDomain();
        $calName = ($operator->name ?? 'Tour Operator').' Bookings';

        $reservations = $operator->reservations()
            ->whereIn('status', [
                ReservationStatus::Confirmed,
                ReservationStatus::PendingConfirmation,
                ReservationStatus::Completed,
            ])
            ->with('bookable')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//TravelEngine Booking Platform//Tour Operator iCal Feed//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            $this->foldIcalLine("X-WR-CALNAME:{$this->escapeIcalText($calName)}"),
            'X-WR-TIMEZONE:UTC',
        ];

        foreach ($reservations as $res) {
            $code = $res->code ?: strtoupper(substr($res->id, -8));
            $startDate = $res->requested_date->format('Ymd');
            $endDate = $res->requested_date->copy()->addDay()->format('Ymd');
            $dtstamp = $res->updated_at?->format('Ymd\THis\Z') ?? now()->format('Ymd\THis\Z');

            $bookableTitle = $res->bookable instanceof Bookable
                ? $res->bookable->getTitle()
                : 'Tour Experience';

            $summary = $this->escapeIcalText("{$res->pax_count}x {$bookableTitle} - {$res->guest_name} (#{$code})");
            $voucherUrl = route('storefront.reservation.receipt', $res);

            $description = $this->escapeIcalText(
                "Booking Code: #{$code}\n"
                ."Guest: {$res->guest_name}\n"
                ."Contact: {$res->guest_contact}\n"
                ."Pax: {$res->pax_count}\n"
                ."Status: {$res->status->label()}\n"
                ."Voucher: {$voucherUrl}"
            );

            $location = $this->escapeIcalText(
                $res->pickup_location ?: ($operator->name ?? 'Tour Operator')
            );

            $eventStatus = $res->status->isPendingConfirmation() ? 'TENTATIVE' : 'CONFIRMED';

            $eventProps = [
                'BEGIN:VEVENT',
                $this->foldIcalLine("UID:reservation-{$res->id}@{$platformDomain}"),
                "DTSTAMP:{$dtstamp}",
                "DTSTART;VALUE=DATE:{$startDate}",
                "DTEND;VALUE=DATE:{$endDate}",
                $this->foldIcalLine("SUMMARY:{$summary}"),
                $this->foldIcalLine("DESCRIPTION:{$description}"),
                $this->foldIcalLine("LOCATION:{$location}"),
                $this->foldIcalLine("URL:{$voucherUrl}"),
                "STATUS:{$eventStatus}",
                'END:VEVENT',
            ];

            foreach ($eventProps as $prop) {
                $lines[] = $prop;
            }
        }

        $lines[] = 'END:VCALENDAR';
        $lines[] = '';

        return implode("\r\n", $lines);
    }

    /**
     * Fold lines to maximum 75 octets as specified in RFC 5545 Section 3.1.
     */
    protected function foldIcalLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $result = '';
        while (strlen($line) > 75) {
            $result .= substr($line, 0, 75)."\r\n ";
            $line = substr($line, 75);
        }

        return $result.$line;
    }

    /**
     * Escape special characters for RFC 5545 format.
     */
    protected function escapeIcalText(string $text): string
    {
        return str_replace(
            ['\\', "\n", "\r", ',', ';'],
            ['\\\\', '\\n', '', '\\,', '\\;'],
            $text
        );
    }
}
