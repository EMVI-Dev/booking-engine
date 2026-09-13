<?php

use App\Models\Operator;
use App\Models\Reservation;
use App\Models\User;
use Livewire\Livewire;

test('new reservations use the RSV prefix by default', function () {
    $operator = Operator::factory()->create();

    $reservation = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'code' => null,
    ]);

    expect($reservation->fresh()->code)->toStartWith('RSV-');
});

test('operators can set a custom reservation code prefix on brand settings', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);

    Livewire::test('pages::settings.brand')
        ->set('reservation_code_prefix', 'bali')
        ->call('updateBrandSettings')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect($operator->fresh()->reservationCodePrefix())->toBe('BALI');

    $reservation = Reservation::factory()->create([
        'operator_id' => $operator->id,
        'code' => null,
    ]);

    expect($reservation->fresh()->code)->toStartWith('BALI-');
});

test('reservation code prefixes are normalized to letters and numbers', function () {
    expect(Reservation::normalizeCodePrefix('ba-li tours!'))->toBe('BALITOUR')
        ->and(Reservation::normalizeCodePrefix(''))->toBe('RSV')
        ->and(Reservation::normalizeCodePrefix(null))->toBe('RSV');
});
