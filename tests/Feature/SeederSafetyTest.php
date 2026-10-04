<?php

use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use App\Services\MediaStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(MediaStore::diskName());
});

test('a second db:seed skips the admin, plans and demo shop that already exist', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('is_admin', true)->sole();
    $admin->update(['password' => Hash::make('changed-after-launch')]);
    Plan::where('slug', 'growth')->update(['price_monthly' => 349000, 'gallery_photo_limit' => 24]);
    $demoId = Operator::query()->where('is_demo', true)->value('id');

    $this->artisan('db:seed', ['--force' => true])
        ->expectsOutputToContain('Platform admin already exists: skipped')
        ->expectsOutputToContain('Plans already exist: skipped')
        ->expectsOutputToContain('Demo shop already exists: skipped')
        ->assertSuccessful();

    expect(Hash::check('changed-after-launch', $admin->fresh()->password))->toBeTrue()
        ->and((float) Plan::where('slug', 'growth')->value('price_monthly'))->toBe(349000.0)
        ->and(Plan::where('slug', 'growth')->value('gallery_photo_limit'))->toBe(24)
        ->and(Operator::query()->where('is_demo', true)->value('id'))->toBe($demoId)
        ->and(User::query()->where('is_admin', true)->count())->toBe(1);
});

test('the nightly demo reset rebuilds the demo shop but never resets plan edits', function () {
    $this->seed(DatabaseSeeder::class);
    Plan::where('slug', 'agency')->update(['price_monthly' => 899000]);
    $oldDemoId = Operator::query()->where('is_demo', true)->value('id');

    $this->artisan('demo:refresh')->assertSuccessful();

    expect((float) Plan::where('slug', 'agency')->value('price_monthly'))->toBe(899000.0)
        ->and(Operator::query()->where('is_demo', true)->value('id'))->not->toBe($oldDemoId);
});

test('seeding checks media storage first: a local disk in production stops it before any demo upload', function () {
    $this->seed(DatabaseSeeder::class);
    Operator::query()->where('is_demo', true)->delete();

    config(['filesystems.media' => 'public', 'platform.admin_password' => 'secret']);
    $this->app['env'] = 'production';

    try {
        expect(fn () => $this->artisan('db:seed', ['--force' => true])->run())
            ->toThrow(RuntimeException::class, 'MEDIA_DISK');
    } finally {
        $this->app['env'] = 'testing';
    }

    expect(Operator::query()->where('is_demo', true)->exists())->toBeFalse();
});
