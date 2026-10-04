<?php

use App\Services\MediaStore;
use Illuminate\Support\Facades\Storage;

test('production refuses to store media on a local disk', function () {
    config(['filesystems.media' => 'public']);
    $this->app['env'] = 'production';

    try {
        expect(fn () => app(MediaStore::class)->storeImageContents(
            (string) file_get_contents(base_path('public/favicon.png')),
            'operators/abc/catalog',
        ))->toThrow(RuntimeException::class, 'MEDIA_DISK');

        $this->artisan('media:check')->assertFailed();
    } finally {
        $this->app['env'] = 'testing';
    }
});

test('media:check passes on a working disk', function () {
    Storage::fake('public');
    config(['filesystems.media' => 'public']);

    $this->artisan('media:check')
        ->expectsOutputToContain('Write, read and delete worked.')
        ->assertSuccessful();
});
