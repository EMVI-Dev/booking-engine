<?php

namespace App\Models;

use App\Contracts\Bookable;
use App\Enums\ListingStatus;
use App\Models\Traits\HasCancellationPolicy;
use App\Models\Traits\InteractsWithBookable;
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
 * @property string|null $vendor_id
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
 * @property-read Vendor|null $vendor
 * @property-read PackageProduct|null $pivot
 */
class Product extends Model implements Bookable
{
    use HasCancellationPolicy;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasUlids;
    use InteractsWithBookable;

    protected $fillable = [
        'operator_id',
        'vendor_id',
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

    /**
     * @return array<string, string>
     */
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
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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

    public function getTitle(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return (float) ($this->price ?? 0.00);
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

    public function isSellable(): bool
    {
        return $this->sellable_standalone && $this->price !== null && (float) $this->price > 0;
    }

    /**
     * @return list<string>
     */
    protected function blackoutProductIds(): array
    {
        return [(string) $this->id];
    }

    protected function blackoutPackageId(): ?string
    {
        return null;
    }
}
