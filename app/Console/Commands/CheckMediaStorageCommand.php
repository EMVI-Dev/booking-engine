<?php

namespace App\Console\Commands;

use App\Services\MediaStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('media:check')]
#[Description('Show which disk media uploads use and test a write, public URL and delete on it. db:seed runs the same check.')]
class CheckMediaStorageCommand extends Command
{
    public function handle(): int
    {
        $this->line('Media disk: '.MediaStore::diskName().' ('.config('filesystems.disks.'.MediaStore::diskName().'.driver').')');

        try {
            $result = MediaStore::verifyWritable();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Public URL base: '.$result['base_url']);
        $this->info('Write, read and delete worked.');
        $this->line('Files will be served like: '.$result['sample_url']);

        return self::SUCCESS;
    }
}
