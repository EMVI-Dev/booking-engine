<?php

use App\Models\User;

test('register page stays open when maintenance is off even if the old registration flag is off', function () {
    config(['fortify.registration_enabled' => false]);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create account')
        ->assertDontSee('We are preparing the operator portal')
        ->assertDontSee('Operator sign-up is paused');
});

test('login offers create account when maintenance is off', function () {
    config(['fortify.registration_enabled' => false]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign In to Operator Portal')
        ->assertSee('Create an account')
        ->assertDontSee('We are preparing operator sign-up. Coming soon.');
});

test('new operator accounts can be created when maintenance is off', function () {
    config(['fortify.registration_enabled' => false]);

    $this->post(route('register.store'), [
        'name' => 'Wayan Sudarma',
        'email' => 'wayan@balitours.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'agency_name' => 'Bali Ocean Treks',
        'terms' => '1',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'wayan@balitours.com')->exists())->toBeTrue();
});

test('operators can log in when sign-up is closed', function () {
    config(['fortify.registration_enabled' => false]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('homepage still offers sign-up when registration is closed but maintenance is off', function () {
    config(['fortify.registration_enabled' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Start free', false)
        ->assertSee('Start Free')
        ->assertDontSee('Coming soon — we are preparing operator sign-up');
});

test('platform admin login still works when operator registration is disabled', function () {
    config(['fortify.registration_enabled' => false]);

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Admin sign in');
});
