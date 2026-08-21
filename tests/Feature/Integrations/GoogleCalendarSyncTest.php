<?php

use App\Enums\OperatorStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Reservation;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create([
        'name' => 'Labuan Bajo Yacht Charters',
        'slug' => 'bajo-yacht',
        'status' => OperatorStatus::Approved,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Sunset Yacht Party',
        'price' => 1500000.00,
    ]);

    $this->reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'guest_name' => 'Dwight Schrute',
        'guest_contact' => '081233445566',
        'requested_date' => '2026-09-15',
        'pax_count' => 2,
        'status' => ReservationStatus::Confirmed,
    ]);
});

test('generates valid 1-click google calendar web event url', function () {
    $service = app(GoogleCalendarService::class);

    $url = $service->buildGoogleCalendarUrl($this->reservation);

    expect($url)->toStartWith('https://calendar.google.com/calendar/render?')
        ->and(urldecode($url))->toContain('action=TEMPLATE')
        ->and(urldecode($url))->toContain('Sunset Yacht Party')
        ->and(urldecode($url))->toContain('Dwight Schrute')
        ->and(urldecode($url))->toContain('dates=20260915/20260916')
        ->and(urldecode($url))->toContain($this->reservation->code);
});

test('live ical calendar feed endpoint returns valid RFC 5545 calendar stream', function () {
    $token = $this->operator->getCalendarFeedToken();
    expect($token)->not->toBeEmpty();

    $response = $this->get(route('calendar.feed', ['token' => $token]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $content = $response->getContent();
    expect($content)->toContain('BEGIN:VCALENDAR')
        ->and($content)->toContain('PRODID:-//TravelEngine Booking Platform//Tour Operator iCal Feed//EN')
        ->and($content)->toContain('X-WR-CALNAME:Labuan Bajo Yacht Charters Bookings')
        ->and($content)->toContain('BEGIN:VEVENT')
        ->and($content)->toContain('DTSTART;VALUE=DATE:20260915')
        ->and($content)->toContain('DTEND;VALUE=DATE:20260916')
        ->and($content)->toContain('Sunset Yacht Party')
        ->and($content)->toContain('Dwight Schrute')
        ->and($content)->toContain('END:VEVENT')
        ->and($content)->toContain('END:VCALENDAR');
});

test('invalid calendar token returns 404', function () {
    $this->get(route('calendar.feed', ['token' => 'invalid-token-12345']))
        ->assertNotFound();
});
