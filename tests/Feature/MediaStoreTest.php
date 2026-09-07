<?php

use App\Models\Operator;
use App\Services\MediaStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(MediaStore::diskName());

    $this->media = app(MediaStore::class);
});

test('raster uploads are resized and stored as webp', function () {
    $file = UploadedFile::fake()->image('cover.jpg', 2000, 1000);

    $path = $this->media->storeUpload($file, 'covers');

    expect($path)->toStartWith('covers/')
        ->and($path)->toEndWith('.webp');

    Storage::disk(MediaStore::diskName())->assertExists($path);

    $image = imagecreatefromstring((string) Storage::disk(MediaStore::diskName())->get($path));

    expect($image)->not->toBeFalse()
        ->and(imagesx($image))->toBe(1600)
        ->and(imagesy($image))->toBe(800);

    imagedestroy($image);
});

test('svg and pdf uploads stay in their original format', function () {
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $pdf = UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4');

    $svgPath = $this->media->storeUpload($svg, 'logos');
    $pdfPath = $this->media->storeUpload($pdf, 'proofs');

    expect($svgPath)->toEndWith('.svg')
        ->and($pdfPath)->toEndWith('.pdf');

    Storage::disk(MediaStore::diskName())->assertExists($svgPath);
    Storage::disk(MediaStore::diskName())->assertExists($pdfPath);
});

test('media urls use the configured disk and leave absolute urls alone', function () {
    expect($this->media->url('operators/logos/whitebox.png'))->toContain('/storage/operators/logos/whitebox.png')
        ->and($this->media->url('https://cdn.example/logo.webp'))->toBe('https://cdn.example/logo.webp')
        ->and($this->media->url(null))->toBeNull();
});

test('uploads are stored under the operator folder', function () {
    $operator = Operator::factory()->create();
    $file = UploadedFile::fake()->image('cover.jpg', 400, 300);

    $path = $this->media->storeUpload($file, $this->media->directoryFor($operator, 'packages/covers'));

    expect($path)->toStartWith('operators/'.$operator->id.'/packages/covers/')
        ->and($path)->toEndWith('.webp');

    Storage::disk(MediaStore::diskName())->assertExists($path);
});

test('the r2 disk public url is the storage subdomain', function () {
    expect(config('filesystems.disks.r2.url'))->toBe('https://storage.travelengine.online');
});
