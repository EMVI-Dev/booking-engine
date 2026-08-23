<?php

use App\Enums\ReservationStatus;
use App\Models\AvailabilityBlock;
use App\Models\Operator;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public int $month = 0;
    public int $year = 0;

    public ?string $selectedDate = null;
    public bool $drawerOpen = false;

    // Date blackout modal state
    public bool $blockModalOpen = false;
    public array $blockProductIds = [];
    public string $blockStartDate = '';
    public string $blockEndDate = '';
    public string $blockReason = '';

    public function mount(): void
    {
        $now = now();
        $this->month = (int) $now->format('n');
        $this->year = (int) $now->format('Y');
        $this->blockStartDate = $now->toDateString();
        $this->blockEndDate = $now->toDateString();
    }

    #[Computed]
    public function currentOperator(): ?Operator
    {
        return auth()->user()?->currentOperator();
    }

    public function prevMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->month = (int) $date->format('n');
        $this->year = (int) $date->format('Y');
        $this->selectedDate = null;
        $this->drawerOpen = false;
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->month = (int) $date->format('n');
        $this->year = (int) $date->format('Y');
        $this->selectedDate = null;
        $this->drawerOpen = false;
    }

    public function currentMonth(): void
    {
        $now = now();
        $this->month = (int) $now->format('n');
        $this->year = (int) $now->format('Y');
        $this->selectedDate = null;
        $this->drawerOpen = false;
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->drawerOpen = true;
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->selectedDate = null;
    }

    public function openBlockModal(?string $defaultDate = null): void
    {
        $date = $defaultDate ?: ($this->selectedDate ?: now()->toDateString());
        $this->blockStartDate = $date;
        $this->blockEndDate = $date;
        $this->blockReason = '';
        $this->blockProductIds = [];
        $this->blockModalOpen = true;
        $this->dispatch('open-modal', 'block-inventory-dates');
    }

    public function setQuickBlock(string $range): void
    {
        $now = now();
        switch ($range) {
            case 'today':
                $this->blockStartDate = $now->toDateString();
                $this->blockEndDate = $now->toDateString();
                break;
            case 'tomorrow':
                $this->blockStartDate = $now->copy()->addDay()->toDateString();
                $this->blockEndDate = $now->copy()->addDay()->toDateString();
                break;
            case 'weekend':
                $this->blockStartDate = $now->copy()->next(Carbon::SATURDAY)->toDateString();
                $this->blockEndDate = $now->copy()->next(Carbon::SUNDAY)->toDateString();
                break;
            case 'next_week':
                $this->blockStartDate = $now->copy()->startOfWeek(Carbon::MONDAY)->addWeek()->toDateString();
                $this->blockEndDate = $now->copy()->endOfWeek(Carbon::SUNDAY)->addWeek()->toDateString();
                break;
        }
    }

    public function saveBlackoutBlock(): void
    {
        $this->validate([
            'blockStartDate' => 'required|date',
            'blockEndDate' => 'required|date|after_or_equal:blockStartDate',
            'blockReason' => 'nullable|string|max:255',
            'blockProductIds' => 'nullable|array',
            'blockProductIds.*' => 'exists:products,id',
        ]);

        if (! $this->currentOperator) {
            return;
        }

        $targetProducts = ! empty($this->blockProductIds)
            ? $this->blockProductIds
            : [null]; // null = all products for this operator

        foreach ($targetProducts as $pId) {
            AvailabilityBlock::create([
                'operator_id' => $this->currentOperator->id,
                'product_id' => $pId,
                'date_start' => $this->blockStartDate,
                'date_end' => $this->blockEndDate,
                'reason' => $this->blockReason ?: 'Operator Manual Blackout',
                'created_by' => auth()->id(),
            ]);
        }

        $this->dispatch('close-modal', 'block-inventory-dates');
        $this->blockModalOpen = false;
        session()->flash('success', __('Blackout block saved successfully.'));
    }

    public function deleteBlackoutBlock(string $blockId): void
    {
        if (! $this->currentOperator) {
            return;
        }

        $block = $this->currentOperator->availabilityBlocks()->where('id', $blockId)->first();
        if ($block) {
            $block->delete();
            session()->flash('success', __('Blackout block removed.'));
        }
    }

    /**
     * Get all calendar grid days for the active month view.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function calendarDays(): array
    {
        $startOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endOfMonth = (clone $startOfMonth)->endOfMonth();

        $startGrid = (clone $startOfMonth)->startOfWeek(Carbon::SUNDAY);
        $endGrid = (clone $endOfMonth)->endOfWeek(Carbon::SATURDAY);

        $reservationsByDate = [];
        $blocks = new Collection();

        if ($this->currentOperator) {
            $reservations = $this->currentOperator->reservations()
                ->whereBetween('requested_date', [$startGrid->format('Y-m-d 00:00:00'), $endGrid->format('Y-m-d 23:59:59')])
                ->whereIn('status', [
                    ReservationStatus::Confirmed->value,
                    ReservationStatus::PendingConfirmation->value,
                    ReservationStatus::PaymentPending->value,
                    ReservationStatus::Completed->value,
                ])
                ->with(['bookable'])
                ->get();

            foreach ($reservations as $res) {
                $dateKey = $res->requested_date->toDateString();
                $reservationsByDate[$dateKey][] = $res;
            }

            $blocks = $this->currentOperator->availabilityBlocks()
                ->where('date_start', '<=', $endGrid->toDateString())
                ->where('date_end', '>=', $startGrid->toDateString())
                ->with('product')
                ->get();
        }

        $days = [];
        $current = clone $startGrid;

        while ($current->lte($endGrid)) {
            $dateString = $current->toDateString();
            $isCurrentMonth = $current->month === $this->month;
            $isToday = $current->isToday();
            $dayReservations = $reservationsByDate[$dateString] ?? [];

            // Check if day has any blackout block
            $dayBlocks = $blocks->filter(function ($b) use ($dateString) {
                return $b->date_start <= $dateString && $b->date_end >= $dateString;
            });

            $totalPax = 0;
            foreach ($dayReservations as $r) {
                $totalPax += $r->pax_count;
            }

            $days[] = [
                'date' => $dateString,
                'dayNumber' => $current->day,
                'isCurrentMonth' => $isCurrentMonth,
                'isToday' => $isToday,
                'reservationsCount' => count($dayReservations),
                'totalPax' => $totalPax,
                'hasBlock' => $dayBlocks->isNotEmpty(),
                'blockReason' => $dayBlocks->first()?->reason,
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Reservations on the currently selected day for the slide-over drawer.
     *
     * @return Collection<int, \App\Models\Reservation>
     */
    #[Computed]
    public function selectedReservations(): Collection
    {
        if (! $this->selectedDate || ! $this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->reservations()
            ->whereDate('requested_date', $this->selectedDate)
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::PendingConfirmation->value,
                ReservationStatus::PaymentPending->value,
                ReservationStatus::Completed->value,
            ])
            ->with(['bookable'])
            ->latest()
            ->get();
    }

    /**
     * Availability blocks on the currently selected day for the slide-over drawer.
     *
     * @return Collection<int, \App\Models\AvailabilityBlock>
     */
    #[Computed]
    public function selectedDayBlocks(): Collection
    {
        if (! $this->selectedDate || ! $this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->availabilityBlocks()
            ->where('date_start', '<=', $this->selectedDate)
            ->where('date_end', '>=', $this->selectedDate)
            ->with('product')
            ->get();
    }

    /**
     * Operator products available for blackout blocks.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function operatorProducts(): Collection
    {
        if (! $this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->products()->get();
    }
};
?>

<div class="space-y-6">
    <!-- Flash Notifications -->
    @if (session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2 animate-fade-in shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 text-sm"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Month Navigation & Controls Bar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
        <div class="flex items-center gap-3">
            <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                {{ \Illuminate\Support\Carbon::createFromDate($year, $month, 1)->format('F Y') }}
            </h2>
            <button
                type="button"
                wire:click="currentMonth"
                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
            >
                {{ __('Today') }}
            </button>
        </div>

        <div class="flex items-center gap-2 self-stretch sm:self-auto justify-between sm:justify-end">
            <!-- Prev / Next Month Buttons -->
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-zinc-800 p-1 rounded-xl">
                <button
                    type="button"
                    wire:click="prevMonth"
                    class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                    title="{{ __('Previous Month') }}"
                >
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button
                    type="button"
                    wire:click="nextMonth"
                    class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                    title="{{ __('Next Month') }}"
                >
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>

            <!-- Block Dates Button -->
            <button
                type="button"
                wire:click="openBlockModal"
                class="h-9 px-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-zinc-100 dark:hover:bg-white text-white dark:text-zinc-900 font-bold text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer shrink-0"
            >
                <i class="fa-solid fa-ban text-rose-400 dark:text-rose-600"></i>
                <span>{{ __('Block Dates') }}</span>
            </button>
        </div>
    </div>

    <!-- Calendar 7x5 Grid Matrix -->
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

        <!-- Days Grid -->
        <div class="grid grid-cols-7 divide-x divide-y divide-slate-100 dark:divide-zinc-800/60 text-xs">
            @foreach ($this->calendarDays as $cell)
                @php
                    $isSelected = $selectedDate === $cell['date'];
                @endphp
                <div
                    wire:click="selectDate('{{ $cell['date'] }}')"
                    class="min-h-[90px] sm:min-h-[110px] p-2 sm:p-2.5 transition-all cursor-pointer relative flex flex-col justify-between group
                        {{ ! $cell['isCurrentMonth'] ? 'bg-slate-50/40 dark:bg-zinc-950/40 text-slate-300 dark:text-zinc-600' : 'bg-white dark:bg-zinc-900 text-slate-800 dark:text-zinc-200 hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20' }}
                        {{ $cell['isToday'] ? 'ring-2 ring-indigo-500/40 dark:ring-indigo-400/40 bg-indigo-50/20 dark:bg-indigo-950/10' : '' }}
                        {{ $isSelected ? 'bg-indigo-50/80 dark:bg-indigo-950/40 ring-2 ring-indigo-600 dark:ring-indigo-400 z-10' : '' }}"
                >
                    <!-- Day Header & Indicators -->
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-xs sm:text-sm {{ $cell['isToday'] ? 'h-6 w-6 rounded-full bg-indigo-600 text-white flex items-center justify-center font-black shadow-xs' : ($cell['isCurrentMonth'] ? 'text-slate-700 dark:text-zinc-300' : 'text-slate-400 dark:text-zinc-600') }}">
                            {{ $cell['dayNumber'] }}
                        </span>

                        @if ($cell['hasBlock'])
                            <span class="p-1 rounded-md bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 text-[10px]" title="{{ $cell['blockReason'] ?? __('Blocked Date') }}">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                        @endif
                    </div>

                    <!-- Day Content / Booking Pills -->
                    <div class="space-y-1 my-1">
                        @if ($cell['reservationsCount'] > 0)
                            <div class="px-2 py-1 rounded-lg bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 font-bold text-[10px] sm:text-[11px] truncate flex items-center justify-between gap-1 shadow-2xs">
                                <span class="truncate">
                                    <i class="fa-solid fa-calendar-check mr-1 text-indigo-600 dark:text-indigo-400"></i>
                                    {{ $cell['reservationsCount'] }} {{ $cell['reservationsCount'] === 1 ? __('Trip') : __('Trips') }}
                                </span>
                                <span class="font-extrabold text-[10px] bg-white/70 dark:bg-black/40 px-1 rounded">
                                    {{ $cell['totalPax'] }}p
                                </span>
                            </div>
                        @elseif ($cell['hasBlock'])
                            <div class="px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 text-[10px] font-semibold truncate border border-rose-200/50 dark:border-rose-800/40">
                                {{ __('Blackout') }}
                            </div>
                        @endif
                    </div>

                    <!-- Subtle Footer / Hover Hint -->
                    <div class="text-[9px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity text-right">
                        {{ __('View') }} &rarr;
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Slide-over Drawer: Day Details & Guest Itinerary -->
    @if ($drawerOpen && $selectedDate)
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-sm space-y-6 animate-fade-in">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 dark:border-zinc-800 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ __('Selected Date') }}</span>
                        <span class="font-mono text-xs text-slate-400">&bull;</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                            {{ count($this->selectedReservations) }} {{ count($this->selectedReservations) === 1 ? __('Reservation') : __('Reservations') }}
                        </span>
                    </div>
                    <h3 class="text-xl font-extrabold text-slate-900 dark:text-white mt-0.5">
                        {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('l, F d, Y') }}
                    </h3>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="openBlockModal('{{ $selectedDate }}')"
                        class="h-8 px-3 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <i class="fa-solid fa-lock text-[10px]"></i>
                        <span>{{ __('Blackout Date') }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="closeDrawer"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Active Blackout Blocks on this Date -->
            @if ($this->selectedDayBlocks->isNotEmpty())
                <div class="space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-ban"></i>
                        <span>{{ __('Active Inventory Blackout on this Day') }}</span>
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($this->selectedDayBlocks as $b)
                            <div class="p-3.5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-900/50 flex items-center justify-between gap-3 text-xs">
                                <div>
                                    <p class="font-bold text-rose-900 dark:text-rose-200">
                                        {{ $b->product ? $b->product->name : __('All Products / Whole Operator') }}
                                    </p>
                                    <p class="text-[11px] text-rose-700 dark:text-rose-400">
                                        {{ $b->reason ?: __('Manual blackout') }} ({{ $b->date_start }} - {{ $b->date_end }})
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    wire:click="deleteBlackoutBlock('{{ $b->id }}')"
                                    class="h-7 px-2.5 rounded-lg bg-white dark:bg-zinc-800 text-rose-600 hover:bg-rose-100 dark:hover:bg-rose-900/60 font-bold text-[11px] transition shadow-2xs cursor-pointer shrink-0"
                                >
                                    {{ __('Remove') }}
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Reservations List on this Date -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-list-check"></i>
                    <span>{{ __('Scheduled Guest Departures') }}</span>
                </h4>

                @if ($this->selectedReservations->isEmpty())
                    <div class="p-8 text-center rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-100 dark:border-zinc-800 space-y-2">
                        <div class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-zinc-800 text-slate-400 flex items-center justify-center mx-auto text-sm">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                            {{ __('No reservations scheduled for this date yet.') }}
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($this->selectedReservations as $res)
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/70 dark:border-zinc-800 space-y-3 flex flex-col justify-between">
                                <div class="space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <span class="font-mono text-[10px] font-bold text-slate-400 block">
                                                #{{ $res->code }}
                                            </span>
                                            <h5 class="font-bold text-sm text-slate-900 dark:text-white leading-tight">
                                                {{ $res->guest_name }}
                                            </h5>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase
                                            {{ $res->status === \App\Enums\ReservationStatus::Confirmed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                            {{ $res->status->label() }}
                                        </span>
                                    </div>

                                    <div class="text-xs space-y-1 text-slate-600 dark:text-slate-300">
                                        <p class="font-semibold text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                            <i class="fa-solid fa-cube text-[10px]"></i>
                                            <span>{{ $res->bookable?->name ?? __('Package') }}</span>
                                        </p>
                                        <div class="flex items-center gap-3 text-[11px] text-slate-500 dark:text-slate-400">
                                            <span><i class="fa-solid fa-users mr-1"></i>{{ $res->pax_count }} Pax</span>
                                            <span>&bull;</span>
                                            <span><i class="fa-solid fa-phone mr-1"></i>{{ $res->guest_contact ?: '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-200/60 dark:border-zinc-700/60 flex items-center justify-between">
                                    <span class="font-bold text-xs text-slate-900 dark:text-white">
                                        Rp {{ number_format((float) ($res->terms_snapshot['price'] ?? 0), 0, ',', '.') }}
                                    </span>
                                    <a
                                        href="{{ route('reservations.index') }}"
                                        wire:navigate
                                        class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                                    >
                                        <span>{{ __('Details') }}</span>
                                        <i class="fa-solid fa-arrow-right text-[9px]"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Blackout Dates Modal -->
    <x-modal name="block-inventory-dates" maxWidth="lg">
        <form wire:submit="saveBlackoutBlock" class="p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="p-3 rounded-2xl bg-rose-50 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 text-lg">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </span>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ __('Block Inventory / Blackout Dates') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Prevent online guest bookings and checkout during dry dock, maintenance, or holidays.') }}
                    </p>
                </div>
            </div>

            <!-- Quick Presets -->
            <div class="space-y-1.5">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{{ __('Quick Range Presets:') }}</span>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" wire:click="setQuickBlock('today')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                        {{ __('Today') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('tomorrow')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                        {{ __('Tomorrow') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('weekend')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                        {{ __('This Weekend') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('next_week')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                        {{ __('Next Week') }}
                    </button>
                </div>
            </div>

            <!-- Date Range Inputs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-label for="blockStartDate" :value="__('Start Date')" required />
                    <x-input id="blockStartDate" type="date" wire:model="blockStartDate" required />
                    <x-input-error :messages="$errors->get('blockStartDate')" />
                </div>
                <div>
                    <x-label for="blockEndDate" :value="__('End Date')" required />
                    <x-input id="blockEndDate" type="date" wire:model="blockEndDate" required />
                    <x-input-error :messages="$errors->get('blockEndDate')" />
                </div>
            </div>

            <!-- Reason Input -->
            <div>
                <x-label for="blockReason" :value="__('Blackout Reason (Internal / Customer Note)')" />
                <x-input id="blockReason" type="text" wire:model="blockReason" placeholder="{{ __('e.g. Scheduled Engine Service / Harbor Holiday') }}" />
                <x-input-error :messages="$errors->get('blockReason')" />
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'block-inventory-dates')">
                    {{ __('Cancel') }}
                </x-button>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center font-semibold rounded-xl transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer gap-2 select-none whitespace-nowrap shrink-0 h-9 px-3 text-xs bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 shadow-xs font-semibold text-xs shadow-xs"
                >
                    <i class="fa-solid fa-lock mr-1.5 text-xs"></i>
                    {{ __('Confirm Blackout Block') }}
                </button>
            </div>
        </form>
    </x-modal>
</div>
