<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Services\DomainResolverService;
use Database\Factories\OperatorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property OperatorStatus $status
 * @property string|null $plan_id
 * @property Carbon|null $subscribed_at
 * @property Carbon|null $plan_expires_at
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
class Operator extends Model
{
    /** @use HasFactory<OperatorFactory> */
    use HasFactory, HasUlids;

    protected $table = 'operators';

    protected $fillable = [
        'name',
        'slug',
        'bio',
        'photo',
        'contact_whatsapp',
        'booking_notification_email',
        'billing_email',
        'status',
        'plan_id',
        'subscribed_at',
        'plan_expires_at',
        'subscription_interval',
        'pending_plan_id',
        'pending_plan_action_at',
        'subscription_auto_renew',
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
            'status' => OperatorStatus::class,
            'subscribed_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'pending_plan_action_at' => 'datetime',
            'subscription_auto_renew' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsToMany<User, $this, OperatorUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'operator_users')
            ->using(OperatorUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    /**
     * @return HasMany<SubscriptionPayment, $this>
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * @return HasMany<OperatorDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(OperatorDomain::class);
    }

    /**
     * @return HasOne<OperatorDomain, $this>
     */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(OperatorDomain::class)->where('is_primary', true);
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
        return $this->status === OperatorStatus::Approved;
    }

    public function isSuspended(): bool
    {
        return $this->status === OperatorStatus::Suspended;
    }

    public function isPending(): bool
    {
        return $this->status === OperatorStatus::Pending;
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->settings['display_name'] ?? $this->name);
    }

    public function getLogoAttribute(): ?string
    {
        return $this->logo_path ?: $this->photo;
    }

    public function getLogoUrlAttribute(): string
    {
        $path = $this->logo_path ?: $this->photo;

        if (! $path) {
            return asset('favicon.png');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::url($path);
    }

    public function getBannerUrlAttribute(): ?string
    {
        $path = $this->banner_path;

        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::url($path);
    }

    public function getBrandColorAttribute(): string
    {
        return (string) ($this->settings['brand_color'] ?? '#FFEF4D');
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
     * Determine if operator has completed their business profile, WhatsApp contact, payout ref, and terms & conditions.
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
     * Determine if operator WhatsApp support is currently online based on configured timezone and business schedule.
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
     * Get the primary public storefront URL for this operator.
     */
    public function getStorefrontUrl(): string
    {
        // 1. Check for active custom domain (e.g. baliridetours.com)
        $activeCustomDomain = $this->domains()
            ->where('status', DomainStatus::Active)
            ->where('type', DomainType::Custom)
            ->first();

        if ($activeCustomDomain) {
            return 'https://'.$activeCustomDomain->domain;
        }

        // 2. Build subdomain URL based on current request host or app.url
        $scheme = request()->getScheme() ?: 'https';
        $currentHost = request()->getHost();
        $platformDomain = app(DomainResolverService::class)->getPlatformDomain();

        if (
            str_ends_with($currentHost, '.'.$platformDomain) ||
            str_ends_with($currentHost, '.booking.test') ||
            str_ends_with($currentHost, '.booking.emvi') ||
            in_array($currentHost, [$platformDomain, 'booking.test', 'booking.emvi', 'localhost', '127.0.0.1'], true)
        ) {
            $baseDomain = $platformDomain;
            if (str_ends_with($currentHost, '.booking.emvi') || $currentHost === 'booking.emvi') {
                $baseDomain = 'booking.emvi';
            } elseif (str_ends_with($currentHost, '.booking.test') || $currentHost === 'booking.test') {
                $baseDomain = 'booking.test';
            }

            return "{$scheme}://{$this->slug}.{$baseDomain}";
        }

        $appUrlHost = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'booking.test';
        if ($appUrlHost === 'localhost' || $appUrlHost === '127.0.0.1') {
            $appUrlHost = 'booking.test';
        }

        return "{$scheme}://{$this->slug}.{$appUrlHost}";
    }

    /**
     * Get the active subscription plan for this operator (falling back to default Starter plan).
     */
    public function getPlan(): Plan
    {
        if ($this->plan) {
            return $this->plan;
        }

        return Plan::getDefaultPlan();
    }

    /**
     * Check if operator has a scheduled plan change pending execution.
     */
    public function hasPendingPlanChange(): bool
    {
        return ! empty($this->pending_plan_id) && $this->pending_plan_id !== $this->plan_id;
    }

    /**
     * Get the scheduled pending plan.
     */
    public function getPendingPlan(): ?Plan
    {
        return $this->pendingPlan;
    }

    /**
     * Cancel any scheduled pending plan change.
     */
    public function cancelPendingPlanChange(): void
    {
        $this->update([
            'pending_plan_id' => null,
            'pending_plan_action_at' => null,
        ]);
        $this->unsetRelation('pendingPlan');
    }

    /**
     * Check if operator's active subscription tier includes a given feature.
     */
    public function hasFeature(string $featureKey): bool
    {
        return $this->getPlan()->hasFeature($featureKey);
    }

    /**
     * Check if the operator can publish an additional package under their tier limit.
     */
    public function canAddPackage(): bool
    {
        $limit = $this->getPlan()->package_limit;

        if ($limit === null) {
            return true;
        }

        return $this->packages()->count() < $limit;
    }

    /**
     * Check if the operator can invite an additional team member under their tier limit.
     */
    public function canAddTeamMember(): bool
    {
        $limit = $this->getPlan()->team_member_limit;

        if ($limit === null) {
            return true;
        }

        return $this->users()->count() < $limit;
    }

    /**
     * Get effective platform commission rate for this operator.
     */
    public function getEffectiveCommissionRate(): float
    {
        if (isset($this->settings['commission_rate']) && is_numeric($this->settings['commission_rate'])) {
            return (float) $this->settings['commission_rate'];
        }

        return $this->getPlan()->commission_rate;
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
     * Check if operator has configured their own custom payment gateway credentials.
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
     * @return HasMany<WalletTransaction, $this>
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * @return HasMany<PayoutRequest, $this>
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /**
     * Get available balance cleared for withdrawal.
     */
    public function getAvailableBalance(): float
    {
        return (float) $this->walletTransactions()
            ->where('status', WalletTransactionStatus::Cleared)
            ->sum('net_amount');
    }

    /**
     * Get funds currently held in pending escrow.
     */
    public function getPendingEscrowBalance(): float
    {
        return (float) $this->walletTransactions()
            ->where('status', WalletTransactionStatus::PendingEscrow)
            ->sum('net_amount');
    }

    /**
     * Get total gross sales earned by operator.
     */
    public function getTotalGrossSales(): float
    {
        return (float) $this->walletTransactions()
            ->where('type', WalletTransactionType::BookingEarning)
            ->sum('gross_amount');
    }

    /**
     * Get total completed payouts transferred to operator.
     */
    public function getTotalLifetimeWithdrawn(): float
    {
        return (float) $this->payoutRequests()
            ->where('status', PayoutStatus::Completed)
            ->sum('amount');
    }

    /**
     * Check if operator has configured valid bank account details.
     */
    public function hasValidBankAccount(): bool
    {
        return filled($this->bank_provider) &&
            filled($this->bank_account_number) &&
            filled($this->bank_account_name);
    }

    /**
     * Get or generate a persistent unique token for the live iCal calendar feed.
     */
    public function getCalendarFeedToken(): string
    {
        $settings = $this->settings ?? [];
        $token = $settings['calendar_feed_token'] ?? null;

        if (! is_string($token) || strlen($token) < 16) {
            $token = bin2hex(random_bytes(16));
            $settings['calendar_feed_token'] = $token;
            $this->update(['settings' => $settings]);
        }

        return $token;
    }

    /**
     * Get the full absolute URL for the operator's live iCal calendar feed.
     */
    public function getCalendarFeedUrl(): string
    {
        return route('calendar.feed', ['token' => $this->getCalendarFeedToken()]);
    }

    /**
     * Check if operator requires manual confirmation for incoming bookings.
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

    /**
     * Get Google Analytics 4 Measurement ID (e.g. G-XXXXXXXXXX).
     */
    public function getGoogleAnalyticsId(): ?string
    {
        $tracking = $this->settings['tracking'] ?? [];
        $id = trim((string) ($tracking['google_analytics_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    /**
     * Get Meta / Facebook Pixel ID (e.g. 123456789012345).
     */
    public function getMetaPixelId(): ?string
    {
        $tracking = $this->settings['tracking'] ?? [];
        $id = trim((string) ($tracking['meta_pixel_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    /**
     * Get Google Tag Manager Container ID (e.g. GTM-XXXXXXX).
     */
    public function getGoogleTagManagerId(): ?string
    {
        $tracking = $this->settings['tracking'] ?? [];
        $id = trim((string) ($tracking['google_tag_manager_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    /**
     * Get Google Search Console Site Verification Meta Tag / Code.
     */
    public function getGoogleSiteVerification(): ?string
    {
        $tracking = $this->settings['tracking'] ?? [];
        $code = trim((string) ($tracking['google_site_verification'] ?? ''));

        return $code !== '' ? $code : null;
    }

    /**
     * Get Google Search Console verification code attribute.
     */
    public function getGoogleSiteVerificationAttribute(): ?string
    {
        return $this->getGoogleSiteVerification();
    }

    /**
     * Get Google Maps / TripAdvisor / External Review URL.
     */
    public function getReviewUrl(): ?string
    {
        $marketing = $this->settings['marketing'] ?? [];
        $url = trim((string) ($marketing['review_url'] ?? ''));

        return $url !== '' ? $url : null;
    }
}
