<?php

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Services\StorefrontPagesService;

beforeEach(function () {
    $this->operator = Operator::factory()->create();
});

function trustFactsFor(Operator $operator): array
{
    return app(StorefrontPagesService::class)->trustFacts($operator->fresh());
}

test('free cancellation is the shortest window across bookable listings', function () {
    Package::factory()->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Published, 'free_cancellation_hours' => 48]);
    Product::factory()->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Published, 'sellable_standalone' => true, 'free_cancellation_hours' => 24]);

    // Not bookable on the shop, so they make no promise either way.
    Package::factory()->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Draft, 'free_cancellation_hours' => 0]);
    Product::factory()->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Published, 'sellable_standalone' => false, 'free_cancellation_hours' => 0]);

    expect(trustFactsFor($this->operator)['free_cancel_hours'])->toBe(24);
});

test('no free cancellation promise when one bookable trip has none', function () {
    Package::factory()->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Published, 'free_cancellation_hours' => 48]);
    Package::factory()->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Published, 'free_cancellation_hours' => 0]);

    expect(trustFactsFor($this->operator)['free_cancel_hours'])->toBeNull();
});

test('no free cancellation promise for an empty shop', function () {
    expect(trustFactsFor($this->operator)['free_cancel_hours'])->toBeNull();
});

test('hold minutes come from the platform setting', function () {
    expect(trustFactsFor($this->operator)['hold_minutes'])
        ->toBe(PlatformSetting::current()->getBookingHoldMinutes());
});
