<?php

use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Reservation;
use App\Models\User;

beforeEach(function () {
    $user = User::factory()->create();
    $this->operator = Operator::factory()->create(['name' => 'Shared Chrome Tours']);
    $this->operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);
});

test('operator dashboard and bookings share dry chrome tokens', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('op-shell')
        ->assertSee('op-nav-item')
        ->assertSee('op-card')
        ->assertSee('op-nav-icon')
        ->assertSee(__('Packages'))
        ->assertSee(__('Direct Revenue'))
        ->assertSee('op-palette-ebony')
        ->assertSee('mailto:support@travelengine.online', false)
        ->assertDontSee('bg-[#FFEF4D] text-[#090d16] font-black shadow-xs', false);

    $this->get(route('packages.index'))
        ->assertOk()
        ->assertSee('op-palette-ebony');

    $this->get(route('reservations.index'))
        ->assertOk()
        ->assertSee('op-palette-ebony')
        ->assertSee(__('Bookings & Reservations'))
        ->assertSee('op-toolbar')
        ->assertSee('op-tab')
        ->assertSee('op-card')
        ->assertSee(__('Create Booking Link'));
});

test('operator catalog pages use the shared page header and toolbar', function () {
    $this->get(route('packages.index'))
        ->assertOk()
        ->assertSee(__('Tour Packages & Expeditions'))
        ->assertSee('op-toolbar')
        ->assertSee(__('Add Tour Package'));

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee(__('Single Activities'))
        ->assertSee('op-toolbar')
        ->assertSee(__('Add Single Activity'));

    $this->get(route('coupons.index'))
        ->assertOk()
        ->assertSee(__('Coupons & Promo Codes'))
        ->assertSee('op-tab')
        ->assertSee(__('New Promo Code'));
});

test('operator settings tabs use the shared filter tab component', function () {
    $this->get(route('brand.edit'))
        ->assertOk()
        ->assertSee('op-tab')
        ->assertSee(__('Brand & Identity'))
        ->assertSee(__('Storefront & Policies'))
        ->assertSee(__('Payout bank account'))
        ->assertSee(__('Your team'));
});

test('the current bookings page shows a readable count badge', function () {
    Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    $this->get(route('reservations.index'))
        ->assertOk()
        ->assertSee('op-nav-badge', false)
        ->assertSee('aria-current="page"', false);
});
