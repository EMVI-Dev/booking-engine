<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        $name = fake()->company().' Tours';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'bio' => fake()->paragraph(),
            'photo' => null,
            'contact_whatsapp' => fake()->phoneNumber(),
            'booking_notification_email' => fake()->safeEmail(),
            'billing_email' => fake()->safeEmail(),
            'status' => AgentStatus::Approved,
            'terms_and_conditions' => 'Standard tour agent terms and conditions.',
            'bank_account_ref' => 'ID_BANK_'.fake()->numerify('##########'),
            'logo_path' => null,
            'favicon_path' => null,
            'banner_path' => null,
            'settings' => [
                'sellable_standalone_default' => true,
                'brand_color' => '#0f172a',
                'display_name' => $name,
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => AgentStatus::Pending,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => AgentStatus::Suspended,
        ]);
    }
}
