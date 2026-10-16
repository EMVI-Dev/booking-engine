<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Mail\GuestBookingCancelledMail;
use App\Mail\OperatorBookingCancelledMail;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ReservationLifecycleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create([
        'name' => 'Calm Sea Tours',
        'slug' => 'calm-sea',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'calm-sea.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
        'free_cancellation_hours' => 48,
    ]);

    $this->headers = ['Host' => 'calm-sea.booking.test'];
});

test('a guest can cancel an unpaid booking from the receipt', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
        'requested_date' => now()->addDays(5)->toDateString(),
        'terms_snapshot' => $this->package->generateTermsSnapshot(),
    ]);

    $this->get('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/receipt', $this->headers)
        ->assertOk()
        ->assertSee('Cancel this unpaid booking');

    $this->from(route('storefront.reservation.receipt', $reservation))
        ->post('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/cancel', [], $this->headers)
        ->assertRedirect(route('storefront.reservation.receipt', $reservation))
        ->assertSessionHas('success', 'Your booking has been cancelled.');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
});

test('a guest can cancel a paid booking while the free-cancel window is still open', function () {
    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'requested_date' => now()->addDays(10)->toDateString(),
        'terms_snapshot' => array_merge($this->package->generateTermsSnapshot(), [
            'free_cancellation_hours' => 48,
        ]),
    ]);
    Payment::factory()->paid()->create(['reservation_id' => $reservation->id]);

    $this->get('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/receipt', $this->headers)
        ->assertOk()
        ->assertSee('Cancel free of charge')
        ->assertSee('Show this code at check-in');

    $this->post('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/cancel', [], $this->headers)
        ->assertRedirect(route('storefront.reservation.receipt', $reservation));

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
});

test('a paid free-cancel sends the money back to the original payment', function () {
    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'requested_date' => now()->addDays(10)->toDateString(),
        'terms_snapshot' => array_merge($this->package->generateTermsSnapshot(), [
            'free_cancellation_hours' => 48,
        ]),
    ]);

    $payment = Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 1500000.00,
        'gateway_ref' => 'INV-CANCEL-1',
    ]);

    $this->post('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/cancel', [], $this->headers)
        ->assertRedirect(route('storefront.reservation.receipt', $reservation));

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->fresh()->isRefunded())->toBeTrue();

    $this->get('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/receipt', $this->headers)
        ->assertOk()
        ->assertSee('Booking Refunded')
        ->assertSeeInOrder(['<dt>Refunded</dt>', 'Rp'], false)
        ->assertDontSee('<dt>Due</dt>', false)
        ->assertDontSee('at check-in');
});

test('an unpaid or expired booking does not ask the guest to show it at check-in', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Expired,
        'hold_expires_at' => now()->subHour(),
        'requested_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->get('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/receipt', $this->headers)
        ->assertOk()
        ->assertSee('Hold Expired')
        ->assertDontSee('<dt>Due</dt>', false)
        ->assertDontSee('at check-in');
});

test('a guest cannot cancel after the free-cancel cutoff', function () {
    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'requested_date' => now()->addDay()->toDateString(),
        'terms_snapshot' => array_merge($this->package->generateTermsSnapshot(), [
            'free_cancellation_hours' => 48,
        ]),
    ]);

    $this->get('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/receipt', $this->headers)
        ->assertOk()
        ->assertDontSee('Cancel free of charge');

    $this->from(route('storefront.reservation.receipt', $reservation))
        ->post('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/cancel', [], $this->headers)
        ->assertRedirect(route('storefront.reservation.receipt', $reservation))
        ->assertSessionHasErrors('reservation');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('a booking cannot be cancelled from another operator storefront', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $rival = Operator::factory()->create([
        'slug' => 'rival-boats',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $rival->id,
        'domain' => 'rival-boats.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->post('http://rival-boats.booking.test/reservations/'.$reservation->public_token.'/cancel', [], [
        'Host' => 'rival-boats.booking.test',
    ])->assertNotFound();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::PaymentPending);
});

test('a guest who cancels a paid booking gets a cancellation email and the operator is told', function () {
    Mail::fake();
    $this->operator->update(['booking_notification_email' => 'desk@calm-sea.test']);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_email' => 'guest@example.com',
        'requested_date' => now()->addDays(10)->toDateString(),
        'terms_snapshot' => array_merge($this->package->generateTermsSnapshot(), ['free_cancellation_hours' => 48]),
    ]);
    Payment::factory()->paid()->create(['reservation_id' => $reservation->id, 'amount' => 945000]);

    $this->post('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/cancel', [], $this->headers);

    Mail::assertQueued(GuestBookingCancelledMail::class, fn (GuestBookingCancelledMail $mail) => $mail->hasTo('guest@example.com')
        && $mail->cancelledByGuest
        && $mail->refundAmount === 945000.0);
    Mail::assertQueued(OperatorBookingCancelledMail::class, fn (OperatorBookingCancelledMail $mail) => $mail->hasTo('desk@calm-sea.test'));
});

test('a guest dropping an unpaid hold gets no cancellation emails', function () {
    Mail::fake();

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_email' => 'guest@example.com',
        'status' => ReservationStatus::PaymentPending,
        'requested_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->post('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/cancel', [], $this->headers);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
    Mail::assertNotQueued(GuestBookingCancelledMail::class);
    Mail::assertNotQueued(OperatorBookingCancelledMail::class);
});

test('when the operator cancels a paid booking the guest is emailed, not the operator', function () {
    Mail::fake();

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_email' => 'guest@example.com',
        'requested_date' => now()->addDays(10)->toDateString(),
    ]);
    Payment::factory()->paid()->create(['reservation_id' => $reservation->id, 'amount' => 500000]);

    app(ReservationLifecycleService::class)->transitionByOperator($reservation, ReservationStatus::Cancelled);

    Mail::assertQueued(GuestBookingCancelledMail::class, fn (GuestBookingCancelledMail $mail) => $mail->hasTo('guest@example.com')
        && ! $mail->cancelledByGuest
        && $mail->refundAmount === 500000.0);
    Mail::assertNotQueued(OperatorBookingCancelledMail::class);
});

test('cancellation emails explain the refund in plain words', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_name' => 'Rina Guest',
        'code' => 'RSV-CANCELME',
        'status' => ReservationStatus::Cancelled,
    ]);

    (new GuestBookingCancelledMail($reservation, cancelledByGuest: true, refundAmount: 945000))
        ->assertSeeInHtml('Your booking is cancelled')
        ->assertSeeInHtml('#RSV-CANCELME')
        ->assertSeeInHtml('Rp 945.000')
        ->assertSeeInHtml('original payment method');

    (new GuestBookingCancelledMail($reservation, cancelledByGuest: false, refundAmount: 0))
        ->assertSeeInHtml('Calm Sea Tours cancelled this booking')
        ->assertDontSeeInHtml('Rp 0');

    (new OperatorBookingCancelledMail($reservation, refundAmount: 945000))
        ->assertSeeInHtml('Rina Guest cancelled')
        ->assertSeeInHtml('#RSV-CANCELME')
        ->assertSeeInHtml('Rp 945.000');
});

test('after a failed payment the receipt tells the guest and keeps the pay button', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(20),
        'requested_date' => now()->addDays(5)->toDateString(),
    ]);
    Payment::factory()->create(['reservation_id' => $reservation->id, 'status' => PaymentStatus::Failed]);

    $this->get('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/receipt', $this->headers)
        ->assertOk()
        ->assertSee('Your last payment did not go through. No money was taken. You can try again below.')
        ->assertSee('Pay Now');
});

test('a booking waiting for its first payment does not mention a failure', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(20),
        'requested_date' => now()->addDays(5)->toDateString(),
    ]);
    Payment::factory()->create(['reservation_id' => $reservation->id, 'status' => PaymentStatus::Pending]);

    $this->get('http://calm-sea.booking.test/reservations/'.$reservation->public_token.'/receipt', $this->headers)
        ->assertOk()
        ->assertDontSee('did not go through');
});
