<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'name' => fake()->company().' Adventures',
            'contact_person' => fake()->name(),
            'reservation_email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'payout_details' => [
                'bank_name' => 'BCA',
                'account_number' => fake()->numerify('##########'),
                'account_holder' => fake()->name(),
            ],
            'metadata' => null,
            'is_active' => true,
        ];
    }
}
