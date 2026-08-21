<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $reference_number
 * @property string $operator_id
 * @property string $amount
 * @property string $bank_provider
 * @property string $bank_account_name
 * @property string $bank_account_number
 * @property PayoutStatus $status
 * @property string|null $notes
 * @property string|null $rejection_reason
 * @property string|null $proof_document_path
 * @property string|null $processed_by
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator|null $operator
 */
class PayoutRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'reference_number',
        'operator_id',
        'amount',
        'bank_provider',
        'bank_account_name',
        'bank_account_number',
        'status',
        'notes',
        'rejection_reason',
        'proof_document_path',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PayoutStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->reference_number)) {
                $model->reference_number = 'PAY-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
            }
        });
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
     * @return HasMany<WalletTransaction, $this>
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
