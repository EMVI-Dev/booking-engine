<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CalendarFeedController extends Controller
{
    /**
     * Serve a live RFC 5545 iCal calendar subscription feed for an operator.
     */
    public function feed(string $token, GoogleCalendarService $calendarService): Response
    {
        $cleanToken = Str::chopEnd($token, '.ics');

        if (preg_match('/^[a-f0-9]{32}$/', $cleanToken) !== 1) {
            abort(404, 'Calendar feed not found or invalid subscription token.');
        }

        /** @var Operator|null $operator */
        $operator = Operator::query()
            ->where('settings->calendar_feed_token', $cleanToken)
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
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
