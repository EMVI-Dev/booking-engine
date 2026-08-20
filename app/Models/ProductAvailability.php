<?php

namespace App\Models;

use Database\Factories\ProductAvailabilityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $product_id
 * @property Carbon $date
 * @property int $capacity_booked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $product
 */
class ProductAvailability extends Model
{
    /** @use HasFactory<ProductAvailabilityFactory> */
    use HasFactory, HasUlids;

    protected $table = 'product_availability';

    protected $fillable = [
        'product_id',
        'date',
        'capacity_booked',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity_booked' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
