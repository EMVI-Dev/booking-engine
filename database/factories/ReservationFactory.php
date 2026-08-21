<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $package = Package::factory()->create();

        return [
            'bookable_type' => 'package',
            'bookable_id' => $package->id,
            'operator_id' => $package->operator_id,
            'guest_name' => fake()->name(),
            'guest_contact' => fake()->phoneNumber(),
            'guest_email' => fake()->safeEmail(),
            'requested_date' => now()->addDays(5)->format('Y-m-d'),
            'pax_count' => 2,
            'notes' => 'Vegetarian lunch please.',
            'terms_snapshot' => $package->generateTermsSnapshot(),
            'status' => ReservationStatus::PaymentPending,
            'hold_expires_at' => now()->addMinutes(30),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Confirmed,
            'hold_expires_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Completed,
            'hold_expires_at' => null,
        ]);
    }
}
