<?php

use App\Mail\GuestBookingConfirmedMail;
use App\Mail\GuestBookingCreatedMail;
use App\Mail\GuestDepartureReminderMail;
use App\Mail\GuestReviewRequestMail;
use App\Mail\OperatorNewBookingNotificationMail;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Plan;
use App\Models\Reservation;

beforeEach(function () {
    Plan::seedDefaultPlans();

    $this->operator = Operator::factory()->create([
        'name' => 'Whitebox Reef Tours',
        'logo_path' => 'https://cdn.example/whitebox-logo.png',
        'settings' => [
            'sellable_standalone_default' => true,
            'brand_color' => '#FFEF4D',
            'display_name' => 'Whitebox Reef Tours',
        ],
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
    ]);

    $this->reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_name' => 'Maya Guest',
        'guest_email' => 'maya@example.com',
    ]);
});

test('review request button does not name a review platform', function () {
    $mail = new GuestReviewRequestMail($this->reservation, 'https://reviews.example/any-link');

    $mail->assertSeeInHtml('Leave a review')
        ->assertDontSeeInHtml('Google')
        ->assertDontSeeInHtml('TripAdvisor');
});

test('guest mail includes the operator logo and brand colour', function (string $mailable) {
    $mail = $mailable === GuestReviewRequestMail::class
        ? new GuestReviewRequestMail($this->reservation, 'https://reviews.example/any-link')
        : new $mailable($this->reservation);

    $mail->assertSeeInHtml('https://cdn.example/whitebox-logo.png')
        ->assertSeeInHtml('#FFEF4D');
})->with([
    GuestBookingCreatedMail::class,
    GuestBookingConfirmedMail::class,
    GuestDepartureReminderMail::class,
    GuestReviewRequestMail::class,
]);

test('platform mail to the operator uses platform colour and logo', function () {
    $mail = new OperatorNewBookingNotificationMail($this->reservation);

    $mail->assertSeeInHtml('#FFEF4D')
        ->assertSeeInHtml('favicon.png')
        ->assertDontSeeInHtml('#1e1b4b')
        ->assertDontSeeInHtml('#4f46e5')
        ->assertDontSeeInHtml('#7e22ce');
});

test('subscription reminder uses platform colour and logo', function () {
    $mail = new SubscriptionRenewalReminderMail(
        $this->operator,
        Plan::where('slug', 'starter')->firstOrFail(),
        3,
    );

    $mail->assertSeeInHtml('#FFEF4D')
        ->assertSeeInHtml('favicon.png')
        ->assertDontSeeInHtml('#1e1b4b')
        ->assertDontSeeInHtml('#7e22ce');
});
