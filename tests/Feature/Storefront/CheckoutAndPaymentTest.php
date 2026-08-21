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
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create([
        'name' => 'Bali Sea Adventures',
        'slug' => 'bali-sea',
        'status' => OperatorStatus::Approved,
        'terms_and_conditions' => 'Standard tour terms apply.',
        'contact_whatsapp' => '081234567890',
    ]);

    $this->domain = OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'bali-sea.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Nusa Penida Snorkeling Safari',
        'price' => 750000.00,
        'advance_booking_hours' => 24,
        'status' => ListingStatus::Published,
    ]);
});

test('storefront package page renders booking box', function () {
    $this->get("http://bali-sea.booking.test/packages/{$this->package->slug}", ['Host' => 'bali-sea.booking.test'])
        ->assertOk()
        ->assertSee('Nusa Penida Snorkeling Safari')
        ->assertSee('Price per person')
        ->assertSee('Rp 750.000');
});

test('guest cannot submit booking without accepting terms', function () {
    Livewire::test('storefront.booking-box', [
        'bookable' => $this->package,
        'operator' => $this->operator,
    ])
        ->set('requested_date', now()->addDays(3)->format('Y-m-d'))
        ->set('pax_count', 2)
        ->set('guest_name', 'Sarah Connor')
        ->set('guest_contact', '081234567890')
        ->set('agreed_terms', false)
        ->call('submitBooking')
        ->assertHasErrors(['agreed_terms']);

    expect(Reservation::count())->toBe(0);
});

test('guest submitting valid booking creates reservation, payment session, and redirects to checkout', function () {
    Livewire::test('storefront.booking-box', [
        'bookable' => $this->package,
        'operator' => $this->operator,
    ])
        ->set('requested_date', now()->addDays(3)->format('Y-m-d'))
        ->set('pax_count', 2)
        ->set('guest_name', 'Sarah Connor')
        ->set('guest_contact', '081234567890')
        ->set('guest_email', 'sarah@example.com')
        ->set('notes', 'Need 2 life jackets size M')
        ->set('agreed_terms', true)
        ->call('submitBooking')
        ->assertHasNoErrors()
        ->assertRedirect();

    $reservation = Reservation::first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->guest_name)->toBe('Sarah Connor')
        ->and($reservation->pax_count)->toBe(2)
        ->and($reservation->status)->toBe(ReservationStatus::PaymentPending)
        ->and($reservation->hold_expires_at)->not->toBeNull();

    $payment = Payment::first();

    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(1575000.00) // 1.500.000 + 5% (75.000) guest service fee
        ->and($payment->status)->toBe(PaymentStatus::Pending);
});

test('doku webhook notification marks payment as paid and reservation as confirmed', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $payment = Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'gateway_ref' => 'INV-TEST-998877',
        'status' => PaymentStatus::Pending,
    ]);

    $response = $this->postJson(route('doku.webhook'), [
        'order' => [
            'invoice_number' => 'INV-TEST-998877',
        ],
        'transaction' => [
            'status' => 'SUCCESS',
        ],
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'success']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('guest can view confirmation receipt page', function () {
    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_name' => 'Alex Turner',
    ]);

    Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 1500000.00,
    ]);

    $this->get(route('storefront.reservation.receipt', $reservation->id))
        ->assertOk()
        ->assertSee('Payment Successful & Confirmed')
        ->assertSee('Alex Turner')
        ->assertSee('Rp 1.500.000');
});
