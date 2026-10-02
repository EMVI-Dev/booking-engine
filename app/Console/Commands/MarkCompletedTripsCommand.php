<?php

namespace App\Console\Commands;

use App\Services\ReservationLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkCompletedTripsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trips:mark-completed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically mark past confirmed reservations as completed';

    /**
     * Execute the console command.
     */
    public function handle(ReservationLifecycleService $lifecycle): int
    {
        $this->info('Scanning for concluded trips to mark as completed...');

        $completed = $lifecycle->completeFinishedTrips();

        foreach ($completed as $code) {
            $this->line("Marked trip #{$code} as completed.");
        }

        if ($completed !== []) {
            Log::info('Marked '.count($completed).' concluded trip(s) as completed.');
        }

        $this->info('Completed: '.count($completed).' reservation(s) marked as completed.');

        return self::SUCCESS;
    }
}
