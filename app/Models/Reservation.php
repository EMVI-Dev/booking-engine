<?php

namespace App\Models;

use App\Contracts\Bookable;
use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $code
 * @property string|null $guest_id
 * @property string $bookable_type
 * @property string $bookable_id
 * @property string $operator_id
 * @property string $guest_name
 * @property string $guest_contact
 * @property string|null $guest_email
 * @property Carbon $requested_date
 * @property int $pax_count
 * @property string|null $notes
 * @property array<string, mixed> $terms_snapshot
 * @property ReservationStatus $status
 * @property Carbon|null $hold_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator|null $operator
 * @property-read Guest|null $guest
 * @property-read Bookable|Model|null $bookable
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'code',
        'guest_id',
        'bookable_type',
        'bookable_id',
        'operator_id',
        'guest_name',
        'guest_contact',
        'guest_email',
        'requested_date',
        'pax_count',
        'notes',
        'terms_snapshot',
        'status',
        'hold_expires_at',
        'review_request_sent_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            if (empty($reservation->code)) {
                $reservation->code = static::generateUniqueCode();
            }

            if (empty($reservation->guest_id) && ! empty($reservation->operator_id)) {
                $email = trim((string) $reservation->guest_email);
                $normalizedEmail = $email !== '' ? strtolower($email) : null;
                $phone = trim((string) $reservation->guest_contact);

                $guestQuery = Guest::query()->where('operator_id', $reservation->operator_id);
                if ($normalizedEmail !== null) {
                    $guestQuery->where('email', $normalizedEmail);
                } elseif ($phone !== '') {
                    $guestQuery->where('phone', $phone);
                } else {
                    $guestQuery = null;
                }

                $guest = $guestQuery?->first();

                if (! $guest) {
                    $guest = Guest::create([
                        'operator_id' => $reservation->operator_id,
                        'name' => $reservation->guest_name,
                        'email' => $normalizedEmail,
                        'phone' => $phone !== '' ? $phone : null,
                    ]);
                }

                $reservation->guest_id = $guest->id;
            }
        });
    }

    /**
     * Generate unique, human-friendly reservation tracking code.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = 'RSV-'.strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'pax_count' => 'integer',
            'terms_snapshot' => 'array',
            'status' => ReservationStatus::class,
            'hold_expires_at' => 'datetime',
            'review_request_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function bookable(): MorphTo
    {
        return $this->morphTo();
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function isPendingPayment(): bool
    {
        return $this->status === ReservationStatus::PaymentPending;
    }

    public function isHoldExpired(): bool
    {
        return $this->isPendingPayment() && $this->hold_expires_at !== null && now()->gt($this->hold_expires_at);
    }

    public function getFrozenPrice(): float
    {
        return (float) ($this->terms_snapshot['price'] ?? 0.00);
    }

    public function getFrozenFreeCancellationHours(): int
    {
        return (int) ($this->terms_snapshot['free_cancellation_hours'] ?? 24);
    }

    public function getTotalAmount(): float
    {
        return $this->getFrozenPrice() * $this->pax_count;
    }
}
