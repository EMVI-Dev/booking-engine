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
        ->assertSee('Pay links for chat')
        ->assertSee('Daily guest lists')
        ->assertSee('Payouts, 0% cut')
        ->assertSee('WhatsApp tickets, guest lists, calendar')
        ->assertSee('Your own website address (yourbrand.com)')
        ->assertSee('Tour website + 24/7 booking')
        ->assertSee('yourname.travelengine.online')
        ->assertSee('Pay links you can copy and share')
        ->assertDontSee('Pay links you send in WhatsApp')
        ->assertDontSee('WhatsApp pay links')
        ->assertDontSee('Send a pay link in WhatsApp')
        ->assertSee('Coupons for guests')
        ->assertSee('Ask for a review after the trip')
        ->assertSee('Guests book and pay themselves')
        ->assertSee('5 trips and activities')
        ->assertSee('Want to try a sample shop?')
        ->assertSee('See a sample shop')
        ->assertSee('Try the operator desk')
        ->assertSee('Operator log in')
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('http://demo.booking.test/login', false)
        ->assertDontSee('Open a live example')
        ->assertSee('You keep 100% of the listed price. A 5% platform fee is added at checkout.')
        ->assertSee('Nothing taken from your ticket')
        ->assertSee('You keep the listed price')
        ->assertSee('Platform fee at checkout')
        ->assertSee('Starter plan, cancel anytime')
        ->assertSee('What is the platform fee?')
        ->assertSee('Guests see it on the payment screen before they confirm')
        ->assertDontSee('Guest fee at checkout')
        ->assertDontSee('Guest pays a 5%')
        ->assertDontSee('Paid by Customer')
        ->assertDontSee('Online Guest Fee')
        ->assertDontSee('Online Customer Fee')
        ->assertDontSee('Pay into your own payment account')
        ->assertDontSee('WhatsApp links, tickets, guest lists')
        ->assertDontSee('5 Tours')
        ->assertDontSee('Cut taken from your ticket')
        ->assertDontSee('You keep of the listed price')
        ->assertDontSee('Processed for Operators')
        ->assertDontSee('Happy Travelers Served')
        ->assertDontSee('Average Setup Time')
        ->assertDontSee('Enterprise')
        ->assertDontSee('QR check-in')
        ->assertDontSee('No coding')
        ->assertDontSee('white-label')
        ->assertDontSee('🏝️')
        ->assertDontSee('🎉')
        ->assertDontSee('🎫')
        ->assertDontSee('📦');
});
