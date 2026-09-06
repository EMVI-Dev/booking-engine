<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create([
        'name' => 'Bali Sea Adventures',
        'slug' => 'bali-sea',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'bali-sea.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);

    $this->reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_name' => 'Sarah Connor',
        'guest_email' => 'sarah@example.com',
        'guest_contact' => '081234567890',
        'status' => ReservationStatus::Confirmed,
    ]);

    $this->headers = ['Host' => 'bali-sea.booking.test'];
});

test('guests can open the find booking page from the operator storefront', function () {
    $this->get('http://bali-sea.booking.test/find-booking', $this->headers)
        ->assertOk()
        ->assertSee('Find your booking')
        ->assertSee('Booking code')
        ->assertSee('prefers-color-scheme: dark', false)
        ->assertSee("document.documentElement.classList.add('dark')", false);
});

test('a matching code and email opens the reservation e-ticket', function () {
    $this->post('http://bali-sea.booking.test/find-booking', [
        'code' => $this->reservation->code,
        'contact' => 'sarah@example.com',
    ], $this->headers)
        ->assertRedirect(route('storefront.reservation.receipt', $this->reservation));
});

test('a matching code and phone opens the reservation e-ticket', function () {
    $this->post('http://bali-sea.booking.test/find-booking', [
        'code' => $this->reservation->code,
        'contact' => '081234567890',
    ], $this->headers)
        ->assertRedirect(route('storefront.reservation.receipt', $this->reservation));
});

test('a matching code and guest name opens the reservation e-ticket', function () {
    $this->post('http://bali-sea.booking.test/find-booking', [
        'code' => $this->reservation->code,
        'contact' => 'Sarah Connor',
    ], $this->headers)
        ->assertRedirect(route('storefront.reservation.receipt', $this->reservation));
});

test('wrong contact details do not reveal that the booking exists', function () {
    $this->from('http://bali-sea.booking.test/find-booking')
        ->post('http://bali-sea.booking.test/find-booking', [
            'code' => $this->reservation->code,
            'contact' => 'not-the-guest@example.com',
        ], $this->headers)
        ->assertRedirect()
        ->assertSessionHasErrors('code');
});

test('a booking cannot be looked up from another operator storefront', function () {
    $rival = Operator::factory()->create([
        'slug' => 'rival-tours',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $rival->id,
        'domain' => 'rival-tours.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->from('http://rival-tours.booking.test/find-booking')
        ->post('http://rival-tours.booking.test/find-booking', [
            'code' => $this->reservation->code,
            'contact' => 'sarah@example.com',
        ], ['Host' => 'rival-tours.booking.test'])
        ->assertRedirect()
        ->assertSessionHasErrors('code');
});
