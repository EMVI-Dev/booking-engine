<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;

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
