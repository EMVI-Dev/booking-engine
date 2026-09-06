<?php

use App\Models\Operator;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

test('fresh migrations create the current booking schema', function () {
    expect(Schema::hasColumn('reservations', 'public_token'))->toBeTrue()
        ->and(Schema::hasColumn('availability_blocks', 'package_id'))->toBeTrue()
        ->and(Schema::hasColumn('platform_coupons', 'scope'))->toBeTrue()
        ->and(Schema::hasColumn('platform_coupons', 'redemption_scope'))->toBeTrue()
        ->and(Schema::hasColumn('platform_coupons', 'eligibility_rule'))->toBeTrue()
        ->and(Schema::hasColumn('platform_coupons', 'announcement_id'))->toBeTrue();
});

test('new reservations receive a public token', function () {
    $reservation = Reservation::factory()->create();

    expect($reservation->public_token)->toBeString()
        ->and(strlen($reservation->public_token))->toBe(48);
});

test('production admin seeder requires an admin password', function () {
    $this->app['env'] = 'production';

    expect(fn () => (new AdminUserSeeder)->run())
        ->toThrow(RuntimeException::class);

    $this->app['env'] = 'testing';
});

test('production seed creates plans and an admin without sample operators', function () {
    $this->app['env'] = 'production';

    putenv('ADMIN_EMAIL=admin@travelengine.online');
    $_ENV['ADMIN_EMAIL'] = 'admin@travelengine.online';
    $_SERVER['ADMIN_EMAIL'] = 'admin@travelengine.online';
    putenv('ADMIN_PASSWORD=secret-admin-pass');
    $_ENV['ADMIN_PASSWORD'] = 'secret-admin-pass';
    $_SERVER['ADMIN_PASSWORD'] = 'secret-admin-pass';

    $this->artisan('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(Plan::where('slug', 'starter')->exists())->toBeTrue()
        ->and(Plan::where('slug', 'growth')->exists())->toBeTrue()
        ->and(Plan::where('slug', 'agency')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'admin@travelengine.online')->where('is_admin', true)->exists())->toBeTrue()
        ->and(Operator::query()->count())->toBe(0);

    $this->app['env'] = 'testing';
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_EMAIL'], $_SERVER['ADMIN_EMAIL'], $_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
});

test('sample catalog seeder writes reservation public tokens without model events', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Reservation::query()->where('code', 'RSV-BALI-001')->value('public_token'))->toBeString()
        ->and(Reservation::query()->where('code', 'RSV-BALI-002')->value('public_token'))->toBeString();
});
