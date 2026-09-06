<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SubscriptionProrationService
{
    /**
     * Calculate proration details for switching from current plan to target plan.
     *
     * @return array{
     *     current_plan: Plan,
     *     target_plan: Plan,
     *     current_interval: string,
     *     target_interval: string,
     *     is_upgrade: bool,
     *     is_downgrade: bool,
     *     is_same: bool,
     *     days_remaining: int,
     *     total_days: int,
     *     period_start: ?Carbon,
     *     period_end: ?Carbon,
     *     current_price: float,
     *     target_price: float,
     *     unused_credit: float,
     *     prorated_target_cost: float,
     *     net_amount_due: float
     * }
     */
    public function calculateSwitch(
        Operator $operator,
        Plan $targetPlan,
        string $targetInterval = 'monthly',
        bool $immediate = true
    ): array {
        $currentPlan = $operator->getPlan();
        $currentInterval = (string) ($operator->subscription_interval ?: 'monthly');

        $isFreeCurrent = $currentPlan->isFree();
        $isFreeTarget = $targetPlan->isFree();

        $currentPrice = $isFreeCurrent
            ? 0.0
            : (float) ($currentInterval === 'yearly' ? $currentPlan->price_yearly : $currentPlan->price_monthly);

        $targetPrice = $isFreeTarget
            ? 0.0
            : (float) ($targetInterval === 'yearly' ? $targetPlan->price_yearly : $targetPlan->price_monthly);

        // Period calculations
        $periodStart = $operator->subscribed_at ? Carbon::parse($operator->subscribed_at) : null;
        $periodEnd = $operator->plan_expires_at ? Carbon::parse($operator->plan_expires_at) : null;

        $now = now();
        $daysRemaining = 0;
        $totalDays = ($currentInterval === 'yearly') ? 365 : 30;

        if (! $isFreeCurrent && $periodEnd && $periodEnd->isFuture()) {
            if ($periodStart) {
                $totalDays = max(1, $periodStart->diffInDays($periodEnd));
            }
            $daysRemaining = max(0, (int) $now->diffInDays($periodEnd));
        }

        $remainingRatio = $totalDays > 0 ? min(1.0, max(0.0, $daysRemaining / $totalDays)) : 0.0;
        $unusedCredit = $isFreeCurrent ? 0.0 : round($currentPrice * $remainingRatio, 2);

        // Rank comparison
        $currentRank = $this->getPlanTierRank($currentPlan);
        $targetRank = $this->getPlanTierRank($targetPlan);

        $isSame = ($currentPlan->id === $targetPlan->id && $currentInterval === $targetInterval);
        $isUpgrade = ($targetRank > $currentRank)
            || ($targetRank === $currentRank && $targetInterval === 'yearly' && $currentInterval === 'monthly');
        $isDowngrade = ($targetRank < $currentRank)
            || ($targetRank === $currentRank && $targetInterval === 'monthly' && $currentInterval === 'yearly');

        if ($isUpgrade) {
            if ($isFreeCurrent || $daysRemaining <= 0 || ($targetInterval === 'yearly' && $currentInterval === 'monthly')) {
                $proratedTargetCost = $targetPrice;
            } else {
                $proratedTargetCost = round($targetPrice * $remainingRatio, 2);
            }
            $netAmountDue = max(0.0, round($proratedTargetCost - $unusedCredit, 2));
        } elseif ($isDowngrade) {
            $proratedTargetCost = 0.0;
            $netAmountDue = 0.0;
        } else {
            $proratedTargetCost = $targetPrice;
            $netAmountDue = 0.0;
        }

        return [
            'current_plan' => $currentPlan,
            'target_plan' => $targetPlan,
            'current_interval' => $currentInterval,
            'target_interval' => $targetInterval,
            'is_upgrade' => $isUpgrade,
            'is_downgrade' => $isDowngrade,
            'is_same' => $isSame,
            'days_remaining' => $daysRemaining,
            'total_days' => $totalDays,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'unused_credit' => $unusedCredit,
            'prorated_target_cost' => $proratedTargetCost,
            'net_amount_due' => $netAmountDue,
        ];
    }

    /**
     * Create a pending subscription payment record before taking the operator to checkout.
     */
    public function createPendingUpgrade(
        Operator $operator,
        Plan $targetPlan,
        string $interval = 'monthly',
        bool $autoRenew = true,
        string $gateway = 'credit_card',
        ?string $couponCode = null,
        float $discountAmount = 0.0
    ): SubscriptionPayment {
        $proration = $this->calculateSwitch($operator, $targetPlan, $interval, true);
        $previousPlanId = $operator->plan_id;

        $netDue = max(0.0, (float) $proration['net_amount_due'] - $discountAmount);

        // If net amount is zero (e.g. 100% coupon or credits covered), execute upgrade immediately
        if ($netDue <= 0) {
            $payment = $this->executeUpgrade(
                operator: $operator,
                targetPlan: $targetPlan,
                interval: $interval,
                autoRenew: $autoRenew,
                gateway: $couponCode ? 'promo_code' : 'wallet_credit',
                gatewayRef: ($couponCode ? 'PROMO-' : 'CREDIT-').strtoupper(bin2hex(random_bytes(3)))
            );

            if ($couponCode) {
                PlatformCoupon::whereNull('operator_id')
                    ->where('code', $couponCode)
                    ->first()
                    ?->incrementUsage();

                $breakdown = $payment->breakdown ?? [];
                $breakdown['coupon_code'] = $couponCode;
                $breakdown['discount_amount'] = $discountAmount;
                $payment->update(['breakdown' => $breakdown]);
            }

            return $payment;
        }

        $invoiceNumber = 'SUB-'.strtoupper(Str::random(6)).'-'.time();

        /** @var SubscriptionPayment $payment */
        $payment = SubscriptionPayment::create([
            'operator_id' => $operator->id,
            'plan_id' => $targetPlan->id,
            'previous_plan_id' => $previousPlanId,
            'invoice_number' => $invoiceNumber,
            'type' => $previousPlanId ? 'subscription_upgrade' : 'subscription_new',
            'billing_interval' => $interval,
            'gross_amount' => $proration['prorated_target_cost'],
            'prorated_credit' => $proration['unused_credit'],
            'net_amount_paid' => $netDue,
            'status' => 'pending',
            'gateway' => $gateway,
            'gateway_ref' => $invoiceNumber,
            'breakdown' => [
                'current_plan_name' => $proration['current_plan']->name,
                'target_plan_name' => $targetPlan->name,
                'days_remaining' => $proration['days_remaining'],
                'unused_credit' => $proration['unused_credit'],
                'prorated_charge' => $proration['prorated_target_cost'],
                'discount_amount' => $discountAmount,
                'coupon_code' => $couponCode,
                'net_amount_paid' => $netDue,
                'auto_renew' => $autoRenew,
            ],
            'paid_at' => null,
        ]);

        return $payment;
    }

    /**
     * Mark a pending subscription payment as completed and activate the operator's new plan tier.
     */
    public function completePendingPayment(
        SubscriptionPayment $payment,
        ?string $gatewayRef = null,
        ?string $gateway = null
    ): void {
        if ($payment->status === 'completed') {
            return;
        }

        $payment->update([
            'status' => 'completed',
            'gateway' => $gateway ?: $payment->gateway,
            'gateway_ref' => $gatewayRef ?: $payment->gateway_ref,
            'paid_at' => now(),
        ]);

        $operator = $payment->operator;
        $targetPlan = $payment->plan;
        $interval = $payment->billing_interval;
        $autoRenew = (bool) ($payment->breakdown['auto_renew'] ?? true);

        $now = now();
        $expiresAt = ($interval === 'yearly') ? $now->copy()->addYear() : $now->copy()->addMonth();

        $operator->update([
            'plan_id' => $targetPlan->id,
            'subscription_interval' => $interval,
            'subscription_auto_renew' => $autoRenew,
            'subscribed_at' => $now,
            'plan_expires_at' => $targetPlan->isFree() ? null : $expiresAt,
            'pending_plan_id' => null,
            'pending_plan_action_at' => null,
        ]);

        $operator->unsetRelation('plan');
        $operator->unsetRelation('pendingPlan');
    }

    /**
     * Execute an immediate plan upgrade and record the subscription payment.
     */
    public function executeUpgrade(
        Operator $operator,
        Plan $targetPlan,
        string $interval = 'monthly',
        bool $autoRenew = true,
        string $gateway = 'manual',
        ?string $gatewayRef = null
    ): SubscriptionPayment {
        $proration = $this->calculateSwitch($operator, $targetPlan, $interval, true);
        $previousPlanId = $operator->plan_id;

        $invoiceNumber = 'SUB-'.strtoupper(Str::random(6)).'-'.time();

        /** @var SubscriptionPayment $payment */
        $payment = SubscriptionPayment::create([
            'operator_id' => $operator->id,
            'plan_id' => $targetPlan->id,
            'previous_plan_id' => $previousPlanId,
            'invoice_number' => $invoiceNumber,
            'type' => $previousPlanId ? 'subscription_upgrade' : 'subscription_new',
            'billing_interval' => $interval,
            'gross_amount' => $proration['prorated_target_cost'],
            'prorated_credit' => $proration['unused_credit'],
            'net_amount_paid' => $proration['net_amount_due'],
            'status' => 'completed',
            'gateway' => $gateway,
            'gateway_ref' => $gatewayRef ?: $invoiceNumber,
            'breakdown' => [
                'current_plan_name' => $proration['current_plan']->name,
                'target_plan_name' => $targetPlan->name,
                'days_remaining' => $proration['days_remaining'],
                'unused_credit' => $proration['unused_credit'],
                'prorated_charge' => $proration['prorated_target_cost'],
                'net_amount_paid' => $proration['net_amount_due'],
                'auto_renew' => $autoRenew,
            ],
            'paid_at' => now(),
        ]);

        $now = now();
        $expiresAt = ($interval === 'yearly') ? $now->copy()->addYear() : $now->copy()->addMonth();

        $operator->update([
            'plan_id' => $targetPlan->id,
            'subscription_interval' => $interval,
            'subscription_auto_renew' => $autoRenew,
            'subscribed_at' => $now,
            'plan_expires_at' => $targetPlan->isFree() ? null : $expiresAt,
            'pending_plan_id' => null,
            'pending_plan_action_at' => null,
        ]);

        $operator->unsetRelation('plan');
        $operator->unsetRelation('pendingPlan');

        return $payment;
    }

    /**
     * Schedule a downgrade to take effect at the end of the current billing cycle.
     */
    public function scheduleDowngrade(Operator $operator, Plan $targetPlan, string $interval = 'monthly'): void
    {
        $actionAt = $operator->plan_expires_at ? Carbon::parse($operator->plan_expires_at) : now()->addMonth();

        $operator->update([
            'pending_plan_id' => $targetPlan->id,
            'pending_plan_action_at' => $actionAt,
        ]);

        $operator->unsetRelation('pendingPlan');
    }

    /**
     * Execute an immediate downgrade.
     */
    public function executeImmediateDowngrade(
        Operator $operator,
        Plan $targetPlan,
        string $interval = 'monthly'
    ): SubscriptionPayment {
        $proration = $this->calculateSwitch($operator, $targetPlan, $interval, true);
        $previousPlanId = $operator->plan_id;

        $invoiceNumber = 'SUB-DOWN-'.strtoupper(Str::random(6)).'-'.time();

        /** @var SubscriptionPayment $payment */
        $payment = SubscriptionPayment::create([
            'operator_id' => $operator->id,
            'plan_id' => $targetPlan->id,
            'previous_plan_id' => $previousPlanId,
            'invoice_number' => $invoiceNumber,
            'type' => 'subscription_downgrade',
            'billing_interval' => $interval,
            'gross_amount' => 0,
            'prorated_credit' => $proration['unused_credit'],
            'net_amount_paid' => 0,
            'status' => 'completed',
            'gateway' => 'wallet_credit',
            'gateway_ref' => $invoiceNumber,
            'breakdown' => [
                'current_plan_name' => $proration['current_plan']->name,
                'target_plan_name' => $targetPlan->name,
                'unused_credit_forfeited_or_credited' => $proration['unused_credit'],
                'mode' => 'immediate',
            ],
            'paid_at' => now(),
        ]);

        $now = now();
        $expiresAt = $targetPlan->isFree() ? null : (($interval === 'yearly') ? $now->copy()->addYear() : $now->copy()->addMonth());

        $operator->update([
            'plan_id' => $targetPlan->id,
            'subscription_interval' => $interval,
            'subscribed_at' => $now,
            'plan_expires_at' => $expiresAt,
            'pending_plan_id' => null,
            'pending_plan_action_at' => null,
        ]);

        $operator->unsetRelation('plan');
        $operator->unsetRelation('pendingPlan');

        return $payment;
    }

    /**
     * Cancel a pending scheduled downgrade.
     */
    public function cancelScheduledDowngrade(Operator $operator): void
    {
        $operator->cancelPendingPlanChange();
    }

    /**
     * Numeric rank of plan tiers for upgrade/downgrade logic.
     */
    protected function getPlanTierRank(Plan $plan): int
    {
        return $plan->tierRank();
    }
}
