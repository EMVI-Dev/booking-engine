<?php

use App\Enums\ReservationStatus;
use App\Models\Agent;
use App\Models\Guest;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create();
    $this->agent->users()->attach($this->user->id, ['role' => 'owner']);
});

test('unauthenticated users cannot view guests directory page', function () {
    $this->get(route('guests.index'))
        ->assertRedirect(route('login'));
});

test('reservations automatically link to dedicated Guest entity and merge by email', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['agent_id' => $this->agent->id]);

    // Booking 1
    $res1 = Reservation::factory()->confirmed()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Wayan Repeat Guest',
        'guest_email' => 'wayan.bali@example.com',
        'guest_contact' => '081234567890',
        'pax_count' => 2,
    ]);

    expect($res1->guest_id)->not->toBeNull();
    $guest = Guest::find($res1->guest_id);
    expect($guest)->not->toBeNull()
        ->and($guest->email)->toBe('wayan.bali@example.com');

    // Booking 2 with same email (uppercase to test case-insensitivity)
    $res2 = Reservation::factory()->confirmed()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Wayan Repeat Guest',
        'guest_email' => 'WAYAN.BALI@example.com',
        'guest_contact' => '081234567890',
        'pax_count' => 3,
    ]);

    expect($res2->guest_id)->toBe($guest->id);
    expect($guest->reservations()->count())->toBe(2);
});

test('agent can view guests directory and edit CRM notes and tags', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['agent_id' => $this->agent->id]);

    $res = Reservation::factory()->confirmed()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Sarah Johnson',
        'guest_email' => 'sarah@example.com',
        'guest_contact' => '08987654321',
        'pax_count' => 2,
    ]);

    Payment::factory()->paid()->create([
        'reservation_id' => $res->id,
        'amount' => 1500000,
    ]);

    $guest = Guest::where('email', 'sarah@example.com')->firstOrFail();

    $this->get(route('guests.index'))
        ->assertOk()
        ->assertSee('Guest Directory & CRM')
        ->assertSee('Sarah Johnson');

    Livewire::test('pages::guests.index')
        ->assertSee('Sarah Johnson')
        ->assertSee('sarah@example.com')
        ->assertSee('1 Bookings')
        ->call('editGuest', $guest->id)
        ->assertSet('showEditModal', true)
        ->set('editNotes', 'Allergic to peanuts. Prefers front row boat seat.')
        ->set('editTagsInput', 'VIP, Vegetarian')
        ->call('saveGuest')
        ->assertSet('showEditModal', false);

    $guest->refresh();
    expect($guest->notes)->toBe('Allergic to peanuts. Prefers front row boat seat.')
        ->and($guest->tags)->toEqual(['VIP', 'Vegetarian']);
});

test('agent only sees guests for their own agent', function () {
    $this->actingAs($this->user);

    $otherAgent = Agent::factory()->create();
    $otherPackage = Package::factory()->create(['agent_id' => $otherAgent->id]);

    $myPackage = Package::factory()->create(['agent_id' => $this->agent->id]);

    Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $myPackage->id,
        'guest_name' => 'Alice Private Guest',
        'guest_email' => 'alice@example.com',
    ]);

    Reservation::factory()->create([
        'agent_id' => $otherAgent->id,
        'bookable_type' => 'package',
        'bookable_id' => $otherPackage->id,
        'guest_name' => 'Bob Secret Guest',
        'guest_email' => 'bob@example.com',
    ]);

    Livewire::test('pages::guests.index')
        ->assertSee('Alice Private Guest')
        ->assertDontSee('Bob Secret Guest');
});

test('agent can search and filter guest directory and view booking history drawer', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create([
        'agent_id' => $this->agent->id,
        'title' => 'Nusa Penida Snorkeling Trip',
    ]);

    $res1 = Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Michael Chen',
        'guest_email' => 'michael@example.com',
        'guest_contact' => '08111222333',
        'pax_count' => 4,
        'status' => ReservationStatus::Confirmed,
    ]);

    Payment::factory()->paid()->create([
        'reservation_id' => $res1->id,
        'amount' => 2000000,
    ]);

    $res2 = Reservation::factory()->create([
        'agent_id' => $this->agent->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Elena Rostova',
        'guest_email' => 'elena@example.com',
        'guest_contact' => '08777888999',
        'pax_count' => 2,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $michaelGuest = Guest::where('email', 'michael@example.com')->firstOrFail();

    // Search by name
    Livewire::test('pages::guests.index')
        ->set('search', 'Michael')
        ->assertSee('Michael Chen')
        ->assertDontSee('Elena Rostova')
        ->set('search', 'elena@example.com')
        ->assertSee('Elena Rostova')
        ->assertDontSee('Michael Chen')
        ->set('search', '')
        ->call('viewGuestHistory', $michaelGuest->id)
        ->assertSet('showHistoryModal', true)
        ->assertSee('Nusa Penida Snorkeling Trip')
        ->assertSee('Michael Chen')
        ->call('closeHistory')
        ->assertSet('showHistoryModal', false);
});
