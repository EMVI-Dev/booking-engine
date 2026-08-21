<?php

use App\Models\AvailabilityBlock;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create();
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);
});

test('unauthenticated user cannot access calendar page', function () {
    $this->get(route('calendar.index'))
        ->assertRedirect(route('login'));
});

test('operator can view calendar with month days and scheduled reservations', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $tripDate = now()->addDays(3)->format('Y-m-d');

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'requested_date' => $tripDate,
        'guest_name' => 'Michael Scott',
        'pax_count' => 4,
    ]);

    $this->get(route('calendar.index'))
        ->assertOk()
        ->assertSee('Booking Calendar & Availability');

    Livewire::test('pages::calendar.index')
        ->assertSee(now()->format('F Y'))
        ->call('selectDate', $tripDate)
        ->assertSee('Michael Scott')
        ->assertSee('4 Pax');
});

test('operator can navigate calendar months', function () {
    $this->actingAs($this->user);

    $nextMonthLabel = now()->addMonth()->format('F Y');
    $prevMonthLabel = now()->subMonth()->format('F Y');

    Livewire::test('pages::calendar.index')
        ->call('nextMonth')
        ->assertSee($nextMonthLabel)
        ->call('prevMonth')
        ->call('prevMonth')
        ->assertSee($prevMonthLabel)
        ->call('goToToday')
        ->assertSee(now()->format('F Y'));
});

test('operator can create and delete availability date blocks', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create(['operator_id' => $this->operator->id]);

    Livewire::test('pages::calendar.index')
        ->set('block_product_id', $product->id)
        ->set('block_date_start', now()->addDays(10)->format('Y-m-d'))
        ->set('block_date_end', now()->addDays(12)->format('Y-m-d'))
        ->set('block_reason', 'Boat Drydock Maintenance')
        ->call('saveBlock')
        ->assertHasNoErrors()
        ->assertSee('Boat Drydock Maintenance');

    $block = AvailabilityBlock::where('operator_id', $this->operator->id)->first();

    expect($block)->not->toBeNull()
        ->and($block->reason)->toBe('Boat Drydock Maintenance')
        ->and($block->product_id)->toBe($product->id);

    Livewire::test('pages::calendar.index')
        ->call('deleteBlock', $block->id);

    expect(AvailabilityBlock::count())->toBe(0);
});
