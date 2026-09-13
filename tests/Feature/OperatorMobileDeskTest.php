<?php

use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create(['name' => 'Phone Desk Tours']);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($this->user);
});

test('brand and storefront settings are available without a desktop gate', function () {
    $this->get(route('brand.edit'))
        ->assertOk()
        ->assertDontSee('Best Managed on Desktop')
        ->assertDontSee('Copy Link for Desktop')
        ->assertDontSee('Review Platform (e.g. Google/Tripadvisor)')
        ->assertSee('Save Brand Settings')
        ->assertSee('w-full h-36 sm:w-24 sm:h-24', false);

    $this->get(route('storefront-settings.edit'))
        ->assertOk()
        ->assertDontSee('Best Managed on Desktop')
        ->assertSee('Save Storefront Settings');

    $this->get(route('review-settings.edit'))
        ->assertOk()
        ->assertDontSee('Best Managed on Desktop')
        ->assertSee('Review Platform (e.g. Google/Tripadvisor)')
        ->assertSee('Save review link');
});

test('activity and package create forms are available without a desktop gate', function () {
    $this->get(route('products.create'))
        ->assertOk()
        ->assertDontSee('Best Managed on Desktop')
        ->assertSee('Create Activity')
        ->assertSee('w-full aspect-video', false);

    $this->get(route('packages.create'))
        ->assertOk()
        ->assertDontSee('Best Managed on Desktop')
        ->assertSee('Create Tour Package')
        ->assertSee('w-full aspect-video', false);
});

test('payout bank can be filled on a phone', function () {
    $this->get(route('payments.edit'))
        ->assertOk()
        ->assertDontSee('Copy Link for Desktop')
        ->assertDontSee('Bank details are easier on a computer')
        ->assertSee('Where we send your money')
        ->assertSee('Save bank account')
        ->assertSee('Name on the account');
});

test('team settings stay desktop-gated', function () {
    $this->get(route('settings.team'))
        ->assertOk()
        ->assertSee('Copy Link for Desktop');
});

test('the bookings list includes a phone card layout', function () {
    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Phone Desk Guest',
        'requested_date' => now()->addDays(2)->format('Y-m-d'),
        'pax_count' => 2,
    ]);

    $this->get(route('reservations.index'))
        ->assertOk()
        ->assertSee('md:hidden space-y-3', false)
        ->assertSee('Phone Desk Guest')
        ->assertSee("wire:click=\"viewDetails('", false)
        ->assertDontSee('viewReservation', false);
});

test('the month calendar uses compact trip dots on phones', function () {
    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'requested_date' => now()->format('Y-m-d'),
        'pax_count' => 2,
    ]);

    $this->get(route('calendar.index'))
        ->assertOk()
        ->assertSee('min-h-[4.5rem] md:min-h-[100px]', false);

    Livewire::test('calendar.month-grid')
        ->assertSee('md:hidden flex items-center justify-center', false)
        ->assertSee('grid w-full grid-cols-[2.75rem_minmax(0,1fr)_2.75rem]', false)
        ->assertSee('h-11 w-full px-4', false);
});

test('operator inputs stay 16px on phones so iOS does not zoom', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('@layer components')
        ->toContain('font-size: 1rem;')
        ->toContain('font-size: 0.8125rem;');
});

test('the operator shell reserves space for the iPhone home indicator', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('viewport-fit=cover', false)
        ->assertSee('safe-area-inset-bottom', false)
        ->assertSee('safe-area-inset-top', false)
        ->assertSee('op-touch-nav fixed bottom-0', false)
        ->assertSee('op-desk-chrome', false);

    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('@media (min-width: 64rem) and (hover: hover) and (pointer: fine)')
        ->not->toContain('@media (min-width: 96rem)');
});

test('registration stacks the create-account actions on a phone', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    auth()->logout();

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('flex-col-reverse sm:flex-row', false)
        ->assertSee('Create account');
});
