<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'amount' => 2500000.00,
            'gateway' => 'doku',
            'gateway_ref' => 'DOKU_'.fake()->uuid(),
            'split_details' => [
                'agent_amount' => 2250000.00,
                'platform_commission' => 250000.00,
                'commission_rate' => 0.10,
            ],
            'status' => PaymentStatus::Pending,
            'refund_status' => null,
            'refunded_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
        ]);
    }
}
