<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Mail\GuestReviewRequestMail;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPostTripReviewRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trips:send-review-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated post-trip review request emails to guests 12 hours after trip departure.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning for completed trips needing review requests...');

        $eligibleReservations = Reservation::query()
            ->with(['agent', 'bookable'])
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Completed])
            ->whereNotNull('guest_email')
            ->where('guest_email', '!=', '')
            ->whereNull('review_request_sent_at')
            ->whereDate('requested_date', '<=', now()->subHours(12)->toDateString())
            ->get();

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($eligibleReservations as $reservation) {
            $agent = $reservation->agent;

            if (! $agent || ! $agent->hasFeature('automated_review_requests')) {
                $skippedCount++;

                continue;
            }

            $reviewUrl = $agent->getReviewUrl();

            // Only send if the operator has configured a review link
            if (! $reviewUrl) {
                $skippedCount++;

                continue;
            }

            try {
                Mail::to($reservation->guest_email)
                    ->send(new GuestReviewRequestMail($reservation, $reviewUrl));

                $reservation->update([
                    'review_request_sent_at' => now(),
                ]);

                $sentCount++;
                $this->line("Sent review request to {$reservation->guest_email} for #{$reservation->code}");
            } catch (Throwable $e) {
                $this->error("Failed sending review request for #{$reservation->code}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Completed: {$sentCount} review emails sent, {$skippedCount} skipped.");

        return Command::SUCCESS;
    }
}
