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
 * @property string $agent_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property array<int, string>|null $tags
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 * @property-read Collection<int, Reservation> $reservations
 */
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'agent_id',
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
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
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
        $phone = preg_replace('/[^0-9]/', '', (string) $this->phone);
        if ($phone !== '' && str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
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
