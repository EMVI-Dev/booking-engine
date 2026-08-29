<?php

namespace App\Models;

use Database\Factories\AvailabilityBlockFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $operator_id
 * @property string|null $product_id
 * @property string|null $package_id
 * @property Carbon $date_start
 * @property Carbon $date_end
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator|null $operator
 * @property-read Product|null $product
 * @property-read Package|null $package
 */
class AvailabilityBlock extends Model
{
    /** @use HasFactory<AvailabilityBlockFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'operator_id',
        'product_id',
        'package_id',
        'date_start',
        'date_end',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function isOperatorWide(): bool
    {
        return $this->product_id === null && $this->package_id === null;
    }

    public function isAgentWide(): bool
    {
        return $this->isOperatorWide();
    }

    public function isPackageSpecific(): bool
    {
        return $this->package_id !== null;
    }

    public function isProductSpecific(): bool
    {
        return $this->product_id !== null;
    }

    public function getTargetLabel(): string
    {
        if ($this->package_id && $this->package) {
            return __('Tour Package: :title', ['title' => $this->package->title]);
        }

        if ($this->product_id && $this->product) {
            return __('Single Activity: :name', ['name' => $this->product->name]);
        }

        return __('Entire Catalog (All Items)');
    }
}
