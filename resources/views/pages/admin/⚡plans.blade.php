<?php

use App\Models\Agent;
use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscription Plans & Tiers')] #[Layout('layouts.admin')] class extends Component {
    // Edit / Create Modal State
    public bool $show_modal = false;
    public ?string $editing_plan_id = null;

    public string $name = '';
    public string $slug = '';
    public string $tagline = '';
    public float $price_monthly = 0.00;
    public float $price_yearly = 0.00;
    public float $commission_percentage = 10.0;
    public ?int $package_limit = null;
    public ?int $team_member_limit = null;
    public bool $is_active = true;
    public bool $is_popular = false;
    public int $sort_order = 0;

    // Feature Toggles Map
    public array $features = [
        'custom_subdomain' => true,
        'standard_checkout' => true,
        'reservations_management' => true,
        'whatsapp_chat_widget' => true,
        'quick_booking_links' => true,
        'google_calendar' => false,
        'guest_crm' => false,
        'whatsapp_dispatch' => false,
        'tracking_pixels' => false,
        'automated_review_requests' => false,
        'custom_domain' => false,
        'byo_gateway' => false,
        'priority_support' => false,
    ];

    /**
     * Open modal to edit an existing plan.
     */
    public function editPlan(string $planId): void
    {
        $plan = Plan::find($planId);

        if (! $plan) {
            return;
        }

        $this->editing_plan_id = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->tagline = (string) ($plan->tagline ?? '');
        $this->price_monthly = (float) $plan->price_monthly;
        $this->price_yearly = (float) $plan->price_yearly;
        $this->commission_percentage = (float) ($plan->commission_rate * 100);
        $this->package_limit = $plan->package_limit;
        $this->team_member_limit = $plan->team_member_limit;
        $this->is_active = $plan->is_active;
        $this->is_popular = $plan->is_popular;
        $this->sort_order = $plan->sort_order;

        $defaultFeatures = [
            'custom_subdomain' => true,
            'standard_checkout' => true,
            'reservations_management' => true,
            'whatsapp_chat_widget' => true,
            'quick_booking_links' => true,
            'google_calendar' => false,
            'guest_crm' => false,
            'whatsapp_dispatch' => false,
            'tracking_pixels' => false,
            'automated_review_requests' => false,
            'custom_domain' => false,
            'byo_gateway' => false,
            'priority_support' => false,
        ];

        $this->features = array_merge($defaultFeatures, $plan->features ?? []);
        $this->show_modal = true;
    }

    /**
     * Open modal to create a new custom plan tier.
     */
    public function createPlan(): void
    {
        $this->editing_plan_id = null;
        $this->name = '';
        $this->slug = '';
        $this->tagline = '';
        $this->price_monthly = 0.00;
        $this->price_yearly = 0.00;
        $this->commission_percentage = 10.0;
        $this->package_limit = null;
        $this->team_member_limit = null;
        $this->is_active = true;
        $this->is_popular = false;
        $this->sort_order = Plan::count() + 1;

        $this->features = [
            'custom_subdomain' => true,
            'standard_checkout' => true,
            'reservations_management' => true,
            'whatsapp_chat_widget' => true,
            'quick_booking_links' => true,
            'google_calendar' => false,
            'guest_crm' => false,
            'whatsapp_dispatch' => false,
            'tracking_pixels' => false,
            'automated_review_requests' => false,
            'custom_domain' => false,
            'byo_gateway' => false,
            'priority_support' => false,
        ];

        $this->show_modal = true;
    }

    public function closeModal(): void
    {
        $this->show_modal = false;
        $this->editing_plan_id = null;
    }

    /**
     * Save or update subscription plan.
     */
    public function savePlan(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:plans,slug,' . ($this->editing_plan_id ?: 'NULL') . ',id'],
            'tagline' => ['nullable', 'string', 'max:500'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'package_limit' => ['nullable', 'integer', 'min:1'],
            'team_member_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'is_popular' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $attributes = [
            'name' => $this->name,
            'slug' => strtolower($this->slug),
            'tagline' => $this->tagline ?: null,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'commission_rate' => round($this->commission_percentage / 100, 4),
            'package_limit' => $this->package_limit ?: null,
            'team_member_limit' => $this->team_member_limit ?: null,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'is_popular' => $this->is_popular,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editing_plan_id) {
            Plan::where('id', $this->editing_plan_id)->update($attributes);
            session()->flash('success', __('Plan :name updated successfully!', ['name' => $this->name]));
        } else {
            Plan::create($attributes);
            session()->flash('success', __('New Plan :name created successfully!', ['name' => $this->name]));
        }

        $this->closeModal();
    }

    /**
     * Reset standard default plans.
     */
    public function resetDefaultPlans(): void
    {
        Plan::seedDefaultPlans();
        session()->flash('success', __('Default platform subscription tiers have been seeded and updated!'));
    }

    /**
     * Render plans component.
     */
    public function render()
    {
        $plans = Plan::withCount(['operators', 'agents'])->orderBy('sort_order')->get();
        $totalOperators = \App\Models\Operator::count();

        return view('pages.admin.⚡plans', [
            'plans' => $plans,
            'totalOperators' => $totalOperators,
            'totalAgents' => $totalOperators,
        ]);
    }
}; ?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Subscription Plans & Feature Limits') }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Configure operator pricing tiers, platform commission overrides, package limits, and feature gating.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="resetDefaultPlans"
                class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition cursor-pointer"
            >
                <i class="fa-solid fa-rotate-left mr-1 text-[10px]"></i>
                {{ __('Reset Default Tiers') }}
            </button>

            <button
                type="button"
                wire:click="createPlan"
                class="h-9 px-3.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5 cursor-pointer"
            >
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>{{ __('New Plan Tier') }}</span>
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 items-stretch">
        @foreach ($plans as $plan)
            <div class="rounded-3xl bg-white dark:bg-zinc-900 border {{ $plan->is_popular ? 'border-purple-500 ring-2 ring-purple-500/20 shadow-md' : 'border-slate-200/80 dark:border-zinc-800 shadow-sm' }} p-6 sm:p-7 flex flex-col justify-between transition-all duration-200 hover:shadow-lg relative">
                
                <div class="space-y-5">
                    <!-- Top Header with Badge Alignment -->
                    <div class="flex items-start justify-between gap-3 min-h-[32px]">
                        <h3 class="font-black text-xl text-slate-900 dark:text-white tracking-tight">
                            {{ $plan->name }}
                        </h3>

                        @if ($plan->is_popular)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-purple-600 text-white shadow-xs shrink-0">
                                {{ __('Most Popular') }}
                            </span>
                        @endif
                    </div>

                    <!-- Tagline Description -->
                    <p class="text-xs text-slate-500 dark:text-slate-400 min-h-[40px] leading-relaxed">
                        {{ $plan->tagline ?: __('Standard platform subscription tier.') }}
                    </p>

                    <!-- Pricing & Platform Take Rate -->
                    <div class="p-5 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800/80 space-y-2.5">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                                Rp {{ number_format((float) $plan->price_monthly, 0, ',', '.') }}
                            </span>
                            <span class="text-xs font-semibold text-slate-400">/ {{ __('month') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs pt-2.5 border-t border-slate-200/60 dark:border-zinc-700/60">
                            <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __('Operator Payout') }}</span>
                            <span class="font-black font-mono text-sm text-emerald-600 dark:text-emerald-400">
                                {{ __('100% Net to Operator') }}
                            </span>
                        </div>
                    </div>

                    <!-- Limits -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Package Limit') }}</span>
                            <span class="font-extrabold text-xs text-slate-900 dark:text-white mt-0.5 block">
                                {{ $plan->package_limit ? __(':count Packages', ['count' => $plan->package_limit]) : __('Unlimited') }}
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Team Seats') }}</span>
                            <span class="font-extrabold text-xs text-slate-900 dark:text-white mt-0.5 block">
                                {{ $plan->team_member_limit ? __(':count Staff', ['count' => $plan->team_member_limit]) : __('Unlimited Staff') }}
                            </span>
                        </div>
                    </div>

                    <!-- Feature Checklist -->
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
                                <span>{{ __('Google Calendar & iCal Feed Sync') }}</span>
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
                                <span>{{ __('BYO Merchant Gateway Keys') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Card Footer: Subscribers Count & Edit Button -->
                <div class="pt-6 border-t border-slate-100 dark:border-zinc-800 mt-6 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-users text-slate-400 text-xs"></i>
                        {{ __(':count Subscribers', ['count' => $plan->agents_count]) }}
                    </span>

                    <button
                        type="button"
                        wire:click="editPlan('{{ $plan->id }}')"
                        class="h-9 px-3.5 rounded-xl bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/60 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-300 font-bold text-xs transition cursor-pointer flex items-center gap-1.5"
                    >
                        <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                        <span>{{ __('Edit Tier') }}</span>
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Edit / Create Plan Modal -->
    @if ($show_modal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-xl rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-sliders"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ $editing_plan_id ? __('Edit Subscription Plan Tier') : __('Create New Plan Tier') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __('Configure pricing, feature capabilities, and limits for this plan.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form wire:submit="savePlan" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="name" :value="__('Plan Name')" required />
                                <x-input id="name" type="text" wire:model="name" placeholder="{{ __('e.g. Pro Operator') }}" :error="$errors->has('name')" />
                                <x-input-error :messages="$errors->get('name')" />
                            </div>
                            <div>
                                <x-label for="slug" :value="__('Plan Identifier (Slug)')" required />
                                <x-input id="slug" type="text" wire:model="slug" placeholder="{{ __('e.g. growth') }}" class="font-mono" :error="$errors->has('slug')" />
                                <x-input-error :messages="$errors->get('slug')" />
                            </div>
                        </div>

                        <div>
                            <x-label for="tagline" :value="__('Marketing Tagline / Summary')" />
                            <x-input id="tagline" type="text" wire:model="tagline" placeholder="{{ __('Short benefit description...') }}" :error="$errors->has('tagline')" />
                            <x-input-error :messages="$errors->get('tagline')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-zinc-800">
                            <div>
                                <x-label for="price_monthly" :value="__('Monthly Price (Rp)')" required />
                                <x-input id="price_monthly" type="number" step="1000" wire:model="price_monthly" class="font-bold" :error="$errors->has('price_monthly')" />
                                <x-input-error :messages="$errors->get('price_monthly')" />
                            </div>
                            <div>
                                <x-label for="price_yearly" :value="__('Yearly Price (Rp)')" required />
                                <x-input id="price_yearly" type="number" step="1000" wire:model="price_yearly" class="font-bold" :error="$errors->has('price_yearly')" />
                                <x-input-error :messages="$errors->get('price_yearly')" />
                            </div>
                            <div>
                                <x-label for="commission_percentage" :value="__('Commission (%)')" required />
                                <x-input id="commission_percentage" type="number" step="0.1" wire:model="commission_percentage" class="font-bold font-mono text-purple-600" :error="$errors->has('commission_percentage')" />
                                <x-input-error :messages="$errors->get('commission_percentage')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="package_limit" :value="__('Max Packages (Blank = Unlimited)')" />
                                <x-input id="package_limit" type="number" wire:model="package_limit" placeholder="{{ __('Unlimited') }}" :error="$errors->has('package_limit')" />
                                <x-input-error :messages="$errors->get('package_limit')" />
                            </div>
                            <div>
                                <x-label for="team_member_limit" :value="__('Max Team Seats (Blank = Unlimited)')" />
                                <x-input id="team_member_limit" type="number" wire:model="team_member_limit" placeholder="{{ __('Unlimited') }}" :error="$errors->has('team_member_limit')" />
                                <x-input-error :messages="$errors->get('team_member_limit')" />
                            </div>
                        </div>

                        <!-- Feature Toggles -->
                        <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">{{ __('Included Feature Permissions') }}</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.quick_booking_links" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('1-Click Direct Booking & Payment Links') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.advanced_calendar" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Advanced Fleet Calendar & Resource Matrix') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.daily_manifest_export" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Daily Run-Sheet & Manifest Export') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.capacity_heatmap" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Monthly Capacity Heatmap Analytics') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.tracking_pixels" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Meta Pixel & Google Analytics 4 (ROAS)') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.automated_review_requests" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('12-Hour Automated Post-Trip Review Emails') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.google_calendar" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Google Calendar Sync & iCal Feed') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.guest_crm" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Guest Directory CRM & Metrics') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.whatsapp_dispatch" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('1-Click WhatsApp Dispatch') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.custom_domain" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Custom Domain (`yourbrand.com`)') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="features.byo_gateway" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('BYO Custom Payment Gateway') }}</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer">
                                    <input type="checkbox" wire:model="is_popular" class="rounded text-purple-600 focus:ring-purple-500" />
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Highlight as Most Popular') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Modal Actions Footer -->
                        <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-end gap-3">
                            <x-button type="button" variant="secondary" wire:click="closeModal" class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" variant="primary" class="text-xs font-bold bg-purple-600 hover:bg-purple-700">
                                <i class="fa-solid fa-floppy-disk mr-1.5 text-xs"></i>
                                <span>{{ __('Save Plan Tier') }}</span>
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
