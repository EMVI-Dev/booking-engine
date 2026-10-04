<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'type' => Enquiry::TYPE_GENERAL,
            'name' => fake()->name(),
            'whatsapp' => '+62812'.fake()->numerify('#######'),
            'email' => fake()->optional()->safeEmail(),
            'message' => fake()->sentence(12),
        ];
    }

    public function privateGroup(): static
    {
        return $this->state(fn () => [
            'type' => Enquiry::TYPE_PRIVATE_GROUP,
            'preferred_date' => now()->addDays(20)->toDateString(),
            'group_size' => 12,
        ]);
    }
}
