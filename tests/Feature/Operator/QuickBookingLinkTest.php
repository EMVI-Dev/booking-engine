<?php

use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows an operator to create a direct booking reservation and generate payment link', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'price' => 500000,
        'status' => 'published',
        'title' => 'Komodo Sunset Cruise',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::reservations.index')
        ->call('openCreateLinkModal')
        ->assertSet('showCreateLinkModal', true)
        ->set('createExperienceSelection', "package:{$package->id}")
        ->set('createRequestedDate', now()->addDays(2)->toDateString())
        ->set('createPaxCount', 2)
        ->set('createGuestName', 'Budi Santoso')
        ->set('createGuestContact', '081234567890')
        ->set('createGuestEmail', 'budi@example.com')
        ->set('createNotes', 'Vegetarian lunch')
        ->call('generateBookingLink')
        ->assertHasNoErrors()
        ->assertSet('linkCreatedSuccessfully', true)
        ->assertSet('showCreateLinkModal', true)
        ->assertSet('generatedWhatsAppUrl', null);

    $reservation = Reservation::where('operator_id', $operator->id)->where('guest_name', 'Budi Santoso')->first();
    expect($reservation)->not->toBeNull()
        ->and($reservation->status)->toBe(ReservationStatus::PaymentPending)
        ->and($reservation->pax_count)->toBe(2)
        ->and($reservation->hold_expires_at)->not->toBeNull()
        ->and($reservation->guest)->not->toBeNull()
        ->and($reservation->guest->name)->toBe('Budi Santoso');
});

it('lets the operator fill the pay-link form with date chips, guest chips, and a pax stepper', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $operator->users()->attach($user->id, ['role' => 'owner']);

    Package::factory()->create([
        'operator_id' => $operator->id,
        'status' => 'published',
        'title' => 'Reef Morning',
        'price' => 350000,
    ]);

    $guest = Guest::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Sari Guest',
        'phone' => '081211122233',
        'email' => 'sari@example.com',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::reservations.index')
        ->call('openCreateLinkModal')
        ->assertSee(__('Create a pay link'))
        ->assertDontSee(__('Sample shop'))
        ->assertSee(__('Today'))
        ->assertSee(__('Tomorrow'))
        ->assertSee(__('Sari Guest'))
        ->assertSee(__('Recent'))
        ->assertSee(__('Add email or pickup notes'))
        ->assertDontSee(__('Pickup notes'))
        ->assertSet('createPaxCount', 1)
        ->call('incrementCreatePax')
        ->assertSet('createPaxCount', 2)
        ->call('decrementCreatePax')
        ->assertSet('createPaxCount', 1)
        ->call('setCreateDatePreset', 'today')
        ->assertSet('createRequestedDate', now()->toDateString())
        ->call('fillGuestFromCrm', $guest->id)
        ->assertSet('createGuestName', 'Sari Guest')
        ->assertSet('createGuestContact', '081211122233')
        ->assertSet('createGuestEmail', 'sari@example.com')
        ->assertSet('createShowExtras', true)
        ->assertSee(__('Pickup notes'))
        ->assertSee('data-date-picker-popover', false)
        ->assertSee('x-teleport="body"', false);
});

it('blocks pay links on the demo operator even when platform maintenance is off', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->demo()->create();
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'status' => 'published',
        'title' => 'Demo Lookaround Trip',
        'price' => 250000,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::reservations.index')
        ->call('openCreateLinkModal')
        ->assertSee(__('Sample shop'))
        ->assertSee(__('Pay links stay off here so visitors are never charged. This is not platform maintenance.'))
        ->assertDontSee(__('Checkout is disabled on the demo storefront. Look around — nothing here charges a card.'))
        ->set('createExperienceSelection', "package:{$package->id}")
        ->set('createRequestedDate', now()->addDays(2)->toDateString())
        ->set('createPaxCount', 1)
        ->set('createGuestName', 'Budi Santoso')
        ->set('createGuestContact', '081234567890')
        ->call('generateBookingLink')
        ->assertHasErrors(['checkout'])
        ->assertSet('linkCreatedSuccessfully', false);

    expect(Reservation::query()->where('operator_id', $operator->id)->exists())->toBeFalse();
});

it('remembers the last trip when opening the pay-link modal again', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $first = Package::factory()->create([
        'operator_id' => $operator->id,
        'status' => 'published',
        'title' => 'First Trip',
        'price' => 100000,
    ]);
    $second = Package::factory()->create([
        'operator_id' => $operator->id,
        'status' => 'published',
        'title' => 'Second Trip',
        'price' => 200000,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::reservations.index')
        ->call('openCreateLinkModal')
        ->set('createExperienceSelection', "package:{$second->id}")
        ->set('createRequestedDate', now()->addDays(2)->toDateString())
        ->set('createPaxCount', 1)
        ->set('createGuestName', 'Budi Santoso')
        ->set('createGuestContact', '081234567890')
        ->call('generateBookingLink')
        ->assertHasNoErrors()
        ->call('openCreateLinkModal')
        ->assertSet('createExperienceSelection', "package:{$second->id}")
        ->assertSet('createBookableId', $second->id);

    expect($first->id)->not->toBe($second->id);
});
