<?php

use App\Enums\ReservationStatus;
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
