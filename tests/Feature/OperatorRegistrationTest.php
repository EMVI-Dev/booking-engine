<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('new user registers as operator with storefront and subdomain automatically', function () {
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

    // 2. Operator created
    $operator = Operator::where('slug', 'baliocean')->first();
    expect($operator)->not->toBeNull()
        ->and($operator->name)->toBe('Bali Ocean Treks')
        ->and($operator->status)->toBe(OperatorStatus::Approved)
        ->and($operator->contact_whatsapp)->toBe('+628123456789')
        ->and($operator->bank_account_ref)->toBe('BCA-987654321');

    // 3. User linked as Owner
    expect($user->operators()->count())->toBe(1);
    $pivotRole = $user->operators()->first()->pivot->role;
    expect($pivotRole)->toBe(OperatorUserRole::Owner);

    // 4. Default subdomain created
    $domain = OperatorDomain::where('operator_id', $operator->id)->first();
    expect($domain)->not->toBeNull()
        ->and($domain->type)->toBe(DomainType::Subdomain)
        ->and($domain->status)->toBe(DomainStatus::Active)
        ->and($domain->is_primary)->toBeTrue();
});

test('operator registration requires terms acceptance', function () {
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

test('operator registration auto generates slug when not provided', function () {
    $this->post(route('register.store'), [
        'name' => 'Made Tours',
        'email' => 'made@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'agency_name' => 'Lombok Coral Dive',
        'terms' => '1',
    ]);

    $operator = Operator::where('name', 'Lombok Coral Dive')->first();
    expect($operator)->not->toBeNull()
        ->and($operator->slug)->toBe('lombok-coral-dive');
});
