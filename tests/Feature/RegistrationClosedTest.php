<?php

use App\Models\User;

test('registration page explains operator sign-up is coming soon when disabled', function () {
    config(['fortify.registration_enabled' => false]);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('We are preparing the operator portal')
        ->assertSee('New operator accounts are not open yet')
        ->assertSee('Coming soon')
        ->assertDontSee('Create account')
        ->assertDontSee('Sign In to Operator Portal');
});

test('login stays open when operator sign-up is closed', function () {
    config(['fortify.registration_enabled' => false]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign In to Operator Portal')
        ->assertSee('We are preparing operator sign-up. Coming soon.')
        ->assertDontSee('Create an account');
});

test('new operator accounts cannot be created when registration is disabled', function () {
    config(['fortify.registration_enabled' => false]);

    $this->post(route('register.store'), [
        'name' => 'Wayan Sudarma',
        'email' => 'wayan@balitours.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'agency_name' => 'Bali Ocean Treks',
        'terms' => '1',
    ])->assertForbidden();

    $this->assertGuest();
    expect(User::query()->where('email', 'wayan@balitours.com')->exists())->toBeFalse();
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

test('homepage advertises coming soon instead of sign-up when registration is disabled', function () {
    config(['fortify.registration_enabled' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Coming soon')
        ->assertSee('Coming soon — we are preparing operator sign-up')
        ->assertDontSee('Create Your Free Tour Website', false);
});

test('platform admin login still works when operator registration is disabled', function () {
    config(['fortify.registration_enabled' => false]);

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Admin sign in');
});
