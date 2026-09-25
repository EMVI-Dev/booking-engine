<?php

namespace App\Console\Commands;

use App\Enums\OperatorStatus;
use App\Mail\OperatorAccountSuspendedInactivityMail;
use App\Mail\OperatorInactivityReminderMail;
use App\Models\Operator;
use App\Services\OperatorActivitySlackNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CheckOperatorInactivityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'operators:check-inactivity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 30-day inactivity reminders and suspend operators inactive for 90 days (3 months).';

    /**
     * Execute the console command.
     */
    public function handle(OperatorActivitySlackNotifier $slack): int
    {
        $operators = Operator::query()
            ->where('status', OperatorStatus::Approved)
            ->where('is_demo', false)
            ->with(['users'])
            ->get();

        $remindedCount = 0;
        $suspendedCount = 0;

        foreach ($operators as $operator) {
            $lastActive = $operator->last_active_at ?? $operator->created_at;

            if (! $lastActive) {
                continue;
            }

            $inactiveDays = (int) $lastActive->diffInDays(now());

            $recipient = $operator->booking_notification_email
                ?: $operator->billing_email
                ?: $operator->users->first()?->email;

            // 1. Suspend operators inactive for 90+ days (3 months)
            if ($inactiveDays >= 90) {
                $operator->update(['status' => OperatorStatus::Suspended]);
                $suspendedCount++;

                if ($recipient) {
                    try {
                        Mail::to($recipient)->queue(new OperatorAccountSuspendedInactivityMail($operator, $inactiveDays));
                    } catch (Throwable $e) {
                        report($e);
                    }
                }

                $slack->statusChanged($operator, 'approved', 'suspended');
                $this->warn("Operator {$operator->name} ({$operator->slug}) suspended due to {$inactiveDays} days of inactivity.");

                continue;
            }

            // 2. Remind operators inactive for 30+ days (1 month)
            if ($inactiveDays >= 30) {
                $alreadyReminded = $operator->inactivity_reminder_sent_at
                    && $operator->inactivity_reminder_sent_at->gte($lastActive);

                if (! $alreadyReminded) {
                    $operator->updateQuietly(['inactivity_reminder_sent_at' => now()]);
                    $remindedCount++;

                    if ($recipient) {
                        try {
                            Mail::to($recipient)->queue(new OperatorInactivityReminderMail($operator, $inactiveDays));
                        } catch (Throwable $e) {
                            report($e);
                        }
                    }

                    $this->info("Inactivity reminder sent to {$operator->name} ({$operator->slug}) inactive for {$inactiveDays} days.");
                }
            }
        }

        $this->info("Completed inactivity check. Reminded: {$remindedCount}, Suspended: {$suspendedCount}.");

        return self::SUCCESS;
    }
}
