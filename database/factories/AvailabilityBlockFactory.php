<?php

namespace Database\Factories;

use App\Models\AvailabilityBlock;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityBlock>
 */
class AvailabilityBlockFactory extends Factory
{
    protected $model = AvailabilityBlock::class;

    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'product_id' => null,
            'date_start' => now()->addDays(10)->format('Y-m-d'),
            'date_end' => now()->addDays(12)->format('Y-m-d'),
            'reason' => 'Maintenance and boat servicing',
        ];
    }
}
