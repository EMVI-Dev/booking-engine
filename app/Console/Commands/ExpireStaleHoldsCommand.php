<?php

namespace App\Console\Commands;

use App\Services\ReservationLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireStaleHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:expire-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically expire and release abandoned 30-minute reservation holds';

    /**
     * Execute the console command.
     */
    public function handle(ReservationLifecycleService $lifecycle): int
    {
        $this->info('Checking for expired reservation holds...');

        $expired = $lifecycle->expireStaleHolds();

        foreach ($expired as $code) {
            $this->line("Expired hold for #{$code}");
        }

        if ($expired !== []) {
            Log::info('Expired '.count($expired).' stale reservation hold(s).');
        }

        $this->info('Completed: '.count($expired).' reservation hold(s) expired.');

        return self::SUCCESS;
    }
}
