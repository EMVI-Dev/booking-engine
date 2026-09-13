<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $operator_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property array<int, string>|null $tags
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator|null $operator
 * @property-read Collection<int, Reservation> $reservations
 */
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'operator_id',
        'name',
        'email',
        'phone',
        'notes',
        'tags',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
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
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Calculate total lifetime spent by this guest across paid reservations.
     */
    public function getTotalSpentAttribute(): float
    {
        return (float) $this->reservations->reduce(function (float $carry, Reservation $res): float {
            return $carry + (float) $res->payments->where('status', PaymentStatus::Paid)->sum('amount');
        }, 0.0);
    }

    /**
     * Calculate total pax count booked by this guest.
     */
    public function getTotalPaxAttribute(): int
    {
        return (int) $this->reservations->sum('pax_count');
    }

    /**
     * Total confirmed / completed bookings.
     */
    public function getConfirmedBookingsCountAttribute(): int
    {
        return $this->reservations->filter(fn (Reservation $r) => in_array($r->status, [ReservationStatus::Confirmed, ReservationStatus::Completed]))->count();
    }

    /**
     * Check if guest is a repeat customer (2+ bookings).
     */
    public function isRepeat(): bool
    {
        return $this->reservations->count() > 1;
    }

    /**
     * Get clean international phone number for WhatsApp.
     */
    public function getCleanPhone(): string
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $this->phone) ?? '';
        if ($phone !== '' && str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Normalize a phone string the same way as getCleanPhone().
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';
        if ($digits !== '' && str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Most recent trip date across all reservations.
     */
    public function lastTripDate(): ?Carbon
    {
        $date = $this->reservations->max('requested_date');

        return $date ? Carbon::parse($date) : null;
    }

    /**
     * Nearest upcoming trip that still counts as active inventory.
     */
    public function nextTripDate(): ?Carbon
    {
        $upcoming = $this->reservations
            ->filter(function (Reservation $reservation): bool {
                if (! $reservation->requested_date) {
                    return false;
                }

                $date = Carbon::parse($reservation->requested_date)->startOfDay();
                if ($date->lt(now()->startOfDay())) {
                    return false;
                }

                return in_array($reservation->status, [
                    ReservationStatus::PaymentPending,
                    ReservationStatus::PendingConfirmation,
                    ReservationStatus::Confirmed,
                ], true);
            })
            ->sortBy('requested_date')
            ->first();

        return $upcoming?->requested_date
            ? Carbon::parse($upcoming->requested_date)
            : null;
    }

    /**
     * Other guests under the same operator that share email or normalized phone.
     *
     * @return Collection<int, Guest>
     */
    public function possibleDuplicates(): Collection
    {
        $email = filled($this->email) ? strtolower(trim((string) $this->email)) : null;
        $phone = $this->getCleanPhone();

        if ($email === null && $phone === '') {
            return collect();
        }

        return $this->operator
            ->guests()
            ->whereKeyNot($this->id)
            ->get()
            ->filter(function (Guest $guest) use ($email, $phone): bool {
                $guestEmail = filled($guest->email) ? strtolower(trim((string) $guest->email)) : null;
                if ($email !== null && $guestEmail === $email) {
                    return true;
                }

                return $phone !== '' && $guest->getCleanPhone() === $phone;
            })
            ->values();
    }

    /**
     * Merge another guest into this one: move reservations, prefer filled fields, delete source.
     */
    public function mergeFrom(Guest $source): void
    {
        if ($source->id === $this->id || $source->operator_id !== $this->operator_id) {
            throw new \InvalidArgumentException('Cannot merge these guests.');
        }

        $source->reservations()->update(['guest_id' => $this->id]);

        $mergedTags = array_values(array_unique(array_filter(array_merge(
            is_array($this->tags) ? $this->tags : [],
            is_array($source->tags) ? $source->tags : [],
        ))));

        $this->update([
            'name' => filled($this->name) ? $this->name : $source->name,
            'email' => filled($this->email) ? $this->email : $source->email,
            'phone' => filled($this->phone) ? $this->phone : $source->phone,
            'notes' => filled($this->notes)
                ? (filled($source->notes) ? trim($this->notes."\n\n".$source->notes) : $this->notes)
                : $source->notes,
            'tags' => $mergedTags === [] ? null : $mergedTags,
        ]);

        $source->delete();
    }

    /**
     * Generate WhatsApp direct link.
     */
    public function getWhatsAppUrl(?string $agentName = null): string
    {
        $clean = $this->getCleanPhone();
        if ($clean === '') {
            return '#';
        }

        $agent = $agentName ?: ($this->agent->name ?? 'Tour Operator');
        $msg = __('Hello :name, reaching out from :agent regarding your reservations', [
            'name' => $this->name,
            'agent' => $agent,
        ]);

        return 'https://wa.me/'.$clean.'?text='.urlencode($msg);
    }
}
