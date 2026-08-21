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
 * @property Carbon $date_start
 * @property Carbon $date_end
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator|null $operator
 * @property-read Product|null $product
 */
class AvailabilityBlock extends Model
{
    /** @use HasFactory<AvailabilityBlockFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'operator_id',
        'product_id',
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

    public function isOperatorWide(): bool
    {
        return $this->product_id === null;
    }

    public function isAgentWide(): bool
    {
        return $this->isOperatorWide();
    }
}
