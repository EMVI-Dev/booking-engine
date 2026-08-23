<?php

namespace App\Jobs;

use App\Mail\GuestReviewRequestMail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessReviewRequestJob implements ShouldQueue
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
        public Reservation $reservation,
        public string $reviewUrl
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->reservation->loadMissing(['agent', 'bookable']);

        if (empty($this->reservation->guest_email)) {
            return;
        }

        // Avoid duplicate sending
        if ($this->reservation->review_request_sent_at !== null) {
            return;
        }

        Mail::to($this->reservation->guest_email)
            ->send(new GuestReviewRequestMail($this->reservation, $this->reviewUrl));

        $this->reservation->update([
            'review_request_sent_at' => now(),
        ]);

        Log::info("Review request email sent to {$this->reservation->guest_email} for #{$this->reservation->code}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("Failed to send review request for #{$this->reservation->code}: ".($exception?->getMessage() ?? 'Unknown error'));
    }
}
