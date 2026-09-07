<?php

use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\WhatsAppDispatchService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create([
        'name' => 'Nusa Penida Explorer',
        'slug' => 'nusa-explorer',
        'status' => OperatorStatus::Approved,
        'contact_whatsapp' => '081234567890',
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Manta Bay Snorkeling Trip',
        'price' => 750000.00,
    ]);

    $this->reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'guest_name' => 'Michael Scott',
        'guest_contact' => '081987654321',
        'requested_date' => now()->addDays(2)->format('Y-m-d'),
        'pax_count' => 3,
        'status' => ReservationStatus::Confirmed,
    ]);

    $this->payment = Payment::factory()->create([
        'reservation_id' => $this->reservation->id,
        'amount' => 2250000.00,
        'status' => PaymentStatus::Paid,
    ]);
});

test('normalizes indonesian local phone numbers to international format', function () {
    $service = app(WhatsAppDispatchService::class);

    expect($service->normalizePhoneNumber('081234567890'))->toBe('6281234567890')
        ->and($service->normalizePhoneNumber('+6281234567890'))->toBe('6281234567890')
        ->and($service->normalizePhoneNumber('0812-3456-7890'))->toBe('6281234567890')
        ->and($service->normalizePhoneNumber('(0812) 3456 7890'))->toBe('6281234567890')
        ->and($service->normalizePhoneNumber('6281234567890'))->toBe('6281234567890');
});

test('generates 1-click booking confirmation WhatsApp url with voucher link', function () {
    $service = app(WhatsAppDispatchService::class);

    $url = $service->getConfirmationUrl($this->reservation);

    expect($url)->toStartWith('https://wa.me/6281987654321?text=')
        ->and(urldecode($url))->toContain('Michael Scott')
        ->and(urldecode($url))->toContain('Nusa Penida Explorer')
        ->and(urldecode($url))->toContain('Manta Bay Snorkeling Trip')
        ->and(urldecode($url))->toContain('3 Pax')
        ->and(urldecode($url))->toContain('#'.$this->reservation->code)
        ->and(urldecode($url))->toContain(route('storefront.reservation.receipt', $this->reservation))
        ->and(urldecode($url))->not->toMatch('/[\x{1F300}-\x{1FAFF}]/u');
});

test('generates departure reminder and meeting point WhatsApp urls', function () {
    $service = app(WhatsAppDispatchService::class);

    $reminderUrl = $service->getReminderUrl($this->reservation);
    $meetingUrl = $service->getMeetingPointUrl($this->reservation);
    $chatUrl = $service->getDirectChatUrl($this->reservation);

    expect($reminderUrl)->toStartWith('https://wa.me/6281987654321?text=')
        ->and(urldecode($reminderUrl))->toContain('Trip Reminder')
        ->and(urldecode($reminderUrl))->toContain('sunscreen')
        ->and($meetingUrl)->toStartWith('https://wa.me/6281987654321?text=')
        ->and(urldecode($meetingUrl))->toContain('Meeting Point')
        ->and($chatUrl)->toStartWith('https://wa.me/6281987654321?text=');
});

test('generates 1-click payment hold reminder WhatsApp url with pay link', function () {
    $holdReservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $this->package->id,
        'guest_name' => 'Pam Beesly',
        'guest_contact' => '08555123456',
        'requested_date' => now()->addDays(5)->format('Y-m-d'),
        'pax_count' => 2,
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(30),
    ]);

    $service = app(WhatsAppDispatchService::class);
    $url = $service->getPaymentHoldLinkUrl($holdReservation);

    expect($url)->toStartWith('https://wa.me/628555123456?text=')
        ->and(urldecode($url))->toContain('Payment Required')
        ->and(urldecode($url))->toContain('Pam Beesly')
        ->and(urldecode($url))->toContain('#'.$holdReservation->code)
        ->and(urldecode($url))->toContain(route('storefront.reservation.pay', $holdReservation));

    // Test clicking the pay link redirects to checkout
    $response = $this->get(route('storefront.reservation.pay', $holdReservation));
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/checkout/simulate');
});
