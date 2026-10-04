<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\OperatorGalleryPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorGalleryPhoto>
 */
class OperatorGalleryPhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'path' => fn (array $attributes) => 'operators/'.$attributes['operator_id'].'/gallery/'.fake()->uuid().'.webp',
            'caption' => fake()->optional()->sentence(4),
            'sort_order' => 0,
        ];
    }
}
