<?php

use App\Enums\ReservationStatus;
use App\Models\Operator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public int $heatmapYear = 0;
    public int $heatmapMonth = 0;

    public function mount(): void
    {
        $now = now();
        $this->heatmapYear = (int) $now->format('Y');
        $this->heatmapMonth = (int) $now->format('n');
    }

    #[Computed]
    public function currentOperator(): ?Operator
    {
        return auth()->user()?->currentOperator();
    }

    public function prevHeatmapMonth(): void
    {
        $d = Carbon::createFromDate($this->heatmapYear, $this->heatmapMonth, 1)->subMonth();
        $this->heatmapYear = (int) $d->format('Y');
        $this->heatmapMonth = (int) $d->format('n');
    }

    public function nextHeatmapMonth(): void
    {
        $d = Carbon::createFromDate($this->heatmapYear, $this->heatmapMonth, 1)->addMonth();
        $this->heatmapYear = (int) $d->format('Y');
        $this->heatmapMonth = (int) $d->format('n');
    }

    /**
     * Heatmap calendar day occupancy percentages.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function heatmapDays(): array
    {
        if (! $this->currentOperator) {
            return [];
        }

        $startOfMonth = Carbon::createFromDate($this->heatmapYear, $this->heatmapMonth, 1)->startOfMonth();
        $endOfMonth = (clone $startOfMonth)->endOfMonth();

        $startGrid = (clone $startOfMonth)->startOfWeek(Carbon::SUNDAY);
        $endGrid = (clone $endOfMonth)->endOfWeek(Carbon::SATURDAY);

        $reservations = $this->currentOperator->reservations()
            ->whereBetween('requested_date', [$startGrid->format('Y-m-d 00:00:00'), $endGrid->format('Y-m-d 23:59:59')])
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Completed->value,
                ReservationStatus::PendingConfirmation->value,
            ])
            ->get();

        $dailyCapacity = $this->currentOperator->products()->sum('capacity_per_day') ?: 20;

        $days = [];
        $current = clone $startGrid;

        while ($current->lte($endGrid)) {
            $dateStr = $current->toDateString();
            $dayPax = $reservations->where('requested_date', $current)->sum('pax_count');
            $rate = min(100, round(($dayPax / max(1, $dailyCapacity)) * 100));

            $days[] = [
                'date' => $dateStr,
                'dayNumber' => $current->day,
                'isCurrentMonth' => $current->month === $this->heatmapMonth,
                'isToday' => $current->isToday(),
                'totalPax' => $dayPax,
                'occupancyRate' => $rate,
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Heatmap summary statistics.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function heatmapSummary(): array
    {
        if (! $this->currentOperator) {
            return [
                'totalMonthPax' => 0,
                'peakDayPax' => 0,
                'activeDaysCount' => 0,
                'avgOccupancyRate' => 0,
            ];
        }

        $start = Carbon::createFromDate($this->heatmapYear, $this->heatmapMonth, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $monthReservations = $this->currentOperator->reservations()
            ->whereBetween('requested_date', [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')])
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Completed->value,
                ReservationStatus::PendingConfirmation->value,
            ])
            ->get();

        $totalPax = $monthReservations->sum('pax_count');
        $daysGroup = $monthReservations->groupBy(fn ($r) => $r->requested_date->toDateString());
        $peakPax = $daysGroup->map(fn ($group) => $group->sum('pax_count'))->max() ?? 0;
        $dailyCapacity = $this->currentOperator->products()->sum('capacity_per_day') ?: 20;
        $totalMonthlyCapacity = $dailyCapacity * $start->daysInMonth;
        $avgRate = min(100, round(($totalPax / max(1, $totalMonthlyCapacity)) * 100));

        return [
            'totalMonthPax' => $totalPax,
            'peakDayPax' => $peakPax,
            'activeDaysCount' => $daysGroup->count(),
            'avgOccupancyRate' => $avgRate,
        ];
    }
};
?>

<div class="space-y-6">
    <!-- Heatmap Month Controls & Metrics Summary -->
    <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-2xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-base">
                    <i class="fa-solid fa-fire-flame-curved"></i>
                </span>
                <div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white leading-tight">
                        {{ __('Occupancy Heatmap & Capacity Density') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ \Illuminate\Support\Carbon::createFromDate($heatmapYear, $heatmapMonth, 1)->format('F Y') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1 bg-slate-100 dark:bg-zinc-800 p-1 rounded-xl">
                <button
                    type="button"
                    wire:click="prevHeatmapMonth"
                    class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                >
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button
                    type="button"
                    wire:click="nextHeatmapMonth"
                    class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                >
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-100 dark:border-zinc-800 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Total Month Passengers') }}</span>
                <p class="text-xl font-black text-indigo-600 dark:text-indigo-400">
                    {{ $this->heatmapSummary['totalMonthPax'] }} Pax
                </p>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-100 dark:border-zinc-800 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Peak Single-Day Departure') }}</span>
                <p class="text-xl font-black text-rose-600 dark:text-rose-400">
                    {{ $this->heatmapSummary['peakDayPax'] }} Pax
                </p>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-100 dark:border-zinc-800 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Active Booking Days') }}</span>
                <p class="text-xl font-black text-slate-900 dark:text-white">
                    {{ $this->heatmapSummary['activeDaysCount'] }} {{ __('Days') }}
                </p>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-100 dark:border-zinc-800 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Monthly Avg Capacity Load') }}</span>
                <p class="text-xl font-black text-emerald-600 dark:text-emerald-400">
                    {{ $this->heatmapSummary['avgOccupancyRate'] }}%
                </p>
            </div>
        </div>
    </div>

    <!-- Monthly Heatmap Calendar View -->
    <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden">
        <!-- Weekday Headers -->
        <div class="grid grid-cols-7 border-b border-slate-200/80 dark:border-zinc-800 bg-slate-50/70 dark:bg-zinc-800/40 text-center text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 py-3">
            <div>{{ __('Sun') }}</div>
            <div>{{ __('Mon') }}</div>
            <div>{{ __('Tue') }}</div>
            <div>{{ __('Wed') }}</div>
            <div>{{ __('Thu') }}</div>
            <div>{{ __('Fri') }}</div>
            <div>{{ __('Sat') }}</div>
        </div>

        <div class="grid grid-cols-7 divide-x divide-y divide-slate-100 dark:divide-zinc-800/60 text-xs">
            @foreach ($this->heatmapDays as $cell)
                @php
                    $rate = $cell['occupancyRate'];
                    $colorClass = 'bg-white dark:bg-zinc-900 text-slate-400';
                    if ($cell['isCurrentMonth']) {
                        if ($rate >= 80) {
                            $colorClass = 'bg-rose-500 text-white dark:bg-rose-600 font-extrabold';
                        } elseif ($rate >= 50) {
                            $colorClass = 'bg-amber-400 text-slate-900 dark:bg-amber-500 font-bold';
                        } elseif ($rate > 0) {
                            $colorClass = 'bg-indigo-400 text-white dark:bg-indigo-500 font-medium';
                        } else {
                            $colorClass = 'bg-slate-50/70 dark:bg-zinc-800/30 text-slate-500 dark:text-slate-400';
                        }
                    } else {
                        $colorClass = 'bg-slate-50/30 dark:bg-zinc-950/40 text-slate-300 dark:text-zinc-600';
                    }
                @endphp
                <div class="min-h-[85px] sm:min-h-[95px] p-3 flex flex-col justify-between transition-all {{ $colorClass }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black">{{ $cell['dayNumber'] }}</span>
                        @if ($cell['isCurrentMonth'] && $rate > 0)
                            <span class="text-[10px] uppercase font-bold opacity-80">{{ $rate }}%</span>
                        @endif
                    </div>
                    @if ($cell['isCurrentMonth'] && $cell['totalPax'] > 0)
                        <div class="text-right">
                            <span class="text-sm sm:text-base font-black">{{ $cell['totalPax'] }}</span>
                            <span class="text-[10px] opacity-80 block leading-tight">{{ __('Pax') }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Heatmap Legend -->
    <div class="flex items-center justify-center gap-4 text-xs font-bold text-slate-500 pt-2 flex-wrap">
        <div class="flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-md bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700"></span>
            <span>0% (Empty)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-md bg-indigo-400"></span>
            <span>1-49% (Moderate)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-md bg-amber-400"></span>
            <span>50-79% (Busy)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-md bg-rose-500"></span>
            <span>80-100% (Full / Peak)</span>
        </div>
    </div>
</div>
