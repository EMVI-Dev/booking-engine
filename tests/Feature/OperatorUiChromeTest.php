<?php

use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\PlatformSetting;
use App\Models\Reservation;
use App\Models\User;

beforeEach(function () {
    $user = User::factory()->create();
    $this->operator = Operator::factory()->create(['name' => 'Shared Chrome Tours']);
    $this->operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);
});

test('operator dashboard and bookings share dry chrome tokens', function () {
    $settings = PlatformSetting::current();
    $settings->update([
        'settings' => array_merge($settings->settings ?? [], [
            'support_email' => 'support@emvi.dev',
        ]),
    ]);

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
        ->assertDontSee(__('Your team'));

    $this->get(route('settings.plan'))
        ->assertOk()
        ->assertSee('op-tab')
        ->assertSee(__('Subscription & Plan'))
        ->assertSee(__('Billing & Invoices'))
        ->assertSee(__('Payout bank account'));

    $this->get(route('payments.edit'))
        ->assertOk()
        ->assertSee(__('Payout bank account'))
        ->assertSee(__('Subscription & Plan'))
        ->assertDontSee(__('Brand & Identity'));

    $this->get(route('settings.team'))
        ->assertOk()
        ->assertSee(__('Your team'))
        ->assertDontSee(__('Storefront & Policies'))
        ->assertSee(__('Team'));
});

test('sidebar lists activities before packages and keeps team out of storefront', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder([
            route('products.index', absolute: false),
            route('packages.index', absolute: false),
        ])
        ->assertSeeInOrder([
            route('settings.team', absolute: false),
            route('brand.edit', absolute: false),
            route('settings.plan', absolute: false),
        ]);
});

test('prefixed operator inputs keep space for icons', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('@layer components')
        ->toContain('.op-input.pl-10')
        ->toContain('.op-input.pl-11')
        ->toContain('padding-inline-start: 2.5rem')
        ->toContain('padding-inline-start: 2.75rem');

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('op-input pl-10', false)
        ->assertSee(__('Search by name or category...'));

    $this->get(route('brand.edit'))
        ->assertOk()
        ->assertSee(__('Brand Accent Color (Hex)'))
        ->assertSee('pl-11 font-mono text-xs uppercase', false);
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
