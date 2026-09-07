<?php

namespace App\Console\Commands;

use App\Enums\OperatorStatus;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Operator;
use App\Services\OperatorActivitySlackNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionRenewalRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-renewal-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated subscription renewal reminders to operators at 7 days, 3 days, and on the due date before expiration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();

        // Target dates: 7 days away, 3 days away, and today (due date)
        $targetDates = [
            7 => $today->copy()->addDays(7)->toDateString(),
            3 => $today->copy()->addDays(3)->toDateString(),
            0 => $today->toDateString(),
        ];

        $operators = Operator::query()
            ->where('status', OperatorStatus::Approved)
            ->whereNotNull('plan_expires_at')
            ->whereNotNull('plan_id')
            ->whereHas('plan', fn ($q) => $q->where('price_monthly', '>', 0))
            ->with(['plan', 'users'])
            ->get();

        $sentCount = 0;

        foreach ($operators as $operator) {
            $plan = $operator->plan;
            if (! $plan || $plan->isFree()) {
                continue;
            }

            $expiryDate = $operator->plan_expires_at->toDateString();
            $matchedDays = null;

            foreach ($targetDates as $days => $dateStr) {
                if ($expiryDate === $dateStr) {
                    $matchedDays = $days;
                    break;
                }
            }

            if ($matchedDays === null) {
                continue;
            }

            $recipientEmail = $operator->billing_email
                ?: ($operator->booking_notification_email ?: $operator->users->first()?->email);

            if (empty($recipientEmail)) {
                continue;
            }

            try {
                Mail::to($recipientEmail)->send(
                    new SubscriptionRenewalReminderMail($operator, $plan, $matchedDays)
                );

                app(OperatorActivitySlackNotifier::class)->renewalReminderSent($operator, $matchedDays);

                $sentCount++;
                $this->info("Sent {$matchedDays}-day renewal reminder to {$operator->name} ({$recipientEmail})");
            } catch (\Throwable $e) {
                $this->error("Failed sending reminder to {$operator->name}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Subscription renewal reminders processing completed. Total sent: {$sentCount}");

        return self::SUCCESS;
    }
}
