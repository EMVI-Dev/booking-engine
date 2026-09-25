<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

test('platform homepage renders platform Google Analytics tag when configured', function () {
    Config::set('services.google.platform_analytics_id', 'G-WBQZPFT82S');

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-WBQZPFT82S');
    $response->assertSee("gtag('config', 'G-WBQZPFT82S');", false);
    $response->assertSee('livewire:navigated');
});

test('platform login page renders platform Google Analytics tag', function () {
    Config::set('services.google.platform_analytics_id', 'G-WBQZPFT82S');

    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-WBQZPFT82S');
    $response->assertSee("gtag('config', 'G-WBQZPFT82S');", false);
});

test('platform pages do not render tracking tag when analytics id is not set', function () {
    Config::set('services.google.platform_analytics_id', null);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee('https://www.googletagmanager.com/gtag/js?id=');
    $response->assertDontSee('Google Analytics 4 - Platform');
});

test('storefront pages do not render platform analytics id', function () {
    Config::set('services.google.platform_analytics_id', 'G-WBQZPFT82S');

    $operator = Operator::factory()->create([
        'slug' => 'bromo-tours',
        'status' => OperatorStatus::Approved,
        'settings' => [
            'tracking' => [
                'google_analytics_id' => 'G-OPERATOR99',
            ],
        ],
    ]);

    Package::factory()->create([
        'operator_id' => $operator->id,
        'status' => 'published',
        'slug' => 'sunrise-bromo',
    ]);

    $response = $this->get("http://{$operator->slug}.booking.test/");

    $response->assertOk();
    $response->assertSee('G-OPERATOR99');
    $response->assertDontSee('G-WBQZPFT82S');
});
