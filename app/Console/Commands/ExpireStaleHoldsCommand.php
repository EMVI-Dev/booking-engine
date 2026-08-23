<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
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
    public function handle(): int
    {
        $this->info('Checking for expired reservation holds...');

        $staleReservations = Reservation::query()
            ->where('status', ReservationStatus::PaymentPending)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->get();

        $expiredCount = 0;

        foreach ($staleReservations as $reservation) {
            $reservation->update([
                'status' => ReservationStatus::Expired,
            ]);

            $expiredCount++;
            $this->line("Expired hold for #{$reservation->code}");
        }

        if ($expiredCount > 0) {
            Log::info("Expired {$expiredCount} stale reservation hold(s).");
        }

        $this->info("Completed: {$expiredCount} reservation hold(s) expired.");

        return self::SUCCESS;
    }
}
