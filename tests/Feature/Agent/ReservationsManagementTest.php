<?php

use App\Enums\ReservationStatus;
use App\Models\Agent;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\DokuPaymentService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create();
    $this->agent->users()->attach($this->user->id, ['role' => 'owner']);
});

test('unauthenticated users cannot view reservations page', function () {
    $this->get(route('reservations.index'))
        ->assertRedirect(route('login'));
});

test('agent can view reservations page with metric counters', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['agent_id' => $this->agent->id]);

    $confirmed = Reservation::factory()->confirmed()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Budi Santoso',
    ]);

    Payment::factory()->paid()->create([
        'reservation_id' => $confirmed->id,
        'amount' => 1500000,
    ]);

    $pending = Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $this->get(route('reservations.index'))
        ->assertOk()
        ->assertSee('Bookings & Reservations')
        ->assertSee('Budi Santoso')
        ->assertSee('Confirmed');

    Livewire::test('pages::reservations.index')
        ->assertSee('Budi Santoso')
        ->assertSee('Rp 1.500.000');
});

test('agent only sees their own reservations and not other agents reservations', function () {
    $this->actingAs($this->user);

    $otherAgent = Agent::factory()->create();
    $otherPackage = Package::factory()->create(['agent_id' => $otherAgent->id]);

    $myPackage = Package::factory()->create(['agent_id' => $this->agent->id]);

    Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $myPackage->id,
        'guest_name' => 'My Guest Alice',
    ]);

    Reservation::factory()->create([
        'agent_id' => $otherAgent->id,
        'bookable_type' => 'package',
        'bookable_id' => $otherPackage->id,
        'guest_name' => 'Other Agent Guest Bob',
    ]);

    Livewire::test('pages::reservations.index')
        ->assertSee('My Guest Alice')
        ->assertDontSee('Other Agent Guest Bob');
});

test('agent can filter reservations by status', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['agent_id' => $this->agent->id]);

    Reservation::factory()->confirmed()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Confirmed Guest John',
    ]);

    Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
        'guest_name' => 'Pending Guest Mary',
    ]);

    Livewire::test('pages::reservations.index')
        ->set('statusFilter', 'confirmed')
        ->assertSee('Confirmed Guest John')
        ->assertDontSee('Pending Guest Mary')
        ->set('statusFilter', 'payment_pending')
        ->assertSee('Pending Guest Mary')
        ->assertDontSee('Confirmed Guest John');
});

test('agent can search reservations by guest name or email', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['agent_id' => $this->agent->id]);

    Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Wayan Sukadana',
        'guest_email' => 'wayan@example.com',
    ]);

    Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Ketut Mangku',
        'guest_email' => 'ketut@example.com',
    ]);

    Livewire::test('pages::reservations.index')
        ->set('search', 'Wayan')
        ->assertSee('Wayan Sukadana')
        ->assertDontSee('Ketut Mangku')
        ->set('search', 'ketut@example.com')
        ->assertSee('Ketut Mangku')
        ->assertDontSee('Wayan Sukadana');
});

test('agent can update reservation status and save notes', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['agent_id' => $this->agent->id]);

    $reservation = Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
        'notes' => 'Original note',
    ]);

    Livewire::test('pages::reservations.index')
        ->call('updateStatus', $reservation->id, 'confirmed')
        ->assertDispatched('reservation-updated');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);

    Livewire::test('pages::reservations.index')
        ->call('viewDetails', $reservation->id)
        ->assertSet('showDetailModal', true)
        ->set('agentNote', 'VIP pickup requested at hotel lobby 7 AM')
        ->call('saveNotes');

    expect($reservation->fresh()->notes)->toBe('VIP pickup requested at hotel lobby 7 AM');
});

test('agent can configure booking confirmation mode in storefront settings', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::settings.storefront')
        ->assertSet('booking_confirmation_mode', 'automatic')
        ->set('booking_confirmation_mode', 'manual')
        ->call('updateStorefrontSettings')
        ->assertDispatched('storefront-updated');

    $this->agent->refresh();
    expect($this->agent->isManualConfirmationEnabled())->toBeTrue()
        ->and($this->agent->getBookingConfirmationMode())->toBe('manual');
});

test('manual confirmation mode sets paid reservation to pending_confirmation until operator approves', function () {
    $this->agent->update([
        'settings' => [
            'storefront' => [
                'booking_confirmation_mode' => 'manual',
            ],
        ],
    ]);

    $package = Package::factory()->create(['agent_id' => $this->agent->id]);

    $reservation = Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $payment = Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'gateway_ref' => 'INV-MANUAL-TEST-001',
    ]);

    $service = app(DokuPaymentService::class);
    $service->processNotification([
        'order' => ['invoice_number' => 'INV-MANUAL-TEST-001'],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::PendingConfirmation);

    // Operator approves via dashboard
    $this->actingAs($this->user);
    Livewire::test('pages::reservations.index')
        ->call('updateStatus', $reservation->id, 'confirmed');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('reservations automatically generate unique code and can be searched by code', function () {
    $package = Package::factory()->create(['agent_id' => $this->agent->id]);

    $reservation = Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Dewi Sartika',
    ]);

    expect($reservation->code)->not->toBeEmpty()
        ->and($reservation->code)->toStartWith('RSV-');

    $this->actingAs($this->user);
    Livewire::test('pages::reservations.index')
        ->set('search', $reservation->code)
        ->assertSee('Dewi Sartika')
        ->assertSee($reservation->code);
});
