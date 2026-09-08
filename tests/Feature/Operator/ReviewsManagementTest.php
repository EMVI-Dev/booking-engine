<?php

use App\Models\Operator;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create();
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);
});

test('the in-app reviews desk page is not available', function () {
    $this->get('/reviews')->assertNotFound();

    $this->actingAs($this->user)
        ->get('/reviews')
        ->assertNotFound();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Guest Reviews');
});
