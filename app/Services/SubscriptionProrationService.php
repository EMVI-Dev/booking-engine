<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionProrationService
{
    public function __construct(
        protected OperatorActivitySlackNotifier $slack,
        protected PlanLimitService $planLimits,
    ) {}

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
     *     net_amount_due: float,
     *     is_prorated: bool
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

        $isProrated = false;

        if ($isUpgrade) {
            if ($isFreeCurrent || $daysRemaining <= 0 || ($targetInterval === 'yearly' && $currentInterval === 'monthly')) {
                $proratedTargetCost = $targetPrice;
            } else {
                $proratedTargetCost = round($targetPrice * $remainingRatio, 2);
                $isProrated = true;
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
            'is_prorated' => $isProrated,
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
        $netDue = max(0.0, (float) $proration['net_amount_due'] - $discountAmount);

        // Fully covered by unused credit or a promo: activate straight away.
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
                PlatformCoupon::findForSubscription($couponCode, $operator)?->incrementUsage();

                $breakdown = $payment->breakdown ?? [];
                $breakdown['coupon_code'] = $couponCode;
                $breakdown['discount_amount'] = $discountAmount;
                $payment->update(['breakdown' => $breakdown]);
            }

            return $payment;
        }

        return $this->recordInvoice(
            operator: $operator,
            targetPlan: $targetPlan,
            interval: $interval,
            status: SubscriptionPayment::STATUS_PENDING,
            type: $operator->plan_id ? 'subscription_upgrade' : 'subscription_new',
            grossAmount: (float) $proration['prorated_target_cost'],
            proratedCredit: (float) $proration['unused_credit'],
            netAmount: $netDue,
            gateway: $gateway,
            breakdown: $this->upgradeBreakdown($proration, $targetPlan, $netDue, $autoRenew) + [
                'discount_amount' => $discountAmount,
                'coupon_code' => $couponCode,
            ],
        );
    }

    /**
     * Mark a pending subscription payment as completed and activate the operator's new plan tier.
     */
    public function completePendingPayment(
        SubscriptionPayment $payment,
        ?string $gatewayRef = null,
        ?string $gateway = null
    ): void {
        $shouldNotify = false;
        $operatorForNotification = null;
        $fromPlanName = '';

        DB::transaction(function () use ($payment, $gatewayRef, $gateway, &$shouldNotify, &$operatorForNotification, &$fromPlanName): void {
            /** @var SubscriptionPayment|null $lockedPayment */
            $lockedPayment = SubscriptionPayment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if (! $lockedPayment || $lockedPayment->status === SubscriptionPayment::STATUS_COMPLETED) {
                return;
            }

            $lockedPayment->update([
                'status' => SubscriptionPayment::STATUS_COMPLETED,
                'gateway' => $gateway ?: $lockedPayment->gateway,
                'gateway_ref' => $gatewayRef ?: $lockedPayment->gateway_ref,
                'paid_at' => now(),
            ]);

            $operator = $lockedPayment->operator;
            $breakdown = $lockedPayment->breakdown ?? [];

            // Count the promo exactly once, when the invoice is actually paid (any gateway path).
            $couponCode = $breakdown['coupon_code'] ?? null;
            if (is_string($couponCode) && $couponCode !== '') {
                PlatformCoupon::findForSubscription($couponCode, $operator)?->incrementUsage();
            }

            $fromPlanName = $lockedPayment->previousPlan?->name ?? $operator->getPlan()->name;

            $this->activatePlan(
                operator: $operator,
                plan: $lockedPayment->plan,
                interval: $lockedPayment->billing_interval,
                autoRenew: (bool) ($breakdown['auto_renew'] ?? true),
                keepCurrentPeriod: (bool) ($breakdown['keeps_current_period'] ?? false),
            );

            $shouldNotify = true;
            $operatorForNotification = $operator;
        });

        if ($shouldNotify && $operatorForNotification) {
            $this->slack->subscriptionPaid(
                $operatorForNotification->fresh() ?? $operatorForNotification,
                $payment->fresh() ?? $payment,
                $fromPlanName,
            );
        }
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

        $payment = $this->recordInvoice(
            operator: $operator,
            targetPlan: $targetPlan,
            interval: $interval,
            status: SubscriptionPayment::STATUS_COMPLETED,
            type: $operator->plan_id ? 'subscription_upgrade' : 'subscription_new',
            grossAmount: (float) $proration['prorated_target_cost'],
            proratedCredit: (float) $proration['unused_credit'],
            netAmount: (float) $proration['net_amount_due'],
            gateway: $gateway,
            breakdown: $this->upgradeBreakdown($proration, $targetPlan, (float) $proration['net_amount_due'], $autoRenew),
            gatewayRef: $gatewayRef,
        );

        $this->activatePlan($operator, $targetPlan, $interval, $autoRenew, keepCurrentPeriod: $proration['is_prorated']);

        $this->slack->subscriptionPaid(
            $operator->fresh() ?? $operator,
            $payment->fresh() ?? $payment,
            $proration['current_plan']->name,
        );

        return $payment;
    }

    /**
     * Schedule a downgrade to take effect at the end of the current billing cycle.
     */
    public function scheduleDowngrade(Operator $operator, Plan $targetPlan, string $interval = 'monthly'): void
    {
        $actionAt = $operator->plan_expires_at ? Carbon::parse($operator->plan_expires_at) : now()->addMonth();

        $fromPlanName = $operator->getPlan()->name;

        $operator->update([
            'pending_plan_id' => $targetPlan->id,
            'pending_plan_action_at' => $actionAt,
        ]);

        $operator->unsetRelation('pendingPlan');

        $this->slack->planChanged(
            $operator->fresh() ?? $operator,
            $fromPlanName,
            $targetPlan->name,
            'scheduled_downgrade',
        );
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

        $payment = $this->recordInvoice(
            operator: $operator,
            targetPlan: $targetPlan,
            interval: $interval,
            status: SubscriptionPayment::STATUS_COMPLETED,
            type: 'subscription_downgrade',
            grossAmount: 0.0,
            proratedCredit: (float) $proration['unused_credit'],
            netAmount: 0.0,
            gateway: 'wallet_credit',
            breakdown: [
                'current_plan_name' => $proration['current_plan']->name,
                'target_plan_name' => $targetPlan->name,
                'unused_credit_forfeited_or_credited' => $proration['unused_credit'],
                'mode' => 'immediate',
            ],
            invoicePrefix: 'SUB-DOWN-',
        );

        $this->activatePlan($operator, $targetPlan, $interval, autoRenew: null);

        $this->slack->subscriptionPaid(
            $operator->fresh() ?? $operator,
            $payment->fresh() ?? $payment,
            $proration['current_plan']->name,
        );

        return $payment;
    }

    /**
     * Give an operator a plan at no charge. Null $days means no end date.
     */
    public function grantComplimentaryPlan(Operator $operator, Plan $targetPlan, ?int $days = null): SubscriptionPayment
    {
        $proration = $this->calculateSwitch($operator, $targetPlan, 'monthly', true);
        $fromPlanName = $proration['current_plan']->name;

        $type = 'subscription_new';

        if ($operator->plan_id) {
            $type = $proration['is_downgrade'] ? 'subscription_downgrade' : 'subscription_upgrade';
        }

        $payment = $this->recordInvoice(
            operator: $operator,
            targetPlan: $targetPlan,
            interval: 'monthly',
            status: SubscriptionPayment::STATUS_COMPLETED,
            type: $type,
            grossAmount: 0.0,
            proratedCredit: 0.0,
            netAmount: 0.0,
            gateway: 'admin_complimentary',
            breakdown: [
                'mode' => 'complimentary',
                'days' => $days,
                'current_plan_name' => $fromPlanName,
                'target_plan_name' => $targetPlan->name,
            ],
            invoicePrefix: 'SUB-COMP-',
        );

        $this->activatePlan(
            operator: $operator,
            plan: $targetPlan,
            interval: 'monthly',
            autoRenew: false,
            expiresAt: $days !== null && $days > 0 ? now()->addDays($days) : null,
            fixedExpiry: true,
        );

        $this->slack->planChanged(
            $operator->fresh() ?? $operator,
            $fromPlanName,
            $targetPlan->name,
            'complimentary',
        );

        return $payment;
    }

    /**
     * Switch the operator onto a plan and re-apply that plan's limits.
     *
     * The one place plan, interval, billing period and pending-change fields are written.
     * A prorated upgrade keeps the current period end (the operator paid only for the days left).
     */
    private function activatePlan(
        Operator $operator,
        Plan $plan,
        string $interval,
        ?bool $autoRenew,
        bool $keepCurrentPeriod = false,
        ?CarbonInterface $expiresAt = null,
        bool $fixedExpiry = false,
    ): void {
        $now = now();
        $keepPeriod = $keepCurrentPeriod && $operator->plan_expires_at?->isFuture();

        $periodEnd = match (true) {
            $plan->isFree() => null,
            $fixedExpiry => $expiresAt,
            $keepPeriod => $operator->plan_expires_at,
            default => $interval === 'yearly' ? $now->copy()->addYear() : $now->copy()->addMonth(),
        };

        $attributes = [
            'plan_id' => $plan->id,
            'subscription_interval' => $interval,
            'subscribed_at' => $keepPeriod ? ($operator->subscribed_at ?? $now) : $now,
            'plan_expires_at' => $periodEnd,
            'pending_plan_id' => null,
            'pending_plan_action_at' => null,
        ];

        if ($autoRenew !== null) {
            $attributes['subscription_auto_renew'] = $autoRenew;
        }

        $operator->update($attributes);
        $operator->unsetRelation('plan');
        $operator->unsetRelation('pendingPlan');

        $this->planLimits->enforce($operator);
    }

    /**
     * Write a subscription invoice row. The only place invoice numbers are minted.
     *
     * @param  array<string, mixed>  $breakdown
     */
    private function recordInvoice(
        Operator $operator,
        Plan $targetPlan,
        string $interval,
        string $status,
        string $type,
        float $grossAmount,
        float $proratedCredit,
        float $netAmount,
        string $gateway,
        array $breakdown,
        ?string $gatewayRef = null,
        string $invoicePrefix = 'SUB-',
    ): SubscriptionPayment {
        $invoiceNumber = $invoicePrefix.strtoupper(Str::random(6)).'-'.time();

        /** @var SubscriptionPayment $payment */
        $payment = SubscriptionPayment::create([
            'operator_id' => $operator->id,
            'plan_id' => $targetPlan->id,
            'previous_plan_id' => $operator->plan_id,
            'invoice_number' => $invoiceNumber,
            'type' => $type,
            'billing_interval' => $interval,
            'gross_amount' => $grossAmount,
            'prorated_credit' => $proratedCredit,
            'net_amount_paid' => $netAmount,
            'status' => $status,
            'gateway' => $gateway,
            'gateway_ref' => $gatewayRef ?: $invoiceNumber,
            'breakdown' => $breakdown,
            'paid_at' => $status === SubscriptionPayment::STATUS_COMPLETED ? now() : null,
        ]);

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $proration
     * @return array<string, mixed>
     */
    private function upgradeBreakdown(array $proration, Plan $targetPlan, float $netAmount, bool $autoRenew): array
    {
        return [
            'current_plan_name' => $proration['current_plan']->name,
            'target_plan_name' => $targetPlan->name,
            'days_remaining' => $proration['days_remaining'],
            'unused_credit' => $proration['unused_credit'],
            'prorated_charge' => $proration['prorated_target_cost'],
            'net_amount_paid' => $netAmount,
            'auto_renew' => $autoRenew,
            'keeps_current_period' => (bool) $proration['is_prorated'],
        ];
    }

    /**
     * Add free days to an operator's current paid period (admin goodwill). Recorded as a Rp 0 invoice.
     *
     * @throws ValidationException
     */
    public function extendPeriod(Operator $operator, int $days): SubscriptionPayment
    {
        if ($days < 1 || $days > 366) {
            throw ValidationException::withMessages(['days' => __('Extend by 1 to 366 days.')]);
        }

        $plan = $operator->getPlan();

        if ($plan->isFree()) {
            throw ValidationException::withMessages(['days' => __('The free plan has no billing period to extend.')]);
        }

        $previousExpiry = $operator->plan_expires_at;
        $base = $previousExpiry !== null && $previousExpiry->isFuture() ? $previousExpiry : now();
        $newExpiry = $base->copy()->addDays($days);

        $payment = $this->recordInvoice(
            operator: $operator,
            targetPlan: $plan,
            interval: (string) ($operator->subscription_interval ?: 'monthly'),
            status: SubscriptionPayment::STATUS_COMPLETED,
            type: 'subscription_renewal',
            grossAmount: 0.0,
            proratedCredit: 0.0,
            netAmount: 0.0,
            gateway: 'admin_extension',
            breakdown: [
                'mode' => 'extension',
                'days' => $days,
                'previous_expires_at' => $previousExpiry?->toIso8601String(),
                'new_expires_at' => $newExpiry->toIso8601String(),
            ],
            invoicePrefix: 'SUB-EXT-',
        );

        $operator->update(['plan_expires_at' => $newExpiry]);

        $this->slack->subscriptionExtended($operator->fresh() ?? $operator, $days, $newExpiry->format('d M Y'));

        return $payment;
    }

    /**
     * Turn recurring renewal on or off (operator settings and admin desk share this).
     */
    public function setAutoRenew(Operator $operator, bool $enabled): void
    {
        $operator->update(['subscription_auto_renew' => $enabled]);

        $this->slack->autoRenewChanged($operator->fresh() ?? $operator, $enabled);
    }

    /**
     * Cancel a pending scheduled downgrade.
     */
    public function cancelScheduledDowngrade(Operator $operator): void
    {
        $fromPlanName = $operator->pendingPlan?->name ?? 'pending';
        $currentPlanName = $operator->getPlan()->name;

        $operator->cancelPendingPlanChange();

        $this->slack->planChanged(
            $operator->fresh() ?? $operator,
            $fromPlanName,
            $currentPlanName,
            'downgrade_cancelled',
        );
    }

    /**
     * Numeric rank of plan tiers for upgrade/downgrade logic.
     */
    protected function getPlanTierRank(Plan $plan): int
    {
        return $plan->tierRank();
    }
}
