<?php

use App\Enums\ListingStatus;
use App\Enums\ReservationStatus;
use App\Jobs\ProcessReviewRequestJob;
use App\Jobs\SendDepartureReminderJob;
use App\Mail\GuestDepartureReminderMail;
use App\Mail\GuestReviewRequestMail;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Plan;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $plan = Plan::factory()->create([
        'features' => ['automated_review_requests' => true],
    ]);

    $this->operator = Operator::factory()->create([
        'plan_id' => $plan->id,
        'settings' => [
            'marketing' => [
                'review_url' => 'https://g.page/r/test',
            ],
        ],
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);
});

test('expire stale holds command expires abandoned reservations', function () {
    $staleReservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->subMinutes(5),
    ]);

    $freshReservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(25),
    ]);

    $this->artisan('reservations:expire-holds')
        ->assertSuccessful();

    expect($staleReservation->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($freshReservation->fresh()->status)->toBe(ReservationStatus::PaymentPending);
});

test('mark completed trips command marks concluded reservations as completed', function () {
    $pastTrip = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->subDays(2)->toDateString(),
    ]);

    $futureTrip = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->addDays(2)->toDateString(),
    ]);

    $this->artisan('trips:mark-completed')
        ->assertSuccessful();

    expect($pastTrip->fresh()->status)->toBe(ReservationStatus::Completed)
        ->and($futureTrip->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('send departure reminders command dispatches queued jobs for upcoming trips', function () {
    Queue::fake();

    $upcomingTrip = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
        'guest_email' => 'guest@example.com',
        'requested_date' => now()->addDay()->toDateString(),
        'departure_reminder_sent_at' => null,
    ]);

    $farTrip = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
        'guest_email' => 'far@example.com',
        'requested_date' => now()->addDays(10)->toDateString(),
        'departure_reminder_sent_at' => null,
    ]);

    $this->artisan('trips:send-departure-reminders')
        ->assertSuccessful();

    Queue::assertPushed(SendDepartureReminderJob::class, function ($job) use ($upcomingTrip) {
        return $job->reservation->id === $upcomingTrip->id;
    });

    Queue::assertNotPushed(SendDepartureReminderJob::class, function ($job) use ($farTrip) {
        return $job->reservation->id === $farTrip->id;
    });
});

test('send departure reminder job sends email and updates timestamp', function () {
    Mail::fake();

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
        'guest_email' => 'guest@example.com',
        'departure_reminder_sent_at' => null,
    ]);

    $job = new SendDepartureReminderJob($reservation);
    $job->handle();

    Mail::assertSent(GuestDepartureReminderMail::class, function ($mail) use ($reservation) {
        return $mail->hasTo($reservation->guest_email);
    });

    expect($reservation->fresh()->departure_reminder_sent_at)->not->toBeNull();
});

test('send review requests command dispatches process review request job', function () {
    Queue::fake();

    $completedTrip = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
        'guest_email' => 'guest@example.com',
        'requested_date' => now()->subDay()->toDateString(),
        'review_request_sent_at' => null,
    ]);

    $this->artisan('trips:send-review-requests')
        ->assertSuccessful();

    Queue::assertPushed(ProcessReviewRequestJob::class, function ($job) use ($completedTrip) {
        return $job->reservation->id === $completedTrip->id;
    });
});

test('process review request job sends email and updates timestamp', function () {
    Mail::fake();

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
        'guest_email' => 'guest@example.com',
        'review_request_sent_at' => null,
    ]);

    $job = new ProcessReviewRequestJob($reservation, 'https://g.page/r/test');
    $job->handle();

    Mail::assertSent(GuestReviewRequestMail::class, function ($mail) use ($reservation) {
        return $mail->hasTo($reservation->guest_email);
    });

    expect($reservation->fresh()->review_request_sent_at)->not->toBeNull();
});
