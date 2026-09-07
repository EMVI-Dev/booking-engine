<?php

namespace App\Models;

use App\Contracts\Bookable;
use App\Enums\ListingStatus;
use App\Models\Traits\HasCancellationPolicy;
use App\Models\Traits\HasRating;
use App\Services\MediaStore;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $operator_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $itinerary_text
 * @property string|null $cover_photo
 * @property array<string>|null $gallery
 * @property string|null $location
 * @property string|null $category
 * @property string $price
 * @property array<string>|null $inclusions
 * @property array<string>|null $exclusions
 * @property string|null $terms_and_conditions
 * @property string $avg_rating
 * @property int $free_cancellation_hours
 * @property int $advance_booking_hours
 * @property string|null $cancellation_terms
 * @property ListingStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator $operator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 */
class Package extends Model implements Bookable
{
    use HasCancellationPolicy;

    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    use HasRating, HasUlids;

    protected $fillable = [
        'operator_id',
        'title',
        'slug',
        'description',
        'itinerary_text',
        'cover_photo',
        'gallery',
        'location',
        'category',
        'price',
        'inclusions',
        'exclusions',
        'terms_and_conditions',
        'avg_rating',
        'free_cancellation_hours',
        'advance_booking_hours',
        'cancellation_terms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'inclusions' => 'array',
            'exclusions' => 'array',
            'price' => 'decimal:2',
            'avg_rating' => 'decimal:2',
            'free_cancellation_hours' => 'integer',
            'advance_booking_hours' => 'integer',
            'status' => ListingStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @deprecated Use operator() instead.
     *
     * @return BelongsTo<Operator, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->operator();
    }

    /**
     * @return BelongsToMany<Product, $this, PackageProduct>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'package_products')
            ->using(PackageProduct::class)
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    /**
     * @return MorphMany<Reservation, $this>
     */
    public function reservations(): MorphMany
    {
        return $this->morphMany(Reservation::class, 'bookable');
    }

    // --- Bookable Contract Implementation ---

    public function getId(): string
    {
        return (string) $this->id;
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return app(MediaStore::class)->url($this->cover_photo);
    }

    /**
     * @return array<string>
     */
    public function getGalleryUrlsAttribute(): array
    {
        if (! is_array($this->gallery)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $path): ?string => app(MediaStore::class)->url($path),
            $this->gallery,
        )));
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getOperator(): Operator
    {
        return $this->operator;
    }

    public function getOperatorId(): string
    {
        return (string) $this->operator_id;
    }

    /**
     * @deprecated Use getOperator() instead.
     */
    public function getAgent(): Operator
    {
        return $this->getOperator();
    }

    /**
     * @deprecated Use getOperatorId() instead.
     */
    public function getAgentId(): string
    {
        return $this->getOperatorId();
    }

    public function getPrice(): float
    {
        return (float) $this->price;
    }

    public function getFreeCancellationHours(): int
    {
        return (int) $this->free_cancellation_hours;
    }

    public function getAdvanceBookingHours(): int
    {
        return (int) $this->advance_booking_hours;
    }

    /**
     * @return array<int, string>
     */
    public function getInclusions(): array
    {
        return (array) ($this->inclusions ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function getExclusions(): array
    {
        return (array) ($this->exclusions ?? []);
    }

    public function getTermsAndConditions(): ?string
    {
        return $this->terms_and_conditions;
    }

    /**
     * Return all linked products and their quantity_required.
     *
     * @return Collection<int, array{product: Product, quantity: int}>
     */
    public function getRequiredProducts(): Collection
    {
        /** @var Collection<int, array{product: Product, quantity: int}> $result */
        $result = $this->products->map(function (Product $product): array {
            /** @var PackageProduct|null $pivot */
            $pivot = $product->pivot;

            return [
                'product' => $product,
                'quantity' => (int) ($pivot->quantity_required ?? 1),
            ];
        });

        return $result;
    }

    /**
     * @return HasMany<AvailabilityBlock, $this>
     */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    /**
     * Check if this Package is blacked out on a specific date.
     * (Evaluates operator-wide blocks, package-specific blocks, and underlying product blocks)
     */
    public function isBlackedOutOn(Carbon|string $date): bool
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        // 1. Operator-wide or package-specific blackout
        $directBlock = AvailabilityBlock::where('operator_id', $this->operator_id)
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('product_id')->whereNull('package_id');
                })->orWhere('package_id', $this->id);
            })
            ->whereDate('date_start', '<=', $dateStr)
            ->whereDate('date_end', '>=', $dateStr)
            ->exists();

        if ($directBlock) {
            return true;
        }

        // 2. Underlying product blackout
        $productIds = $this->products->pluck('id')->filter()->toArray();
        if (! empty($productIds)) {
            $productBlock = AvailabilityBlock::where('operator_id', $this->operator_id)
                ->whereIn('product_id', $productIds)
                ->whereDate('date_start', '<=', $dateStr)
                ->whereDate('date_end', '>=', $dateStr)
                ->exists();

            if ($productBlock) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get array of blacked out date strings for a given date range.
     *
     * @return array<string, string> Key is Y-m-d, value is blackout reason
     */
    public function getBlackoutDates(?Carbon $start = null, ?Carbon $end = null): array
    {
        $startDate = $start ?? now()->startOfDay();
        $endDate = $end ?? now()->addYear()->endOfDay();

        $productIds = $this->products()->pluck('products.id')->toArray();

        $blocks = AvailabilityBlock::where('operator_id', $this->operator_id)
            ->where(function ($q) use ($productIds) {
                $q->where(function ($sub) {
                    $sub->whereNull('product_id')->whereNull('package_id');
                })->orWhere('package_id', $this->id);

                if (! empty($productIds)) {
                    $q->orWhereIn('product_id', $productIds);
                }
            })
            ->where('date_start', '<=', $endDate->toDateString())
            ->where('date_end', '>=', $startDate->toDateString())
            ->get();

        $dates = [];
        foreach ($blocks as $b) {
            $cur = Carbon::parse($b->date_start)->max($startDate);
            $last = Carbon::parse($b->date_end)->min($endDate);

            while ($cur->lte($last)) {
                $dates[$cur->toDateString()] = $b->reason ?: __('Blackout Date');
                $cur->addDay();
            }
        }

        return $dates;
    }

    public function isSellable(): bool
    {
        return true;
    }

    public function isPublished(): bool
    {
        return $this->status === ListingStatus::Published;
    }

    public function getCachedAvgRating(): float
    {
        return (float) $this->avg_rating;
    }

    public function generateTermsSnapshot(): array
    {
        return [
            'bookable_type' => 'package',
            'bookable_id' => $this->getId(),
            'title' => $this->getTitle(),
            'price' => $this->getPrice(),
            'free_cancellation_hours' => $this->getFreeCancellationHours(),
            'advance_booking_hours' => $this->getAdvanceBookingHours(),
            'cancellation_terms' => $this->cancellation_terms,
            'inclusions' => $this->getInclusions(),
            'exclusions' => $this->getExclusions(),
            'terms_and_conditions' => $this->getTermsAndConditions(),
            'frozen_at' => now()->toIso8601String(),
        ];
    }
}
