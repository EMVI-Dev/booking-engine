<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Starter Essential', 'Pro Operator', 'Agency Ultimate']),
            'slug' => fake()->unique()->slug(),
            'tagline' => fake()->sentence(),
            'price_monthly' => fake()->randomElement([0, 299000, 699000]),
            'price_yearly' => fake()->randomElement([0, 2990000, 6990000]),
            'commission_rate' => 0.0000,
            'package_limit' => fake()->randomElement([5, 25, null]),
            'team_member_limit' => null,
            'features' => [
                'custom_subdomain' => true,
                'standard_checkout' => true,
                'reservations_management' => true,
                'whatsapp_chat_widget' => true,
                'google_calendar' => fake()->boolean(),
                'guest_crm' => fake()->boolean(),
                'whatsapp_dispatch' => fake()->boolean(),
                'custom_domain' => fake()->boolean(),
                'byo_gateway' => fake()->boolean(),
            ],
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Starter Essential',
            'slug' => 'starter',
            'price_monthly' => 0.00,
            'price_yearly' => 0.00,
            'commission_rate' => 0.0000,
            'package_limit' => 5,
            'team_member_limit' => null,
            'features' => [
                'custom_subdomain' => true,
                'standard_checkout' => true,
                'reservations_management' => true,
                'whatsapp_chat_widget' => true,
                'google_calendar' => false,
                'guest_crm' => false,
                'whatsapp_dispatch' => false,
                'custom_domain' => false,
                'byo_gateway' => false,
            ],
        ]);
    }

    public function growth(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pro Operator',
            'slug' => 'growth',
            'price_monthly' => 299000.00,
            'price_yearly' => 2990000.00,
            'commission_rate' => 0.0000,
            'package_limit' => 25,
            'team_member_limit' => null,
            'features' => [
                'custom_subdomain' => true,
                'standard_checkout' => true,
                'reservations_management' => true,
                'whatsapp_chat_widget' => true,
                'google_calendar' => true,
                'guest_crm' => true,
                'whatsapp_dispatch' => true,
                'custom_domain' => false,
                'byo_gateway' => false,
            ],
            'is_popular' => true,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Agency Ultimate',
            'slug' => 'enterprise',
            'price_monthly' => 699000.00,
            'price_yearly' => 6990000.00,
            'commission_rate' => 0.0000,
            'package_limit' => null,
            'team_member_limit' => null,
            'features' => [
                'custom_subdomain' => true,
                'standard_checkout' => true,
                'reservations_management' => true,
                'whatsapp_chat_widget' => true,
                'google_calendar' => true,
                'guest_crm' => true,
                'whatsapp_dispatch' => true,
                'custom_domain' => true,
                'byo_gateway' => true,
            ],
        ]);
    }
}
