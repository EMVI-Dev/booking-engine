<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\SubscriptionPayment;
use App\Services\DokuPaymentService;
use App\Services\OperatorActivitySlackNotifier;
use App\Services\SubscriptionProrationService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscription & Plan')] #[Layout('layouts.app')] class extends Component {
    use ResolvesCurrentOperator;

    public ?string $active_plan_id = null;

    public string $billing_interval = 'monthly';

    public bool $show_switch_modal = false;

    public bool $show_cancel_modal = false;

    public bool $show_auto_renew_modal = false;

    public bool $target_auto_renew_state = false;

    public bool $modal_consent_checkbox = false;

    #[Locked]
    public ?string $target_plan_id = null;

    public string $downgrade_mode = 'end_of_cycle'; // 'end_of_cycle' | 'immediate'

    public string $payment_method = 'doku'; // Always routes through Doku checkout

    public bool $auto_renew = true;

    public bool $auto_renew_consent = false;

    public bool $is_processing = false;

    // Platform Subscription Promo Code
    public string $couponCode = '';

    #[Locked]
    public ?string $appliedCouponCode = null;

    #[Locked]
    public float $discountAmount = 0.0;

    public string $couponMessage = '';

    public bool $couponValid = false;

    /**
     * Mount plan settings component.
     */
    public function mount(): void
    {
        $operator = auth()->user()?->currentOperator();

        if ($operator) {
            $this->reconcilePendingSubscriptionPayment($operator);

            $operator->refresh();
            $this->active_plan_id = $operator->plan_id ?: Plan::getDefaultPlan()->id;
            $this->billing_interval = (string) ($operator->subscription_interval ?: 'monthly');
            $this->auto_renew = (bool) ($operator->subscription_auto_renew ?? true);
        }
    }

    /**
     * Poll DOKU for a pending subscription invoice after hosted checkout return.
     */
    protected function reconcilePendingSubscriptionPayment(Operator $operator): void
    {
        $pending = SubscriptionPayment::query()->where('operator_id', $operator->id)->where('status', SubscriptionPayment::STATUS_PENDING)->latest()->first();

        if (!$pending) {
            return;
        }

        $synced = app(DokuPaymentService::class)->syncSubscriptionPaymentStatus($pending);

        if (!$synced) {
            return;
        }

        $pending->refresh();

        if ($pending->status !== SubscriptionPayment::STATUS_COMPLETED) {
            return;
        }

        $planName = $pending->plan?->name ?? __('your new plan');

        session()->flash('success', __('Subscription activated! Upgraded to :plan successfully.', ['plan' => $planName]));
    }

    /**
     * Apply platform subscription promo code in upgrade modal.
     */
    public function applyCoupon(SubscriptionProrationService $prorationService): void
    {
        $cleanCode = strtoupper(trim($this->couponCode));

        if (empty($cleanCode)) {
            $this->couponMessage = __('Please enter a promo code.');
            $this->couponValid = false;

            return;
        }

        $operator = auth()->user()?->currentOperator();

        // Match platform-wide subscription coupons (operator_id = null)
        // or coupons targeted specifically to this operator.
        $coupon = PlatformCoupon::findForSubscription($cleanCode, $operator);

        if (!$coupon) {
            $this->couponMessage = __('Invalid subscription promo code.');
            $this->couponValid = false;
            $this->removeCoupon();

            return;
        }

        // $operator already resolved above.
        $targetPlan = $this->target_plan_id ? Plan::find($this->target_plan_id) : null;

        if (!$operator || !$targetPlan) {
            return;
        }

        $proration = $prorationService->calculateSwitch($operator, $targetPlan, $this->billing_interval);
        $gross = (float) $proration['prorated_target_cost'];
        $result = $coupon->validateFor($gross, $operator?->id);

        if (!$result['valid'] || ($result['discount'] ?? 0) <= 0) {
            $this->couponMessage = $result['reason'] ?? __('Promo code cannot be applied.');
            $this->couponValid = false;
            $this->removeCoupon();

            return;
        }

        $this->appliedCouponCode = $coupon->code;
        $this->discountAmount = (float) $result['discount'];
        $this->couponValid = true;
        $this->couponMessage = __('Code :code applied! Saved Rp :amount', [
            'code' => $coupon->code,
            'amount' => number_format($this->discountAmount, 0, ',', '.'),
        ]);
    }

    /**
     * Remove applied subscription promo code.
     */
    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
        $this->discountAmount = 0.0;
        $this->couponCode = '';
        $this->couponValid = false;
    }

    /**
     * Prompt confirmation modal for changing auto-renewal setting.
     */
    public function promptToggleAutoRenew(): void
    {
        $operator = auth()->user()?->currentOperator();

        if ($operator) {
            $this->target_auto_renew_state = !(bool) ($operator->subscription_auto_renew ?? true);
            $this->modal_consent_checkbox = false;
            $this->show_auto_renew_modal = true;
        }
    }

    /**
     * Close auto-renewal toggle confirmation modal.
     */
    public function closeAutoRenewModal(): void
    {
        $this->show_auto_renew_modal = false;
        $this->modal_consent_checkbox = false;
    }

    /**
     * Confirm and execute auto-renewal state change.
     */
    public function confirmToggleAutoRenew(): void
    {
        $this->authorizeAbility('manageBilling');

        $this->resetErrorBag();

        $operator = auth()->user()?->currentOperator();

        if (!$operator) {
            $this->closeAutoRenewModal();

            return;
        }

        // If enabling auto-renew, require explicit user consent
        if ($this->target_auto_renew_state && !$this->modal_consent_checkbox) {
            $this->addError('modal_consent_checkbox', __('Please check the box to authorize recurring auto-renewal billing.'));

            return;
        }

        app(SubscriptionProrationService::class)->setAutoRenew($operator, $this->target_auto_renew_state);
        $this->auto_renew = $this->target_auto_renew_state;

        session()->flash('success', $this->target_auto_renew_state ? __('Recurring auto-renewal enabled. Your subscription will renew automatically at the end of each billing cycle.') : __('Auto-renewal turned off. Your subscription will lapse at the end of the current term unless manually renewed.'));

        $this->closeAutoRenewModal();
    }

    /**
     * Update billing interval toggle.
     */
    public function setBillingInterval(string $interval): void
    {
        $this->billing_interval = in_array($interval, ['monthly', 'yearly'], true) ? $interval : 'monthly';
    }

    /**
     * Initiate plan switch process and open proration modal.
     */
    public function initiatePlanSwitch(string $planId): void
    {
        $operator = auth()->user()?->currentOperator();
        $targetPlan = Plan::find($planId);

        if (!$operator || !$targetPlan) {
            return;
        }

        $currentPlan = $operator->getPlan();

        // If target is exact same plan & interval, do nothing
        if ($currentPlan->id === $targetPlan->id && ($operator->subscription_interval ?: 'monthly') === $this->billing_interval) {
            return;
        }

        $this->target_plan_id = $planId;
        $this->auto_renew_consent = false;
        $this->removeCoupon();
        $this->couponMessage = '';
        $this->resetErrorBag();
        $this->show_switch_modal = true;
    }

    /**
     * Cancel and close plan switch modal.
     */
    public function closeSwitchModal(): void
    {
        $this->show_switch_modal = false;
        $this->target_plan_id = null;
        $this->auto_renew_consent = false;
        $this->removeCoupon();
        $this->couponMessage = '';
        $this->resetErrorBag();
        $this->is_processing = false;
    }

    /**
     * Confirm and execute the selected plan transition.
     */
    public function confirmPlanSwitch(SubscriptionProrationService $prorationService): void
    {
        $this->authorizeAbility('manageBilling');

        $operator = auth()->user()?->currentOperator();
        $targetPlan = $this->target_plan_id ? Plan::query()->whereKey($this->target_plan_id)->where('is_active', true)->first() : null;
        $this->billing_interval = in_array($this->billing_interval, ['monthly', 'yearly'], true) ? $this->billing_interval : 'monthly';

        if (!$operator || !$targetPlan) {
            $this->closeSwitchModal();

            return;
        }

        $this->is_processing = true;

        $proration = $prorationService->calculateSwitch($operator, $targetPlan, $this->billing_interval);

        // Re-derive the promo discount from the coupon record; component state is never trusted for money.
        $discount = 0.0;
        if ($this->appliedCouponCode !== null) {
            $coupon = PlatformCoupon::findForSubscription($this->appliedCouponCode, $operator);
            $check = $coupon?->validateFor((float) $proration['prorated_target_cost'], $operator?->id) ?? ['valid' => false];

            if (! $check['valid'] || (float) ($check['discount'] ?? 0) <= 0) {
                $this->removeCoupon();
                $this->couponMessage = $check['reason'] ?? __('Promo code cannot be applied.');
                $this->addError('couponCode', $this->couponMessage);
                $this->is_processing = false;

                return;
            }

            $discount = (float) $check['discount'];
            $this->discountAmount = $discount;
        }

        if ($proration['is_upgrade']) {
            if ($this->auto_renew && !$this->auto_renew_consent) {
                $this->addError('auto_renew_consent', __('Please confirm your consent for recurring auto-renewal before proceeding.'));
                $this->is_processing = false;

                return;
            }

            $payment = $prorationService->createPendingUpgrade(operator: $operator, targetPlan: $targetPlan, interval: $this->billing_interval, autoRenew: $this->auto_renew, gateway: 'doku', couponCode: $this->appliedCouponCode, discountAmount: $discount);

            $this->closeSwitchModal();

            if ($payment->status === SubscriptionPayment::STATUS_COMPLETED) {
                $this->active_plan_id = $targetPlan->id;
                session()->flash(
                    'success',
                    __('Upgraded to :plan successfully! Invoice #:invoice paid (Net: Rp :amount). All higher tier features are unlocked immediately.', [
                        'plan' => $targetPlan->name,
                        'invoice' => $payment->invoice_number,
                        'amount' => number_format((float) $payment->net_amount_paid, 0, ',', '.'),
                    ]),
                );
            } else {
                $this->redirectRoute('settings.plan.checkout', $payment->id, navigate: true);

                return;
            }
        } elseif ($proration['is_downgrade']) {
            if ($this->downgrade_mode === 'immediate') {
                $prorationService->executeImmediateDowngrade($operator, $targetPlan, $this->billing_interval);
                $this->active_plan_id = $targetPlan->id;
                session()->flash('success', __('Your plan was immediately downgraded to :plan.', ['plan' => $targetPlan->name]));
            } else {
                $prorationService->scheduleDowngrade($operator, $targetPlan, $this->billing_interval);
                $actionDate = $operator->plan_expires_at ? Carbon::parse($operator->plan_expires_at)->format('d M Y') : now()->addMonth()->format('d M Y');
                session()->flash('success', __('Downgrade to :plan scheduled for :date at the end of your active billing period. You retain all current features until then.', ['plan' => $targetPlan->name, 'date' => $actionDate]));
            }
        }

        $this->closeSwitchModal();
    }

    /**
     * Prompt cancel scheduled downgrade confirmation modal.
     */
    public function promptCancelScheduledDowngrade(): void
    {
        $this->show_cancel_modal = true;
    }

    /**
     * Close cancel scheduled downgrade modal.
     */
    public function closeCancelModal(): void
    {
        $this->show_cancel_modal = false;
    }

    /**
     * Confirm cancellation of a scheduled downgrade.
     */
    public function confirmCancelScheduledDowngrade(SubscriptionProrationService $prorationService): void
    {
        $this->authorizeAbility('manageBilling');

        $operator = auth()->user()?->currentOperator();

        if ($operator) {
            $currentPlan = $operator->getPlan();
            $prorationService->cancelScheduledDowngrade($operator);
            session()->flash('success', __('Scheduled downgrade was cancelled. Your :plan subscription remains active.', ['plan' => $currentPlan->name]));
        }

        $this->show_cancel_modal = false;
    }

    /**
     * Deprecated wrapper for backwards-compatibility with existing tests.
     */
    public function cancelScheduledDowngrade(SubscriptionProrationService $prorationService): void
    {
        $this->confirmCancelScheduledDowngrade($prorationService);
    }

    /**
     * Render plan settings view.
     */
    public function render(SubscriptionProrationService $prorationService)
    {
        $operator = auth()->user()?->currentOperator();
        if ($operator) {
            $operator->refresh();
            $operator->load(['plan', 'pendingPlan']);
        }
        $currentPlan = $operator ? $operator->getPlan() : Plan::getDefaultPlan();
        $plans = Plan::catalog();

        $selectedTargetPlan = $this->target_plan_id ? Plan::find($this->target_plan_id) : null;
        $prorationData = $operator && $selectedTargetPlan ? $prorationService->calculateSwitch($operator, $selectedTargetPlan, $this->billing_interval) : null;

        return view('pages.settings.⚡plan', [
            'operator' => $operator,
            'agent' => $operator,
            'currentPlan' => $currentPlan,
            'plans' => $plans,
            'selectedTargetPlan' => $selectedTargetPlan,
            'prorationData' => $prorationData,
        ]);
    }
}; ?>

<div class="space-y-6 w-full">
    <!-- Subscription & Billing Navigation -->
    <x-billing-nav />

    <!-- Header & Navigation Breadcrumb -->
    <x-page-header
        :title="__('Subscription Plan & Tier')"
        :subtitle="__('Manage your subscription tier, unlock automation tools, and lower your platform take rate.')"
        icon="fa-crown"
        class="pb-4 border-b border-[#E4E5E9] dark:border-[#1E2433]"
    >
        <x-slot:actions>
            <div
                class="inline-flex p-1 rounded-[8px] bg-[#F0F1F3] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] shrink-0 shadow-none">
                <button type="button" wire:click="setBillingInterval('monthly')"
                    class="px-3.5 py-1.5 rounded-[6px] text-xs font-medium transition cursor-pointer {{ $billing_interval === 'monthly' ? 'bg-white dark:bg-[#10141d] text-[#12181E] dark:text-white shadow-none border border-[#E4E5E9] dark:border-[#1E2433]' : 'text-[#5A6578] dark:text-[#9DA4B2] hover:text-[#12181E] dark:hover:text-white border-transparent' }}">
                    {{ __('Monthly Billing') }}
                </button>
                <button type="button" wire:click="setBillingInterval('yearly')"
                    class="px-3.5 py-1.5 rounded-[6px] text-xs font-medium transition cursor-pointer flex items-center gap-1.5 {{ $billing_interval === 'yearly' ? 'bg-white dark:bg-[#10141d] text-[#12181E] dark:text-white shadow-none border border-[#E4E5E9] dark:border-[#1E2433]' : 'text-[#5A6578] dark:text-[#9DA4B2] hover:text-[#12181E] dark:hover:text-white border-transparent' }}">
                    <span>{{ __('Annual Billing') }}</span>
                    <span
                        class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">{{ __('Save 17%') }}</span>
                </button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <!-- Feedback Flash Alerts -->
    @if (session()->has('success'))
        <div
            class="p-3.5 rounded-[8px] bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 text-xs font-medium flex items-center gap-2 shadow-none animate-fade-in">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Scheduled Downgrade Notice Card (if pending change exists) -->
    @if ($operator && $operator->hasPendingPlanChange())
        <div
            class="p-4 sm:p-5 rounded-[12px] bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 shadow-none flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span
                    class="w-10 h-10 rounded-[8px] bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400 flex items-center justify-center text-base shrink-0">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </span>
                <div>
                    <h4 class="text-sm font-semibold text-amber-950 dark:text-amber-200">
                        {{ __('Scheduled Plan Downgrade to :plan', ['plan' => $operator->pendingPlan?->name ?? 'Next Plan']) }}
                    </h4>
                    <p class="text-xs text-amber-800 dark:text-amber-300 mt-0.5">
                        {{ __(
                            'Effective on :date. You retain all current :plan features until your current paid billing period ends.',
                            [
                                'date' => $operator->pending_plan_action_at
                                    ? Carbon::parse($operator->pending_plan_action_at)->format('d M Y')
                                    : 'End of period',
                                'plan' => $currentPlan->name,
                            ],
                        ) }}
                    </p>
                </div>
            </div>

            <button type="button" wire:click="promptCancelScheduledDowngrade"
                class="h-8 px-3 rounded-[6px] bg-white dark:bg-[#10141d] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433] border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 font-medium text-xs shadow-none transition cursor-pointer self-stretch sm:self-auto shrink-0 flex items-center justify-center">
                {{ __('Cancel Downgrade') }}
            </button>
        </div>
    @endif

    <!-- Active Plan Summary Card -->
    <div
        class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
            <div
                class="w-12 h-12 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-xl font-semibold shadow-none shrink-0 mt-0.5">
                <i class="fa-solid fa-crown"></i>
            </div>
            <div class="space-y-1.5 min-w-0 flex-1">
                <!-- Badges Container -->
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="text-[10px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2] shrink-0">
                        {{ __('Active Subscription') }}
                    </span>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-[4px] text-[10px] font-medium uppercase bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 shrink-0">
                        {{ __('Active & Verified') }}
                    </span>
                    @if (!$currentPlan->isFree() && $operator->plan_expires_at)
                        <span class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2] font-medium shrink-0">
                            &bull;
                            {{ __('Renews :date', ['date' => Carbon::parse($operator->plan_expires_at)->format('d M Y')]) }}
                        </span>
                    @endif
                    @if (!$currentPlan->isFree())
                        <button type="button" wire:click="promptToggleAutoRenew"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-[4px] text-[10px] font-medium uppercase transition cursor-pointer shrink-0 border {{ $auto_renew ? 'bg-[#FFEF4D] border-[#FFEF4D] text-[#12181E] font-semibold' : 'bg-transparent border-[#E4E5E9] dark:border-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433]' }}"
                            title="{{ __('Click to configure recurring auto-renewal settings') }}">
                            <i
                                class="fa-solid {{ $auto_renew ? 'fa-repeat text-[#12181E]' : 'fa-hourglass-half text-amber-500' }} text-[9px]"></i>
                            <span>{{ $auto_renew ? __('Auto-Renew: On') : __('One-Time: Manual') }}</span>
                        </button>
                    @endif
                </div>

                <h3 class="text-lg sm:text-xl font-semibold text-[#12181E] dark:text-white leading-tight">
                    {{ $currentPlan->name }}
                </h3>
                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                    {{ $currentPlan->tagline ?: __('Standard tour operator plan.') }}
                </p>
                <p class="mt-2 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                    {{ __('Plan or billing questions:') }}
                    <a href="mailto:{{ \App\Models\PlatformSetting::current()->getOperatorSupportEmail() }}"
                        class="font-medium text-[#12181E] dark:text-white hover:underline">
                        {{ \App\Models\PlatformSetting::current()->getOperatorSupportEmail() }}
                    </a>
                </p>
                @if ($currentPlan->hasFeature('priority_support'))
                    <p class="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                        {{ __('You get faster help from us on this plan.') }}
                    </p>
                @endif
            </div>
        </div>

        <!-- Metrics Box -->
        <div
            class="grid grid-cols-2 gap-4 p-4 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] w-full lg:w-auto shrink-0">
            <div>
                <span
                    class="text-[10px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2] block">{{ __('Platform Fee') }}</span>
                <span
                    class="font-mono font-semibold text-base sm:text-lg {{ $agent->getEffectiveCommissionRate() == 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-[#12181E] dark:text-white' }}">
                    {{ $agent->getEffectiveCommissionRate() == 0 ? __('0% (Zero Fee)') : $agent->getEffectiveCommissionRate() * 100 . '% ' . __('All-Inclusive') }}
                </span>
            </div>
            <div class="pl-4 border-l border-[#E4E5E9] dark:border-[#1E2433]">
                <span
                    class="text-[10px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2] block">{{ __('Listings') }}</span>
                <span class="font-semibold text-xs sm:text-sm text-[#12181E] dark:text-white">
                    {{ $currentPlan->listingLimitLabel() }}
                </span>
            </div>
        </div>
    </div>

    <!-- Mobile Plan Cards (Phone View) -->
    <div class="md:hidden space-y-4">
        @foreach ($plans as $plan)
            @php
                $isCurrent =
                    $currentPlan->id === $plan->id ||
                    ($agent && $agent->plan_id === $plan->id) ||
                    (!$agent->plan_id && $plan->slug === 'starter');
                $priceMonthly = (float) $plan->price_monthly;
                $priceYearly = (float) $plan->price_yearly;
                $currentRank = $currentPlan->tierRank();
                $targetRank = $plan->tierRank();
                $isUpgradeOption =
                    $targetRank > $currentRank ||
                    ($targetRank === $currentRank &&
                        $billing_interval === 'yearly' &&
                        ($operator->subscription_interval ?: 'monthly') === 'monthly');
                $isDowngradeOption = $targetRank < $currentRank;
            @endphp
            <div class="rounded-[12px] bg-white dark:bg-[#10141d] border {{ $plan->is_popular ? 'border-[#FFEF4D] ring-1 ring-[#FFEF4D]' : 'border-[#E4E5E9] dark:border-[#1E2433]' }} p-5 space-y-4 shadow-none">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 class="font-semibold text-base text-[#12181E] dark:text-white">
                            {{ $plan->name }}
                        </h4>
                        @if ($plan->tagline)
                            <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                                {{ $plan->tagline }}
                            </p>
                        @endif
                    </div>
                    @if ($isCurrent)
                        <span class="px-2 py-0.5 rounded-[4px] text-[10px] font-medium uppercase bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 shrink-0">
                            {{ __('Active') }}
                        </span>
                    @elseif ($plan->is_popular)
                        <span class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase bg-[#FFEF4D] text-[#12181E] shrink-0">
                            {{ __('Popular') }}
                        </span>
                    @endif
                </div>

                <div class="p-3.5 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                    <div class="flex items-baseline gap-1">
                        <span class="text-xl font-semibold text-[#12181E] dark:text-white font-mono">
                            {{ $billing_interval === 'yearly' ? 'Rp ' . number_format($priceYearly, 0, ',', '.') : 'Rp ' . number_format($priceMonthly, 0, ',', '.') }}
                        </span>
                        <span class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">/ {{ $billing_interval === 'yearly' ? __('year') : __('month') }}</span>
                    </div>
                </div>

                <!-- Key Highlights List -->
                <ul class="space-y-2 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                    <li class="flex items-center gap-2">
                        <i class="fa-solid fa-cube text-[10px] text-[#12181E] dark:text-[#FFEF4D]"></i>
                        <span class="font-medium text-[#12181E] dark:text-white">{{ $plan->listingLimitLabel() }}</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fa-solid fa-users text-[10px] text-[#12181E] dark:text-[#FFEF4D]"></i>
                        <span>{{ $plan->teamSeatLabel() }}</span>
                    </li>
                    @if ($plan->hasFeature('custom_domain'))
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-globe text-[10px] text-emerald-600 dark:text-emerald-400"></i>
                            <span>{{ __('Custom Domain Support') }}</span>
                        </li>
                    @endif
                    @if ($plan->hasFeature('ai_discovery'))
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-wand-magic-sparkles text-[10px] text-amber-500"></i>
                            <span>{{ __('AI Search Discovery Included') }}</span>
                        </li>
                    @endif
                </ul>

                <div class="pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                    @if ($isCurrent)
                        <button type="button" disabled
                            class="w-full h-9 rounded-[6px] bg-[#F0F1F3] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] font-medium text-xs shadow-none cursor-default flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            <span>{{ __('Current Plan') }}</span>
                        </button>
                    @elseif ($isUpgradeOption)
                        <button type="button" wire:click="initiatePlanSwitch('{{ $plan->id }}')"
                            class="w-full h-9 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] font-semibold text-xs shadow-none transition cursor-pointer flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-arrow-up text-xs"></i>
                            <span>{{ __('Upgrade') }}</span>
                        </button>
                    @else
                        <button type="button" wire:click="initiatePlanSwitch('{{ $plan->id }}')"
                            class="w-full h-9 rounded-[6px] bg-white dark:bg-[#10141d] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#12181E] dark:text-white font-medium text-xs shadow-none transition cursor-pointer flex items-center justify-center gap-1.5">
                            <span>{{ __('Switch Plan') }}</span>
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Desktop Head-to-Head Feature Comparison Table -->
    <div
        class="hidden md:block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-x-auto no-scrollbar select-none">
        <table class="w-full text-left border-collapse min-w-[768px]">
            <thead>
                <tr class="bg-[#F8F9FA] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <th
                        class="p-5 text-xs font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2] w-1/4">
                        {{ __('Plan Features & Capabilities') }}
                    </th>
                    @foreach ($plans as $plan)
                        @php
                            $isCurrent =
                                $currentPlan->id === $plan->id ||
                                ($agent && $agent->plan_id === $plan->id) ||
                                (!$agent->plan_id && $plan->slug === 'starter');
                            $priceMonthly = (float) $plan->price_monthly;
                            $priceYearly = (float) $plan->price_yearly;
                            $currentRank = $currentPlan->tierRank();
                            $targetRank = $plan->tierRank();
                            $isUpgradeOption =
                                $targetRank > $currentRank ||
                                ($targetRank === $currentRank &&
                                    $billing_interval === 'yearly' &&
                                    ($operator->subscription_interval ?: 'monthly') === 'monthly');
                            $isDowngradeOption = $targetRank < $currentRank;
                        @endphp
                        <th class="p-5 text-center w-3/16 border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            <div class="space-y-3">
                                <div class="min-h-[28px] flex items-center justify-center gap-1.5">
                                    <span
                                        class="font-semibold text-base text-[#12181E] dark:text-white">{{ $plan->name }}</span>
                                    @if ($isCurrent)
                                        <span
                                            class="px-2 py-0.5 rounded-[4px] text-[9px] font-medium uppercase bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 shrink-0">
                                            {{ __('Active') }}
                                        </span>
                                    @elseif ($plan->is_popular)
                                        <span
                                            class="px-2 py-0.5 rounded-[4px] text-[9px] font-semibold uppercase bg-[#FFEF4D] text-[#12181E] shrink-0">
                                            {{ __('Popular') }}
                                        </span>
                                    @endif
                                </div>

                                <div>
                                    <span class="text-xl font-semibold text-[#12181E] dark:text-white font-mono">
                                        {{ $billing_interval === 'yearly' ? 'Rp ' . number_format($priceYearly, 0, ',', '.') : 'Rp ' . number_format($priceMonthly, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[11px] font-normal text-[#5A6578] dark:text-[#9DA4B2] block mt-0.5">/
                                        {{ $billing_interval === 'yearly' ? __('year') : __('month') }}</span>
                                </div>

                                <!-- Single Upgrade / Switch CTA Button -->
                                <div class="pt-2">
                                    @if ($isCurrent)
                                        <button type="button" disabled
                                            class="w-full h-8 px-3 rounded-[6px] bg-[#F0F1F3] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] font-medium text-xs shadow-none cursor-default flex items-center justify-center gap-1.5">
                                            <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                                            <span>{{ __('Current Plan') }}</span>
                                        </button>
                                    @elseif ($isUpgradeOption)
                                        <button type="button" wire:click="initiatePlanSwitch('{{ $plan->id }}')"
                                            class="w-full h-8 px-3 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] font-semibold text-xs shadow-none transition cursor-pointer flex items-center justify-center gap-1.5">
                                            <i class="fa-solid fa-arrow-up text-xs"></i>
                                            <span>{{ __('Upgrade') }}</span>
                                        </button>
                                    @else
                                        <button type="button" wire:click="initiatePlanSwitch('{{ $plan->id }}')"
                                            class="w-full h-8 px-3 rounded-[6px] bg-white dark:bg-[#10141d] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#12181E] dark:text-white font-medium text-xs shadow-none transition cursor-pointer flex items-center justify-center gap-1.5">
                                            <span>{{ __('Switch Plan') }}</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433] text-xs">
                <!-- Group 1: Commercial Model & Volume Limits -->
                <tr class="bg-[#F8F9FA]/80 dark:bg-[#10141d]/80">
                    <td colspan="5"
                        class="px-5 py-2.5 font-semibold uppercase tracking-wider text-[10px] text-[#5A6578] dark:text-[#9DA4B2]">
                        <i class="fa-solid fa-calculator mr-1"></i> {{ __('Commercial Model & Volume Limits') }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">{{ __('Operator Net Payout') }}
                    </td>
                    @foreach ($plans as $plan)
                        <td
                            class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433] font-mono font-medium text-emerald-600 dark:text-emerald-400">
                            100% Net
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">{{ __('Guest Service Fee') }}</td>
                    @foreach ($plans as $plan)
                        <td
                            class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433] font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('5% Paid by Guest') }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ __('Trips and activities you can list') }}</td>
                    @foreach ($plans as $plan)
                        <td
                            class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433] font-medium text-[#12181E] dark:text-white">
                            {{ $plan->listingLimitLabel() }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">{{ __('People on your team') }}
                    </td>
                    @foreach ($plans as $plan)
                        <td
                            class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433] font-medium text-[#12181E] dark:text-white">
                            {{ $plan->teamSeatLabel() }}
                        </td>
                    @endforeach
                </tr>

                <!-- Group 2: Operations & Scheduling -->
                <tr class="bg-[#F8F9FA]/80 dark:bg-[#10141d]/80">
                    <td colspan="5"
                        class="px-5 py-2.5 font-semibold uppercase tracking-wider text-[10px] text-[#5A6578] dark:text-[#9DA4B2]">
                        <i class="fa-solid fa-calendar-days mr-1"></i> {{ __('Operations & Scheduling') }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('quick_booking_links') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('promotional_coupons') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('promotional_coupons'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ __('Advanced Resource Matrix Calendar') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('advanced_calendar'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('daily_manifest_export') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('daily_manifest_export'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('google_calendar') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('google_calendar'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('whatsapp_dispatch') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('whatsapp_dispatch'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>

                <!-- Group 3: Branding & Payment Infrastructure -->
                <tr class="bg-[#F8F9FA]/80 dark:bg-[#10141d]/80">
                    <td colspan="5"
                        class="px-5 py-2.5 font-semibold uppercase tracking-wider text-[10px] text-[#5A6578] dark:text-[#9DA4B2]">
                        <i class="fa-solid fa-globe mr-1"></i> {{ __('Branding & Payment Infrastructure') }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ __('Custom Subdomain (`slug.travelengine.id`)') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('custom_domain') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('custom_domain'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ __('Checkout & payouts via EMVI wallet') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                        </td>
                    @endforeach
                </tr>

                <!-- Group 4: Analytics, CRM & AI Search Engine -->
                <tr class="bg-[#F8F9FA]/80 dark:bg-[#10141d]/80">
                    <td colspan="5"
                        class="px-5 py-2.5 font-semibold uppercase tracking-wider text-[10px] text-[#5A6578] dark:text-[#9DA4B2]">
                        <i class="fa-solid fa-chart-line mr-1"></i> {{ __('Analytics, CRM & AI Search Engine') }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('guest_crm') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('guest_crm'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ __('Meta Pixel & GA4 ROAS Tracking') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('tracking_pixels'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('automated_review_requests') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('automated_review_requests'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ __('Monthly Capacity Heatmap Analytics') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('capacity_heatmap'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white flex items-center gap-1.5">
                        <i class="fa-solid fa-wand-magic-sparkles text-amber-500"></i>
                        <span>{{ \App\Models\Plan::featureLabel('ai_discovery') }}</span>
                    </td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('ai_discovery'))
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase bg-[#FFEF4D] text-[#12181E]">
                                    <i class="fa-solid fa-check text-[#12181E]"></i> {{ __('Included') }}
                                </span>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ \App\Models\Plan::featureLabel('remove_branding') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('remove_branding'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-medium text-[#12181E] dark:text-white">
                        {{ __('Faster help from us') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-[#E4E5E9] dark:border-[#1E2433]">
                            @if ($plan->hasFeature('priority_support'))
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-xs"></i>
                            @else
                                <i class="fa-solid fa-minus text-[#C4C7CF] dark:text-[#5A6578] text-xs"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Interactive Plan Switch & Proration Modal -->
    @if ($show_switch_modal && $selectedTargetPlan && $prorationData)
        <div
            class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-6 select-none animate-fade-in"
            wire:keydown.escape.window="closeSwitchModal">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity"
                wire:click="closeSwitchModal"></div>

            <!-- Modal Content Card -->
            <div
                class="relative w-full max-w-lg rounded-t-[16px] sm:rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden p-6 space-y-5 z-10">
                
                <!-- Mobile drag handle -->
                <div class="mx-auto -mt-2 mb-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                <!-- Modal Header -->
                <div
                    class="flex items-start justify-between gap-4 pb-4 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <div class="flex items-center gap-3">
                        <span
                            class="w-10 h-10 rounded-[8px] {{ $prorationData['is_upgrade'] ? 'bg-[#FFEF4D] text-[#12181E]' : 'bg-[#F0F1F3] dark:bg-[#10141d] text-[#12181E] dark:text-white' }} flex items-center justify-center text-sm font-semibold">
                            <i
                                class="fa-solid {{ $prorationData['is_upgrade'] ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                        </span>
                        <div>
                            <h3 class="text-base sm:text-lg font-semibold text-[#12181E] dark:text-white">
                                {{ $prorationData['is_upgrade'] ? __('Upgrade to :plan', ['plan' => $selectedTargetPlan->name]) : __('Downgrade to :plan', ['plan' => $selectedTargetPlan->name]) }}
                            </h3>
                            <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                                {{ __('Current Plan: :current (:interval)', [
                                    'current' => $currentPlan->name,
                                    'interval' => ucfirst($prorationData['current_interval']),
                                ]) }}
                            </p>
                        </div>
                    </div>

                    <button type="button" wire:click="closeSwitchModal"
                        class="h-8 w-8 rounded-[6px] bg-transparent text-[#5A6578] hover:text-[#12181E] dark:text-[#9DA4B2] dark:hover:text-white hover:bg-[#F0F1F3] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center transition cursor-pointer">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>

                <!-- Proration Breakdown Box -->
                @if ($prorationData['is_upgrade'])
                    <div
                        class="p-4 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-3 text-xs">
                        <div class="flex items-center justify-between font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                            <span>{{ __('Billing Cycle') }}</span>
                            <span
                                class="font-semibold text-[#12181E] dark:text-white">{{ ucfirst($billing_interval) }}</span>
                        </div>

                        @if ($prorationData['days_remaining'] > 0 && $prorationData['unused_credit'] > 0)
                            <div class="flex items-center justify-between text-[#5A6578] dark:text-[#9DA4B2]">
                                <span>{{ __('Unused :plan Credit (:days days remaining)', ['plan' => $currentPlan->name, 'days' => $prorationData['days_remaining']]) }}</span>
                                <span class="font-mono font-medium text-emerald-600 dark:text-emerald-400">
                                    - Rp {{ number_format($prorationData['unused_credit'], 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        <div class="flex items-center justify-between text-[#5A6578] dark:text-[#9DA4B2]">
                            <span>{{ __(':plan Prorated Charge', ['plan' => $selectedTargetPlan->name]) }}</span>
                            <span class="font-mono font-medium text-[#12181E] dark:text-white">
                                + Rp {{ number_format($prorationData['prorated_target_cost'], 0, ',', '.') }}
                            </span>
                        </div>

                        @if ($discountAmount > 0)
                            <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                                <span class="flex items-center gap-1.5 font-medium">
                                    <i class="fa-solid fa-tag text-[10px]"></i>
                                    <span>{{ __('Subscription Promo (:code)', ['code' => $appliedCouponCode]) }}</span>
                                </span>
                                <span class="font-mono font-medium">
                                    - Rp {{ number_format($discountAmount, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        <!-- Subscription Promo Code Input Accordion in Modal -->
                        <div class="pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433] space-y-1.5"
                            x-data="{ open: @json($appliedCouponCode || $couponMessage ? true : false) }">
                            <div class="flex items-center justify-between">
                                <button type="button" @click="open = !open"
                                    class="text-xs font-medium text-[#12181E] dark:text-white hover:underline flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-ticket text-[11px] text-[#5A6578] dark:text-[#9DA4B2]"></i>
                                    <span>{{ __('Have a platform promo code?') }}</span>
                                    <i class="fa-solid fa-chevron-down text-[9px] transition-transform duration-200"
                                        :class="{ 'rotate-180': open }"></i>
                                </button>
                                @if ($appliedCouponCode)
                                    <span
                                        class="text-[10px] font-medium uppercase text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 px-2 py-0.5 rounded-[4px]">
                                        {{ $appliedCouponCode }}
                                    </span>
                                @endif
                            </div>

                            <div x-show="open" x-cloak class="space-y-1.5 pt-1">
                                @if (!$appliedCouponCode)
                                    <div class="flex items-center gap-2">
                                        <input type="text" wire:model="couponCode"
                                            wire:keydown.enter.prevent="applyCoupon"
                                            placeholder="{{ __('ENTER PROMO CODE') }}"
                                            class="flex-1 px-3 py-1.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-xs font-mono uppercase font-semibold text-[#12181E] dark:text-white placeholder:text-[#5A6578] focus:border-[#12181E] dark:focus:border-white focus:outline-none shadow-none" />
                                        <button type="button" wire:click="applyCoupon" wire:loading.attr="disabled"
                                            wire:target="applyCoupon"
                                            class="h-8 px-3.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] text-xs font-semibold transition shadow-none cursor-pointer shrink-0 disabled:opacity-50 flex items-center gap-1.5">
                                            <span wire:loading.remove
                                                wire:target="applyCoupon">{{ __('Apply') }}</span>
                                            <span wire:loading wire:target="applyCoupon"><i
                                                    class="fa-solid fa-spinner fa-spin text-xs"></i></span>
                                        </button>
                                    </div>
                                @else
                                    <div
                                        class="flex items-center justify-between p-2.5 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 text-xs">
                                        <div
                                            class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-medium">
                                            <i
                                                class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                                            <span>{{ $appliedCouponCode }} (-Rp
                                                {{ number_format($discountAmount, 0, ',', '.') }})</span>
                                        </div>
                                        <button type="button" wire:click="removeCoupon"
                                            class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-medium cursor-pointer">
                                            {{ __('Remove') }}
                                        </button>
                                    </div>
                                @endif

                                @if ($couponMessage && !$appliedCouponCode)
                                    <p
                                        class="text-[11px] font-medium flex items-center gap-1 {{ $couponValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        <i
                                            class="fa-solid {{ $couponValid ? 'fa-circle-check' : 'fa-circle-exclamation' }} text-[10px]"></i>
                                        <span>{{ $couponMessage }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        @php
                            $effectiveNetDue = max(0, (float) $prorationData['net_amount_due'] - $discountAmount);
                        @endphp
                        <div
                            class="pt-2.5 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between">
                            <div>
                                <span
                                    class="font-semibold text-[#12181E] dark:text-white text-sm block">{{ __('Net Amount Due Today') }}</span>
                                <span
                                    class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Instant activation with immediate feature unlock') }}</span>
                            </div>
                            <span class="text-xl font-semibold font-mono text-[#12181E] dark:text-white">
                                Rp {{ number_format($effectiveNetDue, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Renewal Mode Selection -->
                    <div class="space-y-2">
                        <label
                            class="block text-xs font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('Renewal Type') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <button type="button" wire:click="$set('auto_renew', true)"
                                class="p-3 rounded-[8px] border text-left flex items-center gap-2.5 cursor-pointer transition shadow-none {{ $auto_renew ? 'border-[#12181E] dark:border-white bg-[#F8F9FA] dark:bg-[#10141d] text-[#12181E] dark:text-white ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2]' }}">
                                <i
                                    class="fa-solid fa-repeat text-sm text-[#12181E] dark:text-white shrink-0"></i>
                                <div class="text-xs">
                                    <span class="font-semibold block">{{ __('Auto-Renewing') }}</span>
                                    <span class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Recurring subscription') }}</span>
                                </div>
                            </button>

                            <button type="button" wire:click="$set('auto_renew', false)"
                                class="p-3 rounded-[8px] border text-left flex items-center gap-2.5 cursor-pointer transition shadow-none {{ !$auto_renew ? 'border-[#12181E] dark:border-white bg-[#F8F9FA] dark:bg-[#10141d] text-[#12181E] dark:text-white ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2]' }}">
                                <i class="fa-solid fa-hourglass-half text-sm text-amber-500 shrink-0"></i>
                                <div class="text-xs">
                                    <span class="font-semibold block">{{ __('One-Time Term') }}</span>
                                    <span
                                        class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Manual renewal required') }}</span>
                                </div>
                            </button>
                        </div>

                        @if ($auto_renew)
                            <div
                                class="mt-2.5 p-3 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-xs space-y-1.5">
                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" wire:model="auto_renew_consent"
                                        class="mt-0.5 rounded-[4px] border-[#E4E5E9] dark:border-[#1E2433] text-[#12181E] focus:ring-0 focus:outline-none dark:bg-[#10141d]" />
                                    <span
                                        class="text-xs text-[#12181E] dark:text-white font-medium leading-tight">
                                        {{ __('I consent to recurring auto-renewal charges at the end of each billing cycle until cancelled.') }}
                                    </span>
                                </label>
                                @error('auto_renew_consent')
                                    <p class="text-[11px] font-medium text-rose-600 dark:text-rose-400">{{ $message }}
                                    </p>
                                @enderror
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Downgrade Options -->
                    <div class="space-y-3 text-xs">
                        <label
                            class="p-4 rounded-[8px] border flex items-start gap-3 cursor-pointer transition shadow-none {{ $downgrade_mode === 'end_of_cycle' ? 'border-[#12181E] dark:border-white bg-[#F8F9FA] dark:bg-[#10141d] ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433]' }}">
                            <input type="radio" wire:model.live="downgrade_mode" value="end_of_cycle"
                                class="mt-0.5 text-[#12181E] focus:ring-0" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-semibold text-[#12181E] dark:text-white">{{ __('End of Billing Cycle') }}</span>
                                    <span
                                        class="px-2 py-0.2 rounded-[4px] text-[10px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">{{ __('Recommended') }}</span>
                                </div>
                                <p class="text-[#5A6578] dark:text-[#9DA4B2] mt-1 leading-relaxed">
                                    {{ __(
                                        'Keep all :plan features until your current paid cycle concludes (:date). You will not be charged again.',
                                        [
                                            'plan' => $currentPlan->name,
                                            'date' => $operator->plan_expires_at
                                                ? Carbon::parse($operator->plan_expires_at)->format('d M Y')
                                                : 'end of month',
                                        ],
                                    ) }}
                                </p>
                            </div>
                        </label>

                        <label
                            class="p-4 rounded-[8px] border flex items-start gap-3 cursor-pointer transition shadow-none {{ $downgrade_mode === 'immediate' ? 'border-[#12181E] dark:border-white bg-[#F8F9FA] dark:bg-[#10141d] ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433]' }}">
                            <input type="radio" wire:model.live="downgrade_mode" value="immediate"
                                class="mt-0.5 text-[#12181E] focus:ring-0" />
                            <div>
                                <span
                                    class="font-semibold text-[#12181E] dark:text-white">{{ __('Immediate Downgrade') }}</span>
                                <p class="text-[#5A6578] dark:text-[#9DA4B2] mt-1 leading-relaxed">
                                    {{ __('Switches tier immediately. Listing and team limits will be adjusted immediately to match :target.', ['target' => $selectedTargetPlan->name]) }}
                                </p>
                            </div>
                        </label>
                    </div>
                @endif

                <!-- Modal Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                    <button type="button" wire:click="closeSwitchModal"
                        class="px-4 py-2 rounded-[6px] bg-white dark:bg-[#10141d] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] hover:text-[#12181E] dark:hover:text-white border border-[#E4E5E9] dark:border-[#1E2433] font-medium text-xs shadow-none transition cursor-pointer">
                        {{ __('Cancel') }}
                    </button>

                    <button type="button" wire:click="confirmPlanSwitch"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] font-semibold text-xs shadow-none transition flex items-center gap-1.5 cursor-pointer">
                        <span wire:loading.remove>
                            @if ($prorationData['is_upgrade'])
                                @php
                                    $effectiveNet = max(0, (float) $prorationData['net_amount_due'] - $discountAmount);
                                @endphp
                                <i class="fa-solid fa-lock mr-1 text-xs"></i>
                                {{ $effectiveNet <= 0 ? __('Activate Plan Instantly (100% Discount)') : __('Proceed to Payment (Rp :amount)', ['amount' => number_format($effectiveNet, 0, ',', '.')]) }}
                            @else
                                {{ $downgrade_mode === 'end_of_cycle' ? __('Confirm Scheduled Downgrade') : __('Confirm Immediate Downgrade') }}
                            @endif
                        </span>
                        <span wire:loading>
                            <i class="fa-solid fa-spinner fa-spin mr-1"></i>
                            {{ __('Processing...') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Scheduled Downgrade Confirmation Modal -->
    @if ($show_cancel_modal && $operator && $operator->hasPendingPlanChange())
        <div
            class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-6 select-none animate-fade-in"
            wire:keydown.escape.window="closeCancelModal">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity"
                wire:click="closeCancelModal"></div>

            <!-- Modal Content Card -->
            <div
                class="relative w-full max-w-md rounded-t-[16px] sm:rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden p-6 space-y-4 z-10">
                
                <!-- Mobile drag handle -->
                <div class="mx-auto -mt-2 mb-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                <div class="flex items-center gap-3">
                    <span
                        class="w-10 h-10 rounded-[8px] bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400 flex items-center justify-center text-sm font-semibold">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-[#12181E] dark:text-white">
                            {{ __('Keep Your :plan Subscription?', ['plan' => $currentPlan->name]) }}
                        </h3>
                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                            {{ __('Cancel scheduled downgrade to :target', ['target' => $operator->pendingPlan?->name ?? 'Next Plan']) }}
                        </p>
                    </div>
                </div>

                <div
                    class="p-4 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-2">
                    <p class="text-xs text-[#12181E] dark:text-white font-medium leading-relaxed">
                        {{ __(
                            'Your pending downgrade will be cancelled immediately. Your subscription will remain on the :plan tier.',
                            [
                                'plan' => $currentPlan->name,
                            ],
                        ) }}
                    </p>
                    <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('You will continue to have uninterrupted access to all :plan capabilities and package limits.', ['plan' => $currentPlan->name]) }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                    <button type="button" wire:click="closeCancelModal"
                        class="px-4 py-2 rounded-[6px] bg-white dark:bg-[#10141d] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] hover:text-[#12181E] dark:hover:text-white border border-[#E4E5E9] dark:border-[#1E2433] font-medium text-xs shadow-none transition cursor-pointer">
                        {{ __('No, Keep Downgrade') }}
                    </button>

                    <button type="button" wire:click="confirmCancelScheduledDowngrade"
                        class="px-4 py-2 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] font-semibold text-xs shadow-none transition flex items-center gap-1.5 cursor-pointer">
                        <i class='fa-solid fa-check text-xs'></i>
                        <span>{{ __('Yes, Keep :plan', ['plan' => $currentPlan->name]) }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Auto-Renewal Toggle Confirmation Modal -->
    @if ($show_auto_renew_modal)
        <div
            class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-6 select-none animate-fade-in"
            wire:keydown.escape.window="closeAutoRenewModal">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity"
                wire:click="closeAutoRenewModal"></div>

            <!-- Modal Content Card -->
            <div
                class="relative w-full max-w-md rounded-t-[16px] sm:rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden p-6 space-y-5 z-10">
                
                <!-- Mobile drag handle -->
                <div class="mx-auto -mt-2 mb-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                <div class="flex items-center gap-3.5">
                    <span
                        class="w-10 h-10 rounded-[8px] {{ $target_auto_renew_state ? 'bg-[#FFEF4D] text-[#12181E]' : 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400' }} flex items-center justify-center text-sm font-semibold">
                        <i class="fa-solid {{ $target_auto_renew_state ? 'fa-repeat' : 'fa-hourglass-half' }}"></i>
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-[#12181E] dark:text-white">
                            {{ $target_auto_renew_state ? __('Enable Recurring Auto-Renewal?') : __('Turn Off Auto-Renewal?') }}
                        </h3>
                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                            {{ $target_auto_renew_state ? __('Automatic billing on renewal dates') : __('Switch to one-time manual renewal') }}
                        </p>
                    </div>
                </div>

                @if ($target_auto_renew_state)
                    <div
                        class="p-4 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-xs text-[#12181E] dark:text-white leading-relaxed space-y-3">
                        <p>
                            {{ __('When auto-renewal is active, your subscription will automatically renew at the end of each billing cycle using your default payment method.') }}
                        </p>
                        <label
                            class="flex items-start gap-2.5 cursor-pointer select-none pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <input type="checkbox" wire:model="modal_consent_checkbox"
                                class="mt-0.5 rounded-[4px] border-[#E4E5E9] dark:border-[#1E2433] text-[#12181E] focus:ring-0 dark:bg-[#10141d]" />
                            <span class="text-xs font-medium text-[#12181E] dark:text-white leading-tight">
                                {{ __('I authorize recurring auto-renewal charges for my active subscription until I cancel.') }}
                            </span>
                        </label>
                        @error('modal_consent_checkbox')
                            <p class="text-[11px] font-medium text-rose-600 dark:text-rose-400 mt-1">{{ $message }}
                            </p>
                        @enderror
                    </div>
                @else
                    <div
                        class="p-4 rounded-[8px] bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 leading-relaxed space-y-2">
                        <p>
                            {{ __('Your plan will remain active until :date, after which it will lapse unless renewed manually.', [
                                'date' => $operator->plan_expires_at
                                    ? Carbon::parse($operator->plan_expires_at)->format('d M Y')
                                    : 'the end of your period',
                            ]) }}
                        </p>
                        <p class="text-[11px] text-amber-800 dark:text-amber-300">
                            {{ __('We will send you reminder notifications 7 days, 3 days, and on the due date so you have time to renew.') }}
                        </p>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                    <button type="button" wire:click="closeAutoRenewModal"
                        class="px-4 py-2 rounded-[6px] bg-white dark:bg-[#10141d] hover:bg-[#F8F9FA] dark:hover:bg-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] hover:text-[#12181E] dark:hover:text-white border border-[#E4E5E9] dark:border-[#1E2433] font-medium text-xs shadow-none transition cursor-pointer">
                        {{ __('Cancel') }}
                    </button>

                    <button type="button" wire:click="confirmToggleAutoRenew"
                        class="px-4 py-2 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] font-semibold text-xs shadow-none transition flex items-center gap-1.5 cursor-pointer">
                        <i class='fa-solid fa-check text-xs'></i>
                        <span>{{ $target_auto_renew_state ? __('Confirm & Enable Auto-Renew') : __('Turn Off Auto-Renew') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
