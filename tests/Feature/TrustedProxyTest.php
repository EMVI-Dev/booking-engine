<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;

uses(RefreshDatabase::class);

test('https from the local reverse proxy is trusted', function () {
    $this->call('GET', '/', server: [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
    ])->assertOk();

    expect(request()->secure())->toBeTrue()
        ->and(request()->ip())->toBe('203.0.113.10');
});

test('forwarded https from an untrusted client is ignored', function () {
    $this->call('GET', '/', server: [
        'REMOTE_ADDR' => '198.51.100.4',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
    ])->assertOk();

    expect(request()->secure())->toBeFalse()
        ->and(request()->ip())->toBe('198.51.100.4');
});

test('TRUSTED_PROXIES=* trusts the managed edge on Laravel Cloud', function () {
    config(['app.trusted_proxies' => '*']);
    (new AppServiceProvider(app()))->boot();

    $this->call('GET', '/', server: [
        'REMOTE_ADDR' => '10.20.30.40',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
    ])->assertOk();

    expect(request()->secure())->toBeTrue()
        ->and(request()->ip())->toBe('203.0.113.10');

    TrustProxies::at(['127.0.0.1', '::1']);
});
