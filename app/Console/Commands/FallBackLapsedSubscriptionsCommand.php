<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Models\Plan;
use App\Services\LapsedSubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:return-unpaid-to-free')]
#[Description('After 3 extra days unpaid, move operators back to the free plan.')]
class FallBackLapsedSubscriptionsCommand extends Command
{
    public function handle(LapsedSubscriptionService $lapsedSubscriptions): int
    {
        $starter = Plan::getDefaultPlan();

        $operators = Operator::query()
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now()->subDays(3))
            ->where('plan_id', '!=', $starter->id)
            ->get();

        $moved = 0;

        foreach ($operators as $operator) {
            if ($lapsedSubscriptions->revertToFreePlan($operator)) {
                $moved++;
                $this->info("Moved {$operator->name} back to the free plan.");
            }
        }

        $this->info("Done. Moved {$moved} operator(s).");

        return self::SUCCESS;
    }
}
