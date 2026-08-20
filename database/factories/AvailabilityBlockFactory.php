<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AvailabilityBlock;
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
            'agent_id' => Agent::factory(),
            'product_id' => null,
            'date_start' => now()->addDays(10)->format('Y-m-d'),
            'date_end' => now()->addDays(12)->format('Y-m-d'),
            'reason' => 'Maintenance and boat servicing',
        ];
    }
}
