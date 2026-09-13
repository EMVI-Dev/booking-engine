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
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();
});

test('activity-only shops hide empty package chrome and redirect the packages catalog', function () {
    $operator = Operator::factory()->create([
        'name' => 'Solo Guide Tours',
        'slug' => 'solo-guide',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'solo-guide.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Mount Batur Sunrise Trek',
        'slug' => 'mount-batur-sunrise-trek',
        'sellable_standalone' => true,
        'status' => ListingStatus::Published,
    ]);

    $headers = ['Host' => 'solo-guide.booking.test'];
    $host = 'http://solo-guide.booking.test';

    $this->get($host.'/', $headers)
        ->assertOk()
        ->assertSee('Mount Batur Sunrise Trek')
        ->assertSee('Single Activities')
        ->assertDontSee('Tour Packages')
        ->assertDontSee('Curated Packages & Expeditions')
        ->assertDontSee('No tour packages published yet.')
        ->assertDontSee(route('storefront.packages', absolute: false));

    $this->get($host.'/tours', $headers)
        ->assertRedirect(route('home'));
});

test('dark brand accents stay lightened so active catalog text stays readable', function () {
    $operator = Operator::factory()->create([
        'name' => 'Ink Brand Tours',
        'slug' => 'ink-brand',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
        'settings' => [
            'sellable_standalone_default' => true,
            'brand_color' => '#0f172a',
            'display_name' => 'Ink Brand Tours',
        ],
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'ink-brand.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Harbor Walk',
        'slug' => 'harbor-walk',
        'sellable_standalone' => true,
        'status' => ListingStatus::Published,
    ]);

    $response = $this->get('http://ink-brand.booking.test/', ['Host' => 'ink-brand.booking.test'])
        ->assertOk()
        ->assertSee('Catalog')
        ->assertSee('bg-brand-600 text-brand-foreground', false);

    expect($response->getContent())
        ->toContain('--color-brand-400: color-mix(in srgb, var(--brand-color) 62%, white)')
        ->not->toContain('--color-brand-400: var(--brand-color)');
});

test('published packages restore package chrome on the guest shop', function () {
    $operator = Operator::factory()->create([
        'name' => 'Bundle Ready Tours',
        'slug' => 'bundle-ready',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'bundle-ready.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Ubud Day Combo',
        'slug' => 'ubud-day-combo',
        'status' => ListingStatus::Published,
    ]);

    $headers = ['Host' => 'bundle-ready.booking.test'];
    $host = 'http://bundle-ready.booking.test';

    $this->get($host.'/', $headers)
        ->assertOk()
        ->assertSee('Tour Packages')
        ->assertSee('Ubud Day Combo')
        ->assertSee('Curated Packages & Expeditions')
        ->assertDontSee(route('storefront.products', absolute: false));

    $this->get($host.'/tours', $headers)
        ->assertOk()
        ->assertSee('Ubud Day Combo');
});

test('empty storefront renders branded empty-home state and redirects catalog routes', function () {
    $operator = Operator::factory()->create([
        'name' => 'Bare Ocean Tours',
        'slug' => 'bare-ocean',
        'status' => OperatorStatus::Approved,
        'contact_whatsapp' => '+6281234567890',
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'bare-ocean.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $headers = ['Host' => 'bare-ocean.booking.test'];
    $host = 'http://bare-ocean.booking.test';

    // 1. Home empty-state
    $this->get($host.'/', $headers)
        ->assertOk()
        ->assertSee('New experiences coming soon')
        ->assertSee('Message on WhatsApp')
        ->assertSee('Find Existing Booking')
        ->assertDontSee('Top Featured')
        ->assertDontSee('Curated Packages & Expeditions');

    // 2. Both /tours and /services redirect to home
    $this->get($host.'/tours', $headers)
        ->assertRedirect(route('home'));

    $this->get($host.'/services', $headers)
        ->assertRedirect(route('home'));

    // 3. Sitemap omits /tours and /services catalog links
    $sitemapResp = $this->get($host.'/sitemap.xml', $headers)
        ->assertOk()
        ->assertSee('<loc>http://bare-ocean.booking.test</loc>', false)
        ->assertDontSee('<loc>http://bare-ocean.booking.test/tours</loc>', false)
        ->assertDontSee('<loc>http://bare-ocean.booking.test/services</loc>', false);

    // 4. Robots.txt disallows /reservations/
    $this->get($host.'/robots.txt', $headers)
        ->assertOk()
        ->assertSee("Disallow: /reservations/\n", false)
        ->assertSee("Disallow: /checkout/\n", false);
});

test('package-only storefront redirects services and omits services from sitemap', function () {
    $operator = Operator::factory()->create([
        'name' => 'Package Solo Tours',
        'slug' => 'package-solo',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'package-solo.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Volcano Summit Trek',
        'slug' => 'volcano-summit-trek',
        'status' => ListingStatus::Published,
    ]);

    $headers = ['Host' => 'package-solo.booking.test'];
    $host = 'http://package-solo.booking.test';

    $this->get($host.'/services', $headers)
        ->assertRedirect(route('home'));

    $this->get($host.'/sitemap.xml', $headers)
        ->assertOk()
        ->assertSee('<loc>http://package-solo.booking.test/tours</loc>', false)
        ->assertDontSee('<loc>http://package-solo.booking.test/services</loc>', false)
        ->assertSee('<loc>http://package-solo.booking.test/packages/volcano-summit-trek</loc>', false);
});

test('mobile sticky booking bar includes safe-area-inset-bottom and generous bottom padding', function () {
    $operator = Operator::factory()->create([
        'name' => 'Sticky Bar Tours',
        'slug' => 'sticky-bar',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'sticky-bar.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $pkg = Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Coral Reef Kayak',
        'slug' => 'coral-reef-kayak',
        'status' => ListingStatus::Published,
    ]);

    $prod = Product::factory()->create([
        'operator_id' => $operator->id,
        'name' => 'Snorkel Mask Rental',
        'slug' => 'snorkel-mask-rental',
        'sellable_standalone' => true,
        'status' => ListingStatus::Published,
    ]);

    $headers = ['Host' => 'sticky-bar.booking.test'];
    $host = 'http://sticky-bar.booking.test';

    $this->get($host.'/packages/'.$pkg->slug, $headers)
        ->assertOk()
        ->assertSee('pb-[calc(1rem+env(safe-area-inset-bottom,0px))]', false);

    $this->get($host.'/products/'.$prod->slug, $headers)
        ->assertOk()
        ->assertSee('pb-[calc(1rem+env(safe-area-inset-bottom,0px))]', false);
});
