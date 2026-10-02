<?php

use App\Concerns\RecordsAdminActions;
use App\Services\SubscriptionProrationService;
use App\Models\Operator;
use App\Models\Plan;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Plans')] #[Layout('layouts.admin')] class extends Component {
    use RecordsAdminActions;

    #[Url]
    public string $tab = 'plans'; // 'plans' or 'renewals'

    // Renewals Tab State
    public string $renewalsSearch = '';
    public string $renewalsFilter = 'all'; // 'all', 'active', 'expiring_soon', 'expired'

    // Edit / Create Modal State
    public bool $show_modal = false;
    public ?string $editing_plan_id = null;

    public string $name = '';
    public string $slug = '';
    public string $tagline = '';
    public float $price_monthly = 0.0;
    public float $price_yearly = 0.0;
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
        'promotional_coupons' => true,
        'whatsapp_chat_widget' => true,
        'quick_booking_links' => true,
        'google_calendar' => false,
        'guest_crm' => false,
        'whatsapp_dispatch' => false,
        'tracking_pixels' => false,
        'automated_review_requests' => false,
        'google_reviews' => false,
        'custom_domain' => false,
        'byo_gateway' => false,
        'priority_support' => false,
        'advanced_calendar' => false,
        'daily_manifest_export' => false,
        'capacity_heatmap' => false,
        'ai_discovery' => false,
        'remove_branding' => false,
    ];

    /**
     * Open modal to edit an existing plan.
     */
    public function editPlan(string $planId): void
    {
        $plan = Plan::find($planId);

        if (!$plan) {
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

        $this->features = Plan::featureFlags($plan->features ?? []);
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
        $this->price_monthly = 0.0;
        $this->price_yearly = 0.0;
        $this->commission_percentage = 10.0;
        $this->package_limit = null;
        $this->team_member_limit = null;
        $this->is_active = true;
        $this->is_popular = false;
        $this->sort_order = Plan::count() + 1;

        $this->features = Plan::featureFlags();

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
        $this->validate([
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
            'features' => array_merge($this->features, ['byo_gateway' => false]),
            'is_active' => $this->is_active,
            'is_popular' => $this->is_popular,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editing_plan_id) {
            Plan::where('id', $this->editing_plan_id)->update($attributes);
            $this->audit('plan.updated', Plan::find($this->editing_plan_id), ['price_monthly' => $this->price_monthly, 'price_yearly' => $this->price_yearly, 'is_active' => $this->is_active]);
            session()->flash('success', __('Plan :name updated successfully!', ['name' => $this->name]));
        } else {
            $this->audit('plan.created', Plan::create($attributes), ['slug' => $attributes['slug']]);
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
        $this->audit('plan.defaults_reset');
        session()->flash('success', __('Default platform subscription tiers have been seeded and updated!'));
    }

    /**
     * Send email renewal reminder to operator.
     */
    public function sendRenewalReminder(string $operatorId): void
    {
        $operator = Operator::with('plan')->find($operatorId);

        if (!$operator || !$operator->plan) {
            session()->flash('error', __('Operator or active plan not found.'));

            return;
        }

        try {
            $recipient = app(\App\Services\SubscriptionReminderService::class)->sendRenewalReminder($operator);
            $this->audit('subscription.reminder_sent', $operator, ['recipient' => $recipient]);

            if ($recipient === null) {
                session()->flash('error', __('No billing email configured for :name.', ['name' => $operator->name]));

                return;
            }

            session()->flash(
                'success',
                __('Renewal reminder email sent successfully to :email for :name.', [
                    'email' => $recipient,
                    'name' => $operator->name,
                ]),
            );
        } catch (\Throwable $e) {
            session()->flash('error', __('Failed to send email: :message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Extend operator subscription by given number of days.
     */
    public function extendSubscription(string $operatorId, int $days = 30): void
    {
        $operator = Operator::find($operatorId);

        if (!$operator) {
            return;
        }

        try {
            $invoice = app(SubscriptionProrationService::class)->extendPeriod($operator, $days);
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $operator->refresh();
        $this->audit('subscription.extended', $operator, ['days' => $days, 'invoice' => $invoice->invoice_number, 'new_expires_at' => $operator->plan_expires_at?->toDateString()]);

        session()->flash(
            'success',
            __('Subscription extended by :days days for :name (New expiry: :date).', [
                'days' => $days,
                'name' => $operator->name,
                'date' => $operator->plan_expires_at?->format('d M Y'),
            ]),
        );
    }

    /**
     * Toggle operator auto-renew status.
     */
    public function toggleAutoRenew(string $operatorId): void
    {
        $operator = Operator::find($operatorId);

        if (!$operator) {
            return;
        }

        $enabled = ! (bool) $operator->subscription_auto_renew;
        app(SubscriptionProrationService::class)->setAutoRenew($operator, $enabled);
        $this->audit('subscription.auto_renew_changed', $operator, ['enabled' => $enabled]);

        session()->flash('success', __('Auto-renew updated for :name.', ['name' => $operator->name]));
    }

    /**
     * Render plans component.
     */
    public function render()
    {
        $plans = Plan::withCount(['operators'])
            ->orderBy('sort_order')
            ->get();
        $totalOperators = Operator::count();

        // Query for Renewals Tab
        $renewalsQuery = Operator::query()
            ->with(['plan', 'users'])
            ->whereNotNull('plan_id');

        if (!empty($this->renewalsSearch)) {
            $s = '%' . trim($this->renewalsSearch) . '%';
            $renewalsQuery->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)->orWhere('slug', 'like', $s)->orWhere('billing_email', 'like', $s)->orWhereHas('users', fn($uq) => $uq->where('email', 'like', $s)->orWhere('name', 'like', $s));
            });
        }

        if ($this->renewalsFilter === 'expiring_soon') {
            $renewalsQuery->whereBetween('plan_expires_at', [now(), now()->addDays(7)]);
        } elseif ($this->renewalsFilter === 'expired') {
            $renewalsQuery->where('plan_expires_at', '<', now());
        } elseif ($this->renewalsFilter === 'active') {
            $renewalsQuery->where(function ($q) {
                $q->whereNull('plan_expires_at')->orWhere('plan_expires_at', '>=', now());
            });
        }

        $subscribedOperators = $renewalsQuery->latest('subscribed_at')->get();

        // Renewals Stats
        $activeSubCount = Operator::whereNotNull('plan_id')->count();
        $expiringSoonCount = Operator::whereNotNull('plan_id')
            ->whereBetween('plan_expires_at', [now(), now()->addDays(7)])
            ->count();
        $expiredCount = Operator::whereNotNull('plan_id')->where('plan_expires_at', '<', now())->count();

        return view('pages.admin.⚡plans', [
            'plans' => $plans,
            'totalOperators' => $totalOperators,
            'subscribedOperators' => $subscribedOperators,
            'activeSubCount' => $activeSubCount,
            'expiringSoonCount' => $expiringSoonCount,
            'expiredCount' => $expiredCount,
        ]);
    }
}; ?>

<div class="space-y-6">
    <x-page-header
        :title="__('Plans')"
        :subtitle="__('What operators pay, and what each plan includes.')"
        icon="fa-layer-group"
    >
        @if ($tab === 'plans')
            <x-slot:actions>
                <button type="button" wire:click="resetDefaultPlans"
                    class="h-8 px-3 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white font-medium text-[13px] inline-flex items-center gap-1.5 shadow-none transition cursor-pointer">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>{{ __('Reset Default Tiers') }}</span>
                </button>

                <button type="button" wire:click="createPlan"
                    class="h-8 px-3 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-[13px] inline-flex items-center gap-1.5 shadow-none transition cursor-pointer">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>{{ __('New Plan Tier') }}</span>
                </button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-filter-tabs padded>
        <x-filter-tab wire:click="$set('tab', 'plans')" icon="fa-layer-group" :active="$tab === 'plans'">
            {{ __('Plan Tiers & Features') }}
        </x-filter-tab>
        <x-filter-tab wire:click="$set('tab', 'renewals')" icon="fa-clock-rotate-left" :active="$tab === 'renewals'">
            {{ __('Renewals & Expiries') }}
            @if ($expiringSoonCount > 0)
                <span class="ml-1 rounded-[6px] bg-[#12181E] dark:bg-white text-white dark:text-[#12181E] px-1.5 py-0.5 text-[10px] font-semibold">
                    {{ $expiringSoonCount }}
                </span>
            @endif
        </x-filter-tab>
        <x-filter-tab wire:click="$set('tab', 'wiki')" icon="fa-book-bookmark" :active="$tab === 'wiki'">
            {{ __('Commercial Model & Pricing Wiki') }}
        </x-filter-tab>
    </x-filter-tabs>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div
            class="p-3.5 rounded-[12px] bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0] text-[13px] font-medium flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-sm text-[#059669]"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div
            class="p-3.5 rounded-[12px] bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA] text-[13px] font-medium flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-sm text-[#DC2626]"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- TAB 1: PLANS TIERS CONFIGURATION -->
    @if ($tab === 'plans')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
            @foreach ($plans as $plan)
                <div
                    class="rounded-[12px] bg-white dark:bg-[#10141d] border {{ $plan->is_popular ? 'border-[#FFEF4D] ring-1 ring-[#FFEF4D]' : 'border-[#E4E5E9] dark:border-[#1E2433]' }} p-5 flex flex-col justify-between shadow-none relative transition">
                    <div class="space-y-4">
                        <!-- Top Header with Badge Alignment -->
                        <div class="flex items-start justify-between gap-3 min-h-[28px]">
                            <h3 class="font-semibold text-[17px] text-[#1C2024] dark:text-white tracking-tight">
                                {{ $plan->name }}
                            </h3>
                            @if ($plan->is_popular)
                                <span
                                    class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 shrink-0">
                                    {{ __('Popular') }}
                                </span>
                            @elseif (!$plan->is_active)
                                <span
                                    class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#FAFAFB] text-[#60646C] dark:bg-[#141821] dark:text-slate-400 border border-[#E4E5E9] dark:border-[#1E2433] shrink-0">
                                    {{ __('Archived') }}
                                </span>
                            @endif
                        </div>

                        <!-- Tagline -->
                        @if ($plan->tagline)
                            <p class="text-[13px] text-[#60646C] dark:text-slate-400 min-h-[36px] leading-[1.43]">
                                {{ $plan->tagline }}
                            </p>
                        @endif

                        <!-- Pricing Breakdown -->
                        <div
                            class="p-4 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-2">
                            <div class="flex items-baseline gap-1">
                                <span class="text-[22px] font-semibold text-[#1C2024] dark:text-white">
                                    Rp {{ number_format((float) $plan->price_monthly, 0, ',', '.') }}
                                </span>
                                <span class="text-[12px] text-[#60646C]">/ {{ __('mo') }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between text-[12px] text-[#60646C] border-t border-[#E4E5E9] dark:border-[#1E2433] pt-2">
                                <span>{{ __('Yearly Plan:') }}</span>
                                <span class="font-medium text-[#1C2024] dark:text-slate-200">Rp
                                    {{ number_format((float) $plan->price_yearly, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[12px] text-[#60646C]">
                                <span>{{ __('Cut from listed price:') }}</span>
                                <span
                                    class="font-medium text-[#856404] dark:text-[#FFEF4D] font-mono">{{ $plan->commission_rate * 100 }}%</span>
                            </div>
                        </div>

                        <!-- Active Operators Count -->
                        <div class="flex items-center justify-between text-[13px] text-[#60646C] px-1">
                            <span>{{ __('Active Operators:') }}</span>
                            <span class="font-medium text-[#1C2024] dark:text-slate-200">
                                {{ $plan->operators_count }} {{ __('operators') }}
                            </span>
                        </div>

                        <!-- Features Summary List -->
                        <div class="space-y-2 pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <span
                                class="text-[11px] font-medium uppercase tracking-wider text-[#8B8D98] block">{{ __('Included Capabilities') }}</span>

                            <div class="space-y-1.5 text-[13px] text-[#60646C] dark:text-slate-300">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-cube text-[11px] text-[#856404] dark:text-[#FFEF4D]"></i>
                                    <span>{{ $plan->listingLimitLabel() }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-users text-[11px] text-[#856404] dark:text-[#FFEF4D]"></i>
                                    <span>{{ $plan->teamSeatLabel() }}</span>
                                </div>
                                @if ($plan->hasFeature('custom_domain'))
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-globe text-[11px] text-emerald-600 dark:text-emerald-400"></i>
                                        <span>{{ __('Your own website address') }}</span>
                                    </div>
                                @endif
                                @if ($plan->hasFeature('tracking_pixels'))
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-chart-line text-[11px] text-sky-600 dark:text-sky-400"></i>
                                        <span>{{ __('Tracking Pixels (Meta & GA4)') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Edit Action -->
                    <div class="pt-5 border-t border-[#E4E5E9] dark:border-[#1E2433] mt-5">
                        <button type="button" wire:click="editPlan('{{ $plan->id }}')"
                            class="w-full h-8 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white font-medium text-[13px] transition flex items-center justify-center gap-2 cursor-pointer shadow-none">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                            <span>{{ __('Edit Tier') }}</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- TAB 2: RENEWALS & EXPIRIES -->
    @if ($tab === 'renewals')
        <div class="space-y-6">
            <!-- Renewals Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div
                    class="p-4 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-1">
                    <span
                        class="text-[12px] font-medium text-[#60646C] dark:text-slate-400">{{ __('Total Active Subscriptions') }}</span>
                    <div class="text-[22px] font-semibold text-[#1C2024] dark:text-white">{{ $activeSubCount }}</div>
                    <p class="text-[12px] text-[#8B8D98]">{{ __('Operators on configured plans') }}</p>
                </div>

                <div
                    class="p-4 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-1">
                    <span
                        class="text-[12px] font-medium text-amber-700 dark:text-amber-400">{{ __('Expiring Within 7 Days') }}</span>
                    <div class="text-[22px] font-semibold text-amber-600 dark:text-amber-400">{{ $expiringSoonCount }}</div>
                    <p class="text-[12px] text-[#8B8D98]">{{ __('Needs renewal notification') }}</p>
                </div>

                <div
                    class="p-4 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-1">
                    <span
                        class="text-[12px] font-medium text-rose-700 dark:text-rose-400">{{ __('Expired / Lapsed') }}</span>
                    <div class="text-[22px] font-semibold text-rose-600 dark:text-rose-400">{{ $expiredCount }}</div>
                    <p class="text-[12px] text-[#8B8D98]">{{ __('Past due date') }}</p>
                </div>
            </div>

            <!-- Filters & Search Bar -->
            <div
                class="p-3.5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col sm:flex-row items-center justify-between gap-3">
                <!-- Search -->
                <div class="relative w-full sm:w-80">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[#8B8D98] text-xs"></i>
                    <input wire:model.live.debounce.300ms="renewalsSearch" type="text"
                        placeholder="{{ __('Search by operator name, slug or email...') }}"
                        class="w-full h-8 pl-8 pr-3 text-[13px] rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#8B8D98] focus:border-[#FFEF4D] focus:ring-1 focus:ring-[#FFEF4D] shadow-none" />
                </div>

                <!-- Status Filter Pills -->
                <div class="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
                    @php
                        $renewalTabs = [
                            'all' => __('All (:count)', ['count' => $activeSubCount]),
                            'expiring_soon' => __('Expiring Soon (7d) (:count)', ['count' => $expiringSoonCount]),
                            'expired' => __('Expired / Lapsed (:count)', ['count' => $expiredCount]),
                        ];
                    @endphp

                    @foreach ($renewalTabs as $val => $label)
                        <button type="button" wire:click="$set('renewalsFilter', '{{ $val }}')"
                            class="px-3 py-1.5 rounded-[6px] text-[12px] font-medium transition shrink-0 cursor-pointer {{ $renewalsFilter === $val ? 'bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] shadow-none' : 'bg-[#FAFAFB] dark:bg-[#141821] text-[#60646C] dark:text-slate-400 hover:bg-[#EFEFF0] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433]' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Operators Subscription Table -->
            <div class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead>
                            <tr class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium uppercase tracking-wider text-[#60646C]">
                                <th class="py-3 px-4 sm:px-5">{{ __('Operator') }}</th>
                                <th class="py-3 px-4">{{ __('Plan & Billing') }}</th>
                                <th class="py-3 px-4">{{ __('Subscribed Date') }}</th>
                                <th class="py-3 px-4">{{ __('Expiration / Next Billing') }}</th>
                                <th class="py-3 px-4 text-center">{{ __('Auto-Renew') }}</th>
                                <th class="py-3 px-4 sm:px-5 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                            @forelse ($subscribedOperators as $op)
                                @php
                                    $isExpired = $op->plan_expires_at && $op->plan_expires_at->isPast();
                                    $isExpiringSoon =
                                        $op->plan_expires_at &&
                                        !$isExpired &&
                                        $op->plan_expires_at->diffInDays(now()) <= 7;
                                    $email = $op->billingRecipient();
                                @endphp
                                <tr class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/60 transition group">
                                    <!-- Operator -->
                                    <td class="py-3.5 px-4 sm:px-5">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 rounded-[6px] bg-[#FFEF4D] text-[#12181E] font-semibold flex items-center justify-center text-[12px] shrink-0 overflow-hidden shadow-none">
                                                {{ strtoupper(substr($op->name, 0, 2)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.operators.show', $op->id) }}" wire:navigate
                                                    class="font-medium text-[13px] text-[#1C2024] dark:text-white hover:text-[#856404] dark:hover:text-[#FFEF4D] transition truncate block">
                                                    {{ $op->name }}
                                                </a>
                                                <span
                                                    class="text-[12px] text-[#60646C] dark:text-slate-400 truncate block">{{ $email ?? '-' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Plan & Billing -->
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-0.5">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                                {{ $op->plan?->name ?? 'Custom' }}
                                            </span>
                                            <div class="text-[12px] text-[#60646C] dark:text-slate-400">
                                                {{ ucfirst($op->subscription_interval ?? 'monthly') }} &bull; Rp
                                                {{ number_format((float) ($op->plan?->price_monthly ?? 0), 0, ',', '.') }}
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Subscribed Date -->
                                    <td class="py-3.5 px-4 text-[#60646C] dark:text-slate-400 font-mono text-[12px]">
                                        {{ $op->subscribed_at?->format('d M Y') ?? '-' }}
                                    </td>

                                    <!-- Expiration / Status -->
                                    <td class="py-3.5 px-4">
                                        @if ($op->plan_expires_at)
                                            <div class="space-y-0.5">
                                                <div
                                                    class="font-mono text-[12px] font-medium {{ $isExpired ? 'text-rose-600 dark:text-rose-400' : ($isExpiringSoon ? 'text-amber-600 dark:text-amber-400' : 'text-[#1C2024] dark:text-white') }}">
                                                    {{ $op->plan_expires_at->format('d M Y') }}
                                                </div>
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-[6px] text-[11px] font-medium {{ $isExpired ? 'bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]' : ($isExpiringSoon ? 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]' : 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]') }}">
                                                    {{ $isExpired ? __('Expired') : ($isExpiringSoon ? __('Expiring Soon') : __('Active')) }}
                                                </span>
                                            </div>
                                        @else
                                            <span
                                                class="text-[#8B8D98] text-[12px]">{{ __('No Expiry Set') }}</span>
                                        @endif
                                    </td>

                                    <!-- Auto Renew -->
                                    <td class="py-3.5 px-4 text-center">
                                        <button type="button" wire:click="toggleAutoRenew('{{ $op->id }}')"
                                            class="h-6 px-2.5 rounded-[6px] inline-flex items-center gap-1 text-[11px] font-medium transition cursor-pointer {{ $op->subscription_auto_renew ? 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]' : 'bg-[#FAFAFB] text-[#60646C] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]' }}"
                                            title="{{ __('Click to toggle auto-renewal') }}">
                                            <i
                                                class="fa-solid {{ $op->subscription_auto_renew ? 'fa-check' : 'fa-xmark' }} text-[9px]"></i>
                                            <span>{{ $op->subscription_auto_renew ? __('On') : __('Off') }}</span>
                                        </button>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-4 sm:px-5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button"
                                                wire:click="sendRenewalReminder('{{ $op->id }}')"
                                                class="h-7 px-2.5 rounded-[6px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] hover:bg-[#FFEF4D]/30 text-[11px] font-medium transition border border-[#FFEF4D]/40 inline-flex items-center gap-1 cursor-pointer"
                                                title="{{ __('Send Renewal Reminder Email') }}">
                                                <i class="fa-solid fa-paper-plane text-[9px]"></i>
                                                <span>{{ __('Remind') }}</span>
                                            </button>

                                            <button type="button"
                                                wire:click="extendSubscription('{{ $op->id }}', 30)"
                                                class="h-7 px-2 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] text-[#1C2024] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium transition inline-flex items-center justify-center cursor-pointer"
                                                title="{{ __('Extend subscription by +30 days') }}">
                                                <span>+30d</span>
                                            </button>

                                            <a href="{{ route('admin.operators.show', $op->id) }}" wire:navigate
                                                class="h-7 w-7 rounded-[6px] inline-flex items-center justify-center bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] text-[#60646C] border border-[#E4E5E9] dark:border-[#1E2433] text-[11px] transition cursor-pointer"
                                                title="{{ __('View Operator Details') }}">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-[#8B8D98]">
                                        <i class="fa-solid fa-layer-group text-2xl mb-2 block opacity-40"></i>
                                        <p class="font-medium text-[13px] text-[#60646C] dark:text-slate-300">{{ __('No subscribed operators match your search or filter.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: COMMERCIAL MODEL & PRICING WIKI -->
    @if ($tab === 'wiki')
        <div class="space-y-6">
            <!-- Executive Summary Card -->
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div class="flex items-center gap-3">
                    <span
                        class="w-10 h-10 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm font-semibold shrink-0 shadow-none">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </span>
                    <div>
                        <h2 class="text-[17px] font-semibold text-[#1C2024] dark:text-white">
                            {{ __('Platform Commercial & Pricing Architecture') }}
                        </h2>
                        <p class="text-[13px] text-[#60646C] dark:text-slate-400 mt-0.5">
                            {{ __('Official internal specifications, fee structures, financial splits, and monetization mechanics.') }}
                        </p>
                    </div>
                </div>

                <div
                    class="p-4 rounded-[8px] bg-[#FFEF4D]/10 dark:bg-[#FFEF4D]/5 border border-[#FFEF4D]/30 text-[13px] text-[#1C2024] dark:text-slate-200 leading-relaxed space-y-2">
                    <p class="font-semibold text-[14px] flex items-center gap-2 text-[#856404] dark:text-[#FFEF4D]">
                        <i class="fa-solid fa-lightbulb"></i>
                        {{ __('Industry Benchmark Model (FareHarbor, Loket, Megatix):') }}
                    </p>
                    <p>
                        <strong>{{ __('Core Principle:') }}</strong>
                        {{ __('Operators keep 100% of their listed tour package prices with 0% operator commission deduction. The platform generates revenue through an automated, transparent Guest Service Fee (5.0% / Service & Payment fees) paid by guests at checkout, combined with recurring SaaS subscription plans for Pro automation tools.') }}
                    </p>
                </div>
            </div>

            <!-- Commercial Matrix Table -->
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <h3 class="font-semibold text-[15px] text-[#1C2024] dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-table-columns text-[#856404] dark:text-[#FFEF4D]"></i>
                    {{ __('Subscription Tiers & Commercial Matrix') }}
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead>
                            <tr
                                class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium uppercase tracking-wider text-[#60646C]">
                                <th class="py-2.5 px-3.5">{{ __('Capability / Metric') }}</th>
                                <th class="py-2.5 px-3.5">{{ __('Starter') }}</th>
                                <th class="py-2.5 px-3.5 text-[#856404] dark:text-[#FFEF4D]">{{ __('Growth') }}
                                </th>
                                <th class="py-2.5 px-3.5 text-[#856404] dark:text-[#FFEF4D]">{{ __('Agency') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('Subscription Price') }}</td>
                                <td class="py-2.5 px-3.5 font-mono text-[#60646C] dark:text-slate-300">Free / Rp 0</td>
                                <td class="py-2.5 px-3.5 font-mono font-medium text-[#856404] dark:text-[#FFEF4D]">Rp 299.000 / mo (Rp 2.990.000 / yr)</td>
                                <td class="py-2.5 px-3.5 font-mono font-medium text-[#856404] dark:text-[#FFEF4D]">Rp 799.000 / mo (Rp 7.990.000 / yr)</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('Operator Commission Cut') }}</td>
                                <td class="py-2.5 px-3.5 font-medium text-emerald-600">0.0% (100% Net to Operator)</td>
                                <td class="py-2.5 px-3.5 font-medium text-emerald-600">0.0% (100% Net to Operator)</td>
                                <td class="py-2.5 px-3.5 font-medium text-emerald-600">0.0% (100% Net to Operator)</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('Guest Service Fee') }}</td>
                                <td class="py-2.5 px-3.5 text-[#60646C]">5.0% (Paid by Guest at Checkout)</td>
                                <td class="py-2.5 px-3.5 text-[#60646C]">5.0% (Paid by Guest at Checkout)</td>
                                <td class="py-2.5 px-3.5 text-[#60646C]">5.0% (Paid by Guest at Checkout)</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('Trips and activities') }}</td>
                                <td class="py-2.5 px-3.5 text-[#60646C]">Up to 5 together</td>
                                <td class="py-2.5 px-3.5 text-[#60646C]">Up to 25 together</td>
                                <td class="py-2.5 px-3.5 font-medium text-emerald-600">Unlimited</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('People on the team') }}</td>
                                <td class="py-2.5 px-3.5 text-[#60646C]">You and 1 helper</td>
                                <td class="py-2.5 px-3.5 text-[#60646C]">Unlimited</td>
                                <td class="py-2.5 px-3.5 text-[#60646C]">Unlimited</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('Custom Domain (yourbrand.com)') }}</td>
                                <td class="py-2.5 px-3.5 text-[#8B8D98]">{{ __('Gated') }}</td>
                                <td class="py-2.5 px-3.5 text-[#8B8D98]">{{ __('Gated') }}</td>
                                <td class="py-2.5 px-3.5 text-emerald-600 font-medium">{{ __('Own website address + padlock') }}</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('Google Calendar & Live iCal Feed') }}</td>
                                <td class="py-2.5 px-3.5 text-[#8B8D98]">{{ __('Gated') }}</td>
                                <td class="py-2.5 px-3.5 text-emerald-600 font-medium">{{ __('Included') }}</td>
                                <td class="py-2.5 px-3.5 text-emerald-600 font-medium">{{ __('Included') }}</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('Guest CRM') }}</td>
                                <td class="py-2.5 px-3.5 text-[#8B8D98]">{{ __('Gated') }}</td>
                                <td class="py-2.5 px-3.5 text-emerald-600 font-medium">{{ __('Included') }}</td>
                                <td class="py-2.5 px-3.5 text-emerald-600 font-medium">{{ __('Included') }}</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3.5 font-medium text-[#1C2024] dark:text-white">
                                    {{ __('WhatsApp Dispatch Center') }}</td>
                                <td class="py-2.5 px-3.5 text-[#8B8D98]">{{ __('Gated') }}</td>
                                <td class="py-2.5 px-3.5 text-emerald-600 font-medium">{{ __('Included') }}</td>
                                <td class="py-2.5 px-3.5 text-emerald-600 font-medium">{{ __('Included') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financial Settlement Split & Math Card -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Example Flow Box -->
                <div
                    class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-3">
                    <h3 class="font-semibold text-[15px] text-[#1C2024] dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-calculator text-emerald-600"></i>
                        {{ __('Booking Transaction Flow Example') }}
                    </h3>
                    <p class="text-[12px] text-[#60646C]">
                        {{ __('Simulating Rp 1.000.000 tour booking via QRIS checkout.') }}
                    </p>

                    <div class="p-3.5 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] text-[#1C2024] dark:text-slate-100 font-mono text-[12px] space-y-2 border border-[#E4E5E9] dark:border-[#1E2433]">
                        <div
                            class="text-[#60646C] border-b border-[#E4E5E9] dark:border-[#1E2433] pb-1.5 font-medium uppercase tracking-wider text-[11px]">
                            {{ __('Guest Checkout Cart Breakdown:') }}
                        </div>
                        <div class="flex justify-between text-[#60646C]">
                            <span>Nusa Penida Manta & Snorkel:</span>
                            <span>Rp 1.000.000</span>
                        </div>
                        <div class="flex justify-between text-[#856404] dark:text-[#FFEF4D] font-medium">
                            <span>Guest Service Fee (5%):</span>
                            <span>+ Rp 50.000</span>
                        </div>
                        <div class="flex justify-between font-semibold text-emerald-700 dark:text-emerald-400 border-t border-[#E4E5E9] dark:border-[#1E2433] pt-1.5">
                            <span>Total Paid by Guest via DOKU:</span>
                            <span>Rp 1.050.000</span>
                        </div>

                        <div
                            class="text-[#60646C] border-b border-[#E4E5E9] dark:border-[#1E2433] pt-2.5 pb-1.5 font-medium uppercase tracking-wider text-[11px]">
                            {{ __('Automated Settlement Split:') }}
                        </div>
                        <div class="flex justify-between text-emerald-700 dark:text-emerald-400 font-medium">
                            <span>Operator Wallet (100% Net):</span>
                            <span>Rp 1.000.000</span>
                        </div>
                        <div class="flex justify-between text-[#856404] dark:text-[#FFEF4D] font-medium">
                            <span>Platform Gross Revenue:</span>
                            <span>Rp 50.000</span>
                        </div>
                        <div class="flex justify-between text-rose-600 dark:text-rose-400">
                            <span>DOKU QRIS Fee (0.7%):</span>
                            <span>- Rp 7.350</span>
                        </div>
                        <div class="flex justify-between font-semibold text-sky-700 dark:text-sky-400 border-t border-[#E4E5E9] dark:border-[#1E2433] pt-1.5">
                            <span>Platform Net Margin:</span>
                            <span>Rp 42.650</span>
                        </div>
                    </div>
                </div>

                <!-- Strategic Advantages Box -->
                <div
                    class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-3">
                    <h3 class="font-semibold text-[15px] text-[#1C2024] dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-chart-line text-[#856404] dark:text-[#FFEF4D]"></i>
                        {{ __('Why This Model Succeeds in Indonesia') }}
                    </h3>

                    <div class="space-y-2 text-[12px]">
                        <div
                            class="p-2.5 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                            <strong class="text-[#1C2024] dark:text-white block font-medium">1. Zero Resistance from Tour Operators</strong>
                            <p class="text-[#60646C] mt-0.5">Experience providers, activity hosts, and agencies get 100% of
                                their requested price into their wallet. Zero commission eliminates onboarding
                                hesitation.</p>
                        </div>

                        <div
                            class="p-2.5 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                            <strong class="text-[#1C2024] dark:text-white block font-medium">2. Unlimited Team Seats on All Plans</strong>
                            <p class="text-[#60646C] mt-0.5">Agencies rely heavily on WhatsApp coordinators, field guides, and freelance dispatch staff. Uncapped seats ensure platform-wide adoption.</p>
                        </div>

                        <div
                            class="p-2.5 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                            <strong class="text-[#1C2024] dark:text-white block font-medium">3. Guest Cultural Acceptance</strong>
                            <p class="text-[#60646C] mt-0.5">Domestic travelers and foreign tourists are accustomed to standard 5% checkout service and processing fees (common across Traveloka, Tiket.com, Loket.com).</p>
                        </div>

                        <div
                            class="p-2.5 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                            <strong class="text-[#1C2024] dark:text-white block font-medium">4. Predictable SaaS Subscription MRR</strong>
                            <p class="text-[#60646C] mt-0.5">Operators happily pay Rp 299.000/mo or Rp 2.990.000/yr for Google Calendar sync, automated WhatsApp dispatch, and client CRM management.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit / Create Plan Modal -->
    @if ($show_modal)
        @teleport('body')
            <div
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs overflow-y-auto">
                <div
                    class="w-full max-w-xl rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col my-8 overflow-hidden">
                    <!-- Modal Header -->
                    <div
                        class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-start justify-between gap-4 bg-[#FAFAFB] dark:bg-[#141821]">
                        <div class="flex items-start gap-3 min-w-0">
                            <div
                                class="w-9 h-9 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm shadow-none shrink-0 mt-0.5 font-semibold">
                                <i class="fa-solid fa-sliders"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3
                                    class="font-semibold text-[16px] text-[#1C2024] dark:text-white leading-tight truncate">
                                    {{ $editing_plan_id ? __('Edit Subscription Plan Tier') : __('Create New Plan Tier') }}
                                </h3>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400">
                                    {{ __('Configure pricing, feature capabilities, and limits for this plan.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeModal"
                            class="p-1.5 rounded-[6px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form wire:submit="savePlan" class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="name" :value="__('Plan Name')" required />
                                <x-input id="name" type="text" wire:model="name"
                                    placeholder="{{ __('e.g. Pro') }}" :error="$errors->has('name')" />
                                <x-input-error :messages="$errors->get('name')" />
                            </div>
                            <div>
                                <x-label for="slug" :value="__('Plan Identifier (Slug)')" required />
                                <x-input id="slug" type="text" wire:model="slug"
                                    placeholder="{{ __('e.g. growth') }}" class="font-mono" :error="$errors->has('slug')" />
                                <x-input-error :messages="$errors->get('slug')" />
                            </div>
                        </div>

                        <div>
                            <x-label for="tagline" :value="__('Marketing Tagline / Summary')" />
                            <x-input id="tagline" type="text" wire:model="tagline"
                                placeholder="{{ __('Short benefit description...') }}" :error="$errors->has('tagline')" />
                            <x-input-error :messages="$errors->get('tagline')" />
                        </div>

                        <div
                            class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <div>
                                <x-label for="price_monthly" :value="__('Monthly Price (Rp)')" required />
                                <x-input id="price_monthly" type="number" step="1000" wire:model="price_monthly"
                                    class="font-medium" :error="$errors->has('price_monthly')" />
                                <x-input-error :messages="$errors->get('price_monthly')" />
                            </div>
                            <div>
                                <x-label for="price_yearly" :value="__('Yearly Price (Rp)')" required />
                                <x-input id="price_yearly" type="number" step="1000" wire:model="price_yearly"
                                    class="font-medium" :error="$errors->has('price_yearly')" />
                                <x-input-error :messages="$errors->get('price_yearly')" />
                            </div>
                            <div>
                                <x-label for="commission_percentage" :value="__('Commission (%)')" required />
                                <x-input id="commission_percentage" type="number" step="0.1"
                                    wire:model="commission_percentage" class="font-medium font-mono text-[#856404] dark:text-[#FFEF4D]"
                                    :error="$errors->has('commission_percentage')" />
                                <x-input-error :messages="$errors->get('commission_percentage')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="package_limit" :value="__('Max Packages (Blank = Unlimited)')" />
                                <x-input id="package_limit" type="number" wire:model="package_limit"
                                    placeholder="{{ __('Unlimited') }}" :error="$errors->has('package_limit')" />
                                <x-input-error :messages="$errors->get('package_limit')" />
                            </div>
                            <div>
                                <x-label for="team_member_limit" :value="__('Max Team Seats (Blank = Unlimited)')" />
                                <x-input id="team_member_limit" type="number" wire:model="team_member_limit"
                                    placeholder="{{ __('Unlimited') }}" :error="$errors->has('team_member_limit')" />
                                <x-input-error :messages="$errors->get('team_member_limit')" />
                            </div>
                        </div>

                        <!-- Feature Toggles -->
                        <div class="space-y-3 pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <span
                                class="text-[12px] font-medium text-[#1C2024] dark:text-slate-200 block">{{ __('Included Feature Permissions') }}</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_quick_links" wire:model="features.quick_booking_links"
                                        :label="__('1-Click Booking Links')" :description="__('Direct payment and reservation links')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_promotional_coupons" wire:model="features.promotional_coupons"
                                        :label="__('Coupons & Promo Codes')" :description="__('Guest discounts & marketing campaigns')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_adv_calendar" wire:model="features.advanced_calendar"
                                        :label="__('Advanced Resource Matrix')" :description="__('Resource calendar & capacity timeline')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_manifest" wire:model="features.daily_manifest_export"
                                        :label="__('Daily Run-Sheet Export')" :description="__('Daily passenger manifest downloads')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_heatmap" wire:model="features.capacity_heatmap"
                                        :label="__('Capacity Heatmap')" :description="__('Monthly capacity utilization analytics')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_pixels" wire:model="features.tracking_pixels" :label="__('Marketing Pixels')"
                                        :description="__('Meta Pixel & GA4 tracking')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_reviews" wire:model="features.automated_review_requests"
                                        :label="__('Automated Review Emails')" :description="__('Post-trip customer feedback loop')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_gcal" wire:model="features.google_calendar" :label="__('Google Calendar Sync')"
                                        :description="__('iCal live reservation sync feed')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_crm" wire:model="features.guest_crm" :label="\App\Models\Plan::featureLabel('guest_crm')"
                                        :description="__('Guest contacts, notes, and lifetime spend')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_whatsapp" wire:model="features.whatsapp_dispatch"
                                        :label="__('1-Click WhatsApp')" :description="__('Instant dispatch to guests & drivers')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_google_reviews" wire:model="features.google_reviews"
                                        :label="__('Google reviews slider')" :description="__('Connect one Google listing, or use a review link if they have none')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_custom_domain" wire:model="features.custom_domain"
                                        :label="__('Your own website address')" :description="__('Guests open your brand address with a padlock')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_remove_branding" wire:model="features.remove_branding"
                                        :label="__('Hide our name')" :description="__('No platform credit on their booking page')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_ai_discovery" wire:model="features.ai_discovery"
                                        :label="__('AI catalog page')" :description="__('Let search tools find their trips')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                    <x-checkbox id="feat_priority_support" wire:model="features.priority_support"
                                        :label="__('Faster help')" :description="__('We treat their questions first')" />
                                </div>

                                <div
                                    class="p-2.5 rounded-[8px] border border-[#FFEF4D]/40 bg-[#FFEF4D]/10 transition">
                                    <x-checkbox id="feat_is_popular" wire:model="is_popular" :label="__('Highlight as Popular')"
                                        :description="__('Show popular badge on tier card')" />
                                </div>
                            </div>
                        </div>

                        <!-- Modal Actions Footer -->
                        <div
                            class="pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-end gap-2.5">
                            <button type="button" wire:click="closeModal"
                                class="h-8 px-3.5 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white font-medium text-[13px] shadow-none transition cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] inline-flex items-center gap-1.5 shadow-none transition cursor-pointer">
                                <i class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>{{ __('Save Plan Tier') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
