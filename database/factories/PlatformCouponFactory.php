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
}
