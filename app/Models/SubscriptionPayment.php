<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
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
}
