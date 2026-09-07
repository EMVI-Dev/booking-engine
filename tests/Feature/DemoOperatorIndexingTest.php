<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Http\Middleware\PreventDemoIndexing;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    $this->host = 'demo.booking.test';
    $this->url = 'http://demo.booking.test';
    $this->headers = ['Host' => $this->host];

    $this->operator = Operator::factory()->demo()->create([
        'name' => 'Demo Tours',
        'slug' => 'demo',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => $this->host,
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Hidden Demo Snorkel',
        'slug' => 'hidden-demo-snorkel',
        'status' => ListingStatus::Published,
    ]);
});

test('public robots.txt is not a static allow-all file', function () {
    expect(file_exists(public_path('robots.txt')))->toBeFalse();
});

test('demo robots.txt disallows every crawler and omits sitemap and llms files', function () {
    $this->get($this->url.'/robots.txt', $this->headers)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('X-Robots-Tag', PreventDemoIndexing::ROBOTS_TAG)
        ->assertSee("User-agent: *\nDisallow: /", false)
        ->assertSee("User-agent: GPTBot\nDisallow: /", false)
        ->assertSee("User-agent: ClaudeBot\nDisallow: /", false)
        ->assertDontSee('Allow: /')
        ->assertDontSee('Sitemap:')
        ->assertDontSee('llms-txt:');
});

test('demo sitemap.xml lists no urls', function () {
    $this->get($this->url.'/sitemap.xml', $this->headers)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', PreventDemoIndexing::ROBOTS_TAG)
        ->assertSee('<urlset', false)
        ->assertDontSee('<loc>', false)
        ->assertDontSee('hidden-demo-snorkel');
});

test('demo llms files 404 without listing tours', function () {
    $this->get($this->url.'/llms.txt', $this->headers)
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', PreventDemoIndexing::ROBOTS_TAG)
        ->assertSee('Do not crawl or index')
        ->assertDontSee('Hidden Demo Snorkel');

    $this->get($this->url.'/llms-full.txt', $this->headers)
        ->assertNotFound()
        ->assertDontSee('Hidden Demo Snorkel');
});

test('demo storefront html is noindex and has no json-ld', function () {
    $this->get($this->url.'/', $this->headers)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', PreventDemoIndexing::ROBOTS_TAG)
        ->assertSee('noindex, nofollow, noarchive, nosnippet', false)
        ->assertDontSee('index, follow, max-snippet', false)
        ->assertDontSee('application/ld+json', false)
        ->assertDontSee('rel="sitemap"', false)
        ->assertDontSee('/llms.txt');
});

test('demo operator dashboard is noindex', function () {
    $user = User::factory()->create();
    $this->operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', PreventDemoIndexing::ROBOTS_TAG)
        ->assertSee('noindex, nofollow, noarchive, nosnippet', false);
});

test('platform home and admin stay indexable when a demo operator exists', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('index, follow', false)
        ->assertSee('rel="noopener nofollow"', false)
        ->assertSee('http://demo.booking.test', false);

    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertDontSee('noindex, nofollow, noarchive, nosnippet', false);
});

test('a real operator storefront is still indexable', function () {
    $operator = Operator::factory()->create([
        'name' => 'Komodo Real Tours',
        'slug' => 'komodo-real',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'komodo-real.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->get('http://komodo-real.booking.test/', ['Host' => 'komodo-real.booking.test'])
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('index, follow, max-snippet', false);
});
