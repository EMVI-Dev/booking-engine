<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\Plan;

class LapsedSubscriptionService
{
    public function __construct(
        protected OperatorActivitySlackNotifier $slack,
    ) {}

    /**
     * After 3 extra days unpaid, move a paid operator back to the free plan.
     */
    public function revertToFreePlan(Operator $operator): bool
    {
        $plan = $operator->getPlan();

        if ($plan->isFree()) {
            return false;
        }

        if ($operator->plan_expires_at === null || $operator->plan_expires_at->gte(now()->subDays(3))) {
            return false;
        }

        $starter = Plan::getDefaultPlan();
        $fromPlanName = $plan->name;

        $operator->update([
            'plan_id' => $starter->id,
            'pending_plan_id' => null,
            'pending_plan_action_at' => null,
        ]);

        app(PlanLimitService::class)->enforce($operator);

        $this->slack->subscriptionLapsed(
            $operator->fresh() ?? $operator,
            $fromPlanName,
            $starter->name,
        );

        return true;
    }
}
