<?php

use App\Models\Operator;
use App\Services\CustomDomains\DomainName;
use App\Services\GooglePlacesService;
use App\Services\Integrations\DokuClient;
use App\Services\Integrations\GooglePlacesClient;
use App\Services\Integrations\SlackClient;
use App\Services\MediaStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function configureDokuSandbox(): void
{
    config([
        'doku.mode' => 'sandbox',
        'doku.sandbox.client_id' => 'BRN-TEST',
        'doku.sandbox.secret_key' => 'SK-TEST',
        'doku.sandbox.base_url' => 'https://api-sandbox.doku.test',
    ]);
}

// ── DOKU ─────────────────────────────────────────────────────────────────────

test('doku client signs checkout requests and returns the hosted payment url', function () {
    configureDokuSandbox();

    Http::fake([
        '*/checkout/v1/payment' => Http::response(['response' => ['payment' => ['url' => 'https://pay.doku.test/abc']]]),
    ]);

    $url = app(DokuClient::class)->createCheckout(
        invoiceNumber: 'INV-TEST-1',
        amount: 150000.4,
        callbackUrl: 'https://shop.test/receipt',
        dueMinutes: 30,
        customer: ['name' => 'Sarah', 'email' => 'sarah@example.com', 'phone' => '+62 812-3456-7890'],
    );

    expect($url)->toBe('https://pay.doku.test/abc');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Client-Id', 'BRN-TEST')
        && str_starts_with($request->header('Signature')[0] ?? '', 'HMACSHA256=')
        && $request['order']['amount'] === 150000
        && $request['customer']['phone'] === '6281234567890');
});

test('doku client reports a refused refund as not refunded', function () {
    configureDokuSandbox();

    Http::fake(['*/orders/v1/refund' => Http::response(['error' => 'nope'], 400)]);

    expect(app(DokuClient::class)->refund('INV-TEST-1', 1000))->toBeFalse();
});

test('doku client makes no calls without credentials', function () {
    Http::fake();

    expect(app(DokuClient::class)->createCheckout('INV-1', 1000, 'https://x.test', 30, ['name' => 'A', 'email' => 'a@b.c', 'phone' => null]))->toBeNull()
        ->and(app(DokuClient::class)->orderStatus('INV-1'))->toBeNull();

    Http::assertNothingSent();
});

// ── Slack ────────────────────────────────────────────────────────────────────

test('slack client only ever posts to hooks.slack.com', function () {
    Http::fake();

    $slack = app(SlackClient::class);

    expect($slack->post('http://169.254.169.254/latest/meta-data', ['text' => 'x']))->toBeFalse()
        ->and($slack->post('https://evil.test/hook', ['text' => 'x']))->toBeFalse();

    Http::assertNothingSent();

    expect($slack->post('https://hooks.slack.com/services/T/B/C', ['text' => 'hello']))->toBeTrue();

    Http::assertSentCount(1);
});

// ── Google ───────────────────────────────────────────────────────────────────

test('a pasted place id is only checked with an id-only request; other input sends nothing', function () {
    config(['services.google.places_key' => 'test-key']);
    Http::fake(['places.googleapis.com/*' => Http::response(['id' => 'ChIJsunrise1234567890'])]);

    $places = app(GooglePlacesService::class);

    expect($places->checkPastedPlaceId('https://internal.service.local/secret')['status'])->toBe(GooglePlacesClient::PLACE_MISSING)
        ->and($places->checkPastedPlaceId('EMVI')['status'])->toBe(GooglePlacesClient::PLACE_MISSING);

    Http::assertNothingSent();

    expect($places->checkPastedPlaceId('  ChIJsunrise1234567890 '))->toBe(['status' => GooglePlacesClient::PLACE_FOUND, 'place_id' => 'ChIJsunrise1234567890']);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-FieldMask', 'id'));
});

// ── Domain names ─────────────────────────────────────────────────────────────

test('website addresses are normalized and apex names detected', function () {
    expect(DomainName::normalize('HTTPS://Tours.Example.com/booking?x=1'))->toBe('tours.example.com')
        ->and(DomainName::normalize('example.com.'))->toBe('example.com')
        ->and(DomainName::normalize('192.168.1.10'))->toBeNull()
        ->and(DomainName::normalize('localhost'))->toBeNull()
        ->and(DomainName::normalize('bad_domain.com'))->toBeNull()
        ->and(DomainName::isApex('example.com'))->toBeTrue()
        ->and(DomainName::isApex('example.co.id'))->toBeTrue()
        ->and(DomainName::isApex('tours.example.co.id'))->toBeFalse()
        ->and(DomainName::recordName('tours.example.co.id'))->toBe('tours')
        ->and(DomainName::recordName('example.com'))->toBe('@');
});

// ── Cloudflare R2 media ──────────────────────────────────────────────────────

test('media on an object storage disk is written without an ACL', function () {
    config(['filesystems.media' => 'r2']);
    Storage::fake('r2');

    $path = app(MediaStore::class)->storeImageContents(
        (function (): string {
            $image = imagecreatetruecolor(20, 10);
            ob_start();
            imagepng($image);

            return (string) ob_get_clean();
        })(),
        app(MediaStore::class)->directoryFor(Operator::factory()->create(), 'brand'),
    );

    Storage::disk('r2')->assertExists($path);

    expect(config('filesystems.disks.r2'))->not->toHaveKey('visibility')
        ->and(config('filesystems.disks.r2.throw'))->toBeTrue();
});
