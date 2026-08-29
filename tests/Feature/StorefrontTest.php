<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('root platform domain serves platform welcome page', function () {
    Cache::flush();
    $response = $this->get('/');

    $response->assertOk()
        ->assertViewIs('welcome')
        ->assertSee('Instant Websites &amp; Booking Engine for Tour Guides &amp; Travel Operators', false);
});

test('operator subdomain serves operator storefront with published listings', function () {
    Cache::flush();

    $operator = Operator::factory()->create([
        'name' => 'Lombok Coral Treks',
        'slug' => 'lombok-coral',
        'status' => OperatorStatus::Approved,
        'contact_whatsapp' => '+62812345678',
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'lombok-coral.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Gili Trawangan 3 Island Snorkel Tour',
        'price' => 850000.00,
        'status' => ListingStatus::Published,
    ]);

    $draftPackage = Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Unpublished Hidden Volcano Trip',
        'status' => ListingStatus::Draft,
    ]);

    $response = $this->get('http://lombok-coral.booking.test/', ['Host' => 'lombok-coral.booking.test']);

    $response->assertOk()
        ->assertViewIs('storefront.index')
        ->assertSee('Lombok Coral Treks')
        ->assertSee('Gili Trawangan 3 Island Snorkel Tour')
        ->assertDontSee('Unpublished Hidden Volcano Trip')
        ->assertSee('application/ld+json', false);
});

test('operator storefront provides dedicated all packages catalog page with schema and filters', function () {
    Cache::flush();

    $operator = Operator::factory()->create([
        'name' => 'Nusa Marine Expeditions',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'nusa-marine.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Manta Point Expedition',
        'category' => 'Snorkeling',
        'status' => ListingStatus::Published,
    ]);

    $response = $this->get('http://nusa-marine.booking.test/tours', ['Host' => 'nusa-marine.booking.test']);

    $response->assertOk()
        ->assertViewIs('storefront.packages')
        ->assertSee('Manta Point Expedition')
        ->assertSee('All Tour Packages')
        ->assertSee('BreadcrumbList')
        ->assertSee('TouristTrip');
});

test('operator storefront provides dedicated all products catalog page with schema and filters', function () {
    Cache::flush();

    $operator = Operator::factory()->create([
        'name' => 'Nusa Marine Expeditions',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'nusa-marine.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'GoPro Hero 12 Rental',
        'sellable_standalone' => true,
        'category' => 'Equipment',
        'status' => ListingStatus::Published,
    ]);

    $response = $this->get('http://nusa-marine.booking.test/services', ['Host' => 'nusa-marine.booking.test']);

    $response->assertOk()
        ->assertViewIs('storefront.products')
        ->assertSee('GoPro Hero 12 Rental')
        ->assertSee('Single Activities', false)
        ->assertSee('BreadcrumbList')
        ->assertSee('Product');
});

test('operator storefront serves AI discovery endpoints including robots.txt, sitemap.xml, and llms.txt', function () {
    Cache::flush();

    Plan::seedDefaultPlans();
    $aiPlan = Plan::where('slug', 'ai_ultimate')->first();

    $operator = Operator::factory()->create([
        'name' => 'Komodo Dragon Charters',
        'bio' => 'Private luxury liveaboard and speedboat charters across Komodo National Park.',
        'status' => OperatorStatus::Approved,
        'contact_whatsapp' => '+628199988877',
        'plan_id' => $aiPlan?->id,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'komodo.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Padar Island & Pink Beach Cruise',
        'slug' => 'padar-island-cruise',
        'price' => 1750000.00,
        'status' => ListingStatus::Published,
        'inclusions' => ['Lunch Buffet', 'Snorkel Gear', 'Park Fees'],
    ]);

    $product = Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Underwater Camera Rental',
        'slug' => 'underwater-camera-rental',
        'price' => 250000.00,
        'sellable_standalone' => true,
        'status' => ListingStatus::Published,
    ]);

    // Test 1: robots.txt with AI crawlers and sitemap references
    $robotsResp = $this->get('http://komodo.booking.test/robots.txt', ['Host' => 'komodo.booking.test']);
    $robotsResp->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: GPTBot')
        ->assertSee('User-agent: PerplexityBot')
        ->assertSee('User-agent: ClaudeBot')
        ->assertSee('Sitemap: http://komodo.booking.test/sitemap.xml')
        ->assertSee('llms-txt: http://komodo.booking.test/llms.txt');

    // Test 2: sitemap.xml with catalog, package, and product URLs
    $sitemapResp = $this->get('http://komodo.booking.test/sitemap.xml', ['Host' => 'komodo.booking.test']);
    $sitemapResp->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>http://komodo.booking.test</loc>', false)
        ->assertSee('<loc>http://komodo.booking.test/tours</loc>', false)
        ->assertSee('<loc>http://komodo.booking.test/services</loc>', false)
        ->assertSee('<loc>http://komodo.booking.test/packages/padar-island-cruise</loc>', false)
        ->assertSee('<loc>http://komodo.booking.test/products/underwater-camera-rental</loc>', false);

    // Test 3: llms.txt standard AI summary
    $llmsResp = $this->get('http://komodo.booking.test/llms.txt', ['Host' => 'komodo.booking.test']);
    $llmsResp->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('# Komodo Dragon Charters - Direct Tour & Activity Booking Portal', false)
        ->assertSee('Padar Island & Pink Beach Cruise', false)
        ->assertSee('Underwater Camera Rental', false)
        ->assertSee('Terms & Cancellation Policies', false);

    // Test 4: llms-full.txt complete LLM knowledge base
    $llmsFullResp = $this->get('http://komodo.booking.test/llms-full.txt', ['Host' => 'komodo.booking.test']);
    $llmsFullResp->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('# Komodo Dragon Charters Comprehensive Operator Knowledge Base', false)
        ->assertSee('Direct Booking Architecture & Reservation Lifecycle', false)
        ->assertSee('Lunch Buffet, Snorkel Gear, Park Fees', false);
});

test('storefront reflects custom brand accent hex color in CSS variables and views', function () {
    Cache::flush();

    $operator = Operator::factory()->create([
        'name' => 'Komodo Dragon Charters',
        'slug' => 'komodo',
        'status' => OperatorStatus::Approved,
        'settings' => [
            'brand_color' => '#0ea5e9',
        ],
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'komodo.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $pkg = Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Padar Island Cruise',
        'slug' => 'padar-island-cruise',
        'status' => ListingStatus::Published,
    ]);

    $prod = Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Underwater Camera Rental',
        'slug' => 'underwater-camera-rental',
        'status' => ListingStatus::Published,
    ]);

    expect($operator->brand_color)->toBe('#0ea5e9');

    // Homepage
    $this->get('http://komodo.booking.test', ['Host' => 'komodo.booking.test'])
        ->assertOk()
        ->assertSee('--brand-color: #0ea5e9', false);

    // Packages catalog
    $this->get('http://komodo.booking.test/tours', ['Host' => 'komodo.booking.test'])
        ->assertOk()
        ->assertSee('--brand-color: #0ea5e9', false);

    // Services catalog
    $this->get('http://komodo.booking.test/services', ['Host' => 'komodo.booking.test'])
        ->assertOk()
        ->assertSee('--brand-color: #0ea5e9', false);

    // Package single page
    $this->get('http://komodo.booking.test/packages/padar-island-cruise', ['Host' => 'komodo.booking.test'])
        ->assertOk()
        ->assertSee('--brand-color: #0ea5e9', false);

    // Product single page
    $this->get('http://komodo.booking.test/products/underwater-camera-rental', ['Host' => 'komodo.booking.test'])
        ->assertOk()
        ->assertSee('--brand-color: #0ea5e9', false);

    // Terms page
    $this->get('http://komodo.booking.test/terms', ['Host' => 'komodo.booking.test'])
        ->assertOk()
        ->assertSee('--brand-color: #0ea5e9', false);
});

test('storefront displays operator logo and mobile hamburger navigation menu', function () {
    Cache::flush();

    $operator = Operator::factory()->create([
        'name' => 'Bali Coastal Adventures',
        'slug' => 'bali-coastal',
        'logo_path' => 'operators/logos/bali-coastal-logo.png',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'bali-coastal.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Nusa Penida Snorkel Safari',
        'slug' => 'nusa-penida-snorkel-safari',
        'status' => ListingStatus::Published,
    ]);

    $response = $this->get('http://bali-coastal.booking.test', ['Host' => 'bali-coastal.booking.test']);

    $response->assertOk()
        ->assertSee('storage/operators/logos/bali-coastal-logo.png', false)
        ->assertSee('mobileMenuOpen', false)
        ->assertSee('fa-bars', false)
        ->assertSee('Packages', false);
});
