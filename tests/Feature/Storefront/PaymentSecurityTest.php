<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Mail\GuestBookingConfirmedMail;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->operator = Operator::factory()->create([
        'name' => 'Bali Sea Adventures',
        'slug' => 'bali-sea',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'bali-sea.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 750000.00,
        'status' => ListingStatus::Published,
    ]);

    $this->reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_email' => 'sarah@example.com',
        'pax_count' => 2,
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(30),
    ]);

    $this->payment = Payment::factory()->create([
        'reservation_id' => $this->reservation->id,
        'amount' => 1500000.00,
        'gateway' => 'doku',
        'gateway_ref' => 'INV-SECURE-1',
        'status' => PaymentStatus::Pending,
    ]);
});

/**
 * Build the HMAC-SHA256 signature headers DOKU attaches to genuine notifications.
 *
 * @return array<string, string>
 */
function dokuSignedHeaders(string $body, string $clientId, string $secretKey): array
{
    $requestId = 'req-'.uniqid();
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    $digest = base64_encode(hash('sha256', $body, true));

    $component = "Client-Id:{$clientId}\n"
        ."Request-Id:{$requestId}\n"
        ."Request-Timestamp:{$timestamp}\n"
        .'Request-Target:'.config('doku.notification_path')."\n"
        ."Digest:{$digest}";

    return [
        'HTTP_CLIENT-ID' => $clientId,
        'HTTP_REQUEST-ID' => $requestId,
        'HTTP_REQUEST-TIMESTAMP' => $timestamp,
        'HTTP_SIGNATURE' => 'HMACSHA256='.base64_encode(hash_hmac('sha256', $component, $secretKey, true)),
        'CONTENT_TYPE' => 'application/json',
    ];
}

test('payment simulator is unreachable when disabled', function () {
    config()->set('doku.simulator_enabled', false);

    $this->get(route('storefront.payment.simulate', [
        'reservation' => $this->reservation->id,
        'payment' => $this->payment->id,
    ]))->assertNotFound();
});

test('simulated payment confirmation cannot mark a reservation paid when the simulator is disabled', function () {
    config()->set('doku.simulator_enabled', false);

    $this->post(route('storefront.payment.simulate.confirm'), [
        'reservation_id' => $this->reservation->id,
        'status' => 'SUCCESS',
    ])->assertNotFound();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->reservation->fresh()->status)->toBe(ReservationStatus::PaymentPending);
});

test('simulator cannot be used to view a payment belonging to another reservation', function () {
    $otherPayment = Payment::factory()->create([
        'reservation_id' => Reservation::factory()->create(['operator_id' => $this->operator->id])->id,
        'gateway_ref' => 'INV-OTHER-1',
        'status' => PaymentStatus::Pending,
    ]);

    $this->get(route('storefront.payment.simulate', [
        'reservation' => $this->reservation->id,
        'payment' => $otherPayment->id,
    ]))->assertNotFound();
});

test('doku webhook rejects an unsigned notification when gateway credentials are configured', function () {
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');

    $this->postJson(route('doku.webhook'), [
        'order' => ['invoice_number' => 'INV-SECURE-1'],
        'transaction' => ['status' => 'SUCCESS'],
    ])->assertUnauthorized();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->reservation->fresh()->status)->toBe(ReservationStatus::PaymentPending);
});

test('doku webhook rejects a notification whose signature does not match the body', function () {
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');

    $body = json_encode(['order' => ['invoice_number' => 'INV-SECURE-1'], 'transaction' => ['status' => 'SUCCESS']]);
    $headers = dokuSignedHeaders($body, 'BRN-CLIENT', 'the-wrong-secret');

    $this->call('POST', route('doku.webhook'), [], [], [], $headers, $body)
        ->assertUnauthorized();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('doku webhook accepts a correctly signed notification and confirms the reservation', function () {
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');
    Mail::fake();

    $body = json_encode(['order' => ['invoice_number' => 'INV-SECURE-1'], 'transaction' => ['status' => 'SUCCESS']]);
    $headers = dokuSignedHeaders($body, 'BRN-CLIENT', 'super-secret');

    $this->call('POST', route('doku.webhook'), [], [], [], $headers, $body)
        ->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);

    Mail::assertSent(GuestBookingConfirmedMail::class, 1);
});

test('replaying a settled payment notification does not resend the e-voucher or double credit the wallet', function () {
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');
    Mail::fake();

    $body = json_encode(['order' => ['invoice_number' => 'INV-SECURE-1'], 'transaction' => ['status' => 'SUCCESS']]);

    foreach (range(1, 3) as $attempt) {
        $this->call('POST', route('doku.webhook'), [], [], [], dokuSignedHeaders($body, 'BRN-CLIENT', 'super-secret'), $body)->assertOk();
    }

    Mail::assertSent(GuestBookingConfirmedMail::class, 1);

    expect(WalletTransaction::where('reservation_id', $this->reservation->id)->where('type', WalletTransactionType::BookingEarning)->count())->toBe(1);
});

test('a reservation receipt is not readable from another operator storefront', function () {
    $otherOperator = Operator::factory()->create([
        'slug' => 'rival-tours',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $otherOperator->id,
        'domain' => 'rival-tours.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $path = "/reservations/{$this->reservation->public_token}/receipt";

    $this->get("http://rival-tours.booking.test{$path}", ['Host' => 'rival-tours.booking.test'])
        ->assertNotFound();

    $this->get("http://bali-sea.booking.test{$path}", ['Host' => 'bali-sea.booking.test'])
        ->assertOk();

    $ticketPath = "/reservations/{$this->reservation->public_token}/e-ticket";

    $this->get("http://rival-tours.booking.test{$ticketPath}", ['Host' => 'rival-tours.booking.test'])
        ->assertNotFound();
});

test('reservation urls use an unguessable token rather than the record id', function () {
    expect($this->reservation->public_token)->toHaveLength(48)
        ->and(route('storefront.reservation.receipt', $this->reservation))
        ->toContain($this->reservation->public_token)
        ->and(route('storefront.reservation.receipt', $this->reservation))
        ->not->toContain($this->reservation->id);

    $this->get("http://bali-sea.booking.test/reservations/{$this->reservation->id}/receipt", ['Host' => 'bali-sea.booking.test'])
        ->assertNotFound();
});

test('a dispute notification holds the booking amount plus the card-fight fee', function () {
    config()->set('doku.sandbox.client_id', 'BRN-CLIENT');
    config()->set('doku.sandbox.secret_key', 'super-secret');

    $this->payment->update(['status' => PaymentStatus::Paid]);
    $this->reservation->update(['status' => ReservationStatus::Confirmed]);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $this->reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1500000.00,
        'fee_amount' => 0,
        'net_amount' => 1500000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Paid trip',
    ]);

    $body = json_encode(['order' => ['invoice_number' => 'INV-SECURE-1'], 'transaction' => ['status' => 'DISPUTE_OPENED']]);
    $headers = dokuSignedHeaders($body, 'BRN-CLIENT', 'super-secret');

    $this->call('POST', route('doku.webhook'), [], [], [], $headers, $body)
        ->assertOk();

    expect(WalletTransaction::query()
        ->where('reservation_id', $this->reservation->id)
        ->where('type', WalletTransactionType::DisputeHold)
        ->count())->toBe(1);
});

test('llms-full.txt is gated behind the ai discovery tier just like llms.txt', function () {
    $headers = ['Host' => 'bali-sea.booking.test'];

    $this->get('http://bali-sea.booking.test/llms-full.txt', $headers)
        ->assertForbidden()
        ->assertSee('AI Discovery Not Unlocked');

    $this->operator->update(['plan_id' => Plan::where('slug', 'agency')->value('id')]);
    Cache::flush();

    $this->get('http://bali-sea.booking.test/llms-full.txt', $headers)
        ->assertOk()
        ->assertSee('Comprehensive Operator Knowledge Base');
});
