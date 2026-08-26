<?php

namespace App\Models;

use Database\Factories\PlatformCouponFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string|null $description
 * @property string $discount_type
 * @property string $discount_value
 * @property string $min_spend
 * @property string|null $max_discount_amount
 * @property string|null $operator_id
 * @property int|null $max_uses
 * @property int $used_count
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator|null $operator
 */
class PlatformCoupon extends Model
{
    /** @use HasFactory<PlatformCouponFactory> */
    use HasFactory, HasUlids;

    protected $table = 'platform_coupons';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_spend',
        'max_discount_amount',
        'operator_id',
        'max_uses',
        'used_count',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_spend' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * Check if coupon is active and valid within timeframe.
     *
     * @param  Builder<PlatformCoupon>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $now = now();
        $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    /**
     * Validate coupon eligibility for given subtotal and operator.
     *
     * @return array{valid: bool, reason?: string, discount?: float}
     */
    public function validateFor(float $subtotal, ?string $operatorId = null): array
    {
        if (! $this->is_active) {
            return ['valid' => false, 'reason' => __('This promo code is currently inactive.')];
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return ['valid' => false, 'reason' => __('This promo code is not valid yet.')];
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return ['valid' => false, 'reason' => __('This promo code has expired.')];
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return ['valid' => false, 'reason' => __('This promo code has reached its maximum usage limit.')];
        }

        if ($this->operator_id !== null && $operatorId !== null && $this->operator_id !== $operatorId) {
            return ['valid' => false, 'reason' => __('This promo code is only valid for a specific operator storefront.')];
        }

        if ($this->min_spend > 0 && $subtotal < (float) $this->min_spend) {
            $formattedMin = number_format((float) $this->min_spend, 0, ',', '.');

            return ['valid' => false, 'reason' => __('Minimum booking spend of Rp :min required for this code.', ['min' => $formattedMin])];
        }

        $discount = $this->calculateDiscount($subtotal);

        return [
            'valid' => true,
            'discount' => $discount,
        ];
    }

    /**
     * Calculate discount amount against subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'percentage') {
            $discount = round(($subtotal * (float) $this->discount_value) / 100, 2);
            if ($this->max_discount_amount !== null && $this->max_discount_amount > 0) {
                $discount = min($discount, (float) $this->max_discount_amount);
            }

            return min($discount, $subtotal);
        }

        // Fixed discount
        return min((float) $this->discount_value, $subtotal);
    }

    /**
     * Increment redemption usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
