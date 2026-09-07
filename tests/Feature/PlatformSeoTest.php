<?php

use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('platform homepage has canonical share tags and software schema', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/').'" />', false)
        ->assertSee('<meta property="og:image" content="'.url('/images/hero-cover.jpg').'" />', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', false)
        ->assertSee('Guests book themselves. You keep the listed price.', false)
        ->assertSee('you keep 100% of the listed price', false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SoftwareApplication"', false)
        ->assertSee('"@id":"'.url('/').'#organization"', false)
        ->assertSee('"slogan":"Guests book themselves. You keep the listed price."', false)
        ->assertDontSee('"@type":"TravelAgency"', false)
        ->assertDontSee('WebApplication', false)
        ->assertDontSee('Online Booking System for Tour Operators', false)
        ->assertDontSee('platform commission', false);
});

test('platform legal pages have webpage schema and breadcrumbs', function () {
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('legal.terms').'" />', false)
        ->assertSee('"@type":"WebPage"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('Platform terms', false);

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('legal.privacy').'" />', false)
        ->assertSee('"@type":"WebPage"', false)
        ->assertSee('UU PDP');
});

test('platform sitemap lists legal pages', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>'.url('/').'</loc>', false)
        ->assertSee('<loc>'.url('/legal').'</loc>', false)
        ->assertSee('<loc>'.url('/privacy').'</loc>', false);
});
