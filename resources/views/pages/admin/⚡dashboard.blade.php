<?php

use App\Enums\OperatorStatus;
use App\Enums\PayoutStatus;
use App\Models\Operator;
use App\Models\PayoutRequest;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] #[Layout('layouts.admin')] class extends Component
{
    public string $period = '12m'; // '30d', '6m', '12m'

    #[Computed]
    public function currentMrr(): float
    {
        $plans = Plan::all()->keyBy('id');
        $mrr = 0.0;

        $operators = Operator::where('status', OperatorStatus::Approved)
            ->whereNotNull('plan_id')
            ->get();

        foreach ($operators as $operator) {
            $plan = $plans->get($operator->plan_id);
            if ($plan && ! $plan->isFree()) {
                if ($operator->subscription_interval === 'yearly') {
                    $mrr += ((float) $plan->price_yearly) / 12;
                } else {
                    $mrr += (float) $plan->price_monthly;
                }
            }
        }

        return $mrr;
    }

    #[Computed]
    public function totalSubscriptionRevenue(): float
    {
        return (float) SubscriptionPayment::where('status', 'completed')->sum('net_amount_paid');
    }

    #[Computed]
    public function totalOperatorsCount(): int
    {
        return Operator::count();
    }

    #[Computed]
    public function approvedOperatorsCount(): int
    {
        return Operator::where('status', OperatorStatus::Approved)->count();
    }

    #[Computed]
    public function pendingOperatorsCount(): int
    {
        return Operator::where('status', OperatorStatus::Pending)->count();
    }

    #[Computed]
    public function pendingPayoutsCount(): int
    {
        return PayoutRequest::where('status', PayoutStatus::Pending)->count();
    }

    #[Computed]
    public function pendingPayoutsAmount(): float
    {
        return (float) PayoutRequest::where('status', PayoutStatus::Pending)->sum('amount');
    }

    #[Computed]
    public function isPlatformMaintenance(): bool
    {
        return PlatformSetting::current()->isPlatformMaintenance();
    }

    #[Computed]
    public function monthlyTrends(): array
    {
        $monthsCount = match ($this->period) {
            '30d' => 1,
            '6m' => 6,
            default => 12,
        };

        $rangeStart = now()->subMonths($monthsCount - 1)->startOfMonth();
        $rangeEnd = now()->endOfMonth();

        // Driver-aware month grouping: MySQL uses DATE_FORMAT, SQLite uses strftime.
        $isSqlite = DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite
            ? "strftime('%Y-%m', created_at) as month_key"
            : "DATE_FORMAT(created_at, '%Y-%m') as month_key";

        $subRevByMonth = DB::table('subscription_payments')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->groupBy('month_key')
            ->selectRaw("{$monthExpr}, SUM(net_amount_paid) as total")
            ->pluck('total', 'month_key');

        $months = [];
        $maxRevenue = 1.0;

        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');

            $rev = (float) ($subRevByMonth[$key] ?? 0);

            if ($rev > $maxRevenue) {
                $maxRevenue = $rev;
            }

            $months[] = [
                'label' => $date->format('M Y'),
                'short' => $date->format('M'),
                'revenue' => $rev,
            ];
        }

        return [
            'data' => $months,
            'maxRevenue' => $maxRevenue,
        ];
    }

    #[Computed]
    public function operatorsForReview(): Collection
    {
        return Operator::query()
            ->with(['plan'])
            ->withCount(['packages', 'products'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('created_at')
            ->take(6)
            ->get();
    }

    #[Computed]
    public function planDistribution(): array
    {
        $countByPlan = Operator::query()
            ->selectRaw('plan_id, COUNT(*) as cnt')
            ->groupBy('plan_id')
            ->pluck('cnt', 'plan_id');

        $totalOperators = $countByPlan->sum();

        $plans = Plan::orderBy('sort_order')->get();
        $distribution = [];

        foreach ($plans as $plan) {
            $count = (int) ($countByPlan[$plan->id] ?? 0);
            $distribution[] = [
                'name' => $plan->name,
                'slug' => $plan->slug,
                'count' => $count,
                'price' => (float) $plan->price_monthly,
                'total_operators' => $totalOperators,
            ];
        }

        $unassigned = (int) ($countByPlan[null] ?? $countByPlan->get(null, 0));
        if ($unassigned > 0) {
            $distribution[] = [
                'name' => 'Unassigned',
                'slug' => 'starter',
                'count' => $unassigned,
                'price' => 0.0,
                'total_operators' => $totalOperators,
            ];
        }

        return $distribution;
    }
}; ?>

<div class="space-y-6">
    <div class="op-hero">
        <x-page-header
            :title="__('Platform overview')"
            :subtitle="__('Operator subscriptions, pending approvals, and platform health.')"
            icon="fa-chart-pie"
        >
            <x-slot:actions>
                @if ($this->isPlatformMaintenance)
                    <span class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold bg-amber-500/15 text-amber-500 border border-amber-500/30">
                        <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                        {{ __('Maintenance active') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        {{ __('Platform operational') }}
                    </span>
                @endif
            </x-slot:actions>
        </x-page-header>
    </div>

    {{-- Items Needing Attention Banner --}}
    @if ($this->isPlatformMaintenance || $this->pendingOperatorsCount > 0 || $this->pendingPayoutsCount > 0)
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 space-y-2">
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>{{ __('Items needing attention') }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-3 sm:gap-6 text-xs font-medium text-slate-700 dark:text-slate-300">
                @if ($this->isPlatformMaintenance)
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0"></span>
                        <span>{{ __('Maintenance mode is active — public storefront transactions are paused.') }}</span>
                        <a href="{{ route('admin.platform.edit') }}" wire:navigate class="font-bold underline text-amber-700 dark:text-amber-400 hover:text-amber-600">
                            {{ __('Settings') }} &rarr;
                        </a>
                    </div>
                @endif

                @if ($this->pendingOperatorsCount > 0)
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0"></span>
                        <span>{{ __(':count operator(s) awaiting approval.', ['count' => $this->pendingOperatorsCount]) }}</span>
                        <a href="{{ route('admin.operators.index', ['status_filter' => 'pending']) }}" wire:navigate class="font-bold underline text-amber-700 dark:text-amber-400 hover:text-amber-600">
                            {{ __('Review operators') }} &rarr;
                        </a>
                    </div>
                @endif

                @if ($this->pendingPayoutsCount > 0)
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0"></span>
                        <span>{{ __(':count payout request(s) pending (Rp :amount).', ['count' => $this->pendingPayoutsCount, 'amount' => number_format($this->pendingPayoutsAmount, 0, ',', '.')]) }}</span>
                        <a href="{{ route('admin.payouts.index') }}" wire:navigate class="font-bold underline text-amber-700 dark:text-amber-400 hover:text-amber-600">
                            {{ __('Review payouts') }} &rarr;
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Top 4 Key Metric Cards --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-metric-card
            :label="__('Plan fees this month')"
            :value="'Rp '.number_format($this->currentMrr, 0, ',', '.')"
            :hint="__('Monthly recurring plan revenue')"
            icon="fa-repeat"
            tone="featured"
            :href="route('admin.plans.index')"
        />

        <x-metric-card
            :label="__('Subscription revenue')"
            :value="'Rp '.number_format($this->totalSubscriptionRevenue, 0, ',', '.')"
            :hint="__('Total plan upgrades collected')"
            icon="fa-vault"
            tone="success"
            :href="route('admin.subscriptions.index')"
        />

        <x-metric-card
            :label="__('Operators')"
            :value="$this->approvedOperatorsCount"
            :suffix="'/ '.$this->totalOperatorsCount.' '.__('total')"
            :hint="__('Approved and active')"
            icon="fa-users-gear"
            :href="route('admin.operators.index')"
        />

        <x-metric-card
            :label="__('Pending verification')"
            :value="$this->pendingOperatorsCount"
            :hint="$this->pendingOperatorsCount > 0 ? __('Awaiting your review') : __('All accounts verified')"
            icon="fa-user-clock"
            :tone="$this->pendingOperatorsCount > 0 ? 'warning' : 'neutral'"
            :href="route('admin.operators.index', ['status_filter' => 'pending'])"
        />
    </div>

    <!-- Monthly Platform Revenue Visualizer -->
    <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-[#1e2433]">
            <div>
                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-chart-column text-[#8a7808] dark:text-[#FFEF4D]"></i>
                    {{ __('Subscription revenue over time') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Platform fee earnings from operator plans.') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <x-filter-tabs padded>
                    <x-filter-tab wire:click="$set('period', '30d')" :active="$period === '30d'">
                        {{ __('30d') }}
                    </x-filter-tab>
                    <x-filter-tab wire:click="$set('period', '6m')" :active="$period === '6m'">
                        {{ __('6m') }}
                    </x-filter-tab>
                    <x-filter-tab wire:click="$set('period', '12m')" :active="$period === '12m'">
                        {{ __('12m') }}
                    </x-filter-tab>
                </x-filter-tabs>
            </div>
        </div>

        @php
            $trends = $this->monthlyTrends;
            $data = $trends['data'];
            $maxRevenue = max($trends['maxRevenue'], 1);
        @endphp

        <!-- Bar Visualizer -->
        <div class="grid grid-cols-6 sm:grid-cols-12 gap-2 sm:gap-3 items-end pt-8 pb-2 min-h-[220px]">
            @foreach ($data as $m)
                @php
                    $pct = $m['revenue'] > 0 ? min(100, max(8, round(($m['revenue'] / $maxRevenue) * 100))) : 4;
                @endphp
                <div class="flex flex-col items-center gap-2 group h-full justify-end">
                    <!-- Tooltip Hover Value -->
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-150 text-xs font-bold bg-slate-900 dark:bg-white text-white dark:text-slate-900 py-1 px-2 rounded-lg whitespace-nowrap shadow-md pointer-events-none mb-1">
                        Rp {{ number_format($m['revenue'] / 1000, 0) }}k
                    </div>

                    <!-- Bar -->
                    <div class="w-full max-w-[48px] bg-slate-100 dark:bg-[#141821] rounded-t-xl overflow-hidden flex flex-col justify-end h-40 relative border-t border-x border-slate-200 dark:border-[#1e2433]">
                        <div
                            style="height: {{ $pct }}%"
                            class="w-full bg-[#FFEF4D] rounded-t-xl group-hover:brightness-110 transition-all duration-300"
                        ></div>
                    </div>

                    <!-- Label -->
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300 group-hover:text-[#FFEF4D] transition truncate">
                        {{ $m['short'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Dual Column: Operators & Plan Distribution + Shortcuts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Operators Directory (2 Cols) -->
        <div class="lg:col-span-2 p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                <div>
                    <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-users-gear text-amber-500"></i>
                        {{ __('Operators') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Recent registrations and pending verification accounts.') }}
                    </p>
                </div>
                <a href="{{ route('admin.operators.index') }}" wire:navigate class="text-xs font-bold text-[#8a7808] dark:text-[#FFEF4D] hover:underline">
                    {{ __('View all') }} &rarr;
                </a>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                @forelse ($this->operatorsForReview as $index => $operator)
                    <div class="py-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/50 dark:hover:bg-[#141824]/50 px-2 rounded-2xl transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-6 text-center font-black text-xs text-slate-400">{{ $index + 1 }}</span>
                            <div class="w-10 h-10 rounded-xl bg-[#FFEF4D]/15 text-[#8a7808] dark:text-[#FFEF4D] font-extrabold flex items-center justify-center text-xs shrink-0 overflow-hidden border border-[#FFEF4D]/30">
                                @if ($operator->logo_path)
                                    <img src="{{ $operator->logo_url }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                @else
                                    {{ strtoupper(substr($operator->name, 0, 2)) }}
                                @endif
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="font-bold text-sm text-slate-900 dark:text-white hover:text-[#FFEF4D] transition truncate block">
                                    {{ $operator->name }}
                                </a>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $operator->packages_count + $operator->products_count }} {{ __('offerings') }} &bull; {{ $operator->plan?->name ?? 'Free Tier' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-xs font-bold uppercase px-2.5 py-0.5 rounded-full {{ $operator->status === OperatorStatus::Approved ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : ($operator->status === OperatorStatus::Pending ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-slate-100 text-slate-600') }}">
                                {{ $operator->status->label() }}
                            </span>
                            <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="hidden sm:inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-xs font-bold bg-white dark:bg-[#141821] border border-slate-200 dark:border-[#1e2433] hover:border-slate-300 dark:hover:border-slate-600 transition text-slate-700 dark:text-slate-300">
                                {{ $operator->status === OperatorStatus::Pending ? __('Review') : __('Manage') }}
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 py-6 text-center">{{ __('No operators registered yet.') }}</p>
                @endforelse
            </div>
        </div>

        <!-- Right: Subscription Plans & Quick Actions (1 Col) -->
        <div class="space-y-6">
            <!-- Plans Breakdown -->
            <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <div class="pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-layer-group text-[#8a7808] dark:text-[#FFEF4D]"></i>
                        {{ __('Plans') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('How many operators are on each plan.') }}
                    </p>
                </div>

                <div class="space-y-3">
                    @foreach ($this->planDistribution as $plan)
                        <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#141821]/50 border border-slate-200/80 dark:border-[#1e2433] space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">{{ $plan['name'] }}</span>
                                <span class="font-black text-sm text-[#8a7808] dark:text-[#FFEF4D]">
                                    {{ $plan['count'] }} {{ __('operators') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>
                                    @if ($plan['price'] > 0)
                                        Rp {{ number_format($plan['price'], 0, ',', '.') }}/mo
                                    @else
                                        {{ __('Free Tier') }}
                                    @endif
                                </span>
                                <span>{{ round(($plan['count'] / max(1, $plan['total_operators'])) * 100) }}% {{ __('of total') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-2">
                    <a href="{{ route('admin.plans.index') }}" wire:navigate class="w-full h-9 flex items-center justify-center rounded-xl bg-[#FFEF4D]/15 hover:bg-[#FFEF4D]/25 text-[#8a7808] dark:text-[#FFEF4D] text-xs font-bold transition border border-[#FFEF4D]/30">
                        <i class="fa-solid fa-sliders mr-1.5 text-xs"></i>
                        {{ __('Edit plans') }}
                    </a>
                </div>
            </div>

            <!-- Quick Platform Shortcuts -->
            <div class="p-5 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-3">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Quick shortcuts') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-2">
                    <a href="{{ route('admin.announcements.index') }}" wire:navigate class="flex items-center gap-2.5 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-[#141821] text-xs font-semibold text-slate-700 dark:text-slate-300 transition">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-500">
                            <i class="fa-solid fa-bullhorn text-xs"></i>
                        </span>
                        <span>{{ __('Post announcement') }}</span>
                    </a>

                    <a href="{{ route('admin.coupons.index') }}" wire:navigate class="flex items-center gap-2.5 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-[#141821] text-xs font-semibold text-slate-700 dark:text-slate-300 transition">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-purple-500/10 text-purple-500">
                            <i class="fa-solid fa-ticket text-xs"></i>
                        </span>
                        <span>{{ __('Create coupon') }}</span>
                    </a>

                    <a href="{{ route('admin.platform.edit') }}" wire:navigate class="flex items-center gap-2.5 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-[#141821] text-xs font-semibold text-slate-700 dark:text-slate-300 transition">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                            <i class="fa-solid fa-sliders text-xs"></i>
                        </span>
                        <span>{{ __('Platform settings') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
