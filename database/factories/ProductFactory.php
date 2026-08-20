<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $words = fake()->words(3, true);
        $name = is_array($words) ? implode(' ', $words) : $words;

        return [
            'agent_id' => Agent::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->paragraph(),
            'cover_photo' => null,
            'gallery' => [],
            'location' => 'Bali, Indonesia',
            'category' => 'Snorkeling',
            'capacity_per_day' => 10,
            'price' => 500000.00,
            'sellable_standalone' => true,
            'inclusions' => ['Snorkel gear', 'Life jacket', 'Guide'],
            'exclusions' => ['Hotel transfer', 'Lunch'],
            'terms_and_conditions' => 'Standard product terms.',
            'avg_rating' => 0.00,
            'free_cancellation_hours' => 24,
            'advance_booking_hours' => 12,
            'cancellation_terms' => 'Free cancellation up to 24h prior to tour.',
            'status' => ListingStatus::Published,
        ];
    }
}
