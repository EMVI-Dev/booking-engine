<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
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

        $limit = $starter->package_limit ?? 5;

        $listings = $operator->packages()
            ->where('status', ListingStatus::Published)
            ->get()
            ->concat($operator->products()->where('status', ListingStatus::Published)->get())
            ->sortByDesc(fn ($listing): int => $listing->created_at?->getTimestamp() ?? 0)
            ->values();

        $listings->skip($limit)->each(function ($listing): void {
            $listing->update(['status' => ListingStatus::Draft]);
        });

        $operator->domains()
            ->where('type', DomainType::Custom)
            ->update([
                'status' => DomainStatus::Pending,
                'ssl_issued_at' => null,
            ]);

        $this->slack->subscriptionLapsed(
            $operator->fresh() ?? $operator,
            $fromPlanName,
            $starter->name,
        );

        return true;
    }
}
