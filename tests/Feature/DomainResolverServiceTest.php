<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Services\DomainResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('domain resolver returns null for root platform domain', function () {
    $service = new DomainResolverService;

    expect($service->resolveOperator('localhost'))->toBeNull()
        ->and($service->resolveOperator('127.0.0.1'))->toBeNull()
        ->and($service->resolveOperator('booking.test'))->toBeNull();
});

test('domain resolver resolves approved operator by custom domain', function () {
    $operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'balitours.com',
        'type' => DomainType::Custom,
        'status' => DomainStatus::Active,
    ]);

    $service = new DomainResolverService;
    $resolved = $service->resolveOperator('balitours.com');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($operator->id);
});

test('domain resolver resolves approved operator by subdomain', function () {
    $operator = Operator::factory()->create([
        'slug' => 'balitrek',
        'status' => OperatorStatus::Approved,
    ]);

    $service = new DomainResolverService;
    $resolved = $service->resolveOperator('balitrek.booking.test');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($operator->id);
});

test('domain resolver ignores unapproved operator domains', function () {
    $operator = Operator::factory()->pending()->create();

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'pendingtours.com',
        'status' => DomainStatus::Active,
    ]);

    $service = new DomainResolverService;
    expect($service->resolveOperator('pendingtours.com'))->toBeNull();
});
