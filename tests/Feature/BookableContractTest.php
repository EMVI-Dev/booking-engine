<?php

use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product implements bookable contract and returns self as required product', function () {
    $operator = Operator::factory()->create();

    $product = Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Snorkeling Adventure',
        'price' => 250000,
        'capacity_per_day' => 15,
        'free_cancellation_hours' => 24,
    ]);

    expect($product->getId())->toBe($product->id)
        ->and($product->getTitle())->toBe('Snorkeling Adventure')
        ->and($product->getPrice())->toBe(250000.0)
        ->and($product->getFreeCancellationHours())->toBe(24)
        ->and($product->isSellable())->toBeTrue();

    $required = $product->getRequiredProducts();
    expect($required)->toHaveCount(1)
        ->and($required->first()['product']->id)->toBe($product->id)
        ->and($required->first()['quantity'])->toBe(1);

    $snapshot = $product->generateTermsSnapshot();
    expect($snapshot)->toHaveKey('bookable_type', 'product')
        ->and($snapshot)->toHaveKey('bookable_id', $product->id)
        ->and($snapshot)->toHaveKey('price', 250000.0);
});

test('package links multiple products with quantity requirements', function () {
    $operator = Operator::factory()->create();

    $snorkel = Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Snorkel Gear',
        'capacity_per_day' => 20,
    ]);

    $boatSeat = Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Speedboat Seat',
        'capacity_per_day' => 10,
    ]);

    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Full Day Island Snorkel Tour',
        'price' => 850000,
        'free_cancellation_hours' => 48,
    ]);

    $package->products()->attach([
        $snorkel->id => ['quantity_required' => 1],
        $boatSeat->id => ['quantity_required' => 1],
    ]);

    $package->load('products');

    $required = $package->getRequiredProducts();
    expect($required)->toHaveCount(2);

    $productIds = $required->pluck('product.id')->all();
    expect($productIds)->toContain($snorkel->id)
        ->and($productIds)->toContain($boatSeat->id);

    $snapshot = $package->generateTermsSnapshot();
    expect($snapshot)->toHaveKey('bookable_type', 'package')
        ->and($snapshot)->toHaveKey('bookable_id', $package->id)
        ->and($snapshot)->toHaveKey('price', 850000.0);
});

test('cancellation policy can evaluate free cancellation cutoff correctly', function () {
    $product = Product::factory()->create([
        'free_cancellation_hours' => 24,
    ]);

    $targetTourDate = now()->addDays(3);
    // 2 days before tour is within the 24h cutoff
    expect($product->canCancelForFree($targetTourDate, now()))->toBeTrue();

    // 12 hours before tour is past the 24h cutoff
    $justBeforeTour = $targetTourDate->copy()->startOfDay()->subHours(12);
    expect($product->canCancelForFree($targetTourDate, $justBeforeTour))->toBeFalse();
});
