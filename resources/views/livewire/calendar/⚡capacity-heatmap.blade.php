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
        abort_unless($this->currentOperator?->hasFeature('capacity_heatmap') ?? false, 403);

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
        if (!$this->currentOperator) {
            return [];
        }

        $startOfMonth = Carbon::createFromDate($this->heatmapYear, $this->heatmapMonth, 1)->startOfMonth();
        $endOfMonth = (clone $startOfMonth)->endOfMonth();

        $startGrid = (clone $startOfMonth)->startOfWeek(Carbon::SUNDAY);
        $endGrid = (clone $endOfMonth)->endOfWeek(Carbon::SATURDAY);

        $reservations = $this->currentOperator
            ->reservations()
            ->whereBetween('requested_date', [$startGrid->format('Y-m-d 00:00:00'), $endGrid->format('Y-m-d 23:59:59')])
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Completed->value, ReservationStatus::PendingConfirmation->value])
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
        if (!$this->currentOperator) {
            return [
                'totalMonthPax' => 0,
                'peakDayPax' => 0,
                'activeDaysCount' => 0,
                'avgOccupancyRate' => 0,
            ];
        }

        $start = Carbon::createFromDate($this->heatmapYear, $this->heatmapMonth, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $monthReservations = $this->currentOperator
            ->reservations()
            ->whereBetween('requested_date', [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')])
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Completed->value, ReservationStatus::PendingConfirmation->value])
            ->get();

        $totalPax = $monthReservations->sum('pax_count');
        $daysGroup = $monthReservations->groupBy(fn($r) => $r->requested_date->toDateString());
        $peakPax = $daysGroup->map(fn($group) => $group->sum('pax_count'))->max() ?? 0;
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
    <div
        class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span
                    class="w-9 h-9 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-fire-flame-curved"></i>
                </span>
                <div>
                    <h3 class="text-[16px] sm:text-[18px] font-medium text-[#12181E] dark:text-white leading-tight">
                        {{ __('Occupancy Heatmap & Capacity Density') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ \Illuminate\Support\Carbon::createFromDate($heatmapYear, $heatmapMonth, 1)->format('F Y') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1 bg-[#F9FAFB] dark:bg-[#151a26] p-1 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433]">
                <button type="button" wire:click="prevHeatmapMonth"
                    class="h-7 w-7 rounded-[4px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-[#10141d] transition cursor-pointer"
                    title="{{ __('Previous Month') }}">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" wire:click="nextHeatmapMonth"
                    class="h-7 w-7 rounded-[4px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-[#10141d] transition cursor-pointer"
                    title="{{ __('Next Month') }}">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-1">
            <div
                class="p-3 sm:p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                <span
                    class="text-[10px] uppercase font-medium text-slate-400 block">{{ __('Total Month Passengers') }}</span>
                <p class="text-lg sm:text-xl font-medium text-[#12181E] dark:text-white">
                    {{ $this->heatmapSummary['totalMonthPax'] }} Pax
                </p>
            </div>
            <div
                class="p-3 sm:p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                <span
                    class="text-[10px] uppercase font-medium text-slate-400 block">{{ __('Peak Single-Day Departure') }}</span>
                <p class="text-lg sm:text-xl font-medium text-rose-600 dark:text-rose-400">
                    {{ $this->heatmapSummary['peakDayPax'] }} Pax
                </p>
            </div>
            <div
                class="p-3 sm:p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                <span
                    class="text-[10px] uppercase font-medium text-slate-400 block">{{ __('Active Booking Days') }}</span>
                <p class="text-lg sm:text-xl font-medium text-[#12181E] dark:text-white">
                    {{ $this->heatmapSummary['activeDaysCount'] }} {{ __('Days') }}
                </p>
            </div>
            <div
                class="p-3 sm:p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                <span
                    class="text-[10px] uppercase font-medium text-slate-400 block">{{ __('Monthly Avg Capacity Load') }}</span>
                <p class="text-lg sm:text-xl font-medium text-emerald-600 dark:text-emerald-400">
                    {{ $this->heatmapSummary['avgOccupancyRate'] }}%
                </p>
            </div>
        </div>
    </div>

    <!-- Monthly Heatmap Calendar View -->
    <div
        class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
        <!-- Weekday Headers -->
        <div
            class="grid grid-cols-7 border-b border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] text-center text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 py-2.5">
            <div>{{ __('Sun') }}</div>
            <div>{{ __('Mon') }}</div>
            <div>{{ __('Tue') }}</div>
            <div>{{ __('Wed') }}</div>
            <div>{{ __('Thu') }}</div>
            <div>{{ __('Fri') }}</div>
            <div>{{ __('Sat') }}</div>
        </div>

        <div class="grid grid-cols-7 divide-x divide-y divide-[#E4E5E9] dark:divide-[#1E2433] text-xs">
            @foreach ($this->heatmapDays as $cell)
                @php
                    $rate = $cell['occupancyRate'];
                    $colorClass = 'bg-white dark:bg-[#10141d] text-slate-400';
                    if ($cell['isCurrentMonth']) {
                        if ($rate >= 80) {
                            $colorClass = 'bg-rose-500 text-white font-medium';
                        } elseif ($rate >= 50) {
                            $colorClass = 'bg-amber-400 text-[#12181E] font-medium';
                        } elseif ($rate > 0) {
                            $colorClass = 'bg-[#FFEF4D] text-[#12181E] font-medium';
                        } else {
                            $colorClass = 'bg-white dark:bg-[#10141d] text-slate-600 dark:text-slate-400';
                        }
                    } else {
                        $colorClass = 'bg-[#F9FAFB]/50 dark:bg-[#151a26]/40 text-slate-300 dark:text-zinc-600';
                    }
                @endphp
                <div
                    class="min-h-[70px] sm:min-h-[90px] p-2 sm:p-3 flex flex-col justify-between transition-all {{ $colorClass }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium">{{ $cell['dayNumber'] }}</span>
                        @if ($cell['isCurrentMonth'] && $rate > 0)
                            <span class="text-[10px] uppercase font-medium opacity-90">{{ $rate }}%</span>
                        @endif
                    </div>
                    @if ($cell['isCurrentMonth'] && $cell['totalPax'] > 0)
                        <div class="text-right">
                            <span class="text-sm sm:text-base font-medium">{{ $cell['totalPax'] }}</span>
                            <span class="text-[10px] opacity-80 block leading-tight">{{ __('Pax') }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Heatmap Legend -->
    <div class="flex items-center justify-center gap-3 sm:gap-4 text-xs font-medium text-slate-500 pt-1 flex-wrap">
        <div class="flex items-center gap-1.5">
            <span
                class="w-3.5 h-3.5 rounded-[3px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433]"></span>
            <span>0% (Empty)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-[3px] bg-[#FFEF4D]"></span>
            <span>1-49% (Moderate)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-[3px] bg-amber-400"></span>
            <span>50-79% (Busy)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-[3px] bg-rose-500"></span>
            <span>80-100% (Full / Peak)</span>
        </div>
    </div>
</div>
