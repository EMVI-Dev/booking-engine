<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAvailability>
 */
class ProductAvailabilityFactory extends Factory
{
    protected $model = ProductAvailability::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'date' => now()->addDays(2)->format('Y-m-d'),
            'capacity_booked' => 2,
        ];
    }
}
