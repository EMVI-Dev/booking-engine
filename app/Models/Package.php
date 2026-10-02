<?php

namespace App\Models;

use App\Contracts\Bookable;
use App\Enums\ListingStatus;
use App\Models\Traits\HasCancellationPolicy;
use App\Models\Traits\InteractsWithBookable;
use Database\Factories\PackageFactory;
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

    use HasUlids;
    use InteractsWithBookable;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPrice(): float
    {
        return (float) $this->price;
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

    public function isSellable(): bool
    {
        return true;
    }

    /**
     * Blocks on any bundled activity also block the package.
     *
     * @return list<string>
     */
    protected function blackoutProductIds(): array
    {
        $ids = $this->relationLoaded('products')
            ? $this->products->pluck('id')
            : $this->products()->pluck('products.id');

        return array_values($ids->filter()->map(fn ($id): string => (string) $id)->all());
    }

    protected function blackoutPackageId(): ?string
    {
        return (string) $this->id;
    }
}
