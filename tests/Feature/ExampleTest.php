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
        ->assertSee('WhatsApp pay links')
        ->assertSee('Daily guest lists')
        ->assertSee('Payouts, 0% cut')
        ->assertSee('WhatsApp links, tickets, guest lists')
        ->assertSee('Your domain + white-label site')
        ->assertSee('Tour website + 24/7 booking')
        ->assertSee('yourname.travelengine.online')
        ->assertSee('Open a live example')
        ->assertSee('You keep 100% of the ticket. Guest pays a 5% online fee.')
        ->assertSee('Taken from your ticket')
        ->assertSee('You keep of the listed price')
        ->assertSee('Guest fee at checkout')
        ->assertSee('Starter plan, cancel anytime')
        ->assertDontSee('Processed for Operators')
        ->assertDontSee('Happy Travelers Served')
        ->assertDontSee('Average Setup Time')
        ->assertDontSee('Enterprise')
        ->assertDontSee('QR check-in');
});
