<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Jobs\SendDepartureReminderJob;
use App\Models\Reservation;
use Illuminate\Console\Command;

class SendDepartureRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trips:send-departure-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan upcoming confirmed reservations and dispatch queued departure reminder notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning for upcoming trips departing in the next 24-48 hours...');

        $upcomingReservations = Reservation::query()
            ->with(['agent', 'bookable'])
            ->where('status', ReservationStatus::Confirmed)
            ->whereNotNull('guest_email')
            ->where('guest_email', '!=', '')
            ->whereNull('departure_reminder_sent_at')
            ->whereBetween('requested_date', [
                today()->toDateString(),
                today()->addDays(2)->toDateString(),
            ])
            ->get();

        $dispatchedCount = 0;

        foreach ($upcomingReservations as $reservation) {
            SendDepartureReminderJob::dispatch($reservation);
            $dispatchedCount++;
            $this->line("Dispatched departure reminder job for #{$reservation->code} ({$reservation->guest_email})");
        }

        $this->info("Completed: {$dispatchedCount} departure reminder job(s) dispatched to queue.");

        return self::SUCCESS;
    }
}
