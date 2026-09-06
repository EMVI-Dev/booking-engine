<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSee('Start taking bookings')
        ->assertSee('Your business name')
        ->assertSee('Create account')
        ->assertDontSee('Bank Account Reference')
        ->assertDontSee('Continue to Payouts')
        ->assertDontSee('Tour Operator Onboarding')
        ->assertDontSee('Wayan Sudarma')
        ->assertDontSee('Bali Snorkel')
        ->assertDontSee('balitours');
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agency_name' => 'John Doe Tours',
        'terms' => '1',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
