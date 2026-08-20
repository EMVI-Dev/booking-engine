<?php

namespace App\Models;

use App\Enums\AgentStatus;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $bio
 * @property string|null $photo
 * @property string|null $contact_whatsapp
 * @property string $booking_notification_email
 * @property string $billing_email
 * @property AgentStatus $status
 * @property string|null $terms_and_conditions
 * @property string|null $bank_provider
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property string|null $bank_account_ref
 * @property string|null $logo_path
 * @property string|null $favicon_path
 * @property string|null $banner_path
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'bio',
        'photo',
        'contact_whatsapp',
        'booking_notification_email',
        'billing_email',
        'status',
        'terms_and_conditions',
        'bank_provider',
        'bank_account_name',
        'bank_account_number',
        'bank_account_ref',
        'logo_path',
        'favicon_path',
        'banner_path',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<User, $this, AgentUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_users')
            ->using(AgentUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<AgentDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(AgentDomain::class);
    }

    /**
     * @return HasOne<AgentDomain, $this>
     */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(AgentDomain::class)->where('is_primary', true);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<Guest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * @return HasMany<AvailabilityBlock, $this>
     */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isApproved(): bool
    {
        return $this->status === AgentStatus::Approved;
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->settings['display_name'] ?? $this->name);
    }

    public function getLogoAttribute(): ?string
    {
        return $this->logo_path ?: $this->photo;
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = $this->logo_path ?: $this->photo;

        return $path ? Storage::url($path) : null;
    }

    public function getBrandColorAttribute(): string
    {
        return (string) ($this->settings['brand_color'] ?? '#4f46e5');
    }

    public function setBrandColorAttribute(string $value): void
    {
        $settings = $this->settings ?? [];
        $settings['brand_color'] = $value;
        $this->settings = $settings;
    }

    public function getSellableStandaloneDefaultAttribute(): bool
    {
        return (bool) ($this->settings['sellable_standalone_default'] ?? true);
    }

    /**
     * Determine if agent has completed their business profile, WhatsApp contact, payout ref, and terms & conditions.
     */
    public function isProfileComplete(): bool
    {
        $hasBank = ($this->bank_provider !== null && $this->bank_account_name !== null && $this->bank_account_number !== null)
            || ! empty($this->bank_account_ref)
            || $this->hasCustomPaymentGateway();

        return ! empty($this->contact_whatsapp)
            && ! empty($this->bio)
            && ! empty($this->terms_and_conditions)
            && $hasBank;
    }

    /**
     * Determine if agent WhatsApp support is currently online based on configured timezone and business schedule.
     */
    public function isWhatsAppOnline(): bool
    {
        if (empty($this->contact_whatsapp)) {
            return false;
        }

        $settings = $this->settings ?? [];
        $schedule = $settings['whatsapp_schedule'] ?? [];

        if (($schedule['mode'] ?? 'schedule') === 'always') {
            return true;
        }

        $timezone = (string) ($schedule['timezone'] ?? 'Asia/Makassar');
        try {
            $now = Carbon::now($timezone);
        } catch (\Throwable) {
            $now = Carbon::now('Asia/Makassar');
        }

        $currentDay = strtolower($now->format('D'));
        /** @var array<int, string> $activeDays */
        $activeDays = $schedule['days'] ?? ['mon', 'tue', 'wed', 'thu', 'fri'];

        if (! in_array($currentDay, $activeDays, true)) {
            return false;
        }

        $startTime = (string) ($schedule['start_time'] ?? '08:00');
        $endTime = (string) ($schedule['end_time'] ?? '18:00');
        $currentTime = $now->format('H:i');

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * Get human-readable summary of WhatsApp operating hours.
     */
    public function getWhatsAppScheduleSummary(): string
    {
        $settings = $this->settings ?? [];
        $schedule = $settings['whatsapp_schedule'] ?? [];

        if (($schedule['mode'] ?? 'schedule') === 'always') {
            return '24/7 Available';
        }

        $startTime = (string) ($schedule['start_time'] ?? '08:00');
        $endTime = (string) ($schedule['end_time'] ?? '18:00');
        $tz = (string) ($schedule['timezone'] ?? 'Asia/Makassar');

        $tzAbbr = match ($tz) {
            'Asia/Jakarta' => 'WIB (UTC+7)',
            'Asia/Makassar' => 'WITA (UTC+8)',
            'Asia/Jayapura' => 'WIT (UTC+9)',
            default => $tz,
        };

        /** @var array<int, string> $days */
        $days = $schedule['days'] ?? ['mon', 'tue', 'wed', 'thu', 'fri'];
        $dayCount = count($days);
        $dayLabel = match ($dayCount) {
            7 => 'Everyday',
            5 => 'Mon - Fri',
            default => strtoupper(implode(', ', $days)),
        };

        return "{$dayLabel}, {$startTime} - {$endTime} ({$tzAbbr})";
    }

    /**
     * Get formatted bank account string.
     */
    public function getFormattedBankAccountAttribute(): ?string
    {
        if ($this->bank_provider && $this->bank_account_number) {
            return "{$this->bank_provider} - {$this->bank_account_number}".($this->bank_account_name ? " ({$this->bank_account_name})" : '');
        }

        return $this->bank_account_ref;
    }

    /**
     * Check if agent has configured their own custom payment gateway credentials.
     */
    public function hasCustomPaymentGateway(): bool
    {
        $gatewaySettings = $this->settings['payment_gateway'] ?? [];

        return (bool) ($gatewaySettings['use_custom_credentials'] ?? false)
            && ! empty($gatewaySettings['client_id'])
            && ! empty($gatewaySettings['shared_key']);
    }

    /**
     * Get active payment gateway provider (e.g. 'doku').
     */
    public function getPaymentGatewayProvider(): string
    {
        return (string) ($this->settings['payment_gateway']['provider'] ?? 'doku');
    }

    /**
     * Get effective payment gateway configuration (custom credentials or platform defaults).
     *
     * @return array{provider: string, is_custom: bool, client_id: ?string, shared_key: ?string, mode: string}
     */
    public function getPaymentGatewayConfig(): array
    {
        $platformDokuMode = PlatformSetting::current()->getDokuMode()->value;

        if ($this->hasCustomPaymentGateway()) {
            $custom = $this->settings['payment_gateway'] ?? [];

            return [
                'provider' => (string) ($custom['provider'] ?? 'doku'),
                'is_custom' => true,
                'client_id' => (string) ($custom['client_id'] ?? null),
                'shared_key' => (string) ($custom['shared_key'] ?? null),
                'mode' => (string) ($custom['mode'] ?? $platformDokuMode),
            ];
        }

        $platformConfig = config("doku.{$platformDokuMode}", []);

        return [
            'provider' => 'doku',
            'is_custom' => false,
            'client_id' => is_array($platformConfig) ? ($platformConfig['client_id'] ?? null) : null,
            'shared_key' => is_array($platformConfig) ? ($platformConfig['shared_key'] ?? null) : null,
            'mode' => $platformDokuMode,
        ];
    }

    /**
     * Check if agent requires manual confirmation for incoming bookings.
     */
    public function isManualConfirmationEnabled(): bool
    {
        $storefront = $this->settings['storefront'] ?? [];

        return (string) ($storefront['booking_confirmation_mode'] ?? 'automatic') === 'manual';
    }

    /**
     * Get booking confirmation mode ('automatic' | 'manual').
     */
    public function getBookingConfirmationMode(): string
    {
        $storefront = $this->settings['storefront'] ?? [];

        return (string) ($storefront['booking_confirmation_mode'] ?? 'automatic');
    }
}
