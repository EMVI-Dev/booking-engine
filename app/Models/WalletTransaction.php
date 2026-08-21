<?php

namespace App\Models;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $operator_id
 * @property string|null $reservation_id
 * @property string|null $payout_request_id
 * @property WalletTransactionType $type
 * @property string $gross_amount
 * @property string $fee_amount
 * @property string $net_amount
 * @property string|null $balance_snapshot
 * @property WalletTransactionStatus $status
 * @property Carbon|null $available_at
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator|null $operator
 * @property-read Reservation|null $reservation
 * @property-read PayoutRequest|null $payoutRequest
 */
class WalletTransaction extends Model
{
    use HasUlids;

    protected $fillable = [
        'operator_id',
        'reservation_id',
        'payout_request_id',
        'type',
        'gross_amount',
        'fee_amount',
        'net_amount',
        'balance_snapshot',
        'status',
        'available_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'balance_snapshot' => 'decimal:2',
            'type' => WalletTransactionType::class,
            'status' => WalletTransactionStatus::class,
            'available_at' => 'datetime',
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
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<PayoutRequest, $this>
     */
    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }

    public function isCleared(): bool
    {
        return $this->status === WalletTransactionStatus::Cleared;
    }

    public function isPendingEscrow(): bool
    {
        return $this->status === WalletTransactionStatus::PendingEscrow;
    }
}
