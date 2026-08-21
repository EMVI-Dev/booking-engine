<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        $reservation = Reservation::factory()->completed()->create();

        return [
            'reservation_id' => $reservation->id,
            'bookable_type' => $reservation->bookable_type,
            'bookable_id' => $reservation->bookable_id,
            'operator_id' => $reservation->operator_id,
            'rating' => 5,
            'comment' => fake()->paragraph(),
        ];
    }
}
