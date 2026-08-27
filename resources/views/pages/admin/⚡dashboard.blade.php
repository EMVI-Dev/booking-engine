<?php

use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Platform Revenue & Executive Dashboard')] #[Layout('layouts.admin')] class extends Component {
    public string $period = '12m'; // '30d', '6m', '12m'

    #[Computed]
    public function totalGmv(): float
    {
        return (float) Payment::where('status', PaymentStatus::Paid)->sum('amount');
    }

    #[Computed]
    public function currentMonthGmv(): float
    {
        return (float) Payment::where('status', PaymentStatus::Paid)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
    }

    #[Computed]
    public function previousMonthGmv(): float
    {
        $start = now()->subMonth()->startOfMonth();
        $end = now()->subMonth()->endOfMonth();

        return (float) Payment::where('status', PaymentStatus::Paid)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
    }

    #[Computed]
    public function mtdGrowthPercentage(): float
    {
        $prev = $this->previousMonthGmv;
        if ($prev <= 0) {
            return $this->currentMonthGmv > 0 ? 100.0 : 0.0;
        }

        return round((($this->currentMonthGmv - $prev) / $prev) * 100, 1);
    }

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
    public function monthlyTrends(): array
    {
        $monthsCount = match ($this->period) {
            '30d' => 1,
            '6m' => 6,
            default => 12,
        };

        $months = [];
        $maxGmv = 1.0;

        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = (clone $date)->startOfMonth();
            $end = (clone $date)->endOfMonth();

            $gmv = (float) Payment::where('status', PaymentStatus::Paid)
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount');

            $subRev = (float) SubscriptionPayment::where('status', 'completed')
                ->whereBetween('created_at', [$start, $end])
                ->sum('net_amount_paid');

            $count = Reservation::whereBetween('created_at', [$start, $end])->count();

            if ($gmv > $maxGmv) {
                $maxGmv = $gmv;
            }

            $months[] = [
                'label' => $date->format('M Y'),
                'short' => $date->format('M'),
                'gmv' => $gmv,
                'sub_revenue' => $subRev,
                'bookings_count' => $count,
            ];
        }

        return [
            'data' => $months,
            'maxGmv' => $maxGmv,
        ];
    }

    #[Computed]
    public function topOperators(): Collection
    {
        return Operator::query()
            ->with(['plan'])
            ->withCount(['reservations'])
            ->get()
            ->map(function (Operator $operator) {
                $gmv = (float) Payment::where('status', PaymentStatus::Paid)
                    ->whereHas('reservation', fn ($q) => $q->where('operator_id', $operator->id))
                    ->sum('amount');

                $operator->calculated_gmv = $gmv;

                return $operator;
            })
            ->sortByDesc('calculated_gmv')
            ->take(5)
            ->values();
    }

    #[Computed]
    public function recentTransactions(): Collection
    {
        return Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->with(['reservation.operator', 'reservation.guest'])
            ->latest('created_at')
            ->take(10)
            ->get();
    }

    #[Computed]
    public function planDistribution(): array
    {
        $plans = Plan::orderBy('sort_order')->get();
        $distribution = [];

        foreach ($plans as $plan) {
            $count = Operator::where('plan_id', $plan->id)->count();
            $distribution[] = [
                'name' => $plan->name,
                'slug' => $plan->slug,
                'count' => $count,
                'price' => (float) $plan->price_monthly,
            ];
        }

        // Also add Free / Unassigned
        $unassigned = Operator::whereNull('plan_id')->count();
        if ($unassigned > 0) {
            $distribution[] = [
                'name' => 'Unassigned',
                'slug' => 'starter',
                'count' => $unassigned,
                'price' => 0.0,
            ];
        }

        return $distribution;
    }
}; ?>

<div class="space-y-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300">
                    <i class="fa-solid fa-chart-pie text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Platform Revenue & Financial Intelligence') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Aggregated gross merchandise volume (GMV), subscription MRR, and platform performance metrics.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Time Range Selector -->
        <div class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 p-1.5 rounded-2xl shadow-xs self-start sm:self-auto">
            @php
                $periods = [
                    '30d' => __('30 Days'),
                    '6m' => __('6 Months'),
                    '12m' => __('12 Months'),
                ];
            @endphp
            @foreach ($periods as $key => $label)
                <button
                    type="button"
                    wire:click="$set('period', '{{ $key }}')"
                    class="px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer {{ $period === $key ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Executive KPI Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Platform GMV -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2 relative overflow-hidden group hover:border-purple-300 dark:hover:border-purple-800/80 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Platform Gross GMV') }}</span>
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400">
                    <i class="fa-solid fa-coins text-sm"></i>
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                Rp {{ number_format($this->totalGmv, 0, ',', '.') }}
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('All-time processed customer payments') }}
            </p>
        </div>

        <!-- Card 2: Current Month GMV & Momentum -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2 relative overflow-hidden group hover:border-indigo-300 dark:hover:border-indigo-800/80 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Monthly Volume (MTD)') }}</span>
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400">
                    <i class="fa-solid fa-calendar-check text-sm"></i>
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                Rp {{ number_format($this->currentMonthGmv, 0, ',', '.') }}
            </div>
            <div class="flex items-center gap-1.5 text-xs">
                @if ($this->mtdGrowthPercentage >= 0)
                    <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-0.5">
                        <i class="fa-solid fa-arrow-trend-up"></i> +{{ $this->mtdGrowthPercentage }}%
                    </span>
                @else
                    <span class="font-bold text-rose-600 dark:text-rose-400 flex items-center gap-0.5">
                        <i class="fa-solid fa-arrow-trend-down"></i> {{ $this->mtdGrowthPercentage }}%
                    </span>
                @endif
                <span class="text-slate-400">{{ __('vs previous month') }}</span>
            </div>
        </div>

        <!-- Card 3: Active MRR -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2 relative overflow-hidden group hover:border-emerald-300 dark:hover:border-emerald-800/80 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Subscription MRR') }}</span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400">
                    <i class="fa-solid fa-repeat text-sm"></i>
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                Rp {{ number_format($this->currentMrr, 0, ',', '.') }}
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Contracted recurring plan revenue / month') }}
            </p>
        </div>

        <!-- Card 4: Live Tour Operators -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2 relative overflow-hidden group hover:border-amber-300 dark:hover:border-amber-800/80 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Live Operators') }}</span>
                <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400">
                    <i class="fa-solid fa-users-gear text-sm"></i>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ $this->approvedOperatorsCount }}</span>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">/ {{ $this->totalOperatorsCount }} {{ __('registered') }}</span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Approved operator storefronts selling actively') }}
            </p>
        </div>
    </div>

    <!-- Monthly GMV Trend Visualizer -->
    <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
            <div>
                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-chart-column text-purple-600 dark:text-purple-400"></i>
                    {{ __('Gross Merchandise Volume (GMV) Trend') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Monthly transaction volume trajectory over the selected period.') }}
                </p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
                    <span class="w-3 h-3 rounded bg-purple-600 inline-block"></span>
                    {{ __('Reservation GMV') }}
                </span>
            </div>
        </div>

        @php
            $trends = $this->monthlyTrends;
            $data = $trends['data'];
            $maxGmv = max($trends['maxGmv'], 1);
        @endphp

        <!-- Bar Visualizer -->
        <div class="grid grid-cols-6 sm:grid-cols-12 gap-2 sm:gap-3 items-end pt-8 pb-2 min-h-[220px]">
            @foreach ($data as $m)
                @php
                    $pct = min(100, max(6, round(($m['gmv'] / $maxGmv) * 100)));
                @endphp
                <div class="flex flex-col items-center gap-2 group h-full justify-end">
                    <!-- Tooltip Hover Value -->
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-150 text-xs font-bold bg-slate-900 dark:bg-white text-white dark:text-slate-900 py-1 px-2 rounded-lg whitespace-nowrap shadow-md pointer-events-none mb-1">
                        Rp {{ number_format($m['gmv'] / 1000, 0) }}k ({{ $m['bookings_count'] }} res)
                    </div>

                    <!-- Bar -->
                    <div class="w-full max-w-[48px] bg-slate-100 dark:bg-zinc-800 rounded-t-xl overflow-hidden flex flex-col justify-end h-40 relative">
                        <div
                            style="height: {{ $pct }}%"
                            class="w-full bg-gradient-to-t from-purple-600 to-indigo-500 dark:from-purple-500 dark:to-indigo-400 rounded-t-xl group-hover:brightness-110 transition-all duration-300"
                        ></div>
                    </div>

                    <!-- Label -->
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300 group-hover:text-purple-600 transition truncate">
                        {{ $m['short'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Dual Column: Top Operators & Plan Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Top Operators by GMV (2 Cols) -->
        <div class="lg:col-span-2 p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                <div>
                    <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-trophy text-amber-500"></i>
                        {{ __('Top Performing Operators') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Ranked by all-time processed gross volume.') }}
                    </p>
                </div>
                <a href="{{ route('admin.operators.index') }}" wire:navigate class="text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline">
                    {{ __('View all') }} &rarr;
                </a>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                @forelse ($this->topOperators as $index => $operator)
                    <div class="py-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 px-2 rounded-2xl transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-6 text-center font-black text-xs text-slate-400">{{ $index + 1 }}</span>
                            <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300 font-extrabold flex items-center justify-center text-xs shrink-0 overflow-hidden border border-purple-200 dark:border-purple-800/50">
                                @if ($operator->logo_path)
                                    <img src="{{ Storage::url($operator->logo_path) }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                @else
                                    {{ strtoupper(substr($operator->name, 0, 2)) }}
                                @endif
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="font-bold text-sm text-slate-900 dark:text-white hover:text-purple-600 dark:hover:text-purple-400 transition truncate block">
                                    {{ $operator->name }}
                                </a>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $operator->reservations_count }} {{ __('reservations') }} &bull; {{ $operator->plan?->name ?? 'Free Tier' }}
                                </span>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="font-black text-sm text-slate-900 dark:text-white">
                                Rp {{ number_format($operator->calculated_gmv, 0, ',', '.') }}
                            </div>
                            <span class="text-xs font-bold uppercase px-2.5 py-0.5 rounded-full {{ $operator->status === OperatorStatus::Approved ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600' }}">
                                {{ $operator->status->label() }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 py-6 text-center">{{ __('No operator sales recorded yet.') }}</p>
                @endforelse
            </div>
        </div>

        <!-- Right: Subscription Plans Breakdown (1 Col) -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="pb-3 border-b border-slate-100 dark:border-zinc-800">
                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-indigo-500"></i>
                    {{ __('Plan Subscriptions') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Active operator tier distribution.') }}
                </p>
            </div>

            <div class="space-y-3">
                @foreach ($this->planDistribution as $plan)
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">{{ $plan['name'] }}</span>
                            <span class="font-black text-sm text-purple-600 dark:text-purple-400">
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
                            <span>{{ round(($plan['count'] / max(1, Operator::count())) * 100) }}% {{ __('of total') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-2">
                <a href="{{ route('admin.plans.index') }}" wire:navigate class="w-full h-9 flex items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-950/70 hover:bg-purple-100 text-purple-700 dark:text-purple-300 text-xs font-bold transition border border-purple-200 dark:border-purple-800/50">
                    <i class="fa-solid fa-sliders mr-1.5 text-xs"></i>
                    {{ __('Manage Subscription Tiers') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Platform-Wide Transactions Stream -->
    <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
            <div>
                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-emerald-500"></i>
                    {{ __('Recent Processed Payments') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Real-time transaction feed across all operator storefronts.') }}
                </p>
            </div>
            <a href="{{ route('admin.payouts.index') }}" wire:navigate class="text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline">
                {{ __('View Payouts') }} &rarr;
            </a>
        </div>

        <!-- Mobile Admin Transactions Feed Card List (md:hidden) -->
        <div class="md:hidden space-y-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($this->recentTransactions as $payment)
                <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono font-extrabold text-xs text-purple-600 dark:text-purple-400">
                            #{{ $payment->reservation?->code ?? substr($payment->id, 0, 8) }}
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300">
                            {{ strtoupper($payment->gateway) }}
                        </span>
                    </div>

                    <div class="space-y-1">
                        <h4 class="font-bold text-xs text-slate-900 dark:text-white">
                            {{ $payment->reservation?->operator?->name ?? 'System' }} &bull; <span class="font-normal text-slate-500">{{ $payment->reservation?->guest_name ?? '-' }}</span>
                        </h4>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800 text-xs">
                        <span class="text-slate-400 text-[10px] uppercase font-bold">{{ __('Amount') }}</span>
                        <span class="font-mono font-black text-slate-900 dark:text-white">
                            Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-xs text-slate-400">
                    {{ __('No recent customer transactions recorded.') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop Admin Transactions Table (hidden on mobile) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40">
                        <th class="py-3 px-3.5">{{ __('Transaction / Code') }}</th>
                        <th class="py-3 px-3.5">{{ __('Operator') }}</th>
                        <th class="py-3 px-3.5">{{ __('Guest') }}</th>
                        <th class="py-3 px-3.5">{{ __('Amount') }}</th>
                        <th class="py-3 px-3.5">{{ __('Gateway') }}</th>
                        <th class="py-3 px-3.5 text-right">{{ __('Date & Time') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($this->recentTransactions as $payment)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 px-3.5">
                                <div class="font-mono font-bold text-purple-600 dark:text-purple-400">
                                    #{{ $payment->reservation?->code ?? substr($payment->id, 0, 8) }}
                                </div>
                                <span class="text-xs text-slate-400 font-mono">{{ $payment->gateway_ref ?? '-' }}</span>
                            </td>
                            <td class="py-3.5 px-3.5">
                                @if ($payment->reservation?->operator)
                                    <a href="{{ route('admin.operators.show', $payment->reservation->operator->id) }}" wire:navigate class="font-bold text-slate-900 dark:text-white hover:text-purple-600 transition">
                                        {{ $payment->reservation->operator->name }}
                                    </a>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3.5 text-slate-700 dark:text-slate-300 font-medium">
                                {{ $payment->reservation?->guest_name ?? '-' }}
                            </td>
                            <td class="py-3.5 px-3.5 font-black text-slate-900 dark:text-white">
                                Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold uppercase bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300">
                                    {{ strtoupper($payment->gateway) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3.5 text-right text-slate-500 dark:text-slate-400 font-mono text-xs">
                                {{ $payment->created_at?->format('d M Y, H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                {{ __('No recent customer transactions recorded.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
