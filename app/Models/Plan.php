<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $tagline
 * @property float $price_monthly
 * @property float $price_yearly
 * @property float $commission_rate
 * @property int|null $package_limit
 * @property int|null $team_member_limit
 * @property array<string, bool>|null $features
 * @property bool $is_active
 * @property bool $is_popular
 * @property int $sort_order
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'price_monthly',
        'price_yearly',
        'commission_rate',
        'package_limit',
        'team_member_limit',
        'features',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'commission_rate' => 'float',
            'package_limit' => 'integer',
            'team_member_limit' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Operator, $this>
     */
    public function operators(): HasMany
    {
        return $this->hasMany(Operator::class);
    }

    /**
     * @deprecated Use operators() instead.
     *
     * @return HasMany<Operator, $this>
     */
    public function agents(): HasMany
    {
        return $this->operators();
    }

    /**
     * Check if this plan includes a specific feature flag.
     */
    public function hasFeature(string $featureKey): bool
    {
        $features = $this->features ?? [];

        return (bool) ($features[$featureKey] ?? false);
    }

    /**
     * Determine if this plan is free (zero monthly and yearly cost).
     */
    public function isFree(): bool
    {
        return (float) $this->price_monthly <= 0 && (float) $this->price_yearly <= 0;
    }

    /**
     * Everyday label for how many people can run the business on this plan.
     */
    public function teamSeatLabel(): string
    {
        if ($this->team_member_limit === null) {
            return __('Unlimited people');
        }

        if ($this->team_member_limit === 2) {
            return __('You and 1 helper');
        }

        return __(':count people', ['count' => $this->team_member_limit]);
    }

    /**
     * Everyday label for the shared trips + activities listing cap.
     */
    public function listingLimitLabel(): string
    {
        if ($this->package_limit === null) {
            return __('Unlimited trips and activities');
        }

        return __(':count trips and activities', ['count' => $this->package_limit]);
    }

    /**
     * Rank used for upgrade / downgrade (Starter < Growth < Agency).
     */
    public function tierRank(): int
    {
        return match ($this->slug) {
            'enterprise' => 4,
            'agency' => 3,
            'growth' => 2,
            default => 1,
        };
    }

    /**
     * Get or create the default Starter plan.
     */
    public static function getDefaultPlan(): self
    {
        $plan = self::where('slug', 'starter')->first();

        if ($plan) {
            return $plan;
        }

        self::seedDefaultPlans();

        /** @var self $starter */
        $starter = self::where('slug', 'starter')->first();

        return $starter;
    }

    /**
     * Seed initial standard subscription plans.
     */
    public static function seedDefaultPlans(): void
    {
        $legacyAgency = self::where('slug', 'enterprise')->first();
        if ($legacyAgency && ! self::where('slug', 'agency')->exists()) {
            $legacyAgency->update(['slug' => 'agency']);
        }

        self::updateOrCreate(['slug' => 'starter'], [
            'name' => 'Starter',
            'tagline' => 'For freelance tour guides. Your website, booking, and pay in one place.',
            'price_monthly' => 0.00,
            'price_yearly' => 0.00,
            'commission_rate' => 0.0000,
            'package_limit' => 5,
            'team_member_limit' => 2,
            'features' => self::featureFlags(),
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);

        self::updateOrCreate(['slug' => 'growth'], [
            'name' => 'Growth',
            'tagline' => 'For freelance guides who need more, or a small group selling together.',
            'price_monthly' => 299000.00,
            'price_yearly' => 2990000.00,
            'commission_rate' => 0.0000,
            'package_limit' => 25,
            'team_member_limit' => null,
            'features' => self::featureFlags([
                'advanced_calendar' => true,
                'daily_manifest_export' => true,
                'google_calendar' => true,
                'guest_crm' => true,
                'whatsapp_dispatch' => true,
                'tracking_pixels' => true,
                'automated_review_requests' => true,
            ]),
            'is_active' => true,
            'is_popular' => true,
            'sort_order' => 2,
        ]);

        $agency = self::updateOrCreate(['slug' => 'agency'], [
            'name' => 'Agency',
            'tagline' => 'For small to mid travel agencies. Your own website address and white-label booking page.',
            'price_monthly' => 799000.00,
            'price_yearly' => 7990000.00,
            'commission_rate' => 0.0000,
            'package_limit' => null,
            'team_member_limit' => null,
            'features' => self::featureFlags([
                'advanced_calendar' => true,
                'daily_manifest_export' => true,
                'capacity_heatmap' => true,
                'google_calendar' => true,
                'guest_crm' => true,
                'whatsapp_dispatch' => true,
                'tracking_pixels' => true,
                'automated_review_requests' => true,
                'custom_domain' => true,
                'priority_support' => true,
                'ai_discovery' => true,
                'remove_branding' => true,
            ]),
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 3,
        ]);

        $hiddenEnterprise = self::where('slug', 'enterprise')->first();
        if ($hiddenEnterprise) {
            Operator::where('plan_id', $hiddenEnterprise->id)->update(['plan_id' => $agency->id]);
            $hiddenEnterprise->delete();
        }

        $legacyAiPlan = self::where('slug', 'ai_ultimate')->first();
        if ($legacyAiPlan) {
            Operator::where('plan_id', $legacyAiPlan->id)->update(['plan_id' => $agency->id]);
            $legacyAiPlan->delete();
        }
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    public static function featureFlags(array $overrides = []): array
    {
        return [
            'custom_subdomain' => true,
            'standard_checkout' => true,
            'reservations_management' => true,
            'promotional_coupons' => true,
            'whatsapp_chat_widget' => true,
            'quick_booking_links' => true,
            'basic_calendar' => true,
            'advanced_calendar' => false,
            'daily_manifest_export' => false,
            'capacity_heatmap' => false,
            'google_calendar' => false,
            'guest_crm' => false,
            'whatsapp_dispatch' => false,
            'tracking_pixels' => false,
            'automated_review_requests' => false,
            'custom_domain' => false,
            'byo_gateway' => false,
            'priority_support' => false,
            'ai_discovery' => false,
            'remove_branding' => false,
            'qr_checkin' => false,
            'departure_slots' => false,
            'ground_crew_roles' => false,
            ...$overrides,
        ];
    }
}
