<?php

use App\Contracts\CustomDomainProvider;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use App\Services\CustomDomains\LocalDomainProvider;
use App\Services\CustomDomainService;
use Livewire\Livewire;

beforeEach(function () {
    config(['domains.provider' => 'local']);

    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'name' => 'Padlock Tours',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::factory()->enterprise()->create()->id,
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($this->user);
});

test('locally a saved address lists its record and a check marks it live', function () {
    $component = Livewire::test('pages::settings.brand')
        ->set('custom_domain', 'tours.padlock.test')
        ->call('updateBrandSettings')
        ->assertHasNoErrors();

    $domain = $this->operator->domains()->where('type', DomainType::Custom)->firstOrFail();

    expect($domain->provider)->toBe('local')
        ->and($component->instance()->customDomainRecords()[0])->toMatchArray(['type' => 'CNAME', 'name' => 'tours']);

    $component->call('verifyCustomDomainDns')->assertHasNoErrors();

    expect($domain->fresh()->status)->toBe(DomainStatus::Active)
        ->and($domain->fresh()->isLive())->toBeTrue();
});

test('brand settings list the records to add instead of fixed server numbers', function () {
    app(CustomDomainService::class)->connect($this->operator, 'padlock.com');

    Livewire::test('pages::settings.brand')
        ->assertSee('ALIAS')
        ->assertSee('Add each setting above exactly as shown')
        ->assertDontSee('We’ll show this number once the live server is ready.');
});

test('the local provider refuses to run in production', function () {
    $this->app['env'] = 'production';

    expect(fn () => app(LocalDomainProvider::class)->check(
        $this->operator->domains()->create(['domain' => 'x.padlock.test', 'type' => DomainType::Custom, 'status' => DomainStatus::Pending])
    ))->toThrow(RuntimeException::class);

    $this->app['env'] = 'testing';
});

test('production defaults to the laravel cloud provider when none is set', function () {
    config(['domains.provider' => null]);
    $this->app['env'] = 'production';

    expect(app(CustomDomainProvider::class)->name())->toBe('laravel_cloud');

    $this->app['env'] = 'testing';
    expect(app(CustomDomainProvider::class)->name())->toBe('local');
});
