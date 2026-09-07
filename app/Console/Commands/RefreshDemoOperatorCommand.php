<?php

namespace App\Console\Commands;

use Database\Seeders\DemoOperatorSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:refresh')]
#[Description('Wipe the demo operator and seed a clean catalog')]
class RefreshDemoOperatorCommand extends Command
{
    public function handle(): int
    {
        $this->callSilent('db:seed', [
            '--class' => DemoOperatorSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->info('Demo operator refreshed.');

        return self::SUCCESS;
    }
}
