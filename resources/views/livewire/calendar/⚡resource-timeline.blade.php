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
        class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none">
        <div class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm shrink-0">
                <i class="fa-solid fa-bars-staggered"></i>
            </span>
            <div>
                <h3 class="text-[16px] sm:text-[18px] font-medium text-[#12181E] dark:text-white leading-tight">
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
                class="h-9 px-3 rounded-[6px] text-xs font-medium bg-[#F9FAFB] dark:bg-[#151a26] hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer">
                {{ __('Current Week') }}
            </button>

            <div class="flex items-center gap-1 bg-[#F9FAFB] dark:bg-[#151a26] p-1 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433]">
                <button type="button" wire:click="prevWeek"
                    class="h-7 w-7 rounded-[4px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-[#10141d] transition cursor-pointer"
                    title="{{ __('Previous Week') }}">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" wire:click="nextWeek"
                    class="h-7 w-7 rounded-[4px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-[#10141d] transition cursor-pointer"
                    title="{{ __('Next Week') }}">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Timeline Gantt Matrix Table -->
    <div
        class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse min-w-[760px]">
                <thead>
                    <tr
                        class="border-b border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] text-slate-500 dark:text-slate-400">
                        <th class="py-3 px-4 font-medium uppercase tracking-wider text-[11px] w-1/4">
                            {{ __('Resource / Experience') }}
                        </th>
                        @foreach ($this->timelineDays as $d)
                            <th
                                class="py-3 px-3 text-center font-medium text-xs {{ $d['isToday'] ? 'bg-[#FFEF4D]/15 dark:bg-[#FFEF4D]/10 text-[#12181E] dark:text-[#FFEF4D] border-t-2 border-t-[#FFEF4D]' : '' }}">
                                <span
                                    class="block text-[10px] uppercase font-medium {{ $d['isToday'] ? 'text-[#12181E] dark:text-[#FFEF4D]' : 'opacity-75' }}">{{ $d['dayName'] }}</span>
                                @if ($d['isToday'])
                                    <span
                                        class="inline-flex items-center justify-center h-5 w-5 rounded-[4px] bg-[#FFEF4D] text-[#12181E] font-medium text-xs mt-0.5">{{ $d['dayNumber'] }}</span>
                                @else
                                    <span class="text-xs font-medium">{{ $d['dayNumber'] }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                    @forelse ($this->timelineMatrix as $row)
                        @php
                            $product = $row['product'];
                        @endphp
                        <tr class="hover:bg-[#F9FAFB] dark:hover:bg-[#151a26] transition">
                            <!-- Product Column -->
                            <td class="py-3.5 px-4 font-medium text-[#12181E] dark:text-white">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="w-7 h-7 rounded-[6px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-xs shrink-0">
                                        <i class="fa-solid fa-layer-group"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-xs font-medium text-[#12181E] dark:text-white leading-tight">
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
                                <td class="py-2.5 px-2 text-center align-middle">
                                    @if ($cell['isBlocked'])
                                        <div
                                            class="p-2 rounded-[6px] bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-medium">
                                            <i class="fa-solid fa-lock text-[9px] mr-1"></i>{{ __('Blocked') }}
                                        </div>
                                    @elseif ($cell['totalPax'] > 0)
                                        <div
                                            class="p-2 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] text-center space-y-1 shadow-none">
                                            <div class="font-medium text-[11px] text-[#12181E] dark:text-white">
                                                {{ $cell['totalPax'] }} Pax
                                            </div>
                                            <!-- Mini Progress Bar -->
                                            <div
                                                class="w-full bg-[#E4E5E9] dark:bg-[#1E2433] h-1.5 rounded-[2px] overflow-hidden">
                                                <div class="h-full rounded-[2px] {{ $cell['occupancyRate'] >= 90 ? 'bg-rose-500' : ($cell['occupancyRate'] >= 50 ? 'bg-amber-400' : 'bg-[#FFEF4D]') }}"
                                                    style="width: {{ $cell['occupancyRate'] }}%;"></div>
                                            </div>
                                            <span class="text-[9px] text-slate-400 font-medium block">
                                                {{ $cell['occupancyRate'] }}% {{ __('Cap') }}
                                            </span>
                                        </div>
                                    @else
                                        <div
                                            class="p-2 rounded-[6px] bg-transparent text-slate-300 dark:text-zinc-600 text-[10px] font-medium border border-dashed border-[#E4E5E9] dark:border-[#1E2433]">
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
