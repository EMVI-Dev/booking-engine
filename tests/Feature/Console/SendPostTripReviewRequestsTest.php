<?php

use App\Enums\ReservationStatus;
use App\Mail\GuestReviewRequestMail;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Plan;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('sends automated review request emails to guests 12 hours after trip departure for eligible plan operators', function () {
    Mail::fake();
    Plan::seedDefaultPlans();

    $growthPlan = Plan::where('slug', 'growth')->first();

    $operator = Operator::factory()->create([
        'plan_id' => $growthPlan->id,
        'settings' => [
            'marketing' => [
                'review_url' => 'https://g.page/r/my-tour-review',
            ],
        ],
    ]);

    $package = Package::factory()->create(['operator_id' => $operator->id]);

    // Eligible reservation: confirmed, completed > 12 hours ago, guest email present, review request not yet sent
    $eligible = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->subDays(1)->toDateString(),
        'guest_email' => 'traveler@example.com',
        'review_request_sent_at' => null,
    ]);

    // Future reservation: not eligible yet
    $future = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->addDays(2)->toDateString(),
        'guest_email' => 'future@example.com',
        'review_request_sent_at' => null,
    ]);

    // Already sent reservation
    $alreadySent = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->subDays(2)->toDateString(),
        'guest_email' => 'past@example.com',
        'review_request_sent_at' => now()->subDay(),
    ]);

    $this->artisan('trips:send-review-requests')
        ->assertSuccessful();

    Mail::assertQueued(GuestReviewRequestMail::class, function ($mail) {
        return $mail->hasTo('traveler@example.com') && $mail->reviewUrl === 'https://g.page/r/my-tour-review';
    });

    Mail::assertNotQueued(GuestReviewRequestMail::class, function ($mail) {
        return $mail->hasTo('future@example.com') || $mail->hasTo('past@example.com');
    });

    expect($eligible->fresh()->review_request_sent_at)->not->toBeNull();
});

it('skips sending automated review requests for operators on starter plan without the feature', function () {
    Mail::fake();
    Plan::seedDefaultPlans();

    $starterPlan = Plan::where('slug', 'starter')->first();

    $operator = Operator::factory()->create([
        'plan_id' => $starterPlan->id,
        'settings' => [
            'marketing' => [
                'review_url' => 'https://g.page/r/starter-review',
            ],
        ],
    ]);

    $package = Package::factory()->create(['operator_id' => $operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->subDays(1)->toDateString(),
        'guest_email' => 'traveler@example.com',
        'review_request_sent_at' => null,
    ]);

    $this->artisan('trips:send-review-requests')
        ->assertSuccessful();

    Mail::assertNothingSent();
    expect($reservation->fresh()->review_request_sent_at)->toBeNull();
});

it('sends agency review mail to the connected listing even when a manual review link is set', function () {
    Mail::fake();
    Plan::seedDefaultPlans();

    $operator = Operator::factory()->create([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
        'settings' => [
            'marketing' => [
                'review_url' => 'https://g.page/r/manual-link',
            ],
            'google_place' => [
                'place_id' => 'ChIJsunrise1234567890',
                'name' => 'Sunrise Reef Tours',
                'write_review_url' => 'https://search.google.com/local/writereview?placeid=ChIJsunrise1234567890',
                'fetched_at' => now()->toIso8601String(),
                'reviews' => [],
            ],
        ],
    ]);

    $package = Package::factory()->create(['operator_id' => $operator->id]);

    Reservation::factory()->create([
        'operator_id' => $operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->subDays(1)->toDateString(),
        'guest_email' => 'agency-guest@example.com',
        'review_request_sent_at' => null,
    ]);

    $this->artisan('trips:send-review-requests')->assertSuccessful();

    Mail::assertQueued(GuestReviewRequestMail::class, function ($mail) {
        return $mail->hasTo('agency-guest@example.com')
            && $mail->reviewUrl === 'https://search.google.com/local/writereview?placeid=ChIJsunrise1234567890'
            && str_contains($mail->render(), 'Please leave a review for Sunrise Reef Tours.');
    });
});

it('does not send a review request when the operator has no review page URL', function () {
    Mail::fake();
    Plan::seedDefaultPlans();

    $operator = Operator::factory()->create([
        'plan_id' => Plan::where('slug', 'growth')->value('id'),
        'settings' => [
            'marketing' => [
                'review_url' => '',
            ],
        ],
    ]);

    $package = Package::factory()->create(['operator_id' => $operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->subDays(1)->toDateString(),
        'guest_email' => 'traveler@example.com',
        'review_request_sent_at' => null,
    ]);

    $this->artisan('trips:send-review-requests')
        ->assertSuccessful();

    Mail::assertNothingSent();
    expect($reservation->fresh()->review_request_sent_at)->toBeNull();
});
