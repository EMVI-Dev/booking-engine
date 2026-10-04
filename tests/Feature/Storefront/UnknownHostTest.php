<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

test('the platform landing page is served on the platform host', function () {
    $this->get('http://booking.test/')->assertOk();
});

test('an unknown slug is a 404, not a copy of the platform site', function () {
    $this->get('http://no-such-shop.booking.test/')->assertNotFound();
});

test('an unknown external host is a 404', function () {
    $this->get('http://random-site.example/')->assertNotFound();
});

test('a custom domain that is no longer live redirects to the same page on the slug address', function () {
    $operator = Operator::factory()->create(['slug' => 'reef-tours', 'status' => OperatorStatus::Approved]);
    OperatorDomain::query()->create([
        'operator_id' => $operator->id,
        'domain' => 'tours.reefbrand.com',
        'type' => DomainType::Custom,
        'status' => DomainStatus::Pending,
    ]);

    $this->get('http://tours.reefbrand.com/tours?x=1')
        ->assertRedirect('http://reef-tours.booking.test/tours?x=1');
});
