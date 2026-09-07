<?php

namespace App\Models;

use App\Contracts\Bookable;
use App\Enums\ListingStatus;
use App\Models\Traits\HasCancellationPolicy;
use App\Models\Traits\HasRating;
use App\Services\MediaStore;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $operator_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $cover_photo
 * @property array<string>|null $gallery
 * @property string|null $location
 * @property string|null $category
 * @property int $capacity_per_day
 * @property string|null $price
 * @property bool $sellable_standalone
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
 * @property-read PackageProduct|null $pivot
 */
class Product extends Model implements Bookable
{
    use HasCancellationPolicy;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasRating, HasUlids;

    protected $fillable = [
        'operator_id',
        'name',
        'slug',
        'description',
        'cover_photo',
        'gallery',
        'location',
        'category',
        'capacity_per_day',
        'price',
        'sellable_standalone',
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
            'capacity_per_day' => 'integer',
            'price' => 'decimal:2',
            'sellable_standalone' => 'boolean',
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
     * @return BelongsToMany<Package, $this, PackageProduct>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_products')
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

    /**
     * @return HasMany<ProductAvailability, $this>
     */
    public function availabilityRecords(): HasMany
    {
        return $this->hasMany(ProductAvailability::class);
    }

    /**
     * @return HasMany<AvailabilityBlock, $this>
     */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
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
        return $this->name;
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
        return (float) ($this->price ?? 0.00);
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
     * For a standalone Product, returns self with quantity 1.
     *
     * @return Collection<int, array{product: Product, quantity: int}>
     */
    public function getRequiredProducts(): Collection
    {
        /** @var Collection<int, array{product: Product, quantity: int}> $collection */
        $collection = collect([
            [
                'product' => $this,
                'quantity' => 1,
            ],
        ]);

        return $collection;
    }

    /**
     * Check if this Product is blacked out on a specific date.
     * (Evaluates operator-wide blocks and product-specific blocks)
     */
    public function isBlackedOutOn(Carbon|string $date): bool
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return AvailabilityBlock::where('operator_id', $this->operator_id)
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('product_id')->whereNull('package_id');
                })->orWhere('product_id', $this->id);
            })
            ->whereDate('date_start', '<=', $dateStr)
            ->whereDate('date_end', '>=', $dateStr)
            ->exists();
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

        $blocks = AvailabilityBlock::where('operator_id', $this->operator_id)
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('product_id')->whereNull('package_id');
                })->orWhere('product_id', $this->id);
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
        return $this->sellable_standalone && $this->price !== null && (float) $this->price > 0;
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
            'bookable_type' => 'product',
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
