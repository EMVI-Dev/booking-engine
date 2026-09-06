<?php

use App\Models\Plan;

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('home pricing uses the three public plan names', function () {
    Plan::seedDefaultPlans();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Starter')
        ->assertSee('For freelance tour guides')
        ->assertSee('Growth')
        ->assertSee('small group selling together')
        ->assertSee('Agency')
        ->assertSee('small to mid travel agencies')
        ->assertDontSee('Enterprise')
        ->assertDontSee('QR check-in');
});
