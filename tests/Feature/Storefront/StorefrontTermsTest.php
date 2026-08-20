<?php

use App\Enums\AgentStatus;
use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->agent = Agent::factory()->create([
        'name' => 'Island Cruises Co',
        'slug' => 'island-cruises',
        'status' => AgentStatus::Approved,
        'contact_whatsapp' => '+628123456789',
        'terms_and_conditions' => 'Official cancellation policy: 24-hour notice required.',
    ]);
});

test('storefront terms and policies page is accessible on agent subdomain', function () {
    $this->get('http://island-cruises.booking.test/terms')
        ->assertOk()
        ->assertSee('Island Cruises Co')
        ->assertSee('Official cancellation policy: 24-hour notice required.')
        ->assertSee('Official Direct Booking Policies');
});
