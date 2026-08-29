<?php

namespace App\Models;

use Database\Factories\PlatformCouponFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string|null $description
 * @property string $scope
 * @property string $discount_type
 * @property string $discount_value
 * @property string $min_spend
 * @property string|null $max_discount_amount
 * @property string|null $operator_id
 * @property string $redemption_scope unlimited|first_purchase_only|once_per_period
 * @property array<string,mixed>|null $eligibility_rule
 * @property string|null $announcement_id
 * @property int|null $max_uses
 * @property int $used_count
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator|null $operator
 * @property-read PlatformAnnouncement|null $announcement
 */
class PlatformCoupon extends Model
{
    /** @use HasFactory<PlatformCouponFactory> */
    use HasFactory, HasUlids;

    protected $table = 'platform_coupons';

    protected $fillable = [
        'code',
        'description',
        'scope',
        'redemption_scope',
        'eligibility_rule',
        'announcement_id',
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
            'eligibility_rule' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @return BelongsTo<PlatformAnnouncement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PlatformAnnouncement::class);
    }

    /**
     * @return HasMany<OperatorCouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(OperatorCouponRedemption::class, 'platform_coupon_id');
    }

    // ── Query Scopes ───────────────────────────────────────────────────────────

    /**
     * Scope for platform subscription coupons (for operators subscribing/upgrading).
     *
     * @param  Builder<PlatformCoupon>  $query
     */
    public function scopeForSubscription(Builder $query): void
    {
        $query->where('scope', 'subscription');
    }

    /**
     * Scope for guest coupons (for operator storefront booking box).
     *
     * @param  Builder<PlatformCoupon>  $query
     */
    public function scopeForGuest(Builder $query): void
    {
        $query->where('scope', 'guest');
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
     * Scope to coupons that have an eligibility_rule set (broadcast-able coupons).
     *
     * @param  Builder<PlatformCoupon>  $query
     */
    public function scopeWithEligibilityRule(Builder $query): void
    {
        $query->whereNotNull('eligibility_rule');
    }

    // ── Business Logic ─────────────────────────────────────────────────────────

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
     * Check whether this operator is allowed to redeem the coupon given redemption_scope rules.
     */
    public function meetsRedemptionRuleFor(Operator $operator): bool
    {
        return match ($this->redemption_scope) {
            'first_purchase_only' => ! $this->hasBeenRedeemedBy($operator),
            'once_per_period' => ! $this->hasBeenRedeemedThisPeriodBy($operator),
            default => true, // unlimited
        };
    }

    /**
     * Whether this operator has ever redeemed this coupon.
     */
    public function hasBeenRedeemedBy(Operator $operator): bool
    {
        return $this->redemptions()
            ->where('operator_id', $operator->id)
            ->exists();
    }

    /**
     * Whether this operator has already redeemed this coupon in the current billing cycle.
     */
    public function hasBeenRedeemedThisPeriodBy(Operator $operator): bool
    {
        return $this->redemptions()
            ->where('operator_id', $operator->id)
            ->where('billing_cycle', now()->format('Y-m'))
            ->exists();
    }

    /**
     * Evaluate whether a given operator meets the eligibility_rule for auto-broadcast.
     * Returns true if the operator qualifies for this coupon.
     */
    public function isEligibleFor(Operator $operator): bool
    {
        if ($this->eligibility_rule === null) {
            return true; // No rule = manually targeted, always eligible if operator_id matches
        }

        $rule = $this->eligibility_rule;
        $type = $rule['type'] ?? null;

        return match ($type) {
            'min_monthly_transactions' => $this->evaluateMinMonthlyTransactions($operator, $rule),
            'min_monthly_revenue' => $this->evaluateMinMonthlyRevenue($operator, $rule),
            'subscription_age_months' => $this->evaluateSubscriptionAge($operator, $rule),
            default => false,
        };
    }

    /**
     * Evaluate min_monthly_transactions rule against confirmed reservations.
     *
     * @param  array<string,mixed>  $rule
     */
    private function evaluateMinMonthlyTransactions(Operator $operator, array $rule): bool
    {
        $threshold = (int) ($rule['threshold'] ?? 0);
        $lookback = (int) ($rule['lookback_months'] ?? 1);

        $count = Reservation::where('operator_id', $operator->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonths($lookback)->startOfDay())
            ->count();

        return $count >= $threshold;
    }

    /**
     * Evaluate min_monthly_revenue rule — sum of completed payments via operator reservations.
     *
     * @param  array<string,mixed>  $rule
     */
    private function evaluateMinMonthlyRevenue(Operator $operator, array $rule): bool
    {
        $threshold = (float) ($rule['threshold'] ?? 0);
        $lookback = (int) ($rule['lookback_months'] ?? 1);

        $revenue = Payment::whereHas('reservation', function (Builder $q) use ($operator) {
            $q->where('operator_id', $operator->id);
        })
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths($lookback)->startOfDay())
            ->sum('amount');

        return $revenue >= $threshold;
    }

    /**
     * Evaluate subscription_age_months rule.
     *
     * @param  array<string,mixed>  $rule
     */
    private function evaluateSubscriptionAge(Operator $operator, array $rule): bool
    {
        $minMonths = (int) ($rule['threshold'] ?? 0);

        if (! $operator->subscribed_at) {
            return false;
        }

        return $operator->subscribed_at->diffInMonths(now()) >= $minMonths;
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
