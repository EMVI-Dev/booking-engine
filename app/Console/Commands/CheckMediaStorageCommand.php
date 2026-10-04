<?php

namespace App\Console\Commands;

use App\Services\MediaStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('media:check')]
#[Description('Show which disk media uploads use and test a write, public URL and delete on it (run on Cloud after setting R2).')]
class CheckMediaStorageCommand extends Command
{
    public function handle(): int
    {
        $diskName = MediaStore::diskName();
        $driver = (string) config("filesystems.disks.{$diskName}.driver");

        $this->line("Media disk: {$diskName} ({$driver})");
        $this->line('Public URL base: '.MediaStore::disk()->url(''));

        try {
            MediaStore::assertDurableDisk();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $path = 'healthchecks/'.Str::ulid().'.txt';

        try {
            MediaStore::disk()->put($path, 'ok', ['mimetype' => 'text/plain']);
            $readBack = MediaStore::disk()->get($path);
            $url = MediaStore::disk()->url($path);
            MediaStore::disk()->delete($path);
        } catch (Throwable $e) {
            $this->error('Write test failed: '.$e->getMessage());
            $this->line('Check R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET and R2_ENDPOINT, then redeploy.');

            return self::FAILURE;
        }

        if ($readBack !== 'ok') {
            $this->error('The test file was written but could not be read back.');

            return self::FAILURE;
        }

        $this->info('Write, read and delete worked.');
        $this->line("Files will be served like: {$url}");

        return self::SUCCESS;
    }
}
