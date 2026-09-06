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
            'name' => fake()->randomElement(['Starter', 'Growth', 'Agency']),
            'slug' => fake()->unique()->slug(),
            'tagline' => fake()->sentence(),
            'price_monthly' => fake()->randomElement([0, 299000, 799000, 1999000]),
            'price_yearly' => fake()->randomElement([0, 2990000, 7990000, 19990000]),
            'commission_rate' => 0.0000,
            'package_limit' => fake()->randomElement([5, 25, null]),
            'team_member_limit' => null,
            'features' => Plan::featureFlags([
                'advanced_calendar' => fake()->boolean(),
                'daily_manifest_export' => fake()->boolean(),
                'google_calendar' => fake()->boolean(),
                'guest_crm' => fake()->boolean(),
                'whatsapp_dispatch' => fake()->boolean(),
                'tracking_pixels' => fake()->boolean(),
                'automated_review_requests' => fake()->boolean(),
                'custom_domain' => fake()->boolean(),
            ]),
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Starter',
            'slug' => 'starter',
            'price_monthly' => 0.00,
            'price_yearly' => 0.00,
            'commission_rate' => 0.0000,
            'package_limit' => 5,
            'team_member_limit' => 2,
            'features' => Plan::featureFlags(),
        ]);
    }

    public function growth(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Growth',
            'slug' => 'growth',
            'price_monthly' => 299000.00,
            'price_yearly' => 2990000.00,
            'commission_rate' => 0.0000,
            'package_limit' => 25,
            'team_member_limit' => null,
            'features' => Plan::featureFlags([
                'advanced_calendar' => true,
                'daily_manifest_export' => true,
                'google_calendar' => true,
                'guest_crm' => true,
                'whatsapp_dispatch' => true,
                'tracking_pixels' => true,
                'automated_review_requests' => true,
            ]),
            'is_popular' => true,
        ]);
    }

    public function agency(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Agency',
            'slug' => 'agency',
            'price_monthly' => 799000.00,
            'price_yearly' => 7990000.00,
            'commission_rate' => 0.0000,
            'package_limit' => null,
            'team_member_limit' => null,
            'features' => Plan::featureFlags([
                'advanced_calendar' => true,
                'daily_manifest_export' => true,
                'capacity_heatmap' => true,
                'google_calendar' => true,
                'guest_crm' => true,
                'whatsapp_dispatch' => true,
                'tracking_pixels' => true,
                'automated_review_requests' => true,
                'custom_domain' => true,
                'priority_support' => true,
                'ai_discovery' => true,
                'remove_branding' => true,
            ]),
        ]);
    }

    /**
     * Top-tier fixture used by tests that need Agency capabilities.
     */
    public function enterprise(): static
    {
        return $this->agency()->state(fn (): array => [
            'slug' => fake()->unique()->slug(),
        ]);
    }
}
