<?php

use App\Models\Operator;
use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscription & Plan')] #[Layout('layouts.app')] class extends Component {
    public ?string $active_plan_id = null;
    public string $billing_interval = 'monthly';

    /**
     * Mount plan settings component.
     */
    public function mount(): void
    {
        $operator = auth()->user()?->currentOperator();

        if ($operator) {
            $this->active_plan_id = $operator->plan_id ?: Plan::getDefaultPlan()->id;
        }
    }

    /**
     * Switch / upgrade subscription plan for the operator.
     */
    public function selectPlan(string $planId): void
    {
        $operator = auth()->user()?->currentOperator();
        $plan = Plan::find($planId);

        if ($operator && $plan) {
            $operator->plan_id = $plan->id;
            $operator->subscribed_at = now();
            $operator->save();
            $operator->unsetRelation('plan');

            $this->active_plan_id = $plan->id;
            session()->flash('success', __('Your plan has been updated to :plan! You now have immediate access to all associated features.', ['plan' => $plan->name]));
        }
    }

    /**
     * Render plan settings view.
     */
    public function render()
    {
        $operator = auth()->user()?->currentOperator();
        if ($operator) {
            $operator->unsetRelation('plan');
        }
        $currentPlan = $operator ? $operator->getPlan() : Plan::getDefaultPlan();
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('pages.settings.⚡plan', [
            'operator' => $operator,
            'agent' => $operator,
            'currentPlan' => $currentPlan,
            'plans' => $plans,
        ]);
    }
}; ?>

<div class="max-w-6xl mx-auto space-y-8" x-data="{ billing_interval: 'monthly' }">
    <!-- Header & Navigation Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-zinc-800">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400">
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
                x-on:click="billing_interval = 'monthly'"
                :class="billing_interval === 'monthly' ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
            >
                {{ __('Monthly Billing') }}
            </button>
            <button
                type="button"
                x-on:click="billing_interval = 'yearly'"
                :class="billing_interval === 'yearly' ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2"
            >
                <span>{{ __('Annual Billing') }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ __('Save 17%') }}</span>
            </button>
        </div>
    </div>

    <!-- Feedback Flash Alerts -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2 shadow-xs">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Active Plan Summary Card -->
    <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-purple-100 dark:bg-purple-950/80 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl shadow-xs shrink-0">
                <i class="fa-solid fa-crown"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400 block">{{ __('Active Subscription') }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ __('Active & Verified') }}
                    </span>
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
                <span class="font-mono font-black text-lg {{ $agent->getEffectiveCommissionRate() == 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400' }}">
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
            @endphp
            <div class="rounded-3xl bg-white dark:bg-zinc-900 border {{ $isCurrent ? 'border-purple-600 ring-2 ring-purple-600/30 shadow-xl' : ($plan->is_popular ? 'border-purple-400 dark:border-purple-700 shadow-md' : 'border-slate-200/80 dark:border-zinc-800 shadow-sm') }} p-6 sm:p-7 flex flex-col justify-between transition-all duration-200 hover:shadow-lg relative">
                
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
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-purple-600 text-white shadow-xs shrink-0">
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
                            <span class="text-3xl font-black text-slate-900 dark:text-white tracking-tight" x-text="billing_interval === 'yearly' ? 'Rp {{ number_format($priceYearly, 0, ',', '.') }}' : 'Rp {{ number_format($priceMonthly, 0, ',', '.') }}'">
                                Rp {{ number_format($priceMonthly, 0, ',', '.') }}
                            </span>
                            <span class="text-xs font-semibold text-slate-400" x-text="billing_interval === 'yearly' ? '/ {{ __('year') }}' : '/ {{ __('month') }}'">/ {{ __('month') }}</span>
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
                            class="w-full h-12 rounded-2xl bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-slate-500 font-extrabold text-xs cursor-default flex items-center justify-center gap-2"
                        >
                            <i class="fa-solid fa-check text-xs text-emerald-500"></i>
                            <span>{{ __('Active Subscription Plan') }}</span>
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="selectPlan('{{ $plan->id }}')"
                            class="w-full h-12 rounded-2xl bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white font-extrabold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-bolt text-amber-300 text-xs"></i>
                            <span>{{ __('Switch to :plan', ['plan' => $plan->name]) }}</span>
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Feature Comparison Table Accordion (Closed by default) -->
    <div x-data="{ showComparison: false }" class="max-w-6xl mx-auto space-y-4 pt-4">
        <!-- Accordion Trigger Button -->
        <div class="text-center">
            <button
                type="button"
                @click="showComparison = !showComparison"
                class="inline-flex items-center gap-3 px-6 py-3.5 rounded-2xl bg-white dark:bg-zinc-900 hover:bg-slate-50 dark:hover:bg-zinc-800 border border-slate-200 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 text-xs sm:text-sm font-bold transition-all shadow-xs group cursor-pointer"
            >
                <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-table-list text-xs"></i>
                </span>
                <span x-text="showComparison ? '{{ __('Hide Detailed Plan Comparison') }}' : '{{ __('Compare All Plan Features & Capabilities') }}'">
                    {{ __('Compare All Plan Features & Capabilities') }}
                </span>
                <i class="fa-solid fa-chevron-down text-xs text-slate-400 dark:text-zinc-400 transition-transform duration-300" :class="showComparison ? 'rotate-180 text-purple-600 dark:text-purple-400' : ''"></i>
            </button>
        </div>

        <!-- Accordion Content Panel -->
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
                            <th class="py-4 px-4 font-black uppercase tracking-wider text-[11px] text-center w-1/5 text-purple-600 dark:text-purple-400 bg-purple-50/50 dark:bg-purple-500/5 rounded-t-2xl">
                                <span>Pro Operator</span>
                                <span class="block text-[10px] font-normal text-purple-600/80 dark:text-purple-300/80 mt-0.5">Rp 299.000 / mo</span>
                            </th>
                            <th class="py-4 px-4 font-black uppercase tracking-wider text-[11px] text-center w-1/5 text-indigo-600 dark:text-indigo-400">
                                <span>Agency Ultimate</span>
                                <span class="block text-[10px] font-normal text-slate-500 dark:text-zinc-500 mt-0.5">Rp 699.000 / mo</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60">
                        <!-- Category: Commercials -->
                        <tr class="bg-slate-50/80 dark:bg-zinc-950/60">
                            <td colspan="4" class="py-3 px-3 font-extrabold text-[11px] uppercase tracking-wider text-purple-700 dark:text-purple-400">
                                {{ __('1. Commercials & Payouts') }}
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Operator Commission Cut') }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400">0% (100% Net)</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400 bg-purple-50/50 dark:bg-purple-500/5">0% (100% Net)</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400">0% (100% Net)</td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Guest Online Booking Fee') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-600 dark:text-zinc-400">5.0%</td>
                            <td class="py-3.5 px-4 text-center text-slate-600 dark:text-zinc-400 bg-purple-50/50 dark:bg-purple-500/5">5.0%</td>
                            <td class="py-3.5 px-4 text-center font-bold text-indigo-600 dark:text-indigo-400">0% (BYO Gateway)</td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Direct Payouts to Indonesian Bank') }}</td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>

                        <!-- Category: Storefront & Web Presence -->
                        <tr class="bg-slate-50/80 dark:bg-zinc-950/60">
                            <td colspan="4" class="py-3 px-3 font-extrabold text-[11px] uppercase tracking-wider text-purple-700 dark:text-purple-400">
                                {{ __('2. Storefront & Website') }}
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Branded Tour Storefront') }}</td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Free Subdomain (`slug.booking.emvi`)') }}</td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Custom Website Domain (`yourbrand.com`) + Auto-SSL') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600 bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center font-bold text-indigo-600 dark:text-indigo-400"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i> Included</td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Custom Brand Hex Accent Color & Logo') }}</td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>

                        <!-- Category: Booking Engine & Inventory -->
                        <tr class="bg-slate-50/80 dark:bg-zinc-950/60">
                            <td colspan="4" class="py-3 px-3 font-extrabold text-[11px] uppercase tracking-wider text-purple-700 dark:text-purple-400">
                                {{ __('3. Booking Engine & Inventory') }}
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Tour Package Listings Limit') }}</td>
                            <td class="py-3.5 px-4 text-center font-semibold text-slate-700 dark:text-zinc-300">5 Packages</td>
                            <td class="py-3.5 px-4 text-center font-bold text-purple-600 dark:text-purple-300 bg-purple-50/50 dark:bg-purple-500/5">25 Packages</td>
                            <td class="py-3.5 px-4 text-center font-extrabold text-emerald-600 dark:text-emerald-400">Unlimited</td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Team Staff Seats & Role Accounts') }}</td>
                            <td class="py-3.5 px-4 text-center font-semibold text-emerald-600 dark:text-emerald-400">Unlimited</td>
                            <td class="py-3.5 px-4 text-center font-semibold text-emerald-600 dark:text-emerald-400 bg-purple-50/50 dark:bg-purple-500/5">Unlimited</td>
                            <td class="py-3.5 px-4 text-center font-semibold text-emerald-600 dark:text-emerald-400">Unlimited</td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('1-Click Direct Booking & Payment Links') }}</td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Instant QRIS & Bank Virtual Accounts (BCA, Mandiri, BRI, BNI)') }}</td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>

                        <!-- Category: Operations & Automations -->
                        <tr class="bg-slate-50/80 dark:bg-zinc-950/60">
                            <td colspan="4" class="py-3 px-3 font-extrabold text-[11px] uppercase tracking-wider text-purple-700 dark:text-purple-400">
                                {{ __('4. Automation & Integrations') }}
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('WhatsApp Floating Chat Widget & Operating Schedule') }}</td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Google & Apple Calendar Live Sync (iCal Feed)') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Customer Directory & Guest CRM (LTV & Trip History)') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('1-Click WhatsApp Tickets & Location Pins Dispatch') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Meta Pixel (Ads ROAS) & Google Analytics 4') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('Automated 12-Hour Review Request Emails') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                            <td class="py-3.5 px-4 text-center"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i></td>
                        </tr>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 pr-4 text-slate-800 dark:text-zinc-300 font-medium">{{ __('BYO Custom Payment Gateway (DOKU, Midtrans, Xendit)') }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center text-slate-300 dark:text-zinc-600 bg-purple-50/50 dark:bg-purple-500/5"><i class="fa-solid fa-minus"></i></td>
                            <td class="py-3.5 px-4 text-center font-bold text-indigo-600 dark:text-indigo-400"><i class="fa-solid fa-check text-emerald-500 dark:text-emerald-400"></i> Included</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
