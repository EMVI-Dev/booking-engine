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
        ->and(User::query()->where('email', 'admin@emvi.dev')->where('is_admin', true)->exists())->toBeTrue()
        ->and(Operator::query()->count())->toBe(0);

    $this->app['env'] = 'testing';
    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
});
