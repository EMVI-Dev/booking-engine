<?php

use App\Models\Operator;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

test('every table has exactly one create migration and no alter or drop migrations', function () {
    $creates = [];

    foreach (File::files(database_path('migrations')) as $file) {
        $source = $file->getContents();

        expect($source)->not->toContain('Schema::table(')
            ->and(preg_match('/up\(\): void\s*\{[^}]*Schema::drop/s', $source))->toBe(0, "{$file->getFilename()} drops a table in up()");

        preg_match_all("/Schema::create\('([a-z_]+)'/", $source, $matches);

        foreach ($matches[1] as $table) {
            $creates[$table][] = $file->getFilename();
        }
    }

    foreach ($creates as $table => $files) {
        expect($files)->toHaveCount(1, "{$table} is created in more than one migration");
    }

    expect(array_keys($creates))->not->toContain('reviews')
        ->not->toContain('product_availability');
});

test('folded columns live in their create migrations', function () {
    expect(Schema::hasColumn('products', 'vendor_id'))->toBeTrue()
        ->and(Schema::hasColumn('operators', 'last_active_at'))->toBeTrue()
        ->and(Schema::hasColumn('operators', 'inactivity_reminder_sent_at'))->toBeTrue()
        ->and(Schema::hasIndex('operators', ['last_active_at']))->toBeTrue()
        ->and(Schema::hasIndex('platform_coupons', ['scope', 'operator_id', 'code'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('platform_coupons', ['code'], 'unique'))->toBeFalse()
        ->and(Schema::hasTable('admin_audit_logs'))->toBeTrue()
        ->and(Schema::hasTable('product_availability'))->toBeFalse();

    $commissionDefault = collect(Schema::getColumns('plans'))->firstWhere('name', 'commission_rate')['default'] ?? null;

    expect((float) trim((string) $commissionDefault, "'"))->toBe(0.0);
});

test('fresh migrations create the current booking schema', function () {
    expect(Schema::hasColumn('reservations', 'public_token'))->toBeTrue()
        ->and(Schema::hasColumn('operators', 'is_demo'))->toBeTrue()
        ->and(Schema::hasColumn('availability_blocks', 'package_id'))->toBeTrue()
        ->and(Schema::hasColumn('platform_coupons', 'scope'))->toBeTrue()
        ->and(Schema::hasColumn('platform_coupons', 'redemption_scope'))->toBeTrue()
        ->and(Schema::hasColumn('platform_coupons', 'eligibility_rule'))->toBeTrue()
        ->and(Schema::hasColumn('platform_coupons', 'announcement_id'))->toBeTrue();
});

test('hot query paths have covering indexes', function () {
    expect(Schema::hasIndex('reservations', ['operator_id', 'requested_date']))->toBeTrue()
        ->and(Schema::hasIndex('products', ['operator_id', 'status']))->toBeTrue()
        ->and(Schema::hasIndex('products', ['operator_id', 'status', 'sellable_standalone']))->toBeTrue()
        ->and(Schema::hasIndex('packages', ['operator_id', 'status', 'created_at']))->toBeTrue()
        ->and(Schema::hasTable('reviews'))->toBeFalse()
        ->and(Schema::hasIndex('payments', ['status', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('subscription_payments', ['gateway_ref']))->toBeTrue()
        ->and(Schema::hasIndex('wallet_transactions', ['operator_id', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('operators', ['pending_plan_action_at']))->toBeTrue()
        ->and(Schema::hasIndex('operators', ['plan_expires_at']))->toBeTrue()
        ->and(Schema::hasIndex('operator_domains', ['type', 'status']))->toBeTrue()
        ->and(Schema::hasIndex('guests', ['operator_id', 'updated_at']))->toBeTrue();
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

    putenv('ADMIN_EMAIL=admin@travelengine.id');
    $_ENV['ADMIN_EMAIL'] = 'admin@travelengine.id';
    $_SERVER['ADMIN_EMAIL'] = 'admin@travelengine.id';
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
        ->and(User::query()->where('email', 'admin@travelengine.id')->where('is_admin', true)->exists())->toBeTrue()
        ->and(Operator::query()->where('is_demo', true)->count())->toBe(1)
        ->and(Operator::query()->where('slug', 'bali-ride-tours')->exists())->toBeFalse();

    $this->app['env'] = 'testing';
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_EMAIL'], $_SERVER['ADMIN_EMAIL'], $_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
});

test('local seed includes the demo operator catalog', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Operator::query()->where('slug', 'demo')->where('is_demo', true)->exists())->toBeTrue()
        ->and(Operator::query()->where('slug', 'bali-ride-tours')->exists())->toBeFalse();
});

test('demo catalog seeder writes reservation public tokens without model events', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Reservation::query()->where('code', 'RSV-DEMO-001')->value('public_token'))->toBeString()
        ->and(Reservation::query()->where('code', 'RSV-DEMO-002')->value('public_token'))->toBeString();
});
