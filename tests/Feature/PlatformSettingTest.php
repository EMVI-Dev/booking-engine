<?php

use App\Enums\DokuMode;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('platform setting returns default values when none exist', function () {
    $settings = PlatformSetting::current();

    expect($settings->getCommissionRate())->toBe(0.00)
        ->and($settings->getGuestServiceFeeRate())->toBe(0.05)
        ->and($settings->getBookingHoldMinutes())->toBe(30)
        ->and($settings->getDokuMode())->toBe(DokuMode::Sandbox);
});

test('platform setting reads updated config', function () {
    $settings = PlatformSetting::current();
    $settings->update([
        'settings' => [
            'commission_rate' => 0.15,
            'booking_hold_minutes' => 45,
            'doku_mode' => 'live',
        ],
    ]);

    $fresh = PlatformSetting::current();
    expect($fresh->getCommissionRate())->toBe(0.15)
        ->and($fresh->getBookingHoldMinutes())->toBe(45)
        ->and($fresh->getDokuMode())->toBe(DokuMode::Live);
});
