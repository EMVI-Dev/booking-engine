<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Plan;
use App\Models\User;
use App\Services\CustomDomainService;
use App\Services\DomainResolverService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function cloudDomainResponse(string $id, string $name, string $hostname = 'pending', string $ssl = 'pending', string $origin = 'pending'): array
{
    return [
        'data' => [
            'id' => $id,
            'type' => 'domains',
            'attributes' => [
                'name' => $name,
                'type' => 'root',
                'stage' => 'origin',
                'hostname_status' => $hostname,
                'ssl_status' => $ssl,
                'origin_status' => $origin,
                'action_required' => $origin === 'verified' ? null : 'add_dns_records',
                'dns_records' => [
                    'ssl' => [
                        ['type' => 'CNAME', 'name' => '_acme-challenge.'.$name, 'value' => $name.'.dcv.cloud.test'],
                    ],
                    'pre_verification' => '',
                    'origin' => '203.0.113.50',
                    'origin_cname' => 'travelengine.on-cloud.test',
                    'dcv' => '',
                ],
            ],
            'links' => ['self' => ['href' => 'https://cloud.laravel.com/api/domains/'.$id]],
        ],
    ];
}

beforeEach(function () {
    config([
        'domains.provider' => 'laravel_cloud',
        'services.laravel_cloud.token' => 'cloud-token',
        'services.laravel_cloud.environment' => 'env-123',
        'services.laravel_cloud.base_url' => 'https://cloud.laravel.com/api',
    ]);

    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::factory()->enterprise()->create()->id,
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
});

test('connecting a subdomain adds it to laravel cloud and stores the cname and certificate records', function () {
    Http::fake([
        'cloud.laravel.com/api/environments/env-123/domains' => Http::response(cloudDomainResponse('dom_1', 'tours.bali-sea.com')),
    ]);

    $domain = app(CustomDomainService::class)->connect($this->operator, 'https://Tours.Bali-Sea.com/');

    expect($domain->domain)->toBe('tours.bali-sea.com')
        ->and($domain->provider)->toBe('laravel_cloud')
        ->and($domain->provider_ref)->toBe('dom_1')
        ->and($domain->status)->toBe(DomainStatus::Pending)
        ->and($domain->requiredDnsRecords())->toBe([
            ['type' => 'CNAME', 'name' => 'tours', 'value' => 'travelengine.on-cloud.test', 'purpose' => 'origin'],
            ['type' => 'CNAME', 'name' => '_acme-challenge.tours.bali-sea.com', 'value' => 'tours.bali-sea.com.dcv.cloud.test', 'purpose' => 'ssl'],
        ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer cloud-token')
        && $request['name'] === 'tours.bali-sea.com'
        && $request['verification_method'] === 'real_time');
});

test('an apex address gets an A record to the cloud origin', function () {
    Http::fake([
        'cloud.laravel.com/api/environments/env-123/domains' => Http::response(cloudDomainResponse('dom_2', 'bali-sea.co.id')),
    ]);

    $domain = app(CustomDomainService::class)->connect($this->operator, 'bali-sea.co.id');

    expect($domain->requiredDnsRecords()[0])->toBe(['type' => 'A', 'name' => '@', 'value' => '203.0.113.50', 'purpose' => 'origin']);
});

test('a check marks the address live once cloud verifies hostname, certificate and origin', function () {
    Http::fake([
        'cloud.laravel.com/api/environments/env-123/domains' => Http::response(cloudDomainResponse('dom_1', 'tours.bali-sea.com')),
        'cloud.laravel.com/api/domains/dom_1/verify' => Http::sequence()
            ->push(cloudDomainResponse('dom_1', 'tours.bali-sea.com', 'verified', 'pending', 'verified'))
            ->push(cloudDomainResponse('dom_1', 'tours.bali-sea.com', 'verified', 'verified', 'verified')),
    ]);

    $service = app(CustomDomainService::class);
    $domain = $service->connect($this->operator, 'tours.bali-sea.com');

    $domain = $service->check($domain);
    expect($domain->status)->toBe(DomainStatus::Verifying)
        ->and($domain->isLive())->toBeFalse()
        ->and(app(DomainResolverService::class)->resolveOperator('tours.bali-sea.com')?->id)->toBe($this->operator->id);

    $this->artisan('domains:check')->expectsOutputToContain('1 now live')->assertSuccessful();

    $domain->refresh();
    expect($domain->isLive())->toBeTrue()
        ->and($domain->verified_at)->not->toBeNull()
        ->and($this->operator->fresh()->getStorefrontUrl())->toBe('https://tours.bali-sea.com');
});

test('changing or clearing the address removes the old one from laravel cloud', function () {
    Http::fake([
        'cloud.laravel.com/api/environments/env-123/domains' => Http::sequence()
            ->push(cloudDomainResponse('dom_1', 'tours.bali-sea.com'))
            ->push(cloudDomainResponse('dom_2', 'book.bali-sea.com')),
        'cloud.laravel.com/api/domains/*' => Http::response(null, 204),
    ]);

    $service = app(CustomDomainService::class);
    $service->connect($this->operator, 'tours.bali-sea.com');
    $service->connect($this->operator, 'book.bali-sea.com');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/domains/dom_1'));

    $service->disconnect($this->operator);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/domains/dom_2'));
    expect($this->operator->domains()->where('type', DomainType::Custom)->exists())->toBeFalse();
});

test('saving the same address again does not call laravel cloud twice', function () {
    Http::fake([
        'cloud.laravel.com/api/environments/env-123/domains' => Http::response(cloudDomainResponse('dom_1', 'tours.bali-sea.com')),
    ]);

    $service = app(CustomDomainService::class);
    $service->connect($this->operator, 'tours.bali-sea.com');
    $service->connect($this->operator, 'TOURS.bali-sea.com');

    Http::assertSentCount(1);
});

test('a refused address is not kept and the operator sees why', function () {
    Http::fake([
        'cloud.laravel.com/api/environments/env-123/domains' => Http::response(['message' => 'The name has already been taken.', 'errors' => ['name' => ['taken']]], 422),
    ]);

    expect(fn () => app(CustomDomainService::class)->connect($this->operator, 'tours.bali-sea.com'))
        ->toThrow(ValidationException::class);

    expect(OperatorDomain::query()->where('domain', 'tours.bali-sea.com')->exists())->toBeFalse();
});

test('platform addresses, other operators addresses and junk are refused before calling cloud', function () {
    Http::fake();
    OperatorDomain::factory()->custom()->create(['domain' => 'taken.example.com']);

    $service = app(CustomDomainService::class);
    $platform = app(DomainResolverService::class)->getPlatformDomain();

    foreach (['someone.'.$platform, $platform, 'taken.example.com', 'not a domain', '10.0.0.1'] as $input) {
        expect(fn () => $service->connect($this->operator, $input))->toThrow(ValidationException::class);
    }

    Http::assertNothingSent();
});

test('only plans with the custom domain feature can connect one', function () {
    Http::fake();
    $this->operator->update(['plan_id' => Plan::factory()->starter()->create()->id]);

    expect(fn () => app(CustomDomainService::class)->connect($this->operator->fresh(), 'tours.bali-sea.com'))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});

test('brand settings connect through the service and list the records to add', function () {
    Http::fake([
        'cloud.laravel.com/api/environments/env-123/domains' => Http::response(cloudDomainResponse('dom_1', 'tours.bali-sea.com')),
    ]);

    $this->actingAs($this->user);

    $component = Livewire::test('pages::settings.brand')
        ->set('custom_domain', 'tours.bali-sea.com')
        ->call('updateBrandSettings')
        ->assertHasNoErrors();

    expect($component->instance()->customDomainRecords()[0]['value'])->toBe('travelengine.on-cloud.test')
        ->and($this->operator->domains()->where('type', DomainType::Custom)->value('provider_ref'))->toBe('dom_1');
});

test('cloud:environments lists environment ids so the env can be filled in', function () {
    config(['services.laravel_cloud.environment' => null]);

    Http::fake([
        'cloud.laravel.com/api/applications*' => Http::response([
            'data' => [['id' => 'app_1', 'type' => 'applications', 'attributes' => ['name' => 'TravelEngine']]],
            'included' => [[
                'id' => 'env_prod',
                'type' => 'environments',
                'attributes' => ['name' => 'production', 'vanity_domain' => 'travelengine.laravel.cloud', 'status' => 'running'],
                'relationships' => ['application' => ['data' => ['type' => 'applications', 'id' => 'app_1']]],
            ]],
        ]),
    ]);

    $this->artisan('cloud:environments')
        ->expectsOutputToContain('env_prod')
        ->expectsOutputToContain('LARAVEL_CLOUD_ENVIRONMENT_ID is not set yet')
        ->assertSuccessful();
});

test('domain calls explain a missing environment id instead of failing silently', function () {
    config(['services.laravel_cloud.environment' => null]);
    Http::fake();

    expect(fn () => app(CustomDomainService::class)->connect($this->operator, 'tours.bali-sea.com'))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});
