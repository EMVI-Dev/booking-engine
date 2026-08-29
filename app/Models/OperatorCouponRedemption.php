<?php

namespace App\Models;

use Database\Factories\OperatorCouponRedemptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $platform_coupon_id
 * @property string $operator_id
 * @property string|null $billing_cycle
 * @property Carbon $redeemed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PlatformCoupon $coupon
 * @property-read Operator $operator
 */
class OperatorCouponRedemption extends Model
{
    /** @use HasFactory<OperatorCouponRedemptionFactory> */
    use HasFactory, HasUlids;

    protected $table = 'operator_coupon_redemptions';

    protected $fillable = [
        'platform_coupon_id',
        'operator_id',
        'billing_cycle',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlatformCoupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(PlatformCoupon::class, 'platform_coupon_id');
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * Record a redemption for the given operator and coupon.
     * Billing cycle is the current month key (e.g. "2026-08").
     */
    public static function record(PlatformCoupon $coupon, Operator $operator): self
    {
        return self::create([
            'platform_coupon_id' => $coupon->id,
            'operator_id' => $operator->id,
            'billing_cycle' => now()->format('Y-m'),
            'redeemed_at' => now(),
        ]);
    }
}
