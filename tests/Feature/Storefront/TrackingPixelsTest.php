<?php

use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders tracking scripts and pixel tags on storefront pages when configured', function () {
    $operator = Operator::factory()->create([
        'slug' => 'test-agency',
        'status' => OperatorStatus::Approved,
        'settings' => [
            'tracking' => [
                'google_analytics_id' => 'G-ABC123XYZ',
                'meta_pixel_id' => '9876543210',
                'google_tag_manager_id' => 'GTM-TEST01',
            ],
            'marketing' => [
                'review_url' => 'https://g.page/r/test-review',
            ],
        ],
    ]);

    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'status' => 'published',
        'slug' => 'padar-island-trekking',
    ]);

    $response = $this->get("http://{$operator->slug}.booking.test/");
    $response->assertOk();
    $response->assertSee('G-ABC123XYZ');
    $response->assertSee('9876543210');
    $response->assertSee('GTM-TEST01');
    $response->assertSee("fbq('track', 'PageView')", false);
});

it('renders purchase conversion tracking on booking confirmation receipt', function () {
    $operator = Operator::factory()->create([
        'slug' => 'test-agency-2',
        'status' => OperatorStatus::Approved,
        'settings' => [
            'tracking' => [
                'google_analytics_id' => 'G-ABC123XYZ',
                'meta_pixel_id' => '9876543210',
            ],
        ],
    ]);

    $package = Package::factory()->create(['operator_id' => $operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::Confirmed,
        'code' => 'RSV-PURCHASE01',
    ]);

    Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'amount' => 1500000,
        'status' => PaymentStatus::Paid,
    ]);

    $response = $this->get("http://{$operator->slug}.booking.test/reservations/{$reservation->id}/receipt");
    $response->assertOk();
    $response->assertSee("fbq('track', 'Purchase'", false);
    $response->assertSee('1500000', false);
    $response->assertSee("gtag('event', 'purchase'", false);
});
