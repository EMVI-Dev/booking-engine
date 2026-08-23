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
        self::updateOrCreate(['slug' => 'starter'], [
            'name' => 'Starter Essential',
            'tagline' => 'Launch your tour business with zero monthly subscription cost and pay-as-you-book model.',
            'price_monthly' => 0.00,
            'price_yearly' => 0.00,
            'commission_rate' => 0.0000, // 100% Net to Operator
            'package_limit' => 5,
            'team_member_limit' => null, // unlimited
            'features' => [
                'custom_subdomain' => true,
                'standard_checkout' => true,
                'reservations_management' => true,
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
            ],
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);

        self::updateOrCreate(['slug' => 'growth'], [
            'name' => 'Pro Operator',
            'tagline' => 'Designed for growing tour operators and activity companies needing Fleet Timeline Matrix, CRM, and automation.',
            'price_monthly' => 299000.00,
            'price_yearly' => 2990000.00,
            'commission_rate' => 0.0000, // 100% Net to Operator
            'package_limit' => 25,
            'team_member_limit' => null, // unlimited
            'features' => [
                'custom_subdomain' => true,
                'standard_checkout' => true,
                'reservations_management' => true,
                'whatsapp_chat_widget' => true,
                'quick_booking_links' => true,
                'basic_calendar' => true,
                'advanced_calendar' => true,
                'daily_manifest_export' => true,
                'capacity_heatmap' => false,
                'google_calendar' => true,
                'guest_crm' => true,
                'whatsapp_dispatch' => true,
                'tracking_pixels' => true,
                'automated_review_requests' => true,
                'custom_domain' => false,
                'byo_gateway' => false,
                'priority_support' => false,
            ],
            'is_active' => true,
            'is_popular' => true,
            'sort_order' => 2,
        ]);

        self::updateOrCreate(['slug' => 'enterprise'], [
            'name' => 'Agency Ultimate',
            'tagline' => 'White-label branding on your custom domain with SSL, BYO payment gateway, Capacity Heatmap Analytics, and unlimited listings.',
            'price_monthly' => 699000.00,
            'price_yearly' => 6990000.00,
            'commission_rate' => 0.0000, // 100% Net to Operator
            'package_limit' => null, // unlimited
            'team_member_limit' => null, // unlimited
            'features' => [
                'custom_subdomain' => true,
                'standard_checkout' => true,
                'reservations_management' => true,
                'whatsapp_chat_widget' => true,
                'quick_booking_links' => true,
                'basic_calendar' => true,
                'advanced_calendar' => true,
                'daily_manifest_export' => true,
                'capacity_heatmap' => true,
                'google_calendar' => true,
                'guest_crm' => true,
                'whatsapp_dispatch' => true,
                'tracking_pixels' => true,
                'automated_review_requests' => true,
                'custom_domain' => true,
                'byo_gateway' => true,
                'priority_support' => true,
            ],
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 3,
        ]);
    }
}
