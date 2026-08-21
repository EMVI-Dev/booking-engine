<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $words = fake()->words(4, true);
        $title = (is_array($words) ? implode(' ', $words) : $words).' Experience';

        return [
            'operator_id' => Operator::factory(),
            'title' => ucwords($title),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->paragraph(),
            'itinerary_text' => "08:00 AM - Pickup\n10:00 AM - Activity 1\n01:00 PM - Lunch\n04:00 PM - Dropoff",
            'cover_photo' => null,
            'gallery' => [],
            'location' => 'Lombok, Indonesia',
            'category' => 'Island Hopping',
            'price' => 1250000.00,
            'inclusions' => ['Boat ride', 'Snorkel gear', 'Lunch buffet', 'Hotel pickup'],
            'exclusions' => ['Personal expenses', 'Tips'],
            'terms_and_conditions' => 'Standard package terms.',
            'avg_rating' => 0.00,
            'free_cancellation_hours' => 48,
            'advance_booking_hours' => 24,
            'cancellation_terms' => 'Free cancellation up to 48 hours in advance.',
            'status' => ListingStatus::Published,
        ];
    }
}
