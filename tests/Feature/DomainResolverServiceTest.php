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
        ->and($service->resolveOperator('booking.test'))->toBeNull()
        ->and($service->resolveOperator('travelengine.online'))->toBeNull()
        ->and($service->resolveOperator('www.travelengine.online'))->toBeNull();
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
    $resolvedOnProductionHost = $service->resolveOperator('balitrek.travelengine.online');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($operator->id)
        ->and($resolvedOnProductionHost)->not->toBeNull()
        ->and($resolvedOnProductionHost->id)->toBe($operator->id);
});

test('domain resolver ignores inactive domain records', function () {
    $operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'pendingtours.com',
        'status' => DomainStatus::Pending,
    ]);

    $service = new DomainResolverService;
    expect($service->resolveOperator('pendingtours.com'))->toBeNull();
});

test('domain resolver resolves operator domain and clears cache on demand', function () {
    $operator = Operator::factory()->create([
        'slug' => 'cleartrek',
        'status' => OperatorStatus::Suspended,
    ]);

    $service = new DomainResolverService;
    $resolved = $service->resolveOperator('cleartrek.booking.test');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($operator->id)
        ->and($resolved->isSuspended())->toBeTrue();

    $service->clearOperatorDomainCache($operator);
    expect(true)->toBeTrue();
});

test('an apex address points here when its A record matches the platform number', function () {
    $service = new DomainResolverService;

    expect($service->recordsPointHere(
        cnameTargets: [],
        ipv4s: ['203.0.113.10'],
        ipv6s: [],
        targetHost: 'travelengine.online',
        platformIpv4: ['203.0.113.10'],
    ))->toBeTrue();
});

test('a flattened alias points here when only A records remain', function () {
    $service = new DomainResolverService;

    expect($service->recordsPointHere(
        cnameTargets: [],
        ipv4s: ['203.0.113.10', '203.0.113.11'],
        ipv6s: [],
        targetHost: 'travelengine.online',
        platformIpv4: ['203.0.113.10'],
    ))->toBeTrue();
});

test('an apex address points here when its AAAA record matches', function () {
    $service = new DomainResolverService;

    expect($service->recordsPointHere(
        cnameTargets: [],
        ipv4s: [],
        ipv6s: ['2001:db8::10'],
        targetHost: 'travelengine.online',
        platformIpv6: ['2001:db8::10'],
    ))->toBeTrue();
});

test('a subdomain still points here with a CNAME and no A record', function () {
    $service = new DomainResolverService;

    expect($service->recordsPointHere(
        cnameTargets: ['travelengine.online'],
        ipv4s: [],
        ipv6s: [],
        targetHost: 'travelengine.online',
        platformIpv4: ['203.0.113.10'],
    ))->toBeTrue();
});

test('a custom address does not point here when CNAME and numbers both miss', function () {
    $service = new DomainResolverService;

    expect($service->recordsPointHere(
        cnameTargets: ['somewhere-else.net'],
        ipv4s: ['198.51.100.4'],
        ipv6s: [],
        targetHost: 'travelengine.online',
        platformIpv4: ['203.0.113.10'],
    ))->toBeFalse();
});

test('configured platform ipv4 is listed for apex instructions', function () {
    config(['domains.public_ipv4' => '203.0.113.10, 198.51.100.20']);

    $service = new DomainResolverService;

    expect($service->expectedPlatformIpv4())->toContain('203.0.113.10')
        ->and($service->expectedPlatformIpv4())->toContain('198.51.100.20');
});

test('apex instructions print only the first configured number', function () {
    config(['domains.public_ipv4' => '203.0.113.10, 198.51.100.20']);

    expect((new DomainResolverService)->instructionIpv4())->toBe(['203.0.113.10']);
});

test('loopback is not printed as an apex target', function () {
    config(['domains.public_ipv4' => '127.0.0.1']);

    expect((new DomainResolverService)->instructionIpv4())->toBe([]);
});
