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

    $this->operator = Operator::factory()->create([
        'name' => 'Whitebox Reef Tours',
        'slug' => 'whitebox-reef',
        'bio' => 'Snorkel trips around the reef, with hotel pickup.',
        'status' => OperatorStatus::Approved,
        'logo_path' => 'operators/logos/whitebox.png',
        'banner_path' => null,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'whitebox-reef.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Sunrise Reef Walk',
        'slug' => 'sunrise-reef-walk',
        'cover_photo' => 'operators/covers/sunrise-reef.jpg',
        'status' => ListingStatus::Published,
    ]);

    $this->host = 'http://whitebox-reef.booking.test';
    $this->headers = ['Host' => 'whitebox-reef.booking.test'];
});

test('operator home share card uses a tour photo and the operator name', function () {
    $this->get($this->host.'/', $this->headers)
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Whitebox Reef Tours" />', false)
        ->assertSee('<meta property="og:image" content="http://whitebox-reef.booking.test/storage/operators/covers/sunrise-reef.jpg" />', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', false)
        ->assertSee('Snorkel trips around the reef', false)
        ->assertDontSee('Direct Tour Bookings')
        ->assertDontSee('WebApplication')
        ->assertSee('"@type":"TravelAgency"', false)
        ->assertSee('"@type":"TouristTrip"', false);
});

test('operator home without photos uses a compact logo card', function () {
    $this->package->update(['cover_photo' => null]);
    $this->operator->update(['banner_path' => null]);

    $this->get($this->host.'/', $this->headers)
        ->assertOk()
        ->assertSee('<meta property="og:image" content="http://whitebox-reef.booking.test/storage/operators/logos/whitebox.png" />', false)
        ->assertSee('<meta name="twitter:card" content="summary" />', false)
        ->assertDontSee('content="/storage/', false);
});

test('package page share card uses the cover photo as an absolute url', function () {
    $this->get($this->host.'/packages/sunrise-reef-walk', $this->headers)
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Sunrise Reef Walk · Whitebox Reef Tours" />', false)
        ->assertSee('<meta property="og:image" content="http://whitebox-reef.booking.test/storage/operators/covers/sunrise-reef.jpg" />', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', false)
        ->assertSee('"@type":"TouristTrip"', false)
        ->assertDontSee('content="/storage/', false);
});

test('catalog pages emit absolute share images', function () {
    $this->get($this->host.'/tours', $this->headers)
        ->assertOk()
        ->assertSee('http://whitebox-reef.booking.test/storage/operators/covers/sunrise-reef.jpg', false)
        ->assertDontSee('content="/storage/', false);

    Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Mask Hire',
        'slug' => 'mask-hire',
        'cover_photo' => 'operators/covers/mask.jpg',
        'sellable_standalone' => true,
        'status' => ListingStatus::Published,
    ]);

    $this->get($this->host.'/services', $this->headers)
        ->assertOk()
        ->assertSee('http://whitebox-reef.booking.test/storage/operators/covers/mask.jpg', false)
        ->assertDontSee('content="/storage/', false);
});
