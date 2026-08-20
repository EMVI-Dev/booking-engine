<?php

namespace App\Models\Traits;

use App\Models\Review;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasRating
{
    /**
     * Get all reviews for this bookable model.
     *
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'bookable');
    }

    /**
     * Recalculate and update the cached average rating.
     */
    public function recalculateAvgRating(): void
    {
        $avg = $this->reviews()->avg('rating') ?? 0.00;

        $this->update([
            'avg_rating' => round((float) $avg, 2),
        ]);
    }
}
