<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorUser;
use App\Models\Package;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->user = User::factory()->create();

    $this->starterPlan = Plan::where('slug', 'starter')->first();
    $this->growthPlan = Plan::where('slug', 'growth')->first();
    $this->enterprisePlan = Plan::where('slug', 'enterprise')->first();

    $this->operator = Operator::factory()->create([
        'name' => 'Nusa Cruising Co',
        'slug' => 'nusa-cruising',
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->starterPlan->id,
        'terms_and_conditions' => 'Standard tour terms apply.',
        'bank_provider' => 'BCA',
        'bank_account_number' => '1234567890',
        'contact_whatsapp' => '081234567890',
    ]);

    OperatorUser::create([
        'operator_id' => $this->operator->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);
});

test('starter plan operator sees feature gate on guest directory crm', function () {
    $response = $this->actingAs($this->user)->get(route('guests.index'));

    $response->assertOk()
        ->assertSee('Requires Pro Operator Plan')
        ->assertSee('Guest Directory CRM &amp; Lifetime Tracking', false);
});

test('growth plan operator has full access to guest directory crm', function () {
    $this->operator->update(['plan_id' => $this->growthPlan->id]);

    $response = $this->actingAs($this->user)->get(route('guests.index'));

    $response->assertOk()
        ->assertDontSee('Requires Pro Operator Plan')
        ->assertSee('Guest Directory &amp; CRM', false);
});

test('starter plan operator cannot exceed package limit of 5', function () {
    // Create 5 packages
    Package::factory()->count(5)->create([
        'operator_id' => $this->operator->id,
        'title' => 'Tour Package',
        'status' => 'published',
    ]);

    expect($this->operator->canAddPackage())->toBeFalse();

    // Starter operator sees feature gate on package create
    $response = $this->actingAs($this->user)->get(route('packages.create'));
    $response->assertOk()
        ->assertSee('Package Limit Reached (5 Listings)')
        ->assertSee('Requires Pro Operator Plan');
});

test('growth plan operator can have up to 25 packages', function () {
    $this->operator->update(['plan_id' => $this->growthPlan->id]);

    Package::factory()->count(5)->create([
        'operator_id' => $this->operator->id,
        'title' => 'Tour Package',
        'status' => 'published',
    ]);

    expect($this->operator->canAddPackage())->toBeTrue();
});

test('enterprise plan operator has unlimited packages', function () {
    $this->operator->update(['plan_id' => $this->enterprisePlan->id]);

    Package::factory()->count(30)->create([
        'operator_id' => $this->operator->id,
        'title' => 'Tour Package',
        'status' => 'published',
    ]);

    expect($this->operator->canAddPackage())->toBeTrue();
});

test('starter plan gates tracking pixels and automated review requests while growth unlocks them', function () {
    expect($this->starterPlan->hasFeature('quick_booking_links'))->toBeTrue()
        ->and($this->starterPlan->hasFeature('tracking_pixels'))->toBeFalse()
        ->and($this->starterPlan->hasFeature('automated_review_requests'))->toBeFalse()
        ->and($this->growthPlan->hasFeature('tracking_pixels'))->toBeTrue()
        ->and($this->growthPlan->hasFeature('automated_review_requests'))->toBeTrue()
        ->and($this->enterprisePlan->hasFeature('tracking_pixels'))->toBeTrue()
        ->and($this->enterprisePlan->hasFeature('automated_review_requests'))->toBeTrue();

    // Starter operator sees upgrade banner on brand settings
    $response = $this->actingAs($this->user)->get(route('brand.edit'));
    $response->assertOk()
        ->assertSee('Requires Pro Operator or Agency Ultimate Tier');

    // Upgrade to growth
    $this->operator->update(['plan_id' => $this->growthPlan->id]);
    $response = $this->actingAs($this->user)->get(route('brand.edit'));
    $response->assertOk()
        ->assertDontSee('Requires Pro Operator or Agency Ultimate Tier')
        ->assertSee('Active &amp; Unlocked', false);
});
