<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Services\DokuPaymentService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOperatorSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;

test('the demo seeder creates one marked demo operator and drops bali ride tours', function () {
    Operator::factory()->create([
        'slug' => 'bali-ride-tours',
        'booking_notification_email' => 'bookings@baliridetours.com',
        'billing_email' => 'finance@baliridetours.com',
    ]);

    User::factory()->create([
        'email' => 'baliridetours@gmail.com',
    ]);

    $this->seed(DemoOperatorSeeder::class);

    expect(Operator::query()->where('is_demo', true)->count())->toBe(1)
        ->and(Operator::query()->where('slug', 'demo')->value('is_demo'))->toBeTrue()
        ->and(Operator::query()->where('slug', 'bali-ride-tours')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'baliridetours@gmail.com')->exists())->toBeFalse()
        ->and(User::query()->where('email', config('demo.email'))->exists())->toBeTrue();
});

test('the demo catalog has seven packages, seven activities, and local cover photos', function () {
    $this->seed(DemoOperatorSeeder::class);

    $operator = Operator::query()->where('slug', 'demo')->first();

    expect($operator->packages()->count())->toBe(7)
        ->and($operator->products()->count())->toBe(7);

    $operator->packages()->each(function (Package $package) use ($operator): void {
        expect($package->cover_photo)->toStartWith('operators/'.$operator->id.'/')
            ->and($package->cover_photo)->toEndWith('.webp')
            ->and($package->cover_photo_url)->toContain('/storage/operators/'.$operator->id.'/')
            ->and($package->gallery)->not->toBeEmpty();
    });

    $operator->products()->each(function (Product $product) use ($operator): void {
        expect($product->cover_photo)->toStartWith('operators/'.$operator->id.'/')
            ->and($product->cover_photo)->toEndWith('.webp')
            ->and($product->cover_photo_url)->toContain('/storage/operators/'.$operator->id.'/');
    });

    expect($operator->logo_path)->toStartWith('operators/'.$operator->id.'/')
        ->and($operator->logo_path)->toEndWith('.webp')
        ->and($operator->banner_path)->toStartWith('operators/'.$operator->id.'/')
        ->and($operator->banner_path)->toEndWith('.webp')
        ->and($operator->logo_url)->toContain('/storage/operators/'.$operator->id.'/');
});

test('refreshing the demo operator replaces catalog data and keeps one account', function () {
    $this->seed(DemoOperatorSeeder::class);

    $operator = Operator::query()->where('slug', 'demo')->first();
    $operator->packages()->update(['title' => 'Edited by a visitor']);

    $this->artisan('demo:refresh')->assertSuccessful();

    expect(Operator::query()->where('is_demo', true)->count())->toBe(1)
        ->and(Package::query()->where('title', 'Edited by a visitor')->exists())->toBeFalse()
        ->and(Package::query()->where('title', 'Three-Bay Snorkel Safari')->exists())->toBeTrue()
        ->and(Reservation::query()->where('code', 'RSV-DEMO-001')->value('public_token'))->toBeString();
});

test('demo storefront shows a disabled checkout button and rejects booking', function () {
    $operator = Operator::factory()->demo()->create([
        'status' => OperatorStatus::Approved,
    ]);

    $package = Package::factory()->create([
        'operator_id' => $operator->id,
        'title' => 'Demo Lookaround Trip',
        'price' => 250000.00,
        'status' => ListingStatus::Published,
    ]);

    Livewire::test('storefront.booking-box', [
        'bookable' => $package,
        'operator' => $operator,
    ])
        ->assertSee(__('Checkout disabled'))
        ->assertDontSee(__('Proceed to Secure Payment'))
        ->set('requested_date', now()->addDays(3)->format('Y-m-d'))
        ->set('pax_count', 2)
        ->set('guest_name', 'Visitor One')
        ->set('guest_contact', '081234567890')
        ->set('agreed_terms', true)
        ->call('submitBooking')
        ->assertHasErrors(['checkout']);

    expect(Reservation::query()->where('guest_name', 'Visitor One')->exists())->toBeFalse();
});

test('doku checkout sessions are refused for demo operators', function () {
    $operator = Operator::factory()->demo()->create();
    $reservation = Reservation::factory()->create([
        'operator_id' => $operator->id,
    ]);

    expect(fn () => app(DokuPaymentService::class)->createPaymentSession($reservation, 100000))
        ->toThrow(ValidationException::class);
});

test('demo slug login offers the demo operator and hides platform admin', function () {
    Cache::flush();

    $operator = Operator::factory()->demo()->create([
        'slug' => 'demo',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'demo.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->get('http://demo.booking.test/login', ['Host' => 'demo.booking.test'])
        ->assertOk()
        ->assertSee('action="http://demo.booking.test/login"', false)
        ->assertSee(config('demo.email'))
        ->assertSee(__('Demo operator'))
        ->assertDontSee('baliridetours@gmail.com')
        ->assertDontSee('Platform Admin')
        ->assertDontSee('admin@travelengine.online');
});

test('platform operator login does not offer admin or demo fill helpers', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Platform Admin')
        ->assertDontSee('admin@travelengine.online')
        ->assertDontSee(__('Demo operator'));
});

test('demo storefront links to slug operator login', function () {
    Cache::flush();

    $operator = Operator::factory()->demo()->create([
        'name' => 'Demo Tours',
        'slug' => 'demo',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'demo.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->get('http://demo.booking.test/', ['Host' => 'demo.booking.test'])
        ->assertOk()
        ->assertSee(__('Try the operator desk'))
        ->assertSee(__('Operator desk'))
        ->assertSee('http://demo.booking.test/login', false)
        ->assertDontSee('http://booking.test/login', false);
});

test('a real operator storefront does not advertise the operator desk login', function () {
    Cache::flush();

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
        ->assertDontSee(__('Try the operator desk'))
        ->assertDontSee(__('Operator desk'));
});

test('production demo seeder requires a password', function () {
    $this->app['env'] = 'production';
    config(['demo.password' => '']);

    expect(fn () => (new DemoOperatorSeeder)->run())
        ->toThrow(RuntimeException::class);

    $this->app['env'] = 'testing';
});

test('full database seed still creates plans admin and demo only', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Operator::query()->count())->toBe(1)
        ->and(Operator::query()->first()->isDemo())->toBeTrue()
        ->and(User::query()->where('is_admin', true)->exists())->toBeTrue();
});
