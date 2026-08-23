<?php

namespace App\Jobs;

use App\Mail\GuestDepartureReminderMail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDepartureReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Reservation $reservation
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->reservation->loadMissing(['agent', 'bookable']);

        if (empty($this->reservation->guest_email)) {
            Log::info("Departure reminder skipped for #{$this->reservation->code}: No guest email.");

            return;
        }

        // Avoid duplicate sending
        if ($this->reservation->departure_reminder_sent_at !== null) {
            Log::info("Departure reminder already sent for #{$this->reservation->code}.");

            return;
        }

        Mail::to($this->reservation->guest_email)
            ->send(new GuestDepartureReminderMail($this->reservation));

        $this->reservation->update([
            'departure_reminder_sent_at' => now(),
        ]);

        Log::info("Departure reminder successfully sent to {$this->reservation->guest_email} for #{$this->reservation->code}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("Failed to send departure reminder for #{$this->reservation->code}: ".($exception?->getMessage() ?? 'Unknown error'));
    }
}
