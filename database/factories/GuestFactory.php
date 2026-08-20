<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'notes' => fake()->boolean(40) ? fake()->sentence() : null,
            'tags' => fake()->boolean(50) ? fake()->randomElements(['VIP', 'Repeat', 'Corporate', 'Vegetarian', 'Certified Diver'], rand(1, 2)) : null,
            'metadata' => null,
        ];
    }
}
