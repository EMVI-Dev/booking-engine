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

    /**
     * Readable text colour to place on top of the operator's brand colour.
     *
     * Brand colours range from near-black to bright yellow, so a fixed white
     * foreground makes light brands unreadable. This picks ink or white based on
     * the WCAG relative luminance of the brand colour.
     */
    public function getBrandForegroundColorAttribute(): string
    {
        $hex = ltrim($this->brand_color, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#101730';
        }

        $channels = array_map(static function (string $channel): float {
            $value = hexdec($channel) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        $luminance = (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);

        return $luminance > 0.45 ? '#101730' : '#FFFFFF';
    }

    public function getSellableStandaloneDefaultAttribute(): bool
    {
        return (bool) ($this->settings['sellable_standalone_default'] ?? true);
    }

    /**
     * Whether a payout bank account is on file for sending trip money.
     */
    public function hasPayoutBankAccount(): bool
    {
        return filled($this->bank_provider)
            && filled($this->bank_account_name)
            && filled($this->bank_account_number);
    }

    /**
     * Setup steps that still block the public booking page.
     *
     * @return list<string>
     */
    public function missingStorefrontSetupSteps(): array
    {
        $missing = [];

        if (! filled($this->bio) || ! filled($this->contact_whatsapp)) {
            $missing[] = 'brand';
        }

        if (! filled($this->terms_and_conditions)) {
            $missing[] = 'terms';
        }

        if (! $this->hasPayoutBankAccount()) {
            $missing[] = 'bank';
        }

        if (! filled($this->billing_email)) {
            $missing[] = 'billing';
        }

        if (! filled($this->booking_notification_email)) {
            $missing[] = 'notifications';
        }

        return $missing;
    }

    /**
     * Whether brand, terms, bank, and notification details are filled.
     */
    public function isStorefrontSetupComplete(): bool
    {
        return $this->missingStorefrontSetupSteps() === [];
    }

    /**
     * Whether guests may open the public booking page.
     */
    public function isStorefrontPublic(): bool
    {
        return $this->status === OperatorStatus::Approved && $this->isStorefrontSetupComplete();
    }

    /**
     * Determine if operator has completed their business profile, WhatsApp contact, payout bank, and terms.
     */
    public function isProfileComplete(): bool
    {
        return $this->isStorefrontSetupComplete();
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
        $resolver = app(DomainResolverService::class);

        foreach ($resolver->knownPlatformSuffixes() as $suffix) {
            if ($currentHost === $suffix || str_ends_with($currentHost, '.'.$suffix)) {
                return "{$scheme}://{$this->slug}.{$suffix}";
            }
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
     * Paid plan expired, but the storefront still has the 3-day grace window.
     */
    public function isInSubscriptionGracePeriod(): bool
    {
        if ($this->getPlan()->isFree()) {
            return false;
        }

        if ($this->plan_expires_at === null || $this->plan_expires_at->isFuture()) {
            return false;
        }

        return $this->plan_expires_at->gte(now()->subDays(3));
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
     * Packages and standalone activities share the plan listing cap.
     */
    public function listingCount(): int
    {
        return $this->packages()->count() + $this->products()->count();
    }

    /**
     * Check if the operator can add another trip or activity under their tier limit.
     */
    public function canAddListing(): bool
    {
        $limit = $this->getPlan()->package_limit;

        if ($limit === null) {
            return true;
        }

        return $this->listingCount() < $limit;
    }

    /**
     * Check if the operator can publish an additional package under their tier limit.
     */
    public function canAddPackage(): bool
    {
        return $this->canAddListing();
    }

    /**
     * Check if the operator can publish an additional activity under their tier limit.
     */
    public function canAddProduct(): bool
    {
        return $this->canAddListing();
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
     * Whether guests should see the platform name (storefront, mail From, share cards).
     */
    public function showsPlatformBranding(): bool
    {
        return ! $this->hasFeature('remove_branding');
    }

    /**
     * Share-card site name. Agency hides the platform; other plans credit it.
     */
    public function storefrontSiteName(): string
    {
        if ($this->showsPlatformBranding()) {
            return $this->name.' • '.config('app.name');
        }

        return $this->name;
    }

    /**
     * Inbox From name. Agency uses the operator name; other plans use the platform.
     */
    public function outboundMailFromName(): string
    {
        if ($this->showsPlatformBranding()) {
            return (string) (config('mail.from.name') ?: config('app.name'));
        }

        return $this->name;
    }

    /**
     * Address guests can reply to. Stays on the platform mailbox for sending.
     */
    public function outboundMailReplyToAddress(): ?string
    {
        $address = $this->booking_notification_email ?: $this->users()->first()?->email;

        if (! is_string($address) || $address === '') {
            return null;
        }

        return $address;
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
