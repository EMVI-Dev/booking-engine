<?php

namespace Database\Factories;

use App\Models\PlatformCoupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformCoupon>
 */
class PlatformCouponFactory extends Factory
{
    protected $model = PlatformCoupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'scope' => 'subscription',
            'redemption_scope' => 'unlimited',
            'eligibility_rule' => null,
            'announcement_id' => null,
            'discount_type' => fake()->randomElement(['percentage', 'fixed']),
            'discount_value' => 10,
            'min_spend' => 0,
            'max_discount_amount' => null,
            'operator_id' => null,
            'max_uses' => null,
            'used_count' => 0,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function forGuest(): static
    {
        return $this->state(fn () => ['scope' => 'guest']);
    }

    public function forSubscription(): static
    {
        return $this->state(fn () => ['scope' => 'subscription']);
    }

    public function firstPurchaseOnly(): static
    {
        return $this->state(fn () => ['redemption_scope' => 'first_purchase_only']);
    }

    public function oncePeriod(): static
    {
        return $this->state(fn () => ['redemption_scope' => 'once_per_period']);
    }

    public function withEligibilityRule(string $type = 'min_monthly_transactions', int $threshold = 10): static
    {
        return $this->state(fn () => [
            'eligibility_rule' => ['type' => $type, 'threshold' => $threshold, 'lookback_months' => 1],
        ]);
    }
}
