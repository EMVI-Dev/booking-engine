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
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SoftwareApplication"', false)
        ->assertSee('"@id":"'.url('/').'#organization"', false)
        ->assertDontSee('"@type":"TravelAgency"', false)
        ->assertDontSee('WebApplication', false);
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
