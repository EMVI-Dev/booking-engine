<?php

use App\Models\Operator;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create();
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);
});

test('unauthenticated user cannot access reviews page', function () {
    $this->get(route('reviews.index'))
        ->assertRedirect(route('login'));
});

test('operator can view reviews page with overall satisfaction and star breakdown', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->completed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Emma Watson',
    ]);

    Review::factory()->create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'rating' => 5,
        'comment' => 'Incredible snorkeling experience with manta rays!',
    ]);

    $this->get(route('reviews.index'))
        ->assertOk()
        ->assertSee('Guest Reviews')
        ->assertSee('Overall Satisfaction')
        ->assertSee('5.0');

    Livewire::test('pages::reviews.index')
        ->assertSee('Emma Watson')
        ->assertSee('Incredible snorkeling experience with manta rays!');
});

test('operator only sees their own reviews', function () {
    $this->actingAs($this->user);

    $otherOperator = Operator::factory()->create();
    $otherPackage = Package::factory()->create(['operator_id' => $otherOperator->id]);
    $otherRes = Reservation::factory()->completed()->create(['operator_id' => $otherOperator->id, 'bookable_type' => 'package', 'bookable_id' => $otherPackage->id, 'guest_name' => 'Stranger']);

    Review::factory()->create([
        'operator_id' => $otherOperator->id,
        'reservation_id' => $otherRes->id,
        'bookable_type' => 'package',
        'bookable_id' => $otherPackage->id,
        'comment' => 'Review for other operator only',
    ]);

    Livewire::test('pages::reviews.index')
        ->assertDontSee('Review for other operator only');
});

test('operator can filter reviews by star rating', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $res1 = Reservation::factory()->completed()->create(['operator_id' => $this->operator->id, 'bookable_type' => 'package', 'bookable_id' => $package->id]);
    $res2 = Reservation::factory()->completed()->create(['operator_id' => $this->operator->id, 'bookable_type' => 'package', 'bookable_id' => $package->id]);

    Review::factory()->create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $res1->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'rating' => 5,
        'comment' => 'Five star fabulous tour',
    ]);

    Review::factory()->create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $res2->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'rating' => 3,
        'comment' => 'Three star average tour',
    ]);

    Livewire::test('pages::reviews.index')
        ->set('ratingFilter', '5')
        ->assertSee('Five star fabulous tour')
        ->assertDontSee('Three star average tour')
        ->set('ratingFilter', '3')
        ->assertSee('Three star average tour')
        ->assertDontSee('Five star fabulous tour');
});
