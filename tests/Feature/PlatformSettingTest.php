<?php

use App\Enums\DokuMode;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('platform setting returns default values when none exist', function () {
    $settings = PlatformSetting::current();

    expect($settings->getCommissionRate())->toBe(0.00)
        ->and($settings->getGuestServiceFeeRate())->toBe(0.05)
        ->and($settings->getBookingHoldMinutes())->toBe(30)
        ->and($settings->getDokuMode())->toBe(DokuMode::Sandbox)
        ->and($settings->getOperatorSupportEmail())->toBe('support@travelengine.online');
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

test('operator support never uses a no-reply address', function () {
    $settings = PlatformSetting::current();
    $settings->update([
        'settings' => array_merge($settings->settings ?? [], [
            'support_email' => 'no-reply@travelengine.online',
        ]),
    ]);

    expect($settings->fresh()->getOperatorSupportEmail())->toBe('support@travelengine.online');
});

test('operator support ignores leftover brand mailboxes', function () {
    $settings = PlatformSetting::current();
    $settings->update([
        'settings' => array_merge($settings->settings ?? [], [
            'support_email' => 'support@emvi.dev',
        ]),
    ]);

    expect($settings->fresh()->getOperatorSupportEmail())->toBe('support@travelengine.online');
});

test('guest service fee applies on every plan including agency', function () {
    $settings = PlatformSetting::current();
    $agency = Operator::factory()->create([
        'plan_id' => Plan::factory()->enterprise()->create()->id,
        'settings' => [
            'payment_gateway' => [
                'use_custom_credentials' => true,
                'client_id' => 'MALLID_IGNORED',
                'shared_key' => 'KEY_IGNORED',
            ],
        ],
    ]);

    expect($settings->calculateGuestServiceFee(1_000_000, $agency))->toBe(50_000.0)
        ->and($settings->calculateGuestServiceFee(10_000_000, $agency))->toBe(250_000.0);
});
