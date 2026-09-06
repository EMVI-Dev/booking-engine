<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

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
