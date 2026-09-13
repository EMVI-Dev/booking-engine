<?php

use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Plan::seedDefaultPlans();
    $growthPlan = Plan::where('slug', 'growth')->first();

    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create(['plan_id' => $growthPlan->id]);
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);
});

test('unauthenticated users cannot view guests directory page', function () {
    $this->get(route('guests.index'))
        ->assertRedirect(route('login'));
});

test('reservations automatically link to dedicated Guest entity and merge by email', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $res1 = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
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

    $res2 = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
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

test('operator can view guests directory and edit CRM notes and tags on profile', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $res = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
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
        ->assertSee('Guest CRM')
        ->assertSee('Sarah Johnson')
        ->assertSee('fa-eye', false)
        ->assertSee(route('guests.show', $guest), false);

    $this->get(route('guests.show', $guest))
        ->assertOk()
        ->assertSee('Sarah Johnson')
        ->assertSee('Trip history');

    Livewire::test('pages::guests.show', ['guest' => $guest])
        ->assertSee('Sarah Johnson')
        ->assertSee('sarah@example.com')
        ->call('openEdit')
        ->assertSet('showEditModal', true)
        ->set('editNotes', 'Allergic to peanuts. Prefers front row boat seat.')
        ->set('editTagsInput', 'VIP, Vegetarian')
        ->call('saveGuest')
        ->assertSet('showEditModal', false);

    $guest->refresh();
    expect($guest->notes)->toBe('Allergic to peanuts. Prefers front row boat seat.')
        ->and($guest->tags)->toEqual(['VIP', 'Vegetarian']);
});

test('operator only sees guests for their own operator', function () {
    $this->actingAs($this->user);

    $otherOperator = Operator::factory()->create();
    $otherPackage = Package::factory()->create(['operator_id' => $otherOperator->id]);

    $myPackage = Package::factory()->create(['operator_id' => $this->operator->id]);

    Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $myPackage->id,
        'guest_name' => 'Alice Private Guest',
        'guest_email' => 'alice@example.com',
    ]);

    Reservation::factory()->create([
        'operator_id' => $otherOperator->id,
        'bookable_type' => 'package',
        'bookable_id' => $otherPackage->id,
        'guest_name' => 'Bob Secret Guest',
        'guest_email' => 'bob@example.com',
    ]);

    Livewire::test('pages::guests.index')
        ->assertSee('Alice Private Guest')
        ->assertDontSee('Bob Secret Guest');
});

test('operator cannot open another operators guest profile', function () {
    $this->actingAs($this->user);

    $otherOperator = Operator::factory()->create();
    $otherGuest = Guest::factory()->create([
        'operator_id' => $otherOperator->id,
        'name' => 'Bob Secret Guest',
    ]);

    $this->get(route('guests.show', $otherGuest))->assertNotFound();
});

test('operator can search filter guests and open profile trip history', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Nusa Penida Snorkeling Trip',
    ]);

    $res1 = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Michael Chen',
        'guest_email' => 'michael@example.com',
        'guest_contact' => '08111222333',
        'pax_count' => 4,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->subDays(10)->toDateString(),
    ]);

    Payment::factory()->paid()->create([
        'reservation_id' => $res1->id,
        'amount' => 2000000,
    ]);

    Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Elena Rostova',
        'guest_email' => 'elena@example.com',
        'guest_contact' => '08777888999',
        'pax_count' => 2,
        'status' => ReservationStatus::PaymentPending,
        'requested_date' => now()->addDays(5)->toDateString(),
    ]);

    $michaelGuest = Guest::where('email', 'michael@example.com')->firstOrFail();

    Livewire::test('pages::guests.index')
        ->set('search', 'Michael')
        ->assertSee('Michael Chen')
        ->assertDontSee('Elena Rostova')
        ->set('search', 'elena@example.com')
        ->assertSee('Elena Rostova')
        ->assertDontSee('Michael Chen');

    Livewire::test('pages::guests.show', ['guest' => $michaelGuest])
        ->assertSee('Michael Chen')
        ->assertSee('Nusa Penida Snorkeling Trip')
        ->assertSee('Trip history');
});

test('directory sorts by lifetime spend across all pages', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $low = Guest::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Low Spender Guest',
        'email' => 'low@example.com',
    ]);
    $high = Guest::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'High Spender Guest',
        'email' => 'high@example.com',
    ]);

    $lowRes = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'guest_id' => $low->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => $low->name,
        'guest_email' => $low->email,
    ]);
    Payment::factory()->paid()->create([
        'reservation_id' => $lowRes->id,
        'amount' => 100_000,
    ]);

    $highRes = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'guest_id' => $high->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => $high->name,
        'guest_email' => $high->email,
    ]);
    Payment::factory()->paid()->create([
        'reservation_id' => $highRes->id,
        'amount' => 9_000_000,
    ]);

    Livewire::test('pages::guests.index')
        ->set('sortBy', 'spent')
        ->assertSeeInOrder(['High Spender Guest', 'Low Spender Guest']);
});

test('directory shows last and next trip dates and sorts by next trip', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $soon = Guest::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Soon Trip Guest',
        'email' => 'soon@example.com',
    ]);
    $later = Guest::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Later Trip Guest',
        'email' => 'later@example.com',
    ]);

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'guest_id' => $soon->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => $soon->name,
        'guest_email' => $soon->email,
        'requested_date' => now()->addDays(2)->toDateString(),
    ]);

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'guest_id' => $later->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => $later->name,
        'guest_email' => $later->email,
        'requested_date' => now()->addDays(20)->toDateString(),
    ]);

    Livewire::test('pages::guests.index')
        ->assertSee(now()->addDays(2)->format('M j, Y'))
        ->assertSee(now()->addDays(20)->format('M j, Y'))
        ->set('sortBy', 'next_trip')
        ->assertSeeInOrder(['Soon Trip Guest', 'Later Trip Guest']);
});

test('directory flags possible duplicates and profile can merge them', function () {
    $this->actingAs($this->user);

    $keep = Guest::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Keep Profile',
        'email' => 'same@example.com',
        'phone' => '08111111111',
        'notes' => 'Keep notes',
        'tags' => ['VIP'],
    ]);
    $source = Guest::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Merge Away',
        'email' => 'same@example.com',
        'phone' => '08111111111',
        'notes' => 'Source notes',
        'tags' => ['Vegetarian'],
    ]);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);
    $moved = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'guest_id' => $source->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => $source->name,
        'guest_email' => $source->email,
    ]);

    Livewire::test('pages::guests.index')
        ->assertSee('Duplicate?');

    Livewire::test('pages::guests.show', ['guest' => $keep])
        ->assertSee('Possible duplicates')
        ->assertSee('Merge Away')
        ->call('mergeDuplicate', $source->id)
        ->assertDispatched('toast');

    expect(Guest::find($source->id))->toBeNull();
    expect($moved->fresh()->guest_id)->toBe($keep->id);

    $keep->refresh();
    expect($keep->tags)->toEqualCanonicalizing(['VIP', 'Vegetarian'])
        ->and($keep->notes)->toContain('Keep notes')
        ->and($keep->notes)->toContain('Source notes');
});

test('operator can export filtered guests as csv', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Exportable Guest',
        'guest_email' => 'export@example.com',
        'guest_contact' => '08123400000',
    ]);

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Hidden Guest',
        'guest_email' => 'hidden@example.com',
    ]);

    Livewire::test('pages::guests.index')
        ->set('search', 'Exportable')
        ->call('exportCsv')
        ->assertFileDownloaded('guests-'.now()->format('Y-m-d').'.csv');
});
