<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'master-admin@emvi.dev',
    ]);
});

test('admin can view subscription plans management page', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.plans.index'));

    $response->assertOk()
        ->assertSee('Subscription Plans & Feature Limits')
        ->assertSee('Starter Essential')
        ->assertSee('Pro Operator')
        ->assertSee('Agency Ultimate');
});

test('admin can update plan pricing, commission rate, and feature flags', function () {
    $growthPlan = Plan::where('slug', 'growth')->first();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('editPlan', $growthPlan->id)
        ->set('price_monthly', 349000.00)
        ->set('commission_percentage', 6.5)
        ->set('features.custom_domain', true)
        ->call('savePlan')
        ->assertHasNoErrors();

    $growthPlan->refresh();
    expect((float) $growthPlan->price_monthly)->toBe(349000.00)
        ->and($growthPlan->commission_rate)->toBe(0.0650)
        ->and($growthPlan->hasFeature('custom_domain'))->toBeTrue();
});

test('admin can assign subscription plan to an operator from operators management', function () {
    $operator = Operator::factory()->create([
        'name' => 'Lombok Coral Explorer',
        'slug' => 'lombok-coral',
        'status' => OperatorStatus::Approved,
        'plan_id' => null,
    ]);

    $enterprisePlan = Plan::where('slug', 'enterprise')->first();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.operators.index')
        ->call('assignPlan', $operator->id, $enterprisePlan->id);

    $operator->refresh();
    expect($operator->plan_id)->toBe($enterprisePlan->id)
        ->and($operator->getEffectiveCommissionRate())->toBe(0.0000)
        ->and($operator->hasFeature('custom_domain'))->toBeTrue();
});
