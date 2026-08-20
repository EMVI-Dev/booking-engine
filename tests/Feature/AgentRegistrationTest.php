<?php

use App\Enums\AgentStatus;
use App\Enums\AgentUserRole;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Agent;
use App\Models\AgentDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('new user registers as agent with storefront and subdomain automatically', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Wayan Sudarma',
        'email' => 'wayan@balitours.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'agency_name' => 'Bali Ocean Treks',
        'slug' => 'baliocean',
        'contact_whatsapp' => '+628123456789',
        'bio' => 'Experienced Bali tour guide offering snorkeling and volcano treks.',
        'bank_account_ref' => 'BCA-987654321',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('welcome_onboarding', true);

    $this->assertAuthenticated();

    // 1. User created
    $user = User::where('email', 'wayan@balitours.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Wayan Sudarma');

    // 2. Agent created
    $agent = Agent::where('slug', 'baliocean')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->name)->toBe('Bali Ocean Treks')
        ->and($agent->status)->toBe(AgentStatus::Approved)
        ->and($agent->contact_whatsapp)->toBe('+628123456789')
        ->and($agent->bank_account_ref)->toBe('BCA-987654321');

    // 3. User linked as Owner
    expect($user->agents()->count())->toBe(1);
    $pivotRole = $user->agents()->first()->pivot->role;
    expect($pivotRole)->toBe(AgentUserRole::Owner);

    // 4. Default subdomain created
    $domain = AgentDomain::where('agent_id', $agent->id)->first();
    expect($domain)->not->toBeNull()
        ->and($domain->type)->toBe(DomainType::Subdomain)
        ->and($domain->status)->toBe(DomainStatus::Active)
        ->and($domain->is_primary)->toBeTrue();
});

test('agent registration requires terms acceptance', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Wayan Sudarma',
        'email' => 'wayan@balitours.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'agency_name' => 'Bali Ocean Treks',
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});

test('agent registration auto generates slug when not provided', function () {
    $this->post(route('register.store'), [
        'name' => 'Made Tours',
        'email' => 'made@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'agency_name' => 'Lombok Coral Dive',
        'terms' => '1',
    ]);

    $agent = Agent::where('name', 'Lombok Coral Dive')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->slug)->toBe('lombok-coral-dive');
});
