<?php

use App\Models\Operator;
use App\Models\Plan;
use App\Services\SubscriptionProrationService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscription & Plan')] #[Layout('layouts.app')] class extends Component {
    public ?string $active_plan_id = null;
    public string $billing_interval = 'monthly';

    public bool $show_switch_modal = false;
    public bool $show_cancel_modal = false;
    public bool $show_auto_renew_modal = false;
    public bool $target_auto_renew_state = false;
    public bool $modal_consent_checkbox = false;

    public ?string $target_plan_id = null;
    public string $downgrade_mode = 'end_of_cycle'; // 'end_of_cycle' | 'immediate'
    public string $payment_method = 'cc'; // 'cc' | 'qris' | 'va' | 'direct'
    public bool $auto_renew = true;
    public bool $auto_renew_consent = false;
    public bool $is_processing = false;

    // Platform Subscription Promo Code
    public string $couponCode = '';
    public ?string $appliedCouponCode = null;
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
            $this->active_plan_id = $operator->plan_id ?: Plan::getDefaultPlan()->id;
            $this->billing_interval = (string) ($operator->subscription_interval ?: 'monthly');
            $this->auto_renew = (bool) ($operator->subscription_auto_renew ?? true);
        }
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

        // Platform subscription coupons have operator_id = null
        $coupon = \App\Models\PlatformCoupon::whereNull('operator_id')->where('code', $cleanCode)->first();

        if (!$coupon) {
            $this->couponMessage = __('Invalid subscription promo code.');
            $this->couponValid = false;
            $this->removeCoupon();
            return;
        }

        $operator = auth()->user()?->currentOperator();
        $targetPlan = $this->target_plan_id ? Plan::find($this->target_plan_id) : null;

        if (!$operator || !$targetPlan) {
            return;
        }

        $proration = $prorationService->calculateSwitch($operator, $targetPlan, $this->billing_interval);
        $gross = (float) $proration['prorated_target_cost'];
        $result = $coupon->validateFor($gross);

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

        $operator->update(['subscription_auto_renew' => $this->target_auto_renew_state]);
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
        $operator = auth()->user()?->currentOperator();
        $targetPlan = $this->target_plan_id ? Plan::find($this->target_plan_id) : null;

        if (!$operator || !$targetPlan) {
            $this->closeSwitchModal();
            return;
        }

        $this->is_processing = true;

        $proration = $prorationService->calculateSwitch($operator, $targetPlan, $this->billing_interval);

        if ($proration['is_upgrade']) {
            if ($this->auto_renew && !$this->auto_renew_consent) {
                $this->addError('auto_renew_consent', __('Please confirm your consent for recurring auto-renewal before proceeding.'));
                $this->is_processing = false;
                return;
            }

            $payment = $prorationService->createPendingUpgrade(operator: $operator, targetPlan: $targetPlan, interval: $this->billing_interval, autoRenew: $this->auto_renew, gateway: $this->payment_method, couponCode: $this->appliedCouponCode, discountAmount: $this->discountAmount);

            $this->closeSwitchModal();

            if ($payment->status === 'completed') {
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
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

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
    <div
        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-zinc-800">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400">
                    <i class="fa-solid fa-crown text-lg"></i>
                </span>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ __('Subscription Plan & Tier') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Manage your subscription tier, unlock automation tools, and lower your platform take rate.') }}
            </p>
        </div>

        <!-- Billing Interval Toggle -->
        <div
            class="inline-flex p-1 rounded-2xl bg-slate-100 dark:bg-zinc-800/80 border border-slate-200/80 dark:border-zinc-700 self-start sm:self-auto shrink-0 shadow-2xs">
            <button type="button" wire:click="setBillingInterval('monthly')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $billing_interval === 'monthly' ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">
                {{ __('Monthly Billing') }}
            </button>
            <button type="button" wire:click="setBillingInterval('yearly')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 {{ $billing_interval === 'yearly' ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">
                <span>{{ __('Annual Billing') }}</span>
                <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ __('Save 17%') }}</span>
            </button>
        </div>
    </div>

    <!-- Feedback Flash Alerts -->
    @if (session()->has('success'))
        <div
            class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2 shadow-xs animate-fade-in">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Scheduled Downgrade Notice Card (if pending change exists) -->
    @if ($operator && $operator->hasPendingPlanChange())
        <div
            class="p-5 rounded-3xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <span
                    class="p-2.5 rounded-2xl bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-400 text-lg shrink-0">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </span>
                <div>
                    <h4 class="text-sm font-extrabold text-amber-900 dark:text-amber-200">
                        {{ __('Scheduled Plan Downgrade to :plan', ['plan' => $operator->pendingPlan?->name ?? 'Next Plan']) }}
                    </h4>
                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
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
                class="px-4 py-2 rounded-xl bg-white dark:bg-zinc-900 hover:bg-slate-50 dark:hover:bg-zinc-800 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 font-bold text-xs shadow-xs transition cursor-pointer self-stretch sm:self-auto shrink-0">
                {{ __('Cancel Downgrade') }}
            </button>
        </div>
    @endif

    <!-- Active Plan Summary Card -->
    <div
        class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
            <div
                class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-xl sm:text-2xl font-black shadow-xs shrink-0 mt-0.5">
                <i class="fa-solid fa-crown"></i>
            </div>
            <div class="space-y-1.5 min-w-0 flex-1">
                <!-- Badges Container (Flex Wrap for Mobile) -->
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500 shrink-0">
                        {{ __('Active Subscription') }}
                    </span>
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 shrink-0">
                        {{ __('Active & Verified') }}
                    </span>
                    @if (!$currentPlan->isFree() && $operator->plan_expires_at)
                        <span class="text-[10px] text-slate-400 dark:text-zinc-500 font-medium shrink-0">
                            &bull;
                            {{ __('Renews :date', ['date' => Carbon::parse($operator->plan_expires_at)->format('d M Y')]) }}
                        </span>
                    @endif
                    @if (!$currentPlan->isFree())
                        <button type="button" wire:click="promptToggleAutoRenew"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase transition cursor-pointer shrink-0 {{ $auto_renew ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'bg-slate-100 text-slate-500 dark:bg-[#181d2a] hover:bg-slate-200' }}"
                            title="{{ __('Click to configure recurring auto-renewal settings') }}">
                            <i
                                class="fa-solid {{ $auto_renew ? 'fa-repeat text-[#090d16]' : 'fa-hourglass-half text-amber-500' }} text-[9px]"></i>
                            <span>{{ $auto_renew ? __('Auto-Renew: On') : __('One-Time: Manual') }}</span>
                        </button>
                    @endif
                </div>

                <h3 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white leading-tight">
                    {{ $currentPlan->name }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $currentPlan->tagline ?: __('Standard tour operator plan.') }}
                </p>
            </div>
        </div>

        <!-- Metrics Box -->
        <div
            class="grid grid-cols-2 gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-[#10141d] border border-slate-100 dark:border-[#1e2433] w-full lg:w-auto shrink-0">
            <div>
                <span
                    class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Platform Fee') }}</span>
                <span
                    class="font-mono font-black text-base sm:text-lg {{ $agent->getEffectiveCommissionRate() == 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-[#FFEF4D]' }}">
                    {{ $agent->getEffectiveCommissionRate() == 0 ? __('0% (Zero Fee)') : $agent->getEffectiveCommissionRate() * 100 . '% ' . __('All-Inclusive') }}
                </span>
            </div>
            <div class="pl-4 border-l border-slate-200 dark:border-[#1e2433]">
                <span
                    class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Package Limit') }}</span>
                <span class="font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-200">
                    {{ $currentPlan->package_limit ? __(':count Listings', ['count' => $currentPlan->package_limit]) : __('Unlimited') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Head-to-Head Feature Comparison Table -->
    <div
        class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-sm overflow-x-auto no-scrollbar select-none">
        <table class="w-full text-left border-collapse min-w-[768px]">
            <thead>
                <tr class="bg-slate-50/80 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433]">
                    <th
                        class="p-5 text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 w-1/4">
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
                            $currentRank = match ($currentPlan->slug) {
                                'enterprise' => 3,
                                'growth' => 2,
                                default => 1,
                            };
                            $targetRank = match ($plan->slug) {
                                'enterprise' => 3,
                                'growth' => 2,
                                default => 1,
                            };
                            $isUpgradeOption =
                                $targetRank > $currentRank ||
                                ($targetRank === $currentRank &&
                                    $billing_interval === 'yearly' &&
                                    ($operator->subscription_interval ?: 'monthly') === 'monthly');
                            $isDowngradeOption = $targetRank < $currentRank;
                        @endphp
                        <th class="p-5 text-center w-3/16 border-l border-slate-200/60 dark:border-[#1e2433]">
                            <div class="space-y-3">
                                <div class="min-h-[28px] flex items-center justify-center gap-1.5">
                                    <span
                                        class="font-black text-base text-slate-900 dark:text-white">{{ $plan->name }}</span>
                                    @if ($isCurrent)
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-500 text-white shrink-0">
                                            {{ __('Active') }}
                                        </span>
                                    @elseif ($plan->is_popular)
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-[#FFEF4D] text-[#090d16] shrink-0">
                                            {{ __('Popular') }}
                                        </span>
                                    @endif
                                </div>

                                <div>
                                    <span class="text-2xl font-black text-slate-900 dark:text-white">
                                        {{ $billing_interval === 'yearly' ? 'Rp ' . number_format($priceYearly, 0, ',', '.') : 'Rp ' . number_format($priceMonthly, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[11px] font-medium text-slate-400 block mt-0.5">/
                                        {{ $billing_interval === 'yearly' ? __('year') : __('month') }}</span>
                                </div>

                                <!-- Single Upgrade / Switch CTA Button -->
                                <div class="pt-2">
                                    @if ($isCurrent)
                                        <x-button size="xs" variant="secondary" disabled
                                            class="w-full opacity-60 cursor-default"
                                            icon="<i class='fa-solid fa-check text-emerald-500 text-xs'></i>">
                                            <span>{{ __('Current Plan') }}</span>
                                        </x-button>
                                    @elseif ($isUpgradeOption)
                                        <x-button size="xs" variant="primary"
                                            wire:click="initiatePlanSwitch('{{ $plan->id }}')"
                                            class="w-full shadow-xs"
                                            icon="<i class='fa-solid fa-arrow-up text-amber-300 text-xs'></i>">
                                            <span>{{ __('Upgrade') }}</span>
                                        </x-button>
                                    @else
                                        <x-button size="xs" variant="secondary"
                                            wire:click="initiatePlanSwitch('{{ $plan->id }}')" class="w-full">
                                            <span>{{ __('Switch Plan') }}</span>
                                        </x-button>
                                    @endif
                                </div>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433] text-xs">
                <!-- Group 1: Commercial Model & Volume Limits -->
                <tr class="bg-slate-50/60 dark:bg-[#141824]/80">
                    <td colspan="5"
                        class="px-5 py-2.5 font-extrabold uppercase tracking-wider text-[10px] text-slate-500 dark:text-zinc-400">
                        <i class="fa-solid fa-calculator mr-1"></i> {{ __('Commercial Model & Volume Limits') }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">{{ __('Operator Net Payout') }}
                    </td>
                    @foreach ($plans as $plan)
                        <td
                            class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-[#1e2433] font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            100% Net
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">{{ __('Guest Service Fee') }}</td>
                    @foreach ($plans as $plan)
                        <td
                            class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-[#1e2433] font-medium text-slate-600 dark:text-slate-300">
                            {{ $plan->hasFeature('byo_gateway') ? __('0% (Direct BYO)') : __('5% Paid by Guest') }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Tour Package Listings Limit') }}</td>
                    @foreach ($plans as $plan)
                        <td
                            class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-[#1e2433] font-bold text-slate-900 dark:text-white">
                            {{ $plan->package_limit ? __(':count Listings', ['count' => $plan->package_limit]) : __('Unlimited') }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">{{ __('Team Staff Seats') }}</td>
                    @foreach ($plans as $plan)
                        <td
                            class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60 font-bold text-slate-900 dark:text-white">
                            {{ __('Unlimited Staff') }}
                        </td>
                    @endforeach
                </tr>

                <!-- Group 2: Operations & Scheduling -->
                <tr class="bg-slate-50/60 dark:bg-zinc-800/40">
                    <td colspan="5"
                        class="px-5 py-2.5 font-extrabold uppercase tracking-wider text-[10px] text-indigo-600 dark:text-indigo-400">
                        <i class="fa-solid fa-calendar-days mr-1"></i> {{ __('Operations & Scheduling') }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('1-Click Direct Booking & Payment Links') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Guest Coupons & Promo Code Engine') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('promotional_coupons'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Advanced Resource Matrix Calendar') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('advanced_calendar'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Daily Run-Sheet & Manifest Export') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('daily_manifest_export'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Google Calendar 1-Click & Live iCal Feed') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('google_calendar'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('1-Click WhatsApp Dispatch Center') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('whatsapp_dispatch'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>

                <!-- Group 3: Branding & Payment Infrastructure -->
                <tr class="bg-slate-50/60 dark:bg-zinc-800/40">
                    <td colspan="5"
                        class="px-5 py-2.5 font-extrabold uppercase tracking-wider text-[10px] text-indigo-600 dark:text-indigo-400">
                        <i class="fa-solid fa-globe mr-1"></i> {{ __('Branding & Payment Infrastructure') }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Custom Subdomain (`slug.booking.emvi`)') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Custom Domain (`yourbrand.com`) + SSL') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('custom_domain'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('BYO Custom Payment Gateway Keys') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('byo_gateway'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>

                <!-- Group 4: Analytics, CRM & AI Search Engine -->
                <tr class="bg-slate-50/60 dark:bg-zinc-800/40">
                    <td colspan="5"
                        class="px-5 py-2.5 font-extrabold uppercase tracking-wider text-[10px] text-indigo-600 dark:text-indigo-400">
                        <i class="fa-solid fa-chart-line mr-1"></i> {{ __('Analytics, CRM & AI Search Engine') }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Guest Directory CRM & Lifetime Spend') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('guest_crm'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Meta Pixel & GA4 ROAS Tracking') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('tracking_pixels'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('12-Hour Automated Post-Trip Review Emails') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('automated_review_requests'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ __('Monthly Capacity Heatmap Analytics') }}</td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('capacity_heatmap'))
                                <i class="fa-solid fa-check text-emerald-500 text-sm"></i>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                        <i class="fa-solid fa-wand-magic-sparkles text-purple-600 dark:text-purple-400"></i>
                        <span>{{ __('AI Search & ChatGPT Catalog Discovery (`/llms.txt`)') }}</span>
                    </td>
                    @foreach ($plans as $plan)
                        <td class="px-5 py-3.5 text-center border-l border-slate-100 dark:border-zinc-800/60">
                            @if ($plan->hasFeature('ai_discovery'))
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-purple-100 text-[#090d16] dark:bg-purple-950 dark:text-purple-300">
                                    <i class="fa-solid fa-check text-[#090d16]"></i> {{ __('Included') }}
                                </span>
                            @else
                                <i class="fa-solid fa-minus text-slate-300 dark:text-zinc-700"></i>
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
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6 select-none animate-fade-in">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs transition-opacity"
                wire:click="closeSwitchModal"></div>

            <!-- Modal Content Card -->
            <div
                class="relative w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 sm:p-7 space-y-6 z-10">
                <!-- Modal Header -->
                <div
                    class="flex items-start justify-between gap-4 pb-4 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <span
                            class="p-2.5 rounded-2xl {{ $prorationData['is_upgrade'] ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300' }} text-lg">
                            <i
                                class="fa-solid {{ $prorationData['is_upgrade'] ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                        </span>
                        <div>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">
                                {{ $prorationData['is_upgrade'] ? __('Upgrade to :plan', ['plan' => $selectedTargetPlan->name]) : __('Downgrade to :plan', ['plan' => $selectedTargetPlan->name]) }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ __('Current Plan: :current (:interval)', [
                                    'current' => $currentPlan->name,
                                    'interval' => ucfirst($prorationData['current_interval']),
                                ]) }}
                            </p>
                        </div>
                    </div>

                    <button type="button" wire:click="closeSwitchModal"
                        class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition cursor-pointer">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>

                <!-- Proration Breakdown Box -->
                @if ($prorationData['is_upgrade'])
                    <div
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700/80 space-y-3 text-xs">
                        <div class="flex items-center justify-between font-bold text-slate-500 dark:text-slate-400">
                            <span>{{ __('Billing Cycle') }}</span>
                            <span
                                class="font-extrabold text-slate-800 dark:text-slate-200">{{ ucfirst($billing_interval) }}</span>
                        </div>

                        @if ($prorationData['days_remaining'] > 0 && $prorationData['unused_credit'] > 0)
                            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                <span>{{ __('Unused :plan Credit (:days days remaining)', ['plan' => $currentPlan->name, 'days' => $prorationData['days_remaining']]) }}</span>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    - Rp {{ number_format($prorationData['unused_credit'], 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                            <span>{{ __(':plan Prorated Charge', ['plan' => $selectedTargetPlan->name]) }}</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200">
                                + Rp {{ number_format($prorationData['prorated_target_cost'], 0, ',', '.') }}
                            </span>
                        </div>

                        @if ($discountAmount > 0)
                            <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                                <span class="flex items-center gap-1.5 font-bold">
                                    <i class="fa-solid fa-tag text-[10px]"></i>
                                    <span>{{ __('Subscription Promo (:code)', ['code' => $appliedCouponCode]) }}</span>
                                </span>
                                <span class="font-mono font-bold">
                                    - Rp {{ number_format($discountAmount, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        <!-- Subscription Promo Code Input Accordion in Modal -->
                        <div class="pt-2 border-t border-slate-200/80 dark:border-zinc-700 space-y-1.5"
                            x-data="{ open: @json($appliedCouponCode || $couponMessage ? true : false) }">
                            <div class="flex items-center justify-between">
                                <button type="button" @click="open = !open"
                                    class="text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-ticket text-[11px]"></i>
                                    <span>{{ __('Have a platform promo code?') }}</span>
                                    <i class="fa-solid fa-chevron-down text-[9px] transition-transform duration-200"
                                        :class="{ 'rotate-180': open }"></i>
                                </button>
                                @if ($appliedCouponCode)
                                    <span
                                        class="text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/70 border border-emerald-200/60 dark:border-emerald-800/60 px-2 py-0.5 rounded-full">
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
                                            class="flex-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs font-mono uppercase font-black text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-purple-500 focus:border-purple-500" />
                                        <button type="button" wire:click="applyCoupon" wire:loading.attr="disabled"
                                            wire:target="applyCoupon"
                                            class="h-9 px-3.5 rounded-xl bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white text-xs font-bold transition shadow-xs cursor-pointer shrink-0 disabled:opacity-50 flex items-center gap-1.5">
                                            <span wire:loading.remove
                                                wire:target="applyCoupon">{{ __('Apply') }}</span>
                                            <span wire:loading wire:target="applyCoupon"><i
                                                    class="fa-solid fa-spinner fa-spin text-xs"></i></span>
                                        </button>
                                    </div>
                                @else
                                    <div
                                        class="flex items-center justify-between p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-xs">
                                        <div
                                            class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold">
                                            <i
                                                class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                                            <span>{{ $appliedCouponCode }} (-Rp
                                                {{ number_format($discountAmount, 0, ',', '.') }})</span>
                                        </div>
                                        <button type="button" wire:click="removeCoupon"
                                            class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-bold cursor-pointer">
                                            {{ __('Remove') }}
                                        </button>
                                    </div>
                                @endif

                                @if ($couponMessage && !$appliedCouponCode)
                                    <p
                                        class="text-[11px] font-semibold flex items-center gap-1 {{ $couponValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
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
                            class="pt-2.5 border-t border-slate-200 dark:border-zinc-700 flex items-center justify-between">
                            <div>
                                <span
                                    class="font-extrabold text-slate-900 dark:text-white text-sm block">{{ __('Net Amount Due Today') }}</span>
                                <span
                                    class="text-[10px] text-slate-400">{{ __('Instant activation with immediate feature unlock') }}</span>
                            </div>
                            <span class="text-xl font-black font-mono text-indigo-600 dark:text-indigo-400">
                                Rp {{ number_format($effectiveNetDue, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Renewal Mode Selection -->
                    <div class="space-y-2">
                        <label
                            class="block text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ __('Renewal Type') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <button type="button" wire:click="$set('auto_renew', true)"
                                class="p-3 rounded-2xl border text-left flex items-center gap-2.5 cursor-pointer transition {{ $auto_renew ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/40 text-purple-900 dark:text-white ring-1 ring-purple-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <i
                                    class="fa-solid fa-repeat text-base text-purple-600 dark:text-purple-400 shrink-0"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">{{ __('Auto-Renewing') }}</span>
                                    <span class="text-[10px] text-slate-400">{{ __('Recurring subscription') }}</span>
                                </div>
                            </button>

                            <button type="button" wire:click="$set('auto_renew', false)"
                                class="p-3 rounded-2xl border text-left flex items-center gap-2.5 cursor-pointer transition {{ !$auto_renew ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/40 text-purple-900 dark:text-white ring-1 ring-purple-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <i class="fa-solid fa-hourglass-half text-base text-amber-500 shrink-0"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">{{ __('One-Time Term') }}</span>
                                    <span
                                        class="text-[10px] text-slate-400">{{ __('Manual renewal required') }}</span>
                                </div>
                            </button>
                        </div>

                        @if ($auto_renew)
                            <div
                                class="mt-2.5 p-3 rounded-2xl bg-purple-50/70 dark:bg-purple-950/30 border border-purple-200/80 dark:border-purple-900/50 text-xs space-y-1.5">
                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" wire:model="auto_renew_consent"
                                        class="mt-0.5 rounded border-purple-300 text-purple-600 focus:ring-purple-500 dark:border-purple-800 dark:bg-zinc-900" />
                                    <span
                                        class="text-xs text-purple-900 dark:text-purple-200 font-semibold leading-tight">
                                        {{ __('I consent to recurring auto-renewal charges at the end of each billing cycle until cancelled.') }}
                                    </span>
                                </label>
                                @error('auto_renew_consent')
                                    <p class="text-[11px] font-bold text-rose-600 dark:text-rose-400">{{ $message }}
                                    </p>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <!-- Payment Method Selection -->
                    <div class="space-y-2">
                        <label
                            class="block text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ __('Select Payment Method') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <label
                                class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition {{ $payment_method === 'cc' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <input type="radio" wire:model.live="payment_method" value="cc"
                                    class="hidden" />
                                <i class="fa-solid fa-credit-card text-base text-indigo-600 dark:text-indigo-400"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">Credit Card</span>
                                    <span class="text-[10px] text-slate-400">Visa / Mastercard</span>
                                </div>
                            </label>

                            <label
                                class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition {{ $payment_method === 'qris' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <input type="radio" wire:model.live="payment_method" value="qris"
                                    class="hidden" />
                                <i class="fa-solid fa-qrcode text-base text-indigo-600 dark:text-indigo-400"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">QRIS Instant</span>
                                    <span class="text-[10px] text-slate-400">GoPay / OVO / BCA</span>
                                </div>
                            </label>

                            <label
                                class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition {{ $payment_method === 'va' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <input type="radio" wire:model.live="payment_method" value="va"
                                    class="hidden" />
                                <i
                                    class="fa-solid fa-building-columns text-base text-indigo-600 dark:text-indigo-400"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">Virtual Account</span>
                                    <span class="text-[10px] text-slate-400">Mandiri / BRI / BNI</span>
                                </div>
                            </label>

                            <label
                                class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition {{ $payment_method === 'direct' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <input type="radio" wire:model.live="payment_method" value="direct"
                                    class="hidden" />
                                <i class="fa-solid fa-bolt text-base text-amber-500"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">Test Simulation</span>
                                    <span class="text-[10px] text-slate-400">Instant Activate</span>
                                </div>
                            </label>
                        </div>
                    </div>
                @else
                    <!-- Downgrade Options -->
                    <div class="space-y-3 text-xs">
                        <label
                            class="p-4 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ $downgrade_mode === 'end_of_cycle' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60' }}">
                            <input type="radio" wire:model.live="downgrade_mode" value="end_of_cycle"
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-extrabold text-slate-900 dark:text-white">{{ __('End of Billing Cycle') }}</span>
                                    <span
                                        class="px-2 py-0.2 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ __('Recommended') }}</span>
                                </div>
                                <p class="text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
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
                            class="p-4 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ $downgrade_mode === 'immediate' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60' }}">
                            <input type="radio" wire:model.live="downgrade_mode" value="immediate"
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                            <div>
                                <span
                                    class="font-extrabold text-slate-900 dark:text-white">{{ __('Immediate Downgrade') }}</span>
                                <p class="text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                    {{ __('Switches tier immediately. Listing and team limits will be adjusted immediately to match :target.', ['target' => $selectedTargetPlan->name]) }}
                                </p>
                            </div>
                        </label>
                    </div>
                @endif

                <!-- Modal Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <x-button type="button" variant="secondary" wire:click="closeSwitchModal"
                        class="text-xs font-bold">
                        {{ __('Cancel') }}
                    </x-button>

                    <x-button type="button" variant="primary" wire:click="confirmPlanSwitch"
                        wire:loading.attr="disabled"
                        class="text-xs font-extrabold shadow-md bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800">
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
                    </x-button>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Scheduled Downgrade Confirmation Modal -->
    @if ($show_cancel_modal && $operator && $operator->hasPendingPlanChange())
        <div
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6 select-none animate-fade-in">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs transition-opacity"
                wire:click="closeCancelModal"></div>

            <!-- Modal Content Card -->
            <div
                class="relative w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 sm:p-7 space-y-4 z-10">
                <div class="flex items-center gap-3">
                    <span
                        class="p-2.5 rounded-2xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 text-lg">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">
                            {{ __('Keep Your :plan Subscription?', ['plan' => $currentPlan->name]) }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Cancel scheduled downgrade to :target', ['target' => $operator->pendingPlan?->name ?? 'Next Plan']) }}
                        </p>
                    </div>
                </div>

                <div
                    class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700/80 space-y-2">
                    <p class="text-xs text-slate-700 dark:text-slate-300 font-semibold leading-relaxed">
                        {{ __(
                            'Your pending downgrade will be cancelled immediately. Your subscription will remain on the :plan tier.',
                            [
                                'plan' => $currentPlan->name,
                            ],
                        ) }}
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ __('You will continue to have uninterrupted access to all :plan capabilities and package limits.', ['plan' => $currentPlan->name]) }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                    <x-button type="button" variant="secondary" wire:click="closeCancelModal"
                        class="text-xs font-bold">
                        {{ __('No, Keep Downgrade') }}
                    </x-button>

                    <x-button type="button" variant="primary" wire:click="confirmCancelScheduledDowngrade"
                        class="text-xs font-extrabold shadow-md bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800">
                        <i class='fa-solid fa-check text-xs mr-1.5'></i>
                        <span>{{ __('Yes, Keep :plan', ['plan' => $currentPlan->name]) }}</span>
                    </x-button>
                </div>
            </div>
        </div>
    @endif

    <!-- Auto-Renewal Toggle Confirmation Modal -->
    @if ($show_auto_renew_modal)
        <div
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6 select-none animate-fade-in">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs transition-opacity"
                wire:click="closeAutoRenewModal"></div>

            <!-- Modal Content Card -->
            <div
                class="relative w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 sm:p-7 space-y-5 z-10">
                <div class="flex items-center gap-3.5">
                    <span
                        class="p-3 rounded-2xl {{ $target_auto_renew_state ? 'bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400' : 'bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400' }} text-xl">
                        <i class="fa-solid {{ $target_auto_renew_state ? 'fa-repeat' : 'fa-hourglass-half' }}"></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">
                            {{ $target_auto_renew_state ? __('Enable Recurring Auto-Renewal?') : __('Turn Off Auto-Renewal?') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $target_auto_renew_state ? __('Automatic billing on renewal dates') : __('Switch to one-time manual renewal') }}
                        </p>
                    </div>
                </div>

                @if ($target_auto_renew_state)
                    <div
                        class="p-4 rounded-2xl bg-purple-50/70 dark:bg-purple-950/30 border border-purple-200/80 dark:border-purple-900/50 text-xs text-purple-900 dark:text-purple-200 leading-relaxed space-y-3">
                        <p>
                            {{ __('When auto-renewal is active, your subscription will automatically renew at the end of each billing cycle using your default payment method.') }}
                        </p>
                        <label
                            class="flex items-start gap-2.5 cursor-pointer select-none pt-2 border-t border-purple-200/60 dark:border-purple-900/40">
                            <input type="checkbox" wire:model="modal_consent_checkbox"
                                class="mt-0.5 rounded border-purple-300 text-purple-600 focus:ring-purple-500 dark:border-purple-800 dark:bg-zinc-900" />
                            <span class="text-xs font-semibold text-purple-950 dark:text-white leading-tight">
                                {{ __('I authorize recurring auto-renewal charges for my active subscription until I cancel.') }}
                            </span>
                        </label>
                        @error('modal_consent_checkbox')
                            <p class="text-[11px] font-bold text-rose-600 dark:text-rose-400 mt-1">{{ $message }}
                            </p>
                        @enderror
                    </div>
                @else
                    <div
                        class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/50 text-xs text-amber-900 dark:text-amber-200 leading-relaxed space-y-2">
                        <p>
                            {{ __('Your plan will remain active until :date, after which it will lapse unless renewed manually.', [
                                'date' => $operator->plan_expires_at
                                    ? Carbon::parse($operator->plan_expires_at)->format('d M Y')
                                    : 'the end of your period',
                            ]) }}
                        </p>
                        <p class="text-[11px] text-amber-700 dark:text-amber-300">
                            {{ __('We will send you reminder notifications 7 days, 3 days, and on the due date so you have time to renew.') }}
                        </p>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                    <x-button type="button" variant="secondary" wire:click="closeAutoRenewModal"
                        class="text-xs font-bold">
                        {{ __('Cancel') }}
                    </x-button>

                    <x-button type="button" variant="primary" wire:click="confirmToggleAutoRenew"
                        class="text-xs font-extrabold shadow-md {{ $target_auto_renew_state ? 'bg-purple-600 hover:bg-purple-700 active:bg-purple-800' : 'bg-amber-600 hover:bg-amber-700 active:bg-amber-800' }}">
                        <i class='fa-solid fa-check text-xs mr-1.5'></i>
                        <span>{{ $target_auto_renew_state ? __('Confirm & Enable Auto-Renew') : __('Turn Off Auto-Renew') }}</span>
                    </x-button>
                </div>
            </div>
        </div>
    @endif
</div>
