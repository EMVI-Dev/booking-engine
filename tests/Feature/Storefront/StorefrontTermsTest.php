<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->operator = Operator::factory()->create([
        'name' => 'Island Cruises Co',
        'slug' => 'island-cruises',
        'status' => OperatorStatus::Approved,
        'contact_whatsapp' => '+628123456789',
        'terms_and_conditions' => 'Official cancellation policy: 24-hour notice required.',
    ]);
});

test('storefront terms and policies page is accessible on operator subdomain', function () {
    $this->get('http://island-cruises.booking.test/terms')
        ->assertOk()
        ->assertSee('Island Cruises Co')
        ->assertSee('Official cancellation policy: 24-hour notice required.')
        ->assertSee('Official Direct Booking Policies');
});
