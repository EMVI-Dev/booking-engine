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
        return $this->metrics()->monthlyRecurringRevenue();
    }

    #[Computed]
    public function totalSubscriptionRevenue(): float
    {
        return $this->metrics()->subscriptionRevenue();
    }

    /**
     * Platform income from the guest service fee.
     */
    #[Computed]
    public function guestFeeRevenue(): float
    {
        return $this->metrics()->guestFeeRevenue();
    }

    /**
     * Paid guest checkout value (GMV).
     */
    #[Computed]
    public function grossBookingValue(): float
    {
        return $this->metrics()->grossBookingValue();
    }

    /**
     * Operator money held until trip day.
     */
    #[Computed]
    public function escrowLiability(): float
    {
        return $this->metrics()->escrowLiability();
    }

    /**
     * Cleared operator balances not yet paid out.
     */
    #[Computed]
    public function clearedOperatorBalances(): float
    {
        return $this->metrics()->clearedOperatorBalances();
    }

    /**
     * Money held on open card disputes.
     */
    #[Computed]
    public function openDisputeHolds(): float
    {
        return $this->metrics()->openDisputeHolds();
    }

    protected function metrics(): \App\Services\AdminMetricsService
    {
        return app(\App\Services\AdminMetricsService::class);
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
        return $this->metrics()->payoutTotals(PayoutStatus::Pending)['count'];
    }

    #[Computed]
    public function pendingPayoutsAmount(): float
    {
        return $this->metrics()->payoutTotals(PayoutStatus::Pending)['amount'];
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

        $subRevByMonth = $this->metrics()->subscriptionRevenueByMonth($rangeStart, $rangeEnd);

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
    <div class="op-hero flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-1">
        <div>
            <h1 class="text-[20px] font-medium leading-[1.6] text-[#1C2024] dark:text-white">
                {{ __('Platform overview') }}
            </h1>
            <p class="text-[14px] font-normal leading-[1.43] text-[#60646C] dark:text-slate-400">
                {{ __('Operator subscriptions, pending approvals, and platform health.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if ($this->isPlatformMaintenance)
                <span class="inline-flex items-center gap-1.5 rounded-[6px] px-2.5 py-1 text-[12px] font-medium bg-[#FFFBEB] dark:bg-amber-950/40 text-[#92400E] dark:text-amber-300 border border-[#FDE68A] dark:border-amber-800/50">
                    <span class="h-2 w-2 rounded-full bg-[#F59E0B] animate-pulse"></span>
                    {{ __('Maintenance active') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-[6px] px-2.5 py-1 text-[12px] font-medium bg-[#ECFDF5] dark:bg-emerald-950/40 text-[#065F46] dark:text-emerald-300 border border-[#A7F3D0] dark:border-emerald-800/50">
                    <span class="h-2 w-2 rounded-full bg-[#10B981]"></span>
                    {{ __('Platform operational') }}
                </span>
            @endif
        </div>
    </div>

    {{-- Items Needing Attention Banner --}}
    @if ($this->isPlatformMaintenance || $this->pendingOperatorsCount > 0 || $this->pendingPayoutsCount > 0)
        <div class="rounded-[12px] border border-amber-200 dark:border-amber-900/50 bg-[#FFFBEB] dark:bg-amber-950/20 p-4 space-y-2 shadow-none">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-300">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>{{ __('Items needing attention') }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-3 sm:gap-6 text-[13px] font-normal text-[#1C2024] dark:text-slate-200">
                @if ($this->isPlatformMaintenance)
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F59E0B] shrink-0"></span>
                        <span>{{ __('Maintenance mode is active — public storefront transactions are paused.') }}</span>
                        <a href="{{ route('admin.platform.edit') }}" wire:navigate class="font-medium text-[#856404] dark:text-[#FFEF4D] hover:underline">
                            {{ __('Settings') }} &rarr;
                        </a>
                    </div>
                @endif

                @if ($this->pendingOperatorsCount > 0)
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F59E0B] shrink-0"></span>
                        <span>{{ __(':count operator(s) awaiting approval.', ['count' => $this->pendingOperatorsCount]) }}</span>
                        <a href="{{ route('admin.operators.index', ['status_filter' => 'pending']) }}" wire:navigate class="font-medium text-[#856404] dark:text-[#FFEF4D] hover:underline">
                            {{ __('Review operators') }} &rarr;
                        </a>
                    </div>
                @endif

                @if ($this->pendingPayoutsCount > 0)
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F59E0B] shrink-0"></span>
                        <span>{{ __(':count payout request(s) pending (Rp :amount).', ['count' => $this->pendingPayoutsCount, 'amount' => number_format($this->pendingPayoutsAmount, 0, ',', '.')]) }}</span>
                        <a href="{{ route('admin.payouts.index') }}" wire:navigate class="font-medium text-[#856404] dark:text-[#FFEF4D] hover:underline">
                            {{ __('Review payouts') }} &rarr;
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Top 4 Key Metric Cards (Radix / Laravel Cloud Flat Aesthetic) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Card 1: Featured MRR --}}
        <a href="{{ route('admin.plans.index') }}" wire:navigate
            class="op-metric op-metric-featured group block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#FFEF4D] p-5 shadow-none transition relative overflow-hidden">
            <div class="flex items-center justify-between gap-2">
                <span class="op-metric-label text-[12px] font-normal text-[#60646C] dark:text-slate-400">
                    {{ __('Plan fees this month') }}
                </span>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[4px] text-[10px] font-medium bg-[#FFEF4D]/30 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                    {{ __('MRR') }}
                </span>
            </div>
            <div class="mt-2 flex items-baseline">
                <p class="op-metric-value text-[24px] font-medium leading-none text-[#1C2024] dark:text-white">
                    Rp {{ number_format($this->currentMrr, 0, ',', '.') }}
                </p>
            </div>
            <p class="op-metric-hint text-[12px] text-[#60646C] dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-[#FFEF4D]"></span>
                <span>{{ __('Monthly recurring plan revenue') }}</span>
            </p>
        </a>

        {{-- Card 2: Subscription Revenue --}}
        <a href="{{ route('admin.subscriptions.index') }}" wire:navigate
            class="op-card op-metric group block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 p-5 shadow-none transition">
            <div class="flex items-center justify-between gap-2">
                <span class="op-metric-label text-[12px] font-normal text-[#60646C] dark:text-slate-400">
                    {{ __('Subscription revenue') }}
                </span>
                <i class="fa-solid fa-receipt text-[11px] text-[#8B8D98]"></i>
            </div>
            <div class="mt-2 flex items-baseline">
                <p class="op-metric-value text-[24px] font-medium leading-none text-[#1C2024] dark:text-white">
                    Rp {{ number_format($this->totalSubscriptionRevenue, 0, ',', '.') }}
                </p>
            </div>
            <p class="op-metric-hint text-[12px] text-[#60646C] dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span>{{ __('Total plan upgrades collected') }}</span>
            </p>
        </a>

        {{-- Card 3: Operators --}}
        <a href="{{ route('admin.operators.index') }}" wire:navigate
            class="op-card op-metric group block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 p-5 shadow-none transition">
            <div class="flex items-center justify-between gap-2">
                <span class="op-metric-label text-[12px] font-normal text-[#60646C] dark:text-slate-400">
                    {{ __('Operators') }}
                </span>
                <i class="fa-solid fa-users-gear text-[11px] text-[#8B8D98]"></i>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <p class="op-metric-value text-[24px] font-medium leading-none text-[#1C2024] dark:text-white">
                    {{ $this->approvedOperatorsCount }}
                </p>
                <span class="op-metric-suffix text-[13px] font-normal text-[#60646C] dark:text-slate-400">
                    / {{ $this->totalOperatorsCount }} {{ __('total') }}
                </span>
            </div>
            <p class="op-metric-hint text-[12px] text-[#60646C] dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span>{{ __('Approved and active') }}</span>
            </p>
        </a>

        {{-- Card 4: Pending Verification --}}
        <a href="{{ route('admin.operators.index', ['status_filter' => 'pending']) }}" wire:navigate
            class="op-card op-metric group block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 p-5 shadow-none transition">
            <div class="flex items-center justify-between gap-2">
                <span class="op-metric-label text-[12px] font-normal text-[#60646C] dark:text-slate-400">
                    {{ __('Pending verification') }}
                </span>
                <i class="fa-solid fa-user-clock text-[11px] text-[#8B8D98]"></i>
            </div>
            <div class="mt-2 flex items-baseline">
                <p class="op-metric-value text-[24px] font-medium leading-none text-[#1C2024] dark:text-white">
                    {{ $this->pendingOperatorsCount }}
                </p>
            </div>
            <p class="op-metric-hint text-[12px] text-[#60646C] dark:text-slate-400 mt-3 flex items-center gap-1.5">
                @if ($this->pendingOperatorsCount > 0)
                    <span class="h-1.5 w-1.5 rounded-full bg-[#F59E0B]"></span>
                    <span class="text-[#92400E] dark:text-amber-300 font-medium">{{ __('Awaiting your review') }}</span>
                @else
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    <span>{{ __('All accounts verified') }}</span>
                @endif
            </p>
        </a>
    </div>

    {{-- Monthly Platform Revenue Visualizer (Cloud / Radix Style) --}}
    <div class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#E4E5E9] dark:border-[#1e2433]">
            <div>
                <h3 class="text-[14px] font-medium leading-[1.43] text-[#1C2024] dark:text-white">
                    {{ __('Subscription revenue over time') }}
                </h3>
                <p class="text-[12px] font-normal text-[#60646C] dark:text-slate-400 mt-0.5">
                    {{ __('Platform fee earnings from operator plans.') }}
                </p>
            </div>
            <div class="flex items-center gap-1 bg-[#EFEFF0] dark:bg-[#141821] p-1 rounded-[6px] border border-[#E4E5E9] dark:border-[#1e2433]">
                <button type="button" wire:click="$set('period', '30d')"
                    class="px-2.5 py-1 rounded-[4px] text-[12px] font-medium transition {{ $period === '30d' ? 'bg-white dark:bg-[#1E2433] text-[#1C2024] dark:text-white shadow-none' : 'text-[#60646C] hover:text-[#1C2024] dark:text-slate-400' }}">
                    {{ __('30d') }}
                </button>
                <button type="button" wire:click="$set('period', '6m')"
                    class="px-2.5 py-1 rounded-[4px] text-[12px] font-medium transition {{ $period === '6m' ? 'bg-white dark:bg-[#1E2433] text-[#1C2024] dark:text-white shadow-none' : 'text-[#60646C] hover:text-[#1C2024] dark:text-slate-400' }}">
                    {{ __('6m') }}
                </button>
                <button type="button" wire:click="$set('period', '12m')"
                    class="px-2.5 py-1 rounded-[4px] text-[12px] font-medium transition {{ $period === '12m' ? 'bg-white dark:bg-[#1E2433] text-[#1C2024] dark:text-white shadow-none' : 'text-[#60646C] hover:text-[#1C2024] dark:text-slate-400' }}">
                    {{ __('12m') }}
                </button>
            </div>
        </div>

        @php
            $trends = $this->monthlyTrends;
            $data = $trends['data'];
            $maxRevenue = max($trends['maxRevenue'], 1);
        @endphp

        {{-- Bar Visualizer with Brand Yellow Accent --}}
        <div class="grid grid-cols-6 sm:grid-cols-12 gap-2 sm:gap-3 items-end pt-6 pb-2 min-h-[200px]">
            @foreach ($data as $m)
                @php
                    $pct = $m['revenue'] > 0 ? min(100, max(6, round(($m['revenue'] / $maxRevenue) * 100))) : 3;
                @endphp
                <div class="flex flex-col items-center gap-2 group h-full justify-end">
                    {{-- Tooltip Hover Value --}}
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-150 text-[11px] font-medium bg-[#1C2024] dark:bg-white text-white dark:text-[#1C2024] py-0.5 px-2 rounded-[4px] whitespace-nowrap shadow-none pointer-events-none mb-1">
                        Rp {{ number_format($m['revenue'] / 1000, 0) }}k
                    </div>

                    {{-- Bar track and fill --}}
                    <div class="w-full max-w-[40px] bg-[#F4F5F6] dark:bg-[#141821] rounded-t-[4px] overflow-hidden flex flex-col justify-end h-36 relative border border-[#E4E5E9] dark:border-[#1e2433]">
                        <div
                            style="height: {{ $pct }}%"
                            class="w-full bg-[#FFEF4D] rounded-t-[3px] group-hover:bg-[#F3E13A] transition-all duration-200"
                        ></div>
                    </div>

                    {{-- Month Label --}}
                    <span class="text-[11px] font-medium text-[#60646C] dark:text-slate-400 group-hover:text-[#856404] dark:group-hover:text-[#FFEF4D] transition truncate">
                        {{ $m['short'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Dual Column: Operators & Plan Distribution + Shortcuts --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Operators Directory (2 Cols) --}}
        <div class="lg:col-span-2 p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1e2433]">
                <div>
                    <h3 class="text-[14px] font-medium leading-[1.43] text-[#1C2024] dark:text-white">
                        {{ __('Operators') }}
                    </h3>
                    <p class="text-[12px] font-normal text-[#60646C] dark:text-slate-400 mt-0.5">
                        {{ __('Recent registrations and pending verification accounts.') }}
                    </p>
                </div>
                <a href="{{ route('admin.operators.index') }}" wire:navigate class="text-[12px] font-medium text-[#856404] dark:text-[#FFEF4D] hover:underline">
                    {{ __('View all') }} &rarr;
                </a>
            </div>

            <div class="divide-y divide-[#E4E5E9] dark:divide-[#1e2433]">
                @forelse ($this->operatorsForReview as $index => $operator)
                    <div class="py-3 flex items-center justify-between gap-4 hover:bg-[#FAFAFB] dark:hover:bg-[#141824] px-2 rounded-[6px] transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-5 text-center font-mono text-[11px] text-[#8B8D98]">{{ $index + 1 }}</span>
                            <div class="w-8 h-8 rounded-[6px] bg-[#EFEFF0] dark:bg-[#1E2433] text-[#1C2024] dark:text-white font-medium flex items-center justify-center text-[11px] shrink-0 overflow-hidden border border-[#E4E5E9] dark:border-[#1e2433]">
                                @if ($operator->logo_path)
                                    <img src="{{ $operator->logo_url }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                @else
                                    {{ strtoupper(substr($operator->name, 0, 2)) }}
                                @endif
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="font-medium text-[13px] text-[#1C2024] dark:text-white hover:text-[#856404] dark:hover:text-[#FFEF4D] transition truncate block">
                                    {{ $operator->name }}
                                </a>
                                <span class="text-[12px] text-[#60646C] dark:text-slate-400">
                                    {{ $operator->packages_count + $operator->products_count }} {{ __('offerings') }} &bull; {{ $operator->plan?->name ?? 'Free Tier' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 shrink-0">
                            <span class="text-[11px] font-medium px-2 py-0.5 rounded-[6px] {{ $operator->status === OperatorStatus::Approved ? 'bg-[#ECFDF5] text-[#065F46] dark:bg-emerald-950/40 dark:text-emerald-300 border border-[#A7F3D0] dark:border-emerald-800/50' : ($operator->status === OperatorStatus::Pending ? 'bg-[#FFFBEB] text-[#92400E] dark:bg-amber-950/40 dark:text-amber-300 border border-[#FDE68A] dark:border-amber-800/50' : 'bg-[#EFEFF0] text-[#60646C] border border-[#E4E5E9]') }}">
                                {{ $operator->status->label() }}
                            </span>
                            <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="hidden sm:inline-flex items-center justify-center px-2.5 py-1 rounded-[6px] text-[12px] font-normal bg-white dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1e2433] hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] text-[#1C2024] dark:text-slate-200 transition">
                                {{ $operator->status === OperatorStatus::Pending ? __('Review') : __('Manage') }}
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-[12px] text-[#8B8D98] py-6 text-center">{{ __('No operators registered yet.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Right: Subscription Plans & Quick Actions (1 Col) --}}
        <div class="space-y-6">
            {{-- Plans Breakdown --}}
            <div class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-4">
                <div class="pb-3 border-b border-[#E4E5E9] dark:border-[#1e2433]">
                    <h3 class="text-[14px] font-medium leading-[1.43] text-[#1C2024] dark:text-white">
                        {{ __('Plans') }}
                    </h3>
                    <p class="text-[12px] font-normal text-[#60646C] dark:text-slate-400 mt-0.5">
                        {{ __('How many operators are on each plan.') }}
                    </p>
                </div>

                <div class="space-y-3">
                    @foreach ($this->planDistribution as $plan)
                        @php
                            $pct = round(($plan['count'] / max(1, $plan['total_operators'])) * 100);
                        @endphp
                        <div class="p-3 rounded-[6px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1e2433] space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-[13px] text-[#1C2024] dark:text-white">{{ $plan['name'] }}</span>
                                <span class="text-[12px] font-medium text-[#856404] dark:text-[#FFEF4D]">
                                    {{ $plan['count'] }} {{ __('operators') }}
                                </span>
                            </div>
                            <div class="w-full bg-[#EFEFF0] dark:bg-[#1E2433] h-1.5 rounded-full overflow-hidden">
                                <div class="bg-[#FFEF4D] h-full rounded-full transition-all duration-300" style="width: {{ $pct }}%"></div>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-[#60646C] dark:text-slate-400">
                                <span>
                                    @if ($plan['price'] > 0)
                                        Rp {{ number_format($plan['price'], 0, ',', '.') }}/mo
                                    @else
                                        {{ __('Free Tier') }}
                                    @endif
                                </span>
                                <span>{{ $pct }}% {{ __('of total') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-1">
                    <a href="{{ route('admin.plans.index') }}" wire:navigate class="w-full h-8 flex items-center justify-center rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] text-[#1C2024] dark:text-slate-200 text-[12px] font-normal transition border border-[#E4E5E9] dark:border-[#1e2433]">
                        <i class="fa-solid fa-sliders mr-1.5 text-[11px] text-[#8B8D98]"></i>
                        {{ __('Edit plans') }}
                    </a>
                </div>
            </div>

            {{-- Quick Platform Shortcuts --}}
            <div class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8B8D98]">{{ __('Quick shortcuts') }}</p>
                <div class="grid grid-cols-1 gap-2">
                    <a href="{{ route('admin.announcements.index') }}" wire:navigate
                        class="flex items-center gap-2.5 p-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 hover:bg-[#FAFAFB] dark:hover:bg-[#141821] text-[13px] font-normal text-[#1C2024] dark:text-slate-200 transition">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-[4px] bg-[#FFEF4D]/25 text-[#856404] dark:text-[#FFEF4D]">
                            <i class="fa-solid fa-bullhorn text-[11px]"></i>
                        </span>
                        <span>{{ __('Post announcement') }}</span>
                    </a>

                    <a href="{{ route('admin.coupons.index') }}" wire:navigate
                        class="flex items-center gap-2.5 p-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 hover:bg-[#FAFAFB] dark:hover:bg-[#141821] text-[13px] font-normal text-[#1C2024] dark:text-slate-200 transition">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-[4px] bg-[#FFEF4D]/25 text-[#856404] dark:text-[#FFEF4D]">
                            <i class="fa-solid fa-ticket text-[11px]"></i>
                        </span>
                        <span>{{ __('Create coupon') }}</span>
                    </a>

                    <a href="{{ route('admin.platform.edit') }}" wire:navigate
                        class="flex items-center gap-2.5 p-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 hover:bg-[#FAFAFB] dark:hover:bg-[#141821] text-[13px] font-normal text-[#1C2024] dark:text-slate-200 transition">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-[4px] bg-[#FFEF4D]/25 text-[#856404] dark:text-[#FFEF4D]">
                            <i class="fa-solid fa-sliders text-[11px]"></i>
                        </span>
                        <span>{{ __('Platform settings') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
