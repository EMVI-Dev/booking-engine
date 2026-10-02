<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    use HasFactory, HasUlids;

    protected $fillable = [
        'operator_id',
        'plan_id',
        'previous_plan_id',
        'invoice_number',
        'type',
        'billing_interval',
        'gross_amount',
        'prorated_credit',
        'net_amount_paid',
        'status',
        'gateway',
        'gateway_ref',
        'breakdown',
        'paid_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'prorated_credit' => 'decimal:2',
        'net_amount_paid' => 'decimal:2',
        'breakdown' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'previous_plan_id');
    }

    /**
     * Get user-friendly gateway / payment channel label.
     */
    public function getGatewayLabel(): string
    {
        $breakdown = $this->breakdown ?? [];
        $channel = $breakdown['channel'] ?? $breakdown['payment_channel'] ?? null;

        if ($channel) {
            $normalizedChannel = strtoupper(str_replace(['_', '-'], ' ', (string) $channel));
            if (str_contains($normalizedChannel, 'QRIS')) {
                return 'QRIS';
            }
            if (str_contains($normalizedChannel, 'VIRTUAL ACCOUNT') || str_contains($normalizedChannel, 'VA')) {
                return ucwords(strtolower($normalizedChannel));
            }
            if (str_contains($normalizedChannel, 'CARD') || str_contains($normalizedChannel, 'CREDIT')) {
                return 'Credit Card';
            }

            return ucwords(strtolower($normalizedChannel));
        }

        return match ($this->gateway) {
            'doku', 'doku_checkout' => 'DOKU Checkout',
            'credit_card', 'cc', 'doku_cc' => 'Credit Card',
            'qris', 'doku_qris' => 'QRIS',
            'va', 'doku_va' => 'Virtual Account',
            'admin_complimentary' => 'Complimentary (Admin)',
            'admin_extension' => 'Free extension (Admin)',
            'wallet_credit' => 'Wallet Balance',
            'promo_code' => 'Promo Code (100% off)',
            'free' => 'Free Tier',
            'manual' => 'Manual Transfer',
            'simulation' => 'Sandbox Simulation',
            default => filled($this->gateway) ? ucfirst(str_replace('_', ' ', (string) $this->gateway)) : 'DOKU Checkout',
        };
    }

    /**
     * Get FontAwesome icon class and color for this payment gateway.
     */
    public function getGatewayIcon(): string
    {
        $label = strtolower($this->getGatewayLabel());

        if (str_contains($label, 'qris')) {
            return 'fa-solid fa-qrcode text-indigo-500';
        }
        if (str_contains($label, 'virtual account') || str_contains($label, 'va')) {
            return 'fa-solid fa-building-columns text-indigo-500';
        }
        if (str_contains($label, 'card') || str_contains($label, 'credit')) {
            return 'fa-solid fa-credit-card text-indigo-500';
        }
        if (str_contains($label, 'complimentary') || str_contains($label, 'admin')) {
            return 'fa-solid fa-star text-amber-500';
        }
        if (str_contains($label, 'wallet')) {
            return 'fa-solid fa-wallet text-indigo-500';
        }
        if (str_contains($label, 'promo') || str_contains($label, 'free')) {
            return 'fa-solid fa-tag text-teal-500';
        }
        if ($this->gateway === 'simulation') {
            return 'fa-solid fa-bolt text-amber-500';
        }

        return 'fa-solid fa-shield-halved text-emerald-500';
    }
}
