<?php

use App\Enums\DomainStatus;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Plan;
use App\Models\User;
use App\Services\DomainResolverService;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'name' => 'Padlock Tours',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::factory()->enterprise()->create()->id,
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($this->user);
});

test('a successful domain check connects the address without marking the padlock as already on', function () {
    $domain = OperatorDomain::factory()->custom()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'tours.padlock.test',
        'status' => DomainStatus::Pending,
        'ssl_issued_at' => now(),
    ]);

    $this->partialMock(DomainResolverService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('customDomainPointsHere')
            ->once()
            ->andReturn(true);
    });

    Livewire::test('pages::settings.brand')
        ->set('custom_domain', 'tours.padlock.test')
        ->call('verifyCustomDomainDns')
        ->assertHasNoErrors();

    $domain->refresh();

    expect($domain->status)->toBe(DomainStatus::Active)
        ->and($domain->verified_at)->not->toBeNull()
        ->and($domain->ssl_issued_at)->toBeNull();
});

test('an apex website address becomes active after a successful check', function () {
    $domain = OperatorDomain::factory()->custom()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'padlock.com',
        'status' => DomainStatus::Pending,
        'ssl_issued_at' => null,
    ]);

    $this->partialMock(DomainResolverService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('customDomainPointsHere')
            ->once()
            ->andReturn(true);
    });

    Livewire::test('pages::settings.brand')
        ->set('custom_domain', 'padlock.com')
        ->call('verifyCustomDomainDns')
        ->assertHasNoErrors();

    $domain->refresh();

    expect($domain->status)->toBe(DomainStatus::Active)
        ->and($domain->verified_at)->not->toBeNull()
        ->and($domain->ssl_issued_at)->toBeNull();
});

test('brand settings show an A record for the root name', function () {
    config(['domains.public_ipv4' => '203.0.113.10, 198.51.100.20']);

    $html = Livewire::test('pages::settings.brand')
        ->assertSee('203.0.113.10')
        ->assertDontSee('198.51.100.20')
        ->assertSee('If this is your only website', false)
        ->assertSee('yourname.com')
        ->html();

    expect(substr_count($html, '>A</span>'))->toBe(1);
});
