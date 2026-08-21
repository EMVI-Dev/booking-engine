<?php

namespace App\Models;

use App\Enums\DokuMode;
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
                'booking_hold_minutes' => 30, // 30-minute hold window for unpaid reservations
                'doku_mode' => config('doku.default_mode', 'sandbox'),
                'currency_code' => 'IDR',
                'currency_symbol' => 'Rp',
                'platform_name' => config('app.name', 'Emvi Booking Platform'),
                'support_email' => 'support@emvi.dev',
                'doku' => [
                    'mode' => config('doku.default_mode', 'sandbox'),
                    'sandbox' => [
                        'client_id' => (string) config('doku.sandbox.client_id', ''),
                        'secret_key' => (string) config('doku.sandbox.secret_key', ''),
                        'doku_public_key' => (string) config('doku.sandbox.doku_public_key', ''),
                        'merchant_public_key' => (string) config('doku.sandbox.merchant_public_key', ''),
                        'merchant_private_key' => (string) config('doku.sandbox.merchant_private_key', ''),
                        'snap_token_url' => (string) config('doku.sandbox.snap_token_url', 'https://api-sandbox.doku.com/authorization/v1/access-token/b2b'),
                        'base_url' => (string) config('doku.sandbox.base_url', 'https://api-sandbox.doku.com'),
                        'checkout_url' => (string) config('doku.sandbox.checkout_url', 'https://jokul-sandbox.doku.com/checkout'),
                    ],
                    'live' => [
                        'client_id' => (string) config('doku.live.client_id', ''),
                        'secret_key' => (string) config('doku.live.secret_key', ''),
                        'doku_public_key' => (string) config('doku.live.doku_public_key', ''),
                        'merchant_public_key' => (string) config('doku.live.merchant_public_key', ''),
                        'merchant_private_key' => (string) config('doku.live.merchant_private_key', ''),
                        'snap_token_url' => (string) config('doku.live.snap_token_url', 'https://api.doku.com/authorization/v1/access-token/b2b'),
                        'base_url' => (string) config('doku.live.base_url', 'https://api.doku.com'),
                        'checkout_url' => (string) config('doku.live.checkout_url', 'https://jokul.doku.com/checkout'),
                    ],
                ],
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

    public function getBookingHoldMinutes(): int
    {
        return (int) ($this->settings['booking_hold_minutes'] ?? 30);
    }

    public function getDokuMode(): DokuMode
    {
        $mode = $this->settings['doku']['mode'] ?? ($this->settings['doku_mode'] ?? config('doku.default_mode', 'sandbox'));

        return DokuMode::tryFrom((string) $mode) ?? DokuMode::Sandbox;
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
        return (string) ($this->settings['support_email'] ?? 'support@emvi.dev');
    }

    public function getDokuSandboxClientId(): string
    {
        $val = (string) ($this->settings['doku']['sandbox']['client_id'] ?? '');

        return $val !== '' ? $val : (string) config('doku.sandbox.client_id', '');
    }

    public function getDokuSandboxSecretKey(): string
    {
        $val = (string) ($this->settings['doku']['sandbox']['secret_key'] ?? ($this->settings['doku']['sandbox']['shared_key'] ?? ''));

        return $val !== '' ? $val : (string) config('doku.sandbox.secret_key', '');
    }

    public function getDokuSandboxDokuPublicKey(): string
    {
        $val = (string) ($this->settings['doku']['sandbox']['doku_public_key'] ?? '');

        return $val !== '' ? $val : (string) config('doku.sandbox.doku_public_key', '');
    }

    public function getDokuSandboxMerchantPublicKey(): string
    {
        $val = (string) ($this->settings['doku']['sandbox']['merchant_public_key'] ?? '');

        return $val !== '' ? $val : (string) config('doku.sandbox.merchant_public_key', '');
    }

    public function getDokuSandboxMerchantPrivateKey(): string
    {
        $val = (string) ($this->settings['doku']['sandbox']['merchant_private_key'] ?? '');

        return $val !== '' ? $val : (string) config('doku.sandbox.merchant_private_key', '');
    }

    public function getDokuSandboxSnapTokenUrl(): string
    {
        $val = (string) ($this->settings['doku']['sandbox']['snap_token_url'] ?? '');

        return $val !== '' ? $val : (string) config('doku.sandbox.snap_token_url', 'https://api-sandbox.doku.com/authorization/v1/access-token/b2b');
    }

    public function getDokuLiveClientId(): string
    {
        $val = (string) ($this->settings['doku']['live']['client_id'] ?? '');

        return $val !== '' ? $val : (string) config('doku.live.client_id', '');
    }

    public function getDokuLiveSecretKey(): string
    {
        $val = (string) ($this->settings['doku']['live']['secret_key'] ?? ($this->settings['doku']['live']['shared_key'] ?? ''));

        return $val !== '' ? $val : (string) config('doku.live.secret_key', '');
    }

    public function getDokuLiveDokuPublicKey(): string
    {
        $val = (string) ($this->settings['doku']['live']['doku_public_key'] ?? '');

        return $val !== '' ? $val : (string) config('doku.live.doku_public_key', '');
    }

    public function getDokuLiveMerchantPublicKey(): string
    {
        $val = (string) ($this->settings['doku']['live']['merchant_public_key'] ?? '');

        return $val !== '' ? $val : (string) config('doku.live.merchant_public_key', '');
    }

    public function getDokuLiveMerchantPrivateKey(): string
    {
        $val = (string) ($this->settings['doku']['live']['merchant_private_key'] ?? '');

        return $val !== '' ? $val : (string) config('doku.live.merchant_private_key', '');
    }

    public function getDokuLiveSnapTokenUrl(): string
    {
        $val = (string) ($this->settings['doku']['live']['snap_token_url'] ?? '');

        return $val !== '' ? $val : (string) config('doku.live.snap_token_url', 'https://api.doku.com/authorization/v1/access-token/b2b');
    }
}
