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
    public ?string $target_plan_id = null;
    public string $downgrade_mode = 'end_of_cycle'; // 'end_of_cycle' | 'immediate'
    public string $payment_method = 'qris'; // 'qris' | 'va' | 'cc' | 'direct'
    public bool $is_processing = false;

    /**
     * Mount plan settings component.
     */
    public function mount(): void
    {
        $operator = auth()->user()?->currentOperator();

        if ($operator) {
            $this->active_plan_id = $operator->plan_id ?: Plan::getDefaultPlan()->id;
            $this->billing_interval = (string) ($operator->subscription_interval ?: 'monthly');
        }
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

        if (! $operator || ! $targetPlan) {
            return;
        }

        $currentPlan = $operator->getPlan();

        // If target is exact same plan & interval, do nothing
        if ($currentPlan->id === $targetPlan->id && ($operator->subscription_interval ?: 'monthly') === $this->billing_interval) {
            return;
        }

        $this->target_plan_id = $planId;
        $this->show_switch_modal = true;
    }

    /**
     * Cancel and close plan switch modal.
     */
    public function closeSwitchModal(): void
    {
        $this->show_switch_modal = false;
        $this->target_plan_id = null;
        $this->is_processing = false;
    }

    /**
     * Confirm and execute the selected plan transition.
     */
    public function confirmPlanSwitch(SubscriptionProrationService $prorationService): void
    {
        $operator = auth()->user()?->currentOperator();
        $targetPlan = $this->target_plan_id ? Plan::find($this->target_plan_id) : null;

        if (! $operator || ! $targetPlan) {
            $this->closeSwitchModal();
            return;
        }

        $this->is_processing = true;

        $proration = $prorationService->calculateSwitch($operator, $targetPlan, $this->billing_interval);

        if ($proration['is_upgrade']) {
            $payment = $prorationService->executeUpgrade(
                operator: $operator,
                targetPlan: $targetPlan,
                interval: $this->billing_interval,
                gateway: $this->payment_method,
                gatewayRef: 'INV-'.strtoupper(bin2hex(random_bytes(3)))
            );

            $this->active_plan_id = $targetPlan->id;
            session()->flash('success', __(
                'Upgraded to :plan successfully! Invoice #:invoice paid (Net: Rp :amount). All higher tier features are unlocked immediately.',
                [
                    'plan' => $targetPlan->name,
                    'invoice' => $payment->invoice_number,
                    'amount' => number_format((float) $payment->net_amount_paid, 0, ',', '.'),
                ]
            ));
        } elseif ($proration['is_downgrade']) {
            if ($this->downgrade_mode === 'immediate') {
                $prorationService->executeImmediateDowngrade($operator, $targetPlan, $this->billing_interval);
                $this->active_plan_id = $targetPlan->id;
                session()->flash('success', __(
                    'Your plan was immediately downgraded to :plan.',
                    ['plan' => $targetPlan->name]
                ));
            } else {
                $prorationService->scheduleDowngrade($operator, $targetPlan, $this->billing_interval);
                $actionDate = $operator->plan_expires_at ? Carbon::parse($operator->plan_expires_at)->format('d M Y') : now()->addMonth()->format('d M Y');
                session()->flash('success', __(
                    'Downgrade to :plan scheduled for :date at the end of your active billing period. You retain all current features until then.',
                    ['plan' => $targetPlan->name, 'date' => $actionDate]
                ));
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
        $prorationData = ($operator && $selectedTargetPlan)
            ? $prorationService->calculateSwitch($operator, $selectedTargetPlan, $this->billing_interval)
            : null;

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

<div class="max-w-6xl mx-auto space-y-8">
    <!-- Unified Settings Navigation -->
    <x-settings-nav />

    <!-- Header & Navigation Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-zinc-800">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400">
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
        <div class="inline-flex p-1 rounded-2xl bg-slate-100 dark:bg-zinc-800/80 border border-slate-200/80 dark:border-zinc-700 self-start sm:self-auto shrink-0 shadow-2xs">
            <button
                type="button"
                wire:click="setBillingInterval('monthly')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $billing_interval === 'monthly' ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}"
            >
                {{ __('Monthly Billing') }}
            </button>
            <button
                type="button"
                wire:click="setBillingInterval('yearly')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 {{ $billing_interval === 'yearly' ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}"
            >
                <span>{{ __('Annual Billing') }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ __('Save 17%') }}</span>
            </button>
        </div>
    </div>

    <!-- Feedback Flash Alerts -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2 shadow-xs animate-fade-in">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Scheduled Downgrade Notice Card (if pending change exists) -->
    @if ($operator && $operator->hasPendingPlanChange())
        <div class="p-5 rounded-3xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <span class="p-2.5 rounded-2xl bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-400 text-lg shrink-0">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </span>
                <div>
                    <h4 class="text-sm font-extrabold text-amber-900 dark:text-amber-200">
                        {{ __('Scheduled Plan Downgrade to :plan', ['plan' => $operator->pendingPlan?->name ?? 'Next Plan']) }}
                    </h4>
                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                        {{ __('Effective on :date. You retain all current :plan features until your current paid billing period ends.', [
                            'date' => $operator->pending_plan_action_at ? Carbon::parse($operator->pending_plan_action_at)->format('d M Y') : 'End of period',
                            'plan' => $currentPlan->name
                        ]) }}
                    </p>
                </div>
            </div>

            <button
                type="button"
                wire:click="promptCancelScheduledDowngrade"
                class="px-4 py-2 rounded-xl bg-white dark:bg-zinc-900 hover:bg-slate-50 dark:hover:bg-zinc-800 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 font-bold text-xs shadow-xs transition cursor-pointer self-stretch sm:self-auto shrink-0"
            >
                {{ __('Cancel Downgrade') }}
            </button>
        </div>
    @endif

    <!-- Active Plan Summary Card -->
    <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-indigo-100 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl shadow-xs shrink-0">
                <i class="fa-solid fa-crown"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 block">{{ __('Active Subscription') }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ __('Active & Verified') }}
                    </span>
                    @if (!$currentPlan->isFree() && $operator->plan_expires_at)
                        <span class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">
                            &bull; {{ __('Renews :date', ['date' => Carbon::parse($operator->plan_expires_at)->format('d M Y')]) }}
                        </span>
                    @endif
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white mt-1">{{ $currentPlan->name }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ $currentPlan->tagline ?: __('Standard tour operator plan.') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-6 p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800/80 self-stretch sm:self-auto justify-between sm:justify-end">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Platform Fee') }}</span>
                <span class="font-mono font-black text-lg {{ $agent->getEffectiveCommissionRate() == 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-indigo-600 dark:text-indigo-400' }}">
                    {{ $agent->getEffectiveCommissionRate() == 0 ? __('0% (Zero Fee)') : ($agent->getEffectiveCommissionRate() * 100).'% '.__('All-Inclusive') }}
                </span>
            </div>
            <div class="h-8 w-px bg-slate-200 dark:bg-zinc-700"></div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Package Limit') }}</span>
                <span class="font-bold text-sm text-slate-800 dark:text-slate-200">
                    {{ $currentPlan->package_limit ? __(':count Listings', ['count' => $currentPlan->package_limit]) : __('Unlimited') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Pricing Plans Comparison Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 items-stretch">
        @foreach ($plans as $plan)
            @php
                $isCurrent = ($currentPlan->id === $plan->id) || ($agent && $agent->plan_id === $plan->id) || (!$agent->plan_id && $plan->slug === 'starter');
                $priceMonthly = (float) $plan->price_monthly;
                $priceYearly = (float) $plan->price_yearly;

                $currentRank = match($currentPlan->slug) { 'enterprise' => 3, 'growth' => 2, default => 1 };
                $targetRank = match($plan->slug) { 'enterprise' => 3, 'growth' => 2, default => 1 };
                $isUpgradeOption = ($targetRank > $currentRank) || ($targetRank === $currentRank && $billing_interval === 'yearly' && ($operator->subscription_interval ?: 'monthly') === 'monthly');
                $isDowngradeOption = ($targetRank < $currentRank);
            @endphp
            <div class="rounded-3xl bg-white dark:bg-zinc-900 border {{ $isCurrent ? 'border-indigo-600 ring-2 ring-indigo-600/30 shadow-xl' : ($plan->is_popular ? 'border-indigo-400 dark:border-indigo-700 shadow-md' : 'border-slate-200/80 dark:border-zinc-800 shadow-sm') }} p-6 sm:p-7 flex flex-col justify-between transition-all duration-200 hover:shadow-lg relative">
                
                <div class="space-y-5">
                    <!-- Top Header with Badge Alignment -->
                    <div class="flex items-start justify-between gap-3 min-h-[32px]">
                        <h3 class="font-black text-xl text-slate-900 dark:text-white tracking-tight">
                            {{ $plan->name }}
                        </h3>

                        @if ($isCurrent)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-500 text-white shadow-xs flex items-center gap-1 shrink-0">
                                <i class="fa-solid fa-check text-[9px]"></i>
                                <span>{{ __('Current Plan') }}</span>
                            </span>
                        @elseif ($plan->is_popular)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-indigo-600 text-white shadow-xs shrink-0">
                                {{ __('Most Popular') }}
                            </span>
                        @endif
                    </div>

                    <!-- Tagline Description -->
                    <p class="text-xs text-slate-500 dark:text-slate-400 min-h-[40px] leading-relaxed">
                        {{ $plan->tagline }}
                    </p>

                    <!-- Price Box -->
                    <div class="p-5 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800/80 space-y-2.5">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                                {{ $billing_interval === 'yearly' ? 'Rp ' . number_format($priceYearly, 0, ',', '.') : 'Rp ' . number_format($priceMonthly, 0, ',', '.') }}
                            </span>
                            <span class="text-xs font-semibold text-slate-400">/ {{ $billing_interval === 'yearly' ? __('year') : __('month') }}</span>
                        </div>
                        <div class="space-y-1.5 pt-2.5 border-t border-slate-200/60 dark:border-zinc-700/60 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __('Operator Payout') }}</span>
                                <span class="font-black font-mono text-sm text-emerald-600 dark:text-emerald-400">
                                    {{ __('100% Net to You') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-slate-400">
                                <span>{{ __('Guest Service Fee') }}</span>
                                <span class="font-medium text-slate-600 dark:text-slate-300">
                                    @if ($plan->slug === 'enterprise')
                                        {{ __('0% (Direct BYO)') }}
                                    @else
                                        {{ __('5% Paid by Guest') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tier Limits -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Package Limit') }}</span>
                            <span class="font-extrabold text-xs text-slate-900 dark:text-white mt-0.5 block">
                                {{ $plan->package_limit ? __(':count Listings', ['count' => $plan->package_limit]) : __('Unlimited') }}
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Team Seats') }}</span>
                            <span class="font-extrabold text-xs text-slate-900 dark:text-white mt-0.5 block">
                                {{ $plan->team_member_limit ? __(':count Staff', ['count' => $plan->team_member_limit]) : __('Unlimited Staff') }}
                            </span>
                        </div>
                    </div>

                    <!-- Features Checklist -->
                    <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Included Capabilities') }}</span>
                        <ul class="space-y-2.5 text-xs">
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('quick_booking_links') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('quick_booking_links') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('1-Click Direct Booking & Payment Links') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('advanced_calendar') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('advanced_calendar') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('Advanced Fleet Calendar & Resource Matrix') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('daily_manifest_export') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('daily_manifest_export') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('Daily Run-Sheet & Manifest Export') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('capacity_heatmap') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('capacity_heatmap') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('Monthly Capacity Heatmap Analytics') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('tracking_pixels') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('tracking_pixels') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('Meta Pixel & Google Analytics 4 (ROAS)') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('automated_review_requests') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('automated_review_requests') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('12-Hour Automated Post-Trip Review Emails') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('google_calendar') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('google_calendar') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('Google Calendar 1-Click & Live iCal') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('guest_crm') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('guest_crm') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('Guest Directory CRM & Analytics') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('whatsapp_dispatch') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('whatsapp_dispatch') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('1-Click WhatsApp Dispatch Center') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('custom_domain') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('custom_domain') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('Custom Domain (`yourbrand.com`) + SSL') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5 {{ $plan->hasFeature('byo_gateway') ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400 line-through opacity-75' }}">
                                <i class="fa-solid {{ $plan->hasFeature('byo_gateway') ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300 dark:text-slate-600' }} text-xs mt-0.5 shrink-0"></i>
                                <span>{{ __('BYO Custom Payment Gateway Keys') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- CTA Action Button -->
                <div class="pt-6 border-t border-slate-100 dark:border-zinc-800 mt-6">
                    @if ($isCurrent)
                        <button
                            type="button"
                            disabled
                            class="w-full h-12 rounded-2xl bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-slate-500 font-extrabold text-xs cursor-default flex items-center justify-center gap-2 select-none"
                        >
                            <i class="fa-solid fa-check text-xs text-emerald-500"></i>
                            <span>{{ __('Active Subscription Plan') }}</span>
                        </button>
                    @elseif ($isUpgradeOption)
                        <button
                            type="button"
                            wire:click="initiatePlanSwitch('{{ $plan->id }}')"
                            class="w-full h-12 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-extrabold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-arrow-up text-amber-300 text-xs"></i>
                            <span>{{ __('Upgrade to :plan', ['plan' => $plan->name]) }}</span>
                        </button>
                    @elseif ($isDowngradeOption)
                        <button
                            type="button"
                            wire:click="initiatePlanSwitch('{{ $plan->id }}')"
                            class="w-full h-12 rounded-2xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-200 font-extrabold text-xs sm:text-sm border border-slate-200 dark:border-zinc-700 transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-arrow-down text-slate-400 text-xs"></i>
                            <span>{{ __('Downgrade to :plan', ['plan' => $plan->name]) }}</span>
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="initiatePlanSwitch('{{ $plan->id }}')"
                            class="w-full h-12 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-extrabold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>{{ __('Switch to :plan', ['plan' => $plan->name]) }}</span>
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Feature Comparison Table Accordion (Closed by default) -->
    <div x-data="{ showComparison: false }" class="max-w-6xl mx-auto space-y-4 pt-4">
        <div class="text-center">
            <button
                type="button"
                @click="showComparison = !showComparison"
                class="inline-flex items-center gap-3 px-6 py-3.5 rounded-2xl bg-white dark:bg-zinc-900 hover:bg-slate-50 dark:hover:bg-zinc-800 border border-slate-200 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 text-xs sm:text-sm font-bold transition-all shadow-xs group cursor-pointer"
            >
                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-table-list text-xs"></i>
                </span>
                <span x-text="showComparison ? '{{ __('Hide Detailed Plan Comparison') }}' : '{{ __('Compare All Plan Features & Capabilities') }}'">
                    {{ __('Compare All Plan Features & Capabilities') }}
                </span>
                <i class="fa-solid fa-chevron-down text-xs text-slate-400 dark:text-zinc-400 transition-transform duration-300" :class="showComparison ? 'rotate-180 text-indigo-600 dark:text-indigo-400' : ''"></i>
            </button>
        </div>

        <div
            x-show="showComparison"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            style="display: none;"
            class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-sm overflow-hidden p-6 sm:p-8"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse min-w-[650px]">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400">
                            <th class="py-4 pr-4 font-extrabold uppercase tracking-wider text-[11px] w-2/5">{{ __('Features & Limits') }}</th>
                            <th class="py-4 px-4 font-black uppercase tracking-wider text-[11px] text-center w-1/5 text-slate-800 dark:text-zinc-300">
                                <span>Starter Essential</span>
                                <span class="block text-[10px] font-normal text-slate-500 dark:text-zinc-500 mt-0.5">{{ __('Free Forever') }}</span>
                            </th>
                            <th class="py-4 px-4 font-black uppercase tracking-wider text-[11px] text-center w-1/5 text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-500/5 rounded-t-2xl">
                                <span>Pro Operator</span>
                                <span class="block text-[10px] font-normal text-indigo-600/80 dark:text-indigo-300/80 mt-0.5">Rp 299.000 / mo</span>
                            </th>
                            <th class="py-4 px-4 font-black uppercase tracking-wider text-[11px] text-center w-1/5 text-purple-600 dark:text-purple-400">
                                <span>Agency Ultimate</span>
                                <span class="block text-[10px] font-normal text-purple-600/80 dark:text-purple-300/80 mt-0.5">Rp 999.000 / mo</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                        <tr>
                            <td class="py-3.5 pr-4 font-semibold text-slate-800 dark:text-slate-200">{{ __('Published Package Limit') }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-700 dark:text-slate-300">3 listings</td>
                            <td class="py-3.5 px-4 text-center font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-500/5">10 listings</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400 font-mono">Unlimited</td>
                        </tr>
                        <tr>
                            <td class="py-3.5 pr-4 font-semibold text-slate-800 dark:text-slate-200">{{ __('Team Members & Dispatch Staff') }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-700 dark:text-slate-300">1 Seat</td>
                            <td class="py-3.5 px-4 text-center font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-500/5">3 Seats</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400 font-mono">Unlimited</td>
                        </tr>
                        <tr>
                            <td class="py-3.5 pr-4 font-semibold text-slate-800 dark:text-slate-200">{{ __('Advanced Fleet Matrix & Timelines') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-emerald-500 bg-indigo-50/50 dark:bg-indigo-500/5"><i class="fa-solid fa-check"></i></td>
                            <td class="py-3.5 px-4 text-center text-emerald-500"><i class="fa-solid fa-check"></i></td>
                        </tr>
                        <tr>
                            <td class="py-3.5 pr-4 font-semibold text-slate-800 dark:text-slate-200">{{ __('Daily Run-Sheet & Manifest Export') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-emerald-500 bg-indigo-50/50 dark:bg-indigo-500/5"><i class="fa-solid fa-check"></i></td>
                            <td class="py-3.5 px-4 text-center text-emerald-500"><i class="fa-solid fa-check"></i></td>
                        </tr>
                        <tr>
                            <td class="py-3.5 pr-4 font-semibold text-slate-800 dark:text-slate-200">{{ __('Monthly Capacity Heatmap Analytics') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600 bg-indigo-50/50 dark:bg-indigo-500/5"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-emerald-500 font-bold"><i class="fa-solid fa-check mr-1"></i> Exclusive</td>
                        </tr>
                        <tr>
                            <td class="py-3.5 pr-4 font-semibold text-slate-800 dark:text-slate-200">{{ __('Custom Domain (`yourbrand.com`)') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600 bg-indigo-50/50 dark:bg-indigo-500/5"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-emerald-500"><i class="fa-solid fa-check"></i></td>
                        </tr>
                        <tr>
                            <td class="py-3.5 pr-4 font-semibold text-slate-800 dark:text-slate-200">{{ __('BYO Custom Payment Gateway Keys') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600 bg-indigo-50/50 dark:bg-indigo-500/5"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-emerald-500"><i class="fa-solid fa-check"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Interactive Plan Switch & Proration Modal -->
    @if ($show_switch_modal && $selectedTargetPlan && $prorationData)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6 select-none animate-fade-in">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs transition-opacity" wire:click="closeSwitchModal"></div>

            <!-- Modal Content Card -->
            <div class="relative w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 sm:p-7 space-y-6 z-10">
                <!-- Modal Header -->
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <span class="p-2.5 rounded-2xl {{ $prorationData['is_upgrade'] ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300' }} text-lg">
                            <i class="fa-solid {{ $prorationData['is_upgrade'] ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                        </span>
                        <div>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">
                                {{ $prorationData['is_upgrade'] ? __('Upgrade to :plan', ['plan' => $selectedTargetPlan->name]) : __('Downgrade to :plan', ['plan' => $selectedTargetPlan->name]) }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ __('Current Plan: :current (:interval)', [
                                    'current' => $currentPlan->name,
                                    'interval' => ucfirst($prorationData['current_interval'])
                                ]) }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeSwitchModal"
                        class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition cursor-pointer"
                    >
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>

                <!-- Proration Breakdown Box -->
                @if ($prorationData['is_upgrade'])
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700/80 space-y-3 text-xs">
                        <div class="flex items-center justify-between font-bold text-slate-500 dark:text-slate-400">
                            <span>{{ __('Billing Cycle') }}</span>
                            <span class="font-extrabold text-slate-800 dark:text-slate-200">{{ ucfirst($billing_interval) }}</span>
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

                        <div class="pt-2.5 border-t border-slate-200 dark:border-zinc-700 flex items-center justify-between">
                            <div>
                                <span class="font-extrabold text-slate-900 dark:text-white text-sm block">{{ __('Net Amount Due Today') }}</span>
                                <span class="text-[10px] text-slate-400">{{ __('Instant activation with immediate feature unlock') }}</span>
                            </div>
                            <span class="text-xl font-black font-mono text-indigo-600 dark:text-indigo-400">
                                Rp {{ number_format($prorationData['net_amount_due'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Payment Method Selection -->
                    <div class="space-y-2">
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ __('Select Payment Method') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <label class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition {{ $payment_method === 'qris' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <input type="radio" wire:model.live="payment_method" value="qris" class="hidden" />
                                <i class="fa-solid fa-qrcode text-base text-indigo-600 dark:text-indigo-400"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">QRIS Instant</span>
                                    <span class="text-[10px] text-slate-400">GoPay / OVO / BCA</span>
                                </div>
                            </label>

                            <label class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition {{ $payment_method === 'va' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <input type="radio" wire:model.live="payment_method" value="va" class="hidden" />
                                <i class="fa-solid fa-building-columns text-base text-indigo-600 dark:text-indigo-400"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">Virtual Account</span>
                                    <span class="text-[10px] text-slate-400">Mandiri / BRI / BNI</span>
                                </div>
                            </label>

                            <label class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition {{ $payment_method === 'cc' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <input type="radio" wire:model.live="payment_method" value="cc" class="hidden" />
                                <i class="fa-solid fa-credit-card text-base text-indigo-600 dark:text-indigo-400"></i>
                                <div class="text-xs">
                                    <span class="font-bold block">Credit Card</span>
                                    <span class="text-[10px] text-slate-400">Visa / Mastercard</span>
                                </div>
                            </label>

                            <label class="p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition {{ $payment_method === 'direct' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}">
                                <input type="radio" wire:model.live="payment_method" value="direct" class="hidden" />
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
                        <label class="p-4 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ $downgrade_mode === 'end_of_cycle' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60' }}">
                            <input type="radio" wire:model.live="downgrade_mode" value="end_of_cycle" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-slate-900 dark:text-white">{{ __('End of Billing Cycle') }}</span>
                                    <span class="px-2 py-0.2 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ __('Recommended') }}</span>
                                </div>
                                <p class="text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                    {{ __('Keep all :plan features until your current paid cycle concludes (:date). You will not be charged again.', [
                                        'plan' => $currentPlan->name,
                                        'date' => $operator->plan_expires_at ? Carbon::parse($operator->plan_expires_at)->format('d M Y') : 'end of month'
                                    ]) }}
                                </p>
                            </div>
                        </label>

                        <label class="p-4 rounded-2xl border flex items-start gap-3 cursor-pointer transition {{ $downgrade_mode === 'immediate' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60' }}">
                            <input type="radio" wire:model.live="downgrade_mode" value="immediate" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                            <div>
                                <span class="font-extrabold text-slate-900 dark:text-white">{{ __('Immediate Downgrade') }}</span>
                                <p class="text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                    {{ __('Switches tier immediately. Listing and team limits will be adjusted immediately to match :target.', ['target' => $selectedTargetPlan->name]) }}
                                </p>
                            </div>
                        </label>
                    </div>
                @endif

                <!-- Modal Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button
                        type="button"
                        wire:click="closeSwitchModal"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition cursor-pointer"
                    >
                        {{ __('Cancel') }}
                    </button>

                    <button
                        type="button"
                        wire:click="confirmPlanSwitch"
                        wire:loading.attr="disabled"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                        <span wire:loading.remove>
                            @if ($prorationData['is_upgrade'])
                                <i class="fa-solid fa-lock mr-1 text-xs"></i>
                                {{ __('Pay Rp :amount & Upgrade', ['amount' => number_format($prorationData['net_amount_due'], 0, ',', '.')]) }}
                            @else
                                {{ $downgrade_mode === 'end_of_cycle' ? __('Confirm Scheduled Downgrade') : __('Confirm Immediate Downgrade') }}
                            @endif
                        </span>
                        <span wire:loading class="flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                            <span>{{ __('Processing...') }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Scheduled Downgrade Confirmation Modal -->
    @if ($show_cancel_modal && $operator && $operator->hasPendingPlanChange())
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6 select-none animate-fade-in">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs transition-opacity" wire:click="closeCancelModal"></div>

            <!-- Modal Content Card -->
            <div class="relative w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 sm:p-7 space-y-5 z-10">
                <div class="flex items-center gap-3.5">
                    <span class="p-3 rounded-2xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 text-xl">
                        <i class="fa-solid fa-shield-halved"></i>
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

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700/80 text-xs text-slate-600 dark:text-slate-300 leading-relaxed space-y-2">
                    <p>
                        {{ __('By cancelling the scheduled downgrade, your account will remain on :plan and will renew automatically at the end of your billing cycle (:date).', [
                            'plan' => $currentPlan->name,
                            'date' => $operator->plan_expires_at ? Carbon::parse($operator->plan_expires_at)->format('d M Y') : 'End of period'
                        ]) }}
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ __('You will continue to have uninterrupted access to all :plan capabilities and package limits.', ['plan' => $currentPlan->name]) }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                    <button
                        type="button"
                        wire:click="closeCancelModal"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition cursor-pointer"
                    >
                        {{ __('No, Keep Downgrade') }}
                    </button>

                    <button
                        type="button"
                        wire:click="confirmCancelScheduledDowngrade"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <i class="fa-solid fa-check text-xs"></i>
                        <span>{{ __('Yes, Keep :plan', ['plan' => $currentPlan->name]) }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
