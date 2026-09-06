<?php

use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->starterPlan = Plan::factory()->create([
        'slug' => 'starter',
        'features' => [
            'basic_calendar' => true,
            'advanced_calendar' => false,
            'daily_manifest_export' => false,
            'capacity_heatmap' => false,
            'google_calendar' => false,
        ],
    ]);

    $this->growthPlan = Plan::factory()->create([
        'slug' => 'growth',
        'features' => [
            'basic_calendar' => true,
            'advanced_calendar' => true,
            'daily_manifest_export' => true,
            'capacity_heatmap' => false,
            'google_calendar' => true,
        ],
    ]);

    $this->enterprisePlan = Plan::factory()->create([
        'slug' => 'agency',
        'features' => [
            'basic_calendar' => true,
            'advanced_calendar' => true,
            'daily_manifest_export' => true,
            'capacity_heatmap' => true,
            'google_calendar' => true,
        ],
    ]);

    $this->freeUser = User::factory()->create();
    $this->freeOperator = Operator::factory()->create([
        'plan_id' => $this->starterPlan->id,
    ]);
    $this->freeUser->operators()->attach($this->freeOperator->id, ['role' => 'owner']);

    $this->proUser = User::factory()->create();
    $this->proOperator = Operator::factory()->create([
        'plan_id' => $this->growthPlan->id,
    ]);
    $this->proUser->operators()->attach($this->proOperator->id, ['role' => 'owner']);

    $this->ultimateUser = User::factory()->create();
    $this->ultimateOperator = Operator::factory()->create([
        'plan_id' => $this->enterprisePlan->id,
    ]);
    $this->ultimateUser->operators()->attach($this->ultimateOperator->id, ['role' => 'owner']);
});

test('free tier operator can view month grid calendar', function () {
    $this->actingAs($this->freeUser);

    Livewire::test('pages::calendar.index')
        ->assertOk()
        ->assertSee('Month Grid')
        ->assertSee('Resource Timeline')
        ->assertSee('Growth')
        ->assertSee('Agency');

    Livewire::test('calendar.month-grid')
        ->assertOk()
        ->assertSee('Today')
        ->assertSee('Block Dates');
});

test('free tier operator sees upgrade gate when switching to resource timeline', function () {
    $this->actingAs($this->freeUser);

    Livewire::test('pages::calendar.index')
        ->set('viewMode', 'timeline')
        ->assertOk()
        ->assertSee('Resource Timeline &amp; Capacity Matrix', false)
        ->assertSee('Requires Growth Plan');
});

test('pro tier operator unlocks resource timeline and daily manifest views, but heatmap is gated for agency', function () {
    $this->actingAs($this->proUser);

    $product = Product::factory()->create([
        'operator_id' => $this->proOperator->id,
        'name' => 'Fast Speedboat 1',
        'capacity_per_day' => 20,
    ]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->proOperator->id,
        'bookable_type' => 'product',
        'bookable_id' => $product->id,
        'guest_name' => 'Alice Wonderland',
        'requested_date' => now()->toDateString(),
        'pax_count' => 4,
        'status' => ReservationStatus::Confirmed,
    ]);

    // Parent timeline view
    Livewire::test('pages::calendar.index')
        ->set('viewMode', 'timeline')
        ->assertOk()
        ->assertSee('Fast Speedboat 1');

    // Independent subcomponent: Resource Timeline
    Livewire::test('calendar.resource-timeline')
        ->assertOk()
        ->assertSee('Resource &amp; Experience Timeline', false)
        ->assertSee('Fast Speedboat 1')
        ->assertSee('4 Pax');

    // Parent daily manifest view
    Livewire::test('pages::calendar.index')
        ->set('viewMode', 'manifest')
        ->assertOk()
        ->assertSee('Daily Passenger Run-Sheet')
        ->assertSee('Alice Wonderland')
        ->assertSee('Fast Speedboat 1');

    // Independent subcomponent: Daily Manifest
    Livewire::test('calendar.daily-manifest')
        ->assertOk()
        ->assertSee('Daily Passenger Run-Sheet')
        ->assertSee('Alice Wonderland')
        ->assertSee('Print Manifest');

    // Pro operator checking Heatmap should see Agency upgrade gate
    Livewire::test('pages::calendar.index')
        ->set('viewMode', 'heatmap')
        ->assertOk()
        ->assertSee('Capacity &amp; Occupancy Heatmap Analytics', false)
        ->assertSee('Requires Agency Plan');
});

test('agency tier operator unlocks capacity heatmap view', function () {
    $this->actingAs($this->ultimateUser);

    Reservation::factory()->create([
        'operator_id' => $this->ultimateOperator->id,
        'bookable_type' => 'package',
        'bookable_id' => Package::factory()->create(['operator_id' => $this->ultimateOperator->id])->id,
        'requested_date' => now()->toDateString(),
        'pax_count' => 6,
        'status' => ReservationStatus::Confirmed,
    ]);

    // Parent coordinator
    Livewire::test('pages::calendar.index')
        ->set('viewMode', 'heatmap')
        ->assertOk()
        ->assertSee('Occupancy Heatmap &amp; Capacity Density', false)
        ->assertSee('Total Month Passengers')
        ->assertSee('6 Pax');

    // Independent subcomponent: Capacity Heatmap
    Livewire::test('calendar.capacity-heatmap')
        ->assertOk()
        ->assertSee('Occupancy Heatmap &amp; Capacity Density', false)
        ->assertSee('Total Month Passengers')
        ->assertSee('6 Pax');
});
