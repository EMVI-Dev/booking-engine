<?php

use App\Enums\DomainStatus;
use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\LapsedSubscriptionService;

beforeEach(function () {
    $this->starter = Plan::factory()->starter()->create();
    $this->growth = Plan::factory()->growth()->create();
});

test('an unpaid paid plan falls back to the free plan after three extra days', function () {
    $operator = Operator::factory()->create([
        'plan_id' => $this->growth->id,
        'plan_expires_at' => now()->subDays(4),
    ]);

    Package::factory()->count(6)->create([
        'operator_id' => $operator->id,
        'status' => ListingStatus::Published,
    ]);

    $domain = OperatorDomain::factory()->custom()->create([
        'operator_id' => $operator->id,
        'domain' => 'tours.plainlanguage.test',
        'status' => DomainStatus::Active,
        'ssl_issued_at' => now(),
    ]);

    $moved = app(LapsedSubscriptionService::class)->revertToFreePlan($operator);

    expect($moved)->toBeTrue();

    $operator->refresh();
    $domain->refresh();

    expect($operator->plan_id)->toBe($this->starter->id)
        ->and($operator->packages()->where('status', ListingStatus::Published)->count())->toBe(5)
        ->and($operator->packages()->where('status', ListingStatus::Draft)->count())->toBe(1)
        ->and($domain->status)->toBe(DomainStatus::Pending)
        ->and($domain->ssl_issued_at)->toBeNull();
});

test('falling back to free also drafts extra activities so listings stay at five', function () {
    $operator = Operator::factory()->create([
        'plan_id' => $this->growth->id,
        'plan_expires_at' => now()->subDays(4),
    ]);

    Package::factory()->count(3)->create([
        'operator_id' => $operator->id,
        'status' => ListingStatus::Published,
    ]);
    Product::factory()->count(4)->create([
        'operator_id' => $operator->id,
        'status' => ListingStatus::Published,
    ]);

    expect(app(LapsedSubscriptionService::class)->revertToFreePlan($operator))->toBeTrue();

    $operator->refresh();

    $published = $operator->packages()->where('status', ListingStatus::Published)->count()
        + $operator->products()->where('status', ListingStatus::Published)->count();

    expect($operator->plan_id)->toBe($this->starter->id)
        ->and($published)->toBe(5);
});

test('a plan that expired less than three days ago is left alone', function () {
    $operator = Operator::factory()->create([
        'plan_id' => $this->growth->id,
        'plan_expires_at' => now()->subDays(2),
    ]);

    expect(app(LapsedSubscriptionService::class)->revertToFreePlan($operator))->toBeFalse();

    expect($operator->fresh()->plan_id)->toBe($this->growth->id);
});

test('the daily command moves operators whose plan lapsed more than three days ago', function () {
    $operator = Operator::factory()->create([
        'name' => 'Late Invoice Tours',
        'plan_id' => $this->growth->id,
        'plan_expires_at' => now()->subDays(5),
    ]);

    $this->artisan('subscriptions:return-unpaid-to-free')
        ->assertSuccessful()
        ->expectsOutputToContain('Late Invoice Tours');

    expect($operator->fresh()->plan_id)->toBe($this->starter->id);
});

test('operators in the three-day grace window see a billing warning', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create([
        'plan_id' => $this->growth->id,
        'plan_expires_at' => now()->subDay(),
    ]);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your paid plan ran out')
        ->assertSee('Open billing');
});
