<?php

use App\Enums\DomainStatus;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Plan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['domains.caddy_ask_token' => 'test-caddy-ask-token']);

    $this->operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::factory()->enterprise()->create()->id,
    ]);

    $this->domain = OperatorDomain::factory()->custom()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'guide.test',
        'status' => DomainStatus::Active,
        'ssl_issued_at' => null,
    ]);
});

test('caddy may issue a padlock for a connected agency address', function () {
    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=guide.test')
        ->assertOk();
});

test('caddy is refused when the ask token is missing or wrong', function () {
    $this->get('/internal/caddy/ask?domain=guide.test')
        ->assertForbidden();

    $this->get('/internal/caddy/ask?token=wrong&domain=guide.test')
        ->assertForbidden();
});

test('caddy is refused when the ask token is not configured', function () {
    config(['domains.caddy_ask_token' => '']);

    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=guide.test')
        ->assertForbidden();
});

test('caddy is refused for a name that is still waiting', function () {
    $this->domain->update(['status' => DomainStatus::Pending]);

    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=guide.test')
        ->assertNotFound();
});

test('caddy is refused for a name on the free plan', function () {
    $this->operator->update([
        'plan_id' => Plan::factory()->starter()->create()->id,
    ]);

    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=guide.test')
        ->assertNotFound();
});

test('caddy is refused for a suspended operator', function () {
    $this->operator->update(['status' => OperatorStatus::Suspended]);

    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=guide.test')
        ->assertNotFound();
});

test('caddy is refused for an unknown host', function () {
    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=random.example')
        ->assertNotFound();
});

test('caddy may issue a padlock for an operator slug on the platform domain', function () {
    Operator::factory()->create([
        'slug' => 'bali-trek',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::factory()->starter()->create()->id,
    ]);

    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=bali-trek.travelengine.online')
        ->assertOk();

    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=bali-trek.booking.test')
        ->assertOk();
});

test('caddy may issue a padlock for the orange-clouded platform hosts', function () {
    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=travelengine.online')
        ->assertOk();

    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=www.travelengine.online')
        ->assertOk();
});

test('caddy is refused for unknown slugs', function () {
    $this->get('/internal/caddy/ask?token=test-caddy-ask-token&domain=no-such-operator.travelengine.online')
        ->assertNotFound();
});

test('the ssl probe stamps the padlock after https answers', function () {
    Http::fake([
        'https://guide.test/*' => Http::response('ok', 200),
    ]);

    $this->artisan('domains:probe-ssl')->assertSuccessful();

    expect($this->domain->fresh()->ssl_issued_at)->not->toBeNull();
});

test('the ssl probe leaves the padlock unset when https is not ready', function () {
    Http::fake(function () {
        throw new ConnectionException('certificate is not ready');
    });

    $this->artisan('domains:probe-ssl')->assertSuccessful();

    expect($this->domain->fresh()->ssl_issued_at)->toBeNull();
});
