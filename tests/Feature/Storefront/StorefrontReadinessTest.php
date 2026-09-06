<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('an approved operator with unfinished setup cannot open the public booking page', function () {
    $operator = Operator::factory()->incompleteSetup()->create([
        'name' => 'Half Built Treks',
        'slug' => 'half-built',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'half-built.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Hidden Sunrise Trek',
        'status' => ListingStatus::Published,
    ]);

    $this->get('http://half-built.booking.test/', ['Host' => 'half-built.booking.test'])
        ->assertStatus(503)
        ->assertViewIs('storefront.pending')
        ->assertSee('This page is not open yet')
        ->assertSee('still finishing their details')
        ->assertDontSee('Hidden Sunrise Trek');

    $this->get('http://half-built.booking.test/tours', ['Host' => 'half-built.booking.test'])
        ->assertStatus(503)
        ->assertDontSee('Hidden Sunrise Trek');
});

test('the public booking page opens after brand, terms, bank, and emails are filled', function () {
    $operator = Operator::factory()->incompleteSetup()->create([
        'name' => 'Ready Reef Tours',
        'slug' => 'ready-reef',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'ready-reef.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->get('http://ready-reef.booking.test/', ['Host' => 'ready-reef.booking.test'])
        ->assertStatus(503);

    $operator->update([
        'bio' => 'Day trips around the reef.',
        'contact_whatsapp' => '+628111222333',
        'terms_and_conditions' => 'Free cancel until the day before.',
        'bank_provider' => 'BCA',
        'bank_account_name' => 'Ready Reef Tours',
        'bank_account_number' => '1234567890',
    ]);

    expect($operator->fresh()->isStorefrontPublic())->toBeTrue();

    $this->get('http://ready-reef.booking.test/', ['Host' => 'ready-reef.booking.test'])
        ->assertOk()
        ->assertViewIs('storefront.index')
        ->assertSee('Ready Reef Tours');
});

test('the operator dashboard tells them the page is closed until setup is finished', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->incompleteSetup()->create([
        'name' => 'Closed Page Tours',
    ]);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Page is closed')
        ->assertSee('Closed to guests until setup is finished.')
        ->assertSee('Bio & WhatsApp')
        ->assertSee('Payout bank account')
        ->assertSee('Billing email');
});
