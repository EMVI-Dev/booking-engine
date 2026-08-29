<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\OperatorCouponRedemption;
use App\Models\PlatformCoupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorCouponRedemption>
 */
class OperatorCouponRedemptionFactory extends Factory
{
    protected $model = OperatorCouponRedemption::class;

    public function definition(): array
    {
        return [
            'platform_coupon_id' => PlatformCoupon::factory(),
            'operator_id' => Operator::factory(),
            'billing_cycle' => now()->format('Y-m'),
            'redeemed_at' => now(),
        ];
    }
}
