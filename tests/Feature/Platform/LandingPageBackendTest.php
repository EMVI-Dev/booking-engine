<?php

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Services\PlatformSeoService;

test('the landing page never writes plans to the database', function () {
    expect(Plan::count())->toBe(0);

    $this->get('http://booking.test/')->assertOk();

    expect(Plan::count())->toBe(0);
});

test('structured-data offers follow the live plan catalog', function () {
    Plan::seedDefaultPlans();
    Plan::where('slug', 'growth')->update(['price_monthly' => 123000]);

    $offers = collect(app(PlatformSeoService::class)->homeGraph()['@graph'])
        ->firstWhere('@type', 'SoftwareApplication')['offers'];

    expect(collect($offers)->firstWhere('name', 'Growth Plan')['price'])->toBe('123000');
});

test('the faq states the fee from the platform setting', function () {
    $platform = PlatformSetting::current();
    $platform->update(['settings' => array_merge($platform->settings ?? [], ['guest_service_fee_rate' => 0.04, 'guest_service_fee_cap' => 200000])]);

    expect(app(PlatformSeoService::class)->guestFeeSummary())->toBe('4% (max Rp 200.000)');
});

test('robots blocks the bare dashboard and admin paths', function () {
    $this->get('http://booking.test/robots.txt')
        ->assertOk()
        ->assertSee("Disallow: /dashboard\n", false)
        ->assertSee("Disallow: /admin\n", false);
});
