<?php

namespace Database\Factories;

use App\Models\PlatformAnnouncement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAnnouncement>
 */
class PlatformAnnouncementFactory extends Factory
{
    protected $model = PlatformAnnouncement::class;

    public function definition(): array
    {
        return [
            'target_plan_id' => null,
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'type' => fake()->randomElement(['info', 'warning', 'critical', 'success']),
            'is_active' => true,
            'is_dismissible' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ];
    }
}
