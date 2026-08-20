<?php

use App\Enums\AgentStatus;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Agent;
use App\Models\AgentDomain;
use App\Services\DomainResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('domain resolver returns null for root platform domain', function () {
    $service = new DomainResolverService;

    expect($service->resolveAgent('localhost'))->toBeNull()
        ->and($service->resolveAgent('127.0.0.1'))->toBeNull()
        ->and($service->resolveAgent('booking.test'))->toBeNull();
});

test('domain resolver resolves approved agent by custom domain', function () {
    $agent = Agent::factory()->create([
        'status' => AgentStatus::Approved,
    ]);

    AgentDomain::factory()->create([
        'agent_id' => $agent->id,
        'domain' => 'balitours.com',
        'type' => DomainType::Custom,
        'status' => DomainStatus::Active,
    ]);

    $service = new DomainResolverService;
    $resolved = $service->resolveAgent('balitours.com');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($agent->id);
});

test('domain resolver resolves approved agent by subdomain', function () {
    $agent = Agent::factory()->create([
        'slug' => 'balitrek',
        'status' => AgentStatus::Approved,
    ]);

    $service = new DomainResolverService;
    $resolved = $service->resolveAgent('balitrek.booking.test');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($agent->id);
});

test('domain resolver ignores unapproved agent domains', function () {
    $agent = Agent::factory()->pending()->create();

    AgentDomain::factory()->create([
        'agent_id' => $agent->id,
        'domain' => 'pendingtours.com',
        'status' => DomainStatus::Active,
    ]);

    $service = new DomainResolverService;
    expect($service->resolveAgent('pendingtours.com'))->toBeNull();
});
