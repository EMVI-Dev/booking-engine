<?php

use App\Enums\ReservationStatus;
use App\Models\AvailabilityBlock;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Booking Calendar & Availability')] class extends Component {
    public int $year;
    public int $month;
    public ?string $selectedDate = null;

    // Block Date Modal State
    public bool $showBlockModal = false;
    public ?string $block_product_id = null; // null = all products
    public string $block_date_start = '';
    public string $block_date_end = '';
    public string $block_reason = '';

    public bool $actionSuccess = false;
    public string $actionMessage = '';

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
        $this->selectedDate = now()->toDateString();
        $this->block_date_start = now()->toDateString();
        $this->block_date_end = now()->toDateString();
    }

    #[Computed]
    public function currentOperator(): ?Operator
    {
        return Auth::user()?->currentOperator();
    }

    #[Computed]
    public function currentAgent(): ?Operator
    {
        return $this->currentOperator;
    }

    #[Computed]
    public function currentMonthLabel(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->format('F Y');
    }

    public function prevMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->year = (int) $date->year;
        $this->month = (int) $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->year = (int) $date->year;
        $this->month = (int) $date->month;
    }

    public function goToToday(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
        $this->selectedDate = now()->toDateString();
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
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

        if ($this->currentAgent) {
            $reservations = $this->currentAgent->reservations()
                ->whereBetween('requested_date', [$startGrid->toDateString(), $endGrid->toDateString()])
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

            $blocks = $this->currentAgent->availabilityBlocks()
                ->where('date_start', '<=', $endGrid->toDateString())
                ->where('date_end', '>=', $startGrid->toDateString())
                ->with('product')
                ->get();
        }

        $days = [];
        $current = clone $startGrid;

        while ($current->lte($endGrid)) {
            $dateStr = $current->toDateString();
            $dayReservations = $reservationsByDate[$dateStr] ?? [];
            $totalPax = array_sum(array_map(fn ($r) => (int) $r->pax_count, $dayReservations));

            $dayBlocks = $blocks->filter(function (AvailabilityBlock $b) use ($current) {
                return $current->gte($b->date_start) && $current->lte($b->date_end);
            });

            $days[] = [
                'date' => $dateStr,
                'dayNumber' => $current->day,
                'isCurrentMonth' => $current->month === $this->month,
                'isToday' => $current->isToday(),
                'isSelected' => $this->selectedDate === $dateStr,
                'reservations' => $dayReservations,
                'reservationCount' => count($dayReservations),
                'totalPax' => $totalPax,
                'isBlocked' => $dayBlocks->isNotEmpty(),
                'blocks' => $dayBlocks,
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Get reservations for the currently selected date.
     *
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function selectedDayReservations(): Collection
    {
        if (! $this->selectedDate || ! $this->currentAgent) {
            return new Collection();
        }

        return $this->currentAgent->reservations()
            ->whereDate('requested_date', $this->selectedDate)
            ->with(['bookable', 'latestPayment'])
            ->get();
    }

    /**
     * Get all active blocks for this agent.
     *
     * @return Collection<int, AvailabilityBlock>
     */
    #[Computed]
    public function activeBlocks(): Collection
    {
        if (! $this->currentAgent) {
            return new Collection();
        }

        return $this->currentAgent->availabilityBlocks()
            ->with('product')
            ->latest('date_start')
            ->get();
    }

    #[Computed]
    public function productSelectOptions(): array
    {
        $options = [
            '' => __('All Products & Listings (Entire Storefront)'),
        ];

        if ($this->currentAgent) {
            foreach ($this->currentAgent->products as $product) {
                $options[$product->id] = $product->name;
            }
        }

        return $options;
    }

    public function setQuickDates(string $preset): void
    {
        $today = now();

        match ($preset) {
            'today' => [
                $this->block_date_start = $today->toDateString(),
                $this->block_date_end = $today->toDateString(),
            ],
            'tomorrow' => [
                $this->block_date_start = $today->copy()->addDay()->toDateString(),
                $this->block_date_end = $today->copy()->addDay()->toDateString(),
            ],
            'weekend' => [
                $this->block_date_start = $today->copy()->next(Carbon::SATURDAY)->toDateString(),
                $this->block_date_end = $today->copy()->next(Carbon::SUNDAY)->toDateString(),
            ],
            'next_7_days' => [
                $this->block_date_start = $today->toDateString(),
                $this->block_date_end = $today->copy()->addDays(6)->toDateString(),
            ],
            default => null,
        };
    }

    /**
     * Save a new availability blackout block.
     */
    public function saveBlock(): void
    {
        if (! $this->currentOperator) {
            return;
        }

        $this->validate([
            'block_date_start' => ['required', 'date'],
            'block_date_end' => ['required', 'date', 'after_or_equal:block_date_start'],
            'block_product_id' => ['nullable', 'string', 'exists:products,id'],
            'block_reason' => ['nullable', 'string', 'max:255'],
        ]);

        AvailabilityBlock::query()->create([
            'operator_id' => $this->currentOperator->id,
            'product_id' => $this->block_product_id ?: null,
            'date_start' => $this->block_date_start,
            'date_end' => $this->block_date_end,
            'reason' => $this->block_reason ?: __('Manual Date Block'),
        ]);

        $this->showBlockModal = false;
        $this->dispatch('close-modal', 'block-inventory-dates');
        $this->block_reason = '';
        $this->actionSuccess = true;
        $this->actionMessage = __('Date blackout block created successfully.');
    }

    /**
     * Delete an availability blackout block.
     */
    public function deleteBlock(string $blockId): void
    {
        if (! $this->currentAgent) {
            return;
        }

        $block = $this->currentAgent->availabilityBlocks()->find($blockId);

        if ($block) {
            $block->delete();
            $this->actionSuccess = true;
            $this->actionMessage = __('Date block removed successfully.');
        }
    }
}; ?>

<div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400">
                    <i class="fa-solid fa-calendar-days text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Booking Calendar & Availability') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Visualize daily reservation schedules, inspect booked slots, and block dates for boat maintenance or holidays.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Header Actions -->
        <div class="flex flex-wrap items-center gap-2.5">
            <x-button
                type="button"
                variant="secondary"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'google-calendar-sync')"
                class="h-10 text-xs font-bold"
            >
                <i class="fa-brands fa-google mr-1.5 text-indigo-500"></i>
                {{ __('Sync Calendar') }}
            </x-button>

            <x-button
                type="button"
                variant="secondary"
                wire:click="goToToday"
                class="h-10 text-xs font-bold"
            >
                <i class="fa-solid fa-calendar-day mr-1.5 text-indigo-500"></i>
                {{ __('Today') }}
            </x-button>

            <x-button
                type="button"
                variant="primary"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'block-inventory-dates')"
                class="h-10 text-xs font-bold shadow-sm"
            >
                <i class="fa-solid fa-ban mr-1.5 text-xs"></i>
                {{ __('Block Dates') }}
            </x-button>
        </div>
    </div>

    <!-- Calendar Month Navigation & Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main 2-Col Calendar Card -->
        <div class="lg:col-span-2 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-5 sm:p-6 space-y-5">
            <!-- Month Header Selector -->
            <div class="flex items-center justify-between">
                <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>{{ $this->currentMonthLabel }}</span>
                </h2>

                <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-zinc-800 p-1 rounded-xl">
                    <button
                        type="button"
                        wire:click="prevMonth"
                        class="p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-zinc-700 transition cursor-pointer"
                        title="{{ __('Previous Month') }}"
                    >
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <button
                        type="button"
                        wire:click="nextMonth"
                        class="p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-zinc-700 transition cursor-pointer"
                        title="{{ __('Next Month') }}"
                    >
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Calendar Days Header (Sun - Sat) -->
            <div class="grid grid-cols-7 text-center text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span>Sun</span>
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
            </div>

            <!-- Calendar Days Grid -->
            <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
                @foreach ($this->calendarDays as $day)
                    @php
                        $isSelected = $day['isSelected'];
                        $isToday = $day['isToday'];
                        $hasReservations = $day['reservationCount'] > 0;
                        $isBlocked = $day['isBlocked'];
                        $isCurrentMonth = $day['isCurrentMonth'];
                    @endphp
                    <button
                        type="button"
                        wire:click="selectDate('{{ $day['date'] }}')"
                        class="min-h-[72px] sm:min-h-[88px] p-2 rounded-2xl border text-left flex flex-col justify-between transition-all duration-150 cursor-pointer relative group {{
                            $isSelected
                                ? 'bg-indigo-50/90 dark:bg-indigo-950/60 border-indigo-600 dark:border-indigo-500 ring-2 ring-indigo-500/20 shadow-xs'
                                : ($isBlocked
                                    ? 'bg-rose-50/40 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/60 text-slate-400'
                                    : ($isToday
                                        ? 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-300 dark:border-amber-700/60'
                                        : ($isCurrentMonth
                                            ? 'bg-white dark:bg-zinc-900 border-slate-200/80 dark:border-zinc-800 hover:border-indigo-300 dark:hover:border-zinc-700'
                                            : 'bg-slate-50/40 dark:bg-zinc-900/40 border-transparent opacity-40 hover:opacity-100')))
                        }}"
                    >
                        <!-- Day Number -->
                        <div class="flex items-center justify-between w-full">
                            <span class="text-xs font-bold {{ $isToday ? 'w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center' : ($isCurrentMonth ? 'text-slate-800 dark:text-slate-200' : 'text-slate-400') }}">
                                {{ $day['dayNumber'] }}
                            </span>

                            @if ($isBlocked)
                                <span class="text-[10px] text-rose-500" title="{{ __('Blocked date') }}">
                                    <i class="fa-solid fa-ban"></i>
                                </span>
                            @endif
                        </div>

                        <!-- Reservation Indicators / Badges -->
                        <div class="space-y-1 w-full pt-1">
                            @if ($hasReservations)
                                <div class="px-1.5 py-0.5 rounded-md bg-indigo-600 text-white text-[10px] font-extrabold flex items-center justify-between truncate shadow-xs">
                                    <span>{{ $day['reservationCount'] }} {{ $day['reservationCount'] === 1 ? 'Trip' : 'Trips' }}</span>
                                    <span class="opacity-90">{{ $day['totalPax'] }}p</span>
                                </div>
                            @elseif ($isBlocked)
                                <span class="block px-1 rounded text-[9px] font-bold uppercase tracking-tight text-rose-600 dark:text-rose-400 truncate">
                                    {{ __('Blocked') }}
                                </span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Right Side: Selected Day Inspector & Active Blocks -->
        <div class="space-y-6">
            <!-- Selected Day Card -->
            <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            {{ __('Schedule Details') }}
                        </span>
                        <h3 class="font-black text-base text-slate-900 dark:text-white">
                            {{ $selectedDate ? Carbon::parse($selectedDate)->format('l, M d, Y') : __('Select a date') }}
                        </h3>
                    </div>

                    @if ($selectedDate && Carbon::parse($selectedDate)->isToday())
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            {{ __('Today') }}
                        </span>
                    @endif
                </div>

                <!-- Bookings on this date -->
                <div class="space-y-3">
                    @forelse ($this->selectedDayReservations as $res)
                        @php
                            $bookable = $res->bookable;
                            $latestPayment = $res->latestPayment;
                            $resCode = $res->code ?? ('RSV-' . strtoupper(substr($res->id, -8)));
                        @endphp
                        <div class="p-3.5 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/50 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 px-1.5 py-0.5 rounded">
                                        #{{ $resCode }}
                                    </span>
                                    <span class="font-bold text-xs text-slate-900 dark:text-white truncate">
                                        {{ $res->guest_name }}
                                    </span>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 {{ $res->status === ReservationStatus::Confirmed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                    {{ $res->status->label() }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                {{ $bookable->name ?? ($bookable->title ?? 'Direct Booking') }} &bull; {{ $res->pax_count }} Pax
                            </p>
                            @if ($latestPayment && $latestPayment->isPaid())
                                <p class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                    Rp {{ number_format((float) $latestPayment->amount, 0, ',', '.') }} (Paid)
                                </p>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-6 space-y-2 text-slate-400">
                            <i class="fa-solid fa-calendar-check text-2xl text-slate-300 dark:text-zinc-700"></i>
                            <p class="text-xs">{{ __('No reservations scheduled on this date.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Active Date Blocks Management -->
            <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-zinc-800">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-ban text-rose-500 text-xs"></i>
                        {{ __('Blackout Dates & Blocks') }}
                    </h3>
                    <span class="text-[11px] text-slate-400 font-bold">{{ $this->activeBlocks->count() }}</span>
                </div>

                <div class="space-y-2.5 max-h-60 overflow-y-auto">
                    @forelse ($this->activeBlocks as $block)
                        <div class="p-3 rounded-xl border border-rose-100 dark:border-rose-950/60 bg-rose-50/30 dark:bg-rose-950/20 flex items-center justify-between text-xs">
                            <div class="space-y-0.5">
                                <p class="font-bold text-slate-800 dark:text-slate-200">
                                    {{ $block->date_start->format('M d') }} &rarr; {{ $block->date_end->format('M d, Y') }}
                                </p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $block->reason ?? 'Manual Block' }} &bull; {{ $block->product ? $block->product->name : __('All Products') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                wire:click="deleteBlock('{{ $block->id }}')"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 transition cursor-pointer"
                                title="{{ __('Remove Block') }}"
                            >
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </div>
                    @empty
                        <p class="text-center text-xs text-slate-400 py-4">{{ __('No blackout date blocks active.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- System UI Aligned Block Date Modal -->
    <x-modal name="block-inventory-dates" maxWidth="md">
        <div class="p-6 space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-white">{{ __('Block Inventory Dates') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Blackout dates for maintenance, holidays or bad weather.') }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', 'block-inventory-dates')"
                    class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition cursor-pointer"
                >
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form wire:submit="saveBlock" class="space-y-4">
                <!-- Scope Product with System x-select -->
                <div class="space-y-1.5">
                    <x-label for="block_product_id" :value="__('Applies To')" />
                    <x-select
                        id="block_product_id"
                        wire:model="block_product_id"
                        :options="$this->productSelectOptions"
                        :placeholder="__('All Products & Listings (Entire Storefront)')"
                    />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ __('Select a specific item or leave as entire storefront to block all bookings.') }}
                    </p>
                </div>

                <!-- Date Range Pickers & Quick Presets -->
                <div class="space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <x-label for="block_date_start" :value="__('Start Date')" required />
                            <x-date-picker
                                id="block_date_start"
                                wire:model="block_date_start"
                                :presets="false"
                                :placeholder="__('Select start date...')"
                                :error="$errors->has('block_date_start')"
                            />
                            <x-input-error :messages="$errors->get('block_date_start')" />
                        </div>

                        <div class="space-y-1.5">
                            <x-label for="block_date_end" :value="__('End Date')" required />
                            <x-date-picker
                                id="block_date_end"
                                wire:model="block_date_end"
                                :presets="false"
                                :placeholder="__('Select end date...')"
                                :error="$errors->has('block_date_end')"
                            />
                            <x-input-error :messages="$errors->get('block_date_end')" />
                        </div>
                    </div>

                    <!-- Quick Presets -->
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mr-1">{{ __('Presets:') }}</span>
                        <button
                            type="button"
                            wire:click="setQuickDates('today')"
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/70 dark:hover:text-rose-300 transition cursor-pointer"
                        >
                            {{ __('Today') }}
                        </button>
                        <button
                            type="button"
                            wire:click="setQuickDates('tomorrow')"
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/70 dark:hover:text-rose-300 transition cursor-pointer"
                        >
                            {{ __('Tomorrow') }}
                        </button>
                        <button
                            type="button"
                            wire:click="setQuickDates('weekend')"
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/70 dark:hover:text-rose-300 transition cursor-pointer"
                        >
                            {{ __('This Weekend') }}
                        </button>
                        <button
                            type="button"
                            wire:click="setQuickDates('next_7_days')"
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/70 dark:hover:text-rose-300 transition cursor-pointer"
                        >
                            {{ __('Next 7 Days') }}
                        </button>
                    </div>
                </div>

                <!-- Reason -->
                <div class="space-y-1.5">
                    <x-label for="block_reason" :value="__('Reason / Internal Note')" />
                    <x-input
                        id="block_reason"
                        wire:model="block_reason"
                        type="text"
                        placeholder="{{ __('e.g., Scheduled boat maintenance, extreme weather...') }}"
                        :error="$errors->has('block_reason')"
                    />
                    <x-input-error :messages="$errors->get('block_reason')" />
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <x-button
                        type="button"
                        variant="secondary"
                        size="sm"
                        x-on:click="$dispatch('close-modal', 'block-inventory-dates')"
                        class="font-semibold text-xs"
                    >
                        {{ __('Cancel') }}
                    </x-button>

                    <x-button
                        type="submit"
                        variant="danger"
                        size="sm"
                        class="font-semibold text-xs shadow-xs"
                    >
                        <i class="fa-solid fa-lock mr-1.5 text-xs"></i>
                        {{ __('Confirm Blackout Block') }}
                    </x-button>
                </div>
            </form>
        </div>
    </x-modal>

    <!-- Google / Apple Calendar Live Sync Modal -->
    <x-modal name="google-calendar-sync" :show="false" maxWidth="lg">
        @if (!$this->currentAgent?->hasFeature('google_calendar'))
            <div class="p-6">
                <x-feature-gate
                    :title="__('Google & Apple Calendar Sync')"
                    :description="__('Subscribe your personal Google Calendar, Apple Calendar, or Outlook to all incoming confirmed reservations via a live private iCal feed.')"
                    required-plan="Pro Operator"
                    plan-slug="growth"
                    icon="fa-brands fa-google"
                    :features="[
                        __('Real-time calendar updates when new bookings are paid'),
                        __('Automatic trip details, guest headcount, and contact links in event descriptions'),
                        __('Compatible with Google Calendar, iOS / macOS Calendar, and Microsoft Outlook'),
                    ]"
                />
            </div>
        @else
            @php
                $feedUrl = $this->currentAgent?->getCalendarFeedUrl() ?? '';
            @endphp
            <div class="p-6 space-y-5" x-data="{ copied: false }">
                <div class="flex items-center gap-3">
                    <span class="p-3 rounded-2xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-lg">
                        <i class="fa-brands fa-google"></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                            {{ __('Sync with Google & Apple Calendar') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Automatically display incoming reservations in your personal calendar in real-time.') }}
                        </p>
                    </div>
                </div>

                <!-- Live Feed URL Box -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-800 space-y-2">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">
                        {{ __('Your Private Live Calendar Feed URL (iCal / .ics)') }}
                    </span>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="{{ $feedUrl }}" id="calendarFeedUrlInput"
                            class="h-9 w-full px-3 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs text-slate-700 dark:text-slate-300 select-all" />
                        <button type="button"
                            @click="navigator.clipboard.writeText('{{ $feedUrl }}'); copied = true; setTimeout(() => copied = false, 2500)"
                            class="h-9 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs shadow-2xs transition shrink-0 flex items-center gap-1.5 cursor-pointer">
                            <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                            <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                        </button>
                    </div>
                </div>

                <!-- How to Add to Google Calendar Steps -->
                <div class="space-y-3 text-xs">
                    <h4 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-indigo-500"></i>
                        {{ __('How to subscribe in Google Calendar:') }}
                    </h4>
                    <ol class="list-decimal list-inside space-y-1.5 text-slate-600 dark:text-slate-300 pl-1">
                        <li>{{ __('Open Google Calendar on your browser.') }}</li>
                        <li>{{ __('On the left sidebar, click the "+" icon next to "Other calendars".') }}</li>
                        <li>{{ __('Select "From URL".') }}</li>
                        <li>{{ __('Paste the feed URL copied above and click "Add calendar".') }}</li>
                    </ol>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <a href="https://calendar.google.com/calendar/r/settings/addbyurl" target="_blank"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                        <span>{{ __('Open Google Calendar Settings') }}</span>
                    </a>

                    <x-button
                        type="button"
                        variant="secondary"
                        size="sm"
                        x-on:click="$dispatch('close-modal', 'google-calendar-sync')"
                        class="font-semibold text-xs"
                    >
                        {{ __('Done') }}
                    </x-button>
                </div>
            </div>
        @endif
    </x-modal>
</div>
