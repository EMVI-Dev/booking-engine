<?php

use App\Enums\ReservationStatus;
use App\Models\Operator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $timelineWeekStart = '';

    public function mount(): void
    {
        abort_unless($this->currentOperator?->hasFeature('advanced_calendar') ?? false, 403);

        $now = now();
        $this->timelineWeekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    #[Computed]
    public function currentOperator(): ?Operator
    {
        return auth()->user()?->currentOperator();
    }

    public function prevWeek(): void
    {
        $start = Carbon::parse($this->timelineWeekStart)->subWeek();
        $this->timelineWeekStart = $start->toDateString();
    }

    public function nextWeek(): void
    {
        $start = Carbon::parse($this->timelineWeekStart)->addWeek();
        $this->timelineWeekStart = $start->toDateString();
    }

    public function currentWeek(): void
    {
        $this->timelineWeekStart = now()->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    /**
     * Timeline week days (Monday - Sunday).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function timelineDays(): array
    {
        $start = Carbon::parse($this->timelineWeekStart)->startOfDay();
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $days[] = [
                'date' => $day->toDateString(),
                'dayName' => $day->format('D'),
                'dayNumber' => $day->format('d M'),
                'isToday' => $day->isToday(),
            ];
        }

        return $days;
    }

    /**
     * Timeline products with bookings and blocks for the selected week.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function timelineMatrix(): array
    {
        if (!$this->currentOperator) {
            return [];
        }

        $startDate = Carbon::parse($this->timelineWeekStart)->startOfDay();
        $endDate = $startDate->copy()->addDays(6)->endOfDay();

        $products = $this->currentOperator->products()->get();
        $reservations = $this->currentOperator
            ->reservations()
            ->whereBetween('requested_date', [$startDate->format('Y-m-d 00:00:00'), $endDate->format('Y-m-d 23:59:59')])
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Completed->value, ReservationStatus::PendingConfirmation->value, ReservationStatus::PaymentPending->value])
            ->with(['bookable'])
            ->get();

        $blocks = $this->currentOperator->availabilityBlocks()->where('date_start', '<=', $endDate->toDateString())->where('date_end', '>=', $startDate->toDateString())->get();

        $matrix = [];

        foreach ($products as $product) {
            $daysData = [];

            for ($i = 0; $i < 7; $i++) {
                $day = $startDate->copy()->addDays($i);
                $dateStr = $day->toDateString();

                // Check reservations for this product on this day
                $productReservations = $reservations->filter(function ($res) use ($product, $dateStr) {
                    $matchesDate = $res->requested_date->toDateString() === $dateStr;
                    $matchesProduct = $res->bookable_id === $product->id;

                    return $matchesDate && $matchesProduct;
                });

                $totalPax = $productReservations->sum('pax_count');
                $capacity = $product->capacity_per_day ?: 20;
                $occupancyRate = min(100, round(($totalPax / max(1, $capacity)) * 100));

                // Check availability blocks for this product or operator-wide
                $isBlocked = $blocks->contains(function ($b) use ($product, $dateStr) {
                    $start = $b->date_start instanceof Carbon ? $b->date_start->toDateString() : (string) $b->date_start;
                    $end = $b->date_end instanceof Carbon ? $b->date_end->toDateString() : (string) $b->date_end;
                    $inRange = $start <= $dateStr && $end >= $dateStr;
                    $matchesScope = ($b->product_id === null && $b->package_id === null) || $b->product_id === $product->id;

                    return $inRange && $matchesScope;
                });

                $daysData[] = [
                    'date' => $dateStr,
                    'totalPax' => $totalPax,
                    'capacity' => $capacity,
                    'occupancyRate' => $occupancyRate,
                    'reservationsCount' => $productReservations->count(),
                    'isBlocked' => $isBlocked,
                ];
            }

            $matrix[] = [
                'product' => $product,
                'days' => $daysData,
            ];
        }

        return $matrix;
    }
};
?>

<div class="space-y-6">
    <!-- Timeline Week Controls -->
    <div
        class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
        <div class="flex items-center gap-3">
            <span class="p-2 rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-300 text-sm">
                <i class="fa-solid fa-bars-staggered"></i>
            </span>
            <div>
                <h3 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white leading-tight">
                    {{ __('Resource & Experience Timeline') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ \Illuminate\Support\Carbon::parse($timelineWeekStart)->format('M d') }} -
                    {{ \Illuminate\Support\Carbon::parse($timelineWeekStart)->addDays(6)->format('M d, Y') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-stretch sm:self-auto justify-between sm:justify-end">
            <button type="button" wire:click="currentWeek"
                class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                {{ __('Current Week') }}
            </button>

            <div class="flex items-center gap-1 bg-slate-100 dark:bg-zinc-800 p-1 rounded-xl">
                <button type="button" wire:click="prevWeek"
                    class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                    title="{{ __('Previous Week') }}">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" wire:click="nextWeek"
                    class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                    title="{{ __('Next Week') }}">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Timeline Gantt Matrix Table -->
    <div
        class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse min-w-[760px]">
                <thead>
                    <tr
                        class="border-b border-slate-200/80 dark:border-zinc-800 bg-slate-50/70 dark:bg-zinc-800/40 text-slate-500 dark:text-slate-400">
                        <th class="py-3.5 px-4 font-extrabold uppercase tracking-wider text-[11px] w-1/4">
                            {{ __('Resource / Experience') }}
                        </th>
                        @foreach ($this->timelineDays as $d)
                            <th
                                class="py-3.5 px-3 text-center font-bold text-xs {{ $d['isToday'] ? 'bg-[#FFEF4D] dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border-t-2 border-t-indigo-600 dark:border-t-indigo-400' : '' }}">
                                <span
                                    class="block text-[10px] uppercase font-extrabold {{ $d['isToday'] ? 'text-[#090d16] dark:text-indigo-400' : 'opacity-75' }}">{{ $d['dayName'] }}</span>
                                @if ($d['isToday'])
                                    <span
                                        class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400 font-black shadow-2xs mt-0.5">{{ $d['dayNumber'] }}</span>
                                @else
                                    <span class="text-xs font-black">{{ $d['dayNumber'] }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60">
                    @forelse ($this->timelineMatrix as $row)
                        @php
                            $product = $row['product'];
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition">
                            <!-- Product Column -->
                            <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="w-7 h-7 rounded-lg bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400 flex items-center justify-center text-xs shrink-0">
                                        <i class="fa-solid fa-layer-group"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-xs font-extrabold text-slate-900 dark:text-white leading-tight">
                                            {{ $product->name }}
                                        </p>
                                        <span class="text-[10px] text-slate-400 block mt-0.5">
                                            {{ __('Cap:') }} {{ $product->capacity_per_day ?: 20 }}
                                            {{ __('pax/day') }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- 7 Day Matrix Cells -->
                            @foreach ($row['days'] as $cell)
                                <td class="py-3 px-2 text-center align-middle">
                                    @if ($cell['isBlocked'])
                                        <div
                                            class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200/60 dark:border-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold">
                                            <i class="fa-solid fa-lock text-[9px] mr-1"></i>{{ __('Blocked') }}
                                        </div>
                                    @elseif ($cell['totalPax'] > 0)
                                        <div
                                            class="p-2 rounded-xl {{ $cell['occupancyRate'] >= 90 ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : ($cell['occupancyRate'] >= 50 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300') }} text-center space-y-1 shadow-2xs">
                                            <div class="font-extrabold text-[11px]">
                                                {{ $cell['totalPax'] }} Pax
                                            </div>
                                            <!-- Mini Progress Bar -->
                                            <div
                                                class="w-full bg-black/10 dark:bg-white/10 h-1.5 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full {{ $cell['occupancyRate'] >= 90 ? 'bg-rose-600' : ($cell['occupancyRate'] >= 50 ? 'bg-amber-500' : 'bg-indigo-600') }}"
                                                    style="width: {{ $cell['occupancyRate'] }}%;"></div>
                                            </div>
                                            <span class="text-[9px] opacity-75 font-semibold block">
                                                {{ $cell['occupancyRate'] }}% {{ __('Cap') }}
                                            </span>
                                        </div>
                                    @else
                                        <div
                                            class="p-2 rounded-xl bg-slate-50 dark:bg-zinc-800/30 text-slate-300 dark:text-zinc-600 text-[10px] font-medium border border-dashed border-slate-200 dark:border-zinc-800">
                                            {{ __('Open') }}
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-xs text-slate-400">
                                {{ __('No experience products found. Add products in your catalog to track resource allocation.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
