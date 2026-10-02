<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property array<string, mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlatformSetting extends Model
{
    use HasUlids;

    protected $fillable = [
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * Get the singleton or latest platform settings instance.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'settings' => [
                'commission_rate' => 0.00, // Default 0% commission from operator
                'guest_service_fee_rate' => 0.05, // 5% Guest Service Fee added at checkout
                'guest_service_fee_cap' => 250000.00, // Max Rp 250.000 fee cap for high-ticket bookings (e.g. 100M charters)
                'booking_hold_minutes' => 30, // 30-minute hold window for unpaid reservations
                'currency_code' => 'IDR',
                'currency_symbol' => 'Rp',
                'platform_name' => config('app.name', 'Emvi Booking Platform'),
                'support_email' => 'support@travelengine.id',
            ],
        ]);
    }

    public function getCommissionRate(): float
    {
        return (float) ($this->settings['commission_rate'] ?? 0.00);
    }

    public function getGuestServiceFeeRate(): float
    {
        return (float) ($this->settings['guest_service_fee_rate'] ?? 0.05);
    }

    public function getGuestServiceFeeCap(): float
    {
        return (float) ($this->settings['guest_service_fee_cap'] ?? 250000.00);
    }

    /**
     * Calculate guest service fee with high-ticket cap support (e.g. 100M transaction).
     *
     * Charged on every plan, including Agency. Subscription buys features, not a fee waiver.
     */
    public function calculateGuestServiceFee(float $subtotal, ?Operator $operator = null): float
    {
        $rawFee = round($subtotal * $this->getGuestServiceFeeRate(), 2);
        $cap = $this->getGuestServiceFeeCap();

        return $cap > 0 ? min($rawFee, $cap) : $rawFee;
    }

    public function getBookingHoldMinutes(): int
    {
        return (int) ($this->settings['booking_hold_minutes'] ?? 30);
    }

    public function getCurrencyCode(): string
    {
        return (string) ($this->settings['currency_code'] ?? 'IDR');
    }

    public function getCurrencySymbol(): string
    {
        return (string) ($this->settings['currency_symbol'] ?? 'Rp');
    }

    public function getPlatformName(): string
    {
        return (string) ($this->settings['platform_name'] ?? config('app.name', 'Emvi Booking Platform'));
    }

    public function getSupportEmail(): string
    {
        return $this->getOperatorSupportEmail();
    }

    /**
     * Inbox operators write to. no-reply and leftover brand mailboxes are never used.
     */
    public function getOperatorSupportEmail(): string
    {
        $configured = trim((string) ($this->settings['support_email'] ?? ''));

        if ($configured !== '' && ! $this->isUnusableOperatorSupportEmail($configured)) {
            return $configured;
        }

        return 'support@travelengine.id';
    }

    public function isUnusableOperatorSupportEmail(string $email): bool
    {
        $normalized = strtolower(trim($email));
        $from = strtolower(trim((string) config('mail.from.address', '')));

        if ($normalized === '' || str_starts_with($normalized, 'no-reply@')) {
            return true;
        }

        if ($from !== '' && $normalized === $from) {
            return true;
        }

        return in_array($normalized, [
            'hello@travelengine.id',
            'hello@emvi.dev',
            'support@emvi.dev',
        ], true);
    }

    /**
     * Platform commerce pause (not Laravel artisan down).
     *
     * Admin Settings can override this. Until that toggle is saved, the
     * PLATFORM_MAINTENANCE env value is used.
     */
    public function isPlatformMaintenance(): bool
    {
        $settings = $this->settings ?? [];

        if (array_key_exists('platform_maintenance', $settings)) {
            return filter_var($settings['platform_maintenance'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('app.platform_maintenance');
    }

    public function setPlatformMaintenance(bool $on): void
    {
        $settings = $this->settings ?? [];
        $settings['platform_maintenance'] = $on;
        $this->update(['settings' => $settings]);
    }

    /**
     * Existing operators and the demo desk stay available even when
     * REGISTRATION_ENABLED=false (sign-up can stay closed).
     */
    public function operatorLoginAllowed(): bool
    {
        return true;
    }

    /**
     * New operator accounts are blocked only while platform maintenance is on.
     */
    public function operatorRegistrationAllowed(): bool
    {
        return ! $this->isPlatformMaintenance();
    }

    /**
     * Guest bookings, checkout, and cancellations on every storefront.
     */
    public function storefrontTransactionsAllowed(): bool
    {
        return ! $this->isPlatformMaintenance();
    }

    public function assertStorefrontTransactionsAllowed(): void
    {
        abort_unless($this->storefrontTransactionsAllowed(), 403);
    }

    /**
     * @return list<array{payment_id: string, invoice: string, amount: float, reason: string, found_at: string}>
     */
    public function getUnmatchedPayments(): array
    {
        $items = $this->settings['unmatched_payments'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @param  list<array{payment_id: string, invoice: string, amount: float, reason: string, found_at: string}>  $items
     */
    public function storeUnmatchedPayments(array $items): void
    {
        $settings = $this->settings ?? [];
        $settings['unmatched_payments'] = $items;
        $this->update(['settings' => $settings]);
    }
}
