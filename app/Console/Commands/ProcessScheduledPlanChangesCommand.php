<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Services\SubscriptionProrationService;
use Illuminate\Console\Command;

class ProcessScheduledPlanChangesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-scheduled-changes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute scheduled operator plan downgrades and transitions whose effective date has arrived.';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionProrationService $prorationService): int
    {
        $this->info('Checking for scheduled operator plan changes...');

        $operators = Operator::query()
            ->whereNotNull('pending_plan_id')
            ->where('pending_plan_action_at', '<=', now())
            ->with('pendingPlan')
            ->get();

        if ($operators->isEmpty()) {
            $this->info('No pending plan changes to process.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($operators as $operator) {
            $targetPlan = $operator->pendingPlan;

            if (! $targetPlan) {
                $operator->update([
                    'pending_plan_id' => null,
                    'pending_plan_action_at' => null,
                ]);

                continue;
            }

            $this->line("Processing plan transition for operator [{$operator->name}] -> Plan: [{$targetPlan->name}]");

            $interval = (string) ($operator->subscription_interval ?: 'monthly');
            $prorationService->executeImmediateDowngrade($operator, $targetPlan, $interval);
            $count++;
        }

        $this->info("Successfully processed {$count} scheduled plan changes.");

        return self::SUCCESS;
    }
}
