<?php

namespace App\Console\Commands;

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Services\SubscriptionReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

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

            try {
                $recipientEmail = app(SubscriptionReminderService::class)->sendRenewalReminder($operator, $matchedDays);

                if ($recipientEmail === null) {
                    continue;
                }

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
