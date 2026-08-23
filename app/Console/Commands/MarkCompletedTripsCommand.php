<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
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
    public function handle(): int
    {
        $this->info('Scanning for concluded trips to mark as completed...');

        $concludedReservations = Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->whereDate('requested_date', '<', today())
            ->get();

        $completedCount = 0;

        foreach ($concludedReservations as $reservation) {
            $reservation->update([
                'status' => ReservationStatus::Completed,
            ]);

            $completedCount++;
            $this->line("Marked trip #{$reservation->code} as completed.");
        }

        if ($completedCount > 0) {
            Log::info("Marked {$completedCount} concluded trip(s) as completed.");
        }

        $this->info("Completed: {$completedCount} reservation(s) marked as completed.");

        return self::SUCCESS;
    }
}
