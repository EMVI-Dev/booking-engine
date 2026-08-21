<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    /**
     * Serve a live RFC 5545 iCal calendar subscription feed for an operator.
     */
    public function feed(string $token, GoogleCalendarService $calendarService): Response
    {
        // Strip trailing .ics if passed
        $cleanToken = str_replace('.ics', '', $token);

        /** @var Operator|null $operator */
        $operator = Operator::query()
            ->whereJsonContains('settings->calendar_feed_token', $cleanToken)
            ->first();

        if (! $operator) {
            abort(404, 'Calendar feed not found or invalid subscription token.');
        }

        $icalContent = $calendarService->generateIcalFeed($operator);

        return response($icalContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="bookings.ics"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
