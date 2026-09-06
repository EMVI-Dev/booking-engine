<?php

use App\Models\Operator;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('new operators see a welcome banner and clickable setup steps', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create([
        'name' => 'First Week Tours',
        'bio' => null,
        'contact_whatsapp' => null,
        'terms_and_conditions' => null,
        'bank_provider' => null,
        'bank_account_number' => null,
        'bank_account_name' => null,
        'bank_account_ref' => null,
    ]);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->withSession(['welcome_onboarding' => true])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your account is created')
        ->assertSee('Page is closed')
        ->assertSee('Guests cannot open your page yet')
        ->assertSee('Add a trip')
        ->assertSee('Payout bank account')
        ->assertSee(route('packages.create', absolute: false))
        ->assertSee(route('payments.edit', absolute: false));
});

test('operator dashboard uses quiet chrome and yellow primary actions', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create(['name' => 'Quiet Chrome Tours']);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Quiet Chrome Tours')
        ->assertSee(__('Operator Portal'))
        ->assertSee(__('Create Booking Link'))
        ->assertSee(__('Live Storefront'))
        ->assertSee('op-shell')
        ->assertSee('op-palette-ebony')
        ->assertSee('op-hero')
        ->assertSee('op-metric-featured')
        ->assertSee('op-metric')
        ->assertSee('shadow-none')
        ->assertSee('op-nav-item')
        ->assertSee('op-nav-icon')
        ->assertSee('op-sidebar')
        ->assertSee('w-72')
        ->assertSee('op-card')
        ->assertSee('Bookings')
        ->assertSee('Wallet')
        ->assertSee(__('Create package'))
        ->assertSee(__('Add activity'))
        ->assertSee(__('Upcoming'))
        ->assertSee(__('Recent bookings'))
        ->assertSee(__('Shortcuts'))
        ->assertDontSee('bg-[#FFEF4D] text-[#090d16] font-black shadow-xs', false);
});
