<?php

namespace App\Services;

use App\Models\Operator;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class MediaStore
{
    public const int COVER_MAX_WIDTH = 1600;

    public const int LOGO_MAX_WIDTH = 800;

    public static function diskName(): string
    {
        return (string) config('filesystems.media', 'public');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(static::diskName());
    }

    public function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return static::disk()->url($path);
    }

    public function delete(?string $path): void
    {
        if (! filled($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        static::disk()->delete($path);
    }

    /**
     * @param  list<string|null>  $paths
     */
    public function deleteMany(array $paths): void
    {
        foreach ($paths as $path) {
            $this->delete($path);
        }
    }

    public function deleteDirectory(string $directory): void
    {
        static::disk()->deleteDirectory($directory);
    }

    /**
     * Operator media prefix. The first put creates this folder on local disks and R2.
     */
    public function directoryFor(Operator|string $operator, string $within = ''): string
    {
        $id = trim($operator instanceof Operator ? (string) $operator->id : $operator, '/');

        if ($id === '') {
            throw new InvalidArgumentException('Operator id is required for media storage.');
        }

        $base = 'operators/'.$id;
        $within = trim($within, '/');

        return $within === '' ? $base : $base.'/'.$within;
    }

    /**
     * Store an upload. Raster images are resized and saved as WebP. SVG and PDF stay as uploaded.
     */
    public function storeUpload(UploadedFile $file, string $directory, int $maxWidth = self::COVER_MAX_WIDTH): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if ($extension === 'svg' || $mime === 'image/svg+xml' || $mime === 'application/pdf' || $extension === 'pdf') {
            return $file->store($directory, static::diskName());
        }

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Could not read the uploaded image.');
        }

        return $this->storeImageContents($contents, $directory, $maxWidth);
    }

    /**
     * Resize a raster image and store it as WebP on the media disk.
     */
    public function storeImageContents(string $contents, string $directory, int $maxWidth = self::COVER_MAX_WIDTH): string
    {
        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException('Could not read the image.');
        }

        $image = $this->resizeToMaxWidth($image, $maxWidth);

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        $encoded = imagewebp($image, null, $this->quality());
        $webp = (string) ob_get_clean();
        imagedestroy($image);

        if ($encoded === false || $webp === '') {
            throw new RuntimeException('Could not encode the image as WebP.');
        }

        $path = trim($directory, '/').'/'.Str::ulid().'.webp';

        static::disk()->put($path, $webp, [
            'visibility' => 'public',
            'mimetype' => 'image/webp',
        ]);

        return $path;
    }

    protected function resizeToMaxWidth(GdImage $image, int $maxWidth): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth || $maxWidth < 1) {
            return $image;
        }

        $newHeight = (int) max(1, round($height * ($maxWidth / $width)));
        $resized = imagecreatetruecolor($maxWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $maxWidth, $newHeight, $transparent);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    protected function quality(): int
    {
        return (int) config('filesystems.media_quality', 80);
    }
}
