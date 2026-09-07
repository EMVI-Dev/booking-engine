<?php

use App\Enums\ReservationStatus;
use App\Models\AvailabilityBlock;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public int $month;
    public int $year;

    public string $selectedDate = '';
    public string $filterExperience = 'all'; // 'all', 'package:ID', 'product:ID'

    // Date blackout modal state
    public bool $blockModalOpen = false;
    public string $blockTargetType = 'all'; // 'all', 'package', 'product'
    public array $blockPackageIds = [];
    public array $blockProductIds = [];
    public string $blockStartDate = '';
    public string $blockEndDate = '';
    public string $blockReason = '';

    // Blackout deletion confirmation modal state
    public ?string $deletingBlockId = null;
    public ?string $deletingBlockLabel = null;
    public ?string $deletingBlockDates = null;

    public function mount(): void
    {
        $now = now();
        $this->month = (int) $now->format('n');
        $this->year = (int) $now->format('Y');
        $this->selectedDate = $now->toDateString();
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
        $this->selectedDate = Carbon::createFromDate($this->year, $this->month, 1)->toDateString();
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->month = (int) $date->format('n');
        $this->year = (int) $date->format('Y');
        $this->selectedDate = Carbon::createFromDate($this->year, $this->month, 1)->toDateString();
    }

    public function currentMonth(): void
    {
        $now = now();
        $this->month = (int) $now->format('n');
        $this->year = (int) $now->format('Y');
        $this->selectedDate = $now->toDateString();
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function openBlockModal(?string $defaultDate = null): void
    {
        $date = $defaultDate ?: ($this->selectedDate ?: now()->toDateString());
        $this->blockStartDate = $date;
        $this->blockEndDate = $date;
        $this->blockReason = '';
        $this->blockTargetType = 'all';
        $this->blockPackageIds = [];
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
        $this->validate(
            [
                'blockStartDate' => 'required|date',
                'blockEndDate' => 'required|date|after_or_equal:blockStartDate',
                'blockReason' => 'nullable|string|max:255',
                'blockTargetType' => 'required|in:all,package,product',
                'blockPackageIds' => 'required_if:blockTargetType,package|array',
                'blockPackageIds.*' => 'exists:packages,id',
                'blockProductIds' => 'required_if:blockTargetType,product|array',
                'blockProductIds.*' => 'exists:products,id',
            ],
            [
                'blockPackageIds.required_if' => __('Please select at least one tour package to block.'),
                'blockProductIds.required_if' => __('Please select at least one single activity to block.'),
            ],
        );

        if (!$this->currentOperator) {
            return;
        }

        $reason = $this->blockReason ?: 'Operator Manual Blackout';

        if ($this->blockTargetType === 'all') {
            AvailabilityBlock::create([
                'operator_id' => $this->currentOperator->id,
                'product_id' => null,
                'package_id' => null,
                'date_start' => $this->blockStartDate,
                'date_end' => $this->blockEndDate,
                'reason' => $reason,
            ]);
        } elseif ($this->blockTargetType === 'package') {
            foreach ($this->blockPackageIds as $pkgId) {
                AvailabilityBlock::create([
                    'operator_id' => $this->currentOperator->id,
                    'product_id' => null,
                    'package_id' => $pkgId,
                    'date_start' => $this->blockStartDate,
                    'date_end' => $this->blockEndDate,
                    'reason' => $reason,
                ]);
            }
        } elseif ($this->blockTargetType === 'product') {
            foreach ($this->blockProductIds as $pId) {
                AvailabilityBlock::create([
                    'operator_id' => $this->currentOperator->id,
                    'product_id' => $pId,
                    'package_id' => null,
                    'date_start' => $this->blockStartDate,
                    'date_end' => $this->blockEndDate,
                    'reason' => $reason,
                ]);
            }
        }

        $this->dispatch('close-modal', 'block-inventory-dates');
        $this->blockModalOpen = false;
        session()->flash('success', __('Blackout block saved successfully.'));
    }

    public function confirmDeleteBlackoutBlock(string $blockId): void
    {
        if (!$this->currentOperator) {
            return;
        }

        $block = $this->currentOperator->availabilityBlocks()->where('id', $blockId)->first();
        if ($block) {
            $this->deletingBlockId = $block->id;
            $this->deletingBlockLabel = $block->getTargetLabel() . ($block->reason ? ' — ' . $block->reason : '');
            $this->deletingBlockDates = $block->date_start->format('M d, Y') . ' → ' . $block->date_end->format('M d, Y');
            $this->dispatch('open-modal', 'confirm-blackout-removal');
        }
    }

    public function deleteBlackoutBlock(?string $blockId = null): void
    {
        if (!$this->currentOperator) {
            return;
        }

        $targetId = $blockId ?: $this->deletingBlockId;
        if (!$targetId) {
            return;
        }

        $block = $this->currentOperator->availabilityBlocks()->where('id', $targetId)->first();
        if ($block) {
            $block->delete();
            $this->deletingBlockId = null;
            $this->deletingBlockLabel = null;
            $this->deletingBlockDates = null;
            $this->dispatch('close-modal', 'confirm-blackout-removal');
            session()->flash('success', __('Blackout block removed.'));
        }
    }

    /**
     * Array of calendar matrix day metadata for the active month view.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function calendarDays(): array
    {
        $startOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();

        // 7x5 or 7x6 calendar grid boundary calculation (starts Sunday)
        $startGrid = $startOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $endGrid = $endOfMonth->copy()->endOfWeek(Carbon::SATURDAY);

        $reservationsByDate = [];
        $blocks = collect();

        if ($this->currentOperator) {
            $query = $this->currentOperator
                ->reservations()
                ->whereBetween('requested_date', [$startGrid->toDateString(), $endGrid->toDateString()])
                ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::PendingConfirmation->value, ReservationStatus::PaymentPending->value, ReservationStatus::Completed->value]);

            if ($this->filterExperience !== 'all' && str_contains($this->filterExperience, ':')) {
                [$filterType, $filterId] = explode(':', $this->filterExperience, 2);
                $query->where('bookable_type', $filterType)->where('bookable_id', $filterId);
            }

            $reservations = $query->with(['bookable'])->get();

            foreach ($reservations as $res) {
                $d = $res->requested_date?->toDateString();
                if ($d) {
                    $reservationsByDate[$d][] = $res;
                }
            }

            $blocks = $this->currentOperator
                ->availabilityBlocks()
                ->whereDate('date_start', '<=', $endGrid->toDateString())
                ->whereDate('date_end', '>=', $startGrid->toDateString())
                ->with(['product', 'package'])
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
                $start = substr((string) $b->date_start, 0, 10);
                $end = substr((string) $b->date_end, 0, 10);

                return $start <= $dateString && $end >= $dateString;
            });

            $totalPax = 0;
            foreach ($dayReservations as $r) {
                $totalPax += $r->pax_count;
            }

            $isOperatorWide = $dayBlocks->contains(fn($b) => $b->isOperatorWide());
            $blockLabels = $dayBlocks->map(fn($b) => $b->getTargetLabel())->values()->toArray();

            $days[] = [
                'date' => $dateString,
                'dayNumber' => $current->day,
                'isCurrentMonth' => $isCurrentMonth,
                'isToday' => $isToday,
                'reservations' => $dayReservations,
                'reservationsCount' => count($dayReservations),
                'totalPax' => $totalPax,
                'hasBlock' => $dayBlocks->isNotEmpty(),
                'blocksCount' => $dayBlocks->count(),
                'isOperatorWideBlock' => $isOperatorWide,
                'blockLabels' => $blockLabels,
                'blockReason' => $dayBlocks->first()?->reason,
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Reservations on the currently selected day for the inspector panel.
     *
     * @return Collection<int, \App\Models\Reservation>
     */
    #[Computed]
    public function selectedReservations(): Collection
    {
        if (!$this->selectedDate || !$this->currentOperator) {
            return collect();
        }

        $query = $this->currentOperator
            ->reservations()
            ->whereDate('requested_date', $this->selectedDate)
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::PendingConfirmation->value, ReservationStatus::PaymentPending->value, ReservationStatus::Completed->value]);

        if ($this->filterExperience !== 'all' && str_contains($this->filterExperience, ':')) {
            [$filterType, $filterId] = explode(':', $this->filterExperience, 2);
            $query->where('bookable_type', $filterType)->where('bookable_id', $filterId);
        }

        return $query
            ->with(['bookable'])
            ->latest()
            ->get();
    }

    /**
     * Availability blocks on the currently selected day for the inspector panel.
     *
     * @return Collection<int, \App\Models\AvailabilityBlock>
     */
    #[Computed]
    public function selectedDayBlocks(): Collection
    {
        if (!$this->selectedDate || !$this->currentOperator) {
            return collect();
        }

        return $this->currentOperator
            ->availabilityBlocks()
            ->whereDate('date_start', '<=', $this->selectedDate)
            ->whereDate('date_end', '>=', $this->selectedDate)
            ->with(['product', 'package'])
            ->get();
    }

    /**
     * High-level summary metrics for the active month view.
     *
     * @return array{tripsCount: int, totalPax: int, blackoutDaysCount: int}
     */
    #[Computed]
    public function monthSummary(): array
    {
        $days = $this->calendarDays;
        $currentMonthDays = array_filter($days, fn($d) => $d['isCurrentMonth']);

        $trips = 0;
        $pax = 0;
        $blackoutDays = 0;

        foreach ($currentMonthDays as $d) {
            $trips += $d['reservationsCount'];
            $pax += $d['totalPax'];
            if ($d['hasBlock']) {
                $blackoutDays++;
            }
        }

        return [
            'tripsCount' => $trips,
            'totalPax' => $pax,
            'blackoutDaysCount' => $blackoutDays,
        ];
    }

    /**
     * Filter options for experiences dropdown.
     *
     * @return list<array{value: string, label: string, icon: string, hint: string}>
     */
    #[Computed]
    public function experienceFilterOptions(): array
    {
        if (!$this->currentOperator) {
            return [];
        }

        $options = [
            [
                'value' => 'all',
                'label' => __('All Experiences (Catalog)'),
                'icon' => 'fa-solid fa-layer-group',
                'hint' => '',
            ],
        ];

        foreach ($this->currentOperator->packages()->get() as $pkg) {
            $options[] = [
                'value' => "package:{$pkg->id}",
                'label' => $pkg->title,
                'icon' => 'fa-solid fa-cubes',
                'hint' => __('Package'),
            ];
        }

        foreach ($this->currentOperator->products()->get() as $prod) {
            $options[] = [
                'value' => "product:{$prod->id}",
                'label' => $prod->name,
                'icon' => 'fa-solid fa-compass',
                'hint' => __('Activity'),
            ];
        }

        return $options;
    }

    /**
     * Existing blackout dates for the date-picker popovers.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function existingBlackoutDates(): array
    {
        if (!$this->currentOperator) {
            return [];
        }

        $blocks = $this->currentOperator
            ->availabilityBlocks()
            ->whereDate('date_end', '>=', now()->subMonths(2)->toDateString())
            ->with(['product', 'package'])
            ->get();

        $dates = [];
        foreach ($blocks as $b) {
            $startStr = substr((string) $b->date_start, 0, 10);
            $endStr = substr((string) $b->date_end, 0, 10);
            $cur = Carbon::parse($startStr);
            $last = Carbon::parse($endStr);
            $label = $b->getTargetLabel();

            while ($cur->lte($last)) {
                $dates[$cur->toDateString()] = $label . ($b->reason ? ": {$b->reason}" : '');
                $cur = $cur->copy()->addDay();
            }
        }

        return $dates;
    }

    /**
     * Operator packages available for blackout blocks.
     *
     * @return Collection<int, Package>
     */
    #[Computed]
    public function operatorPackages(): Collection
    {
        if (!$this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->packages()->get();
    }

    /**
     * Operator products available for blackout blocks.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function operatorProducts(): Collection
    {
        if (!$this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->products()->get();
    }
};
?>

<div class="space-y-6">
    <!-- Flash Notifications -->
    @if (session('success'))
        <div
            class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2 animate-fade-in shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 text-sm"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Month Navigation & Controls Toolbar -->
    <div
        class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 p-4 sm:p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-wrap items-center gap-3.5">
            <div class="flex items-center gap-2.5">
                <span
                    class="w-10 h-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400 font-black flex items-center justify-center text-base shadow-xs">
                    <i class="fa-solid fa-calendar-days"></i>
                </span>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ \Illuminate\Support\Carbon::createFromDate($year, $month, 1)->format('F Y') }}
                </h2>
            </div>

            <!-- Month Quick Nav Buttons -->
            <div class="flex items-center gap-1 dark:bg-zinc-800 p-1 rounded-2xl dark:border-zinc-700/60">
                <button type="button" wire:click="prevMonth"
                    class="h-8 w-8 rounded-xl flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                    title="{{ __('Previous Month') }}">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" wire:click="currentMonth"
                    class="px-3 h-8 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-zinc-700 transition cursor-pointer shadow-2xs flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    <span>{{ __('Today') }}</span>
                </button>
                <button type="button" wire:click="nextMonth"
                    class="h-8 w-8 rounded-xl flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                    title="{{ __('Next Month') }}">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 self-stretch lg:self-auto justify-between lg:justify-end">
            <!-- Experience Filter Dropdown -->
            <div class="min-w-[180px] sm:min-w-[220px]">
                <x-select wire:model.live="filterExperience" :options="$this->experienceFilterOptions" class="text-xs h-9 font-semibold" />
            </div>

            <!-- Block Dates Button -->
            <button type="button" wire:click="openBlockModal"
                class="h-9 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-zinc-100 dark:hover:bg-white text-white dark:text-zinc-900 font-extrabold text-xs shadow-xs transition flex items-center gap-2 cursor-pointer shrink-0">
                <i class="fa-solid fa-ban text-rose-400 dark:text-rose-600"></i>
                <span>{{ __('Block Dates') }}</span>
            </button>
        </div>
    </div>

    <!-- 2-Column Master-Inspector Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Main Column: Calendar Month Grid (8 Cols on Desktop) -->
        <div class="lg:col-span-8 space-y-4">
            <div
                class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
                <!-- Weekday Headers -->
                <div
                    class="grid grid-cols-7 border-b border-slate-200/80 dark:border-zinc-800 bg-slate-50/80 dark:bg-zinc-800/50 text-center text-xs font-black uppercase tracking-wider py-3.5">
                    <div class="text-rose-500/90 dark:text-rose-400/90">{{ __('Sun') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Mon') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Tue') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Wed') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Thu') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Fri') }}</div>
                    <div class="text-indigo-600/90 dark:text-indigo-400/90">{{ __('Sat') }}</div>
                </div>

                <!-- Days Grid -->
                <div class="grid grid-cols-7 divide-x divide-y divide-slate-100 dark:divide-zinc-800/70 text-xs">
                    @foreach ($this->calendarDays as $cell)
                        @php
                            $isSelected = $selectedDate === $cell['date'];
                        @endphp
                        <div wire:click="selectDate('{{ $cell['date'] }}')"
                            class="min-h-[100px] sm:min-h-[120px] p-2.5 sm:p-3 transition-colors duration-150 cursor-pointer relative flex flex-col justify-between group
                                {{ !$cell['isCurrentMonth'] ? 'bg-slate-50/70 dark:bg-zinc-950/50 text-slate-400 dark:text-zinc-600 hover:bg-slate-100/60 dark:hover:bg-zinc-900/60' : 'bg-white dark:bg-zinc-900 text-slate-800 dark:text-zinc-200 hover:bg-slate-50 dark:hover:bg-zinc-800/60' }}
                                {{ $cell['hasBlock'] ? '!bg-rose-50 dark:!bg-rose-950/40' : '' }}
                                {{ $cell['isToday'] ? '!bg-indigo-50/90 dark:!bg-indigo-950/50' : '' }}
                                {{ $isSelected ? '!bg-indigo-100/90 dark:!bg-indigo-900/60' : '' }}">
                            <!-- Day Header & Indicators -->
                            <div class="flex items-center justify-between gap-1">
                                <div class="flex items-center gap-1.5">
                                    @if ($cell['isToday'])
                                        <span
                                            class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-indigo-600 dark:bg-indigo-500 text-white flex items-center justify-center font-black text-xs">
                                            {{ $cell['dayNumber'] }}
                                        </span>
                                        <span
                                            class="hidden sm:inline-flex px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-indigo-200/80 dark:bg-indigo-900/80 text-indigo-800 dark:text-indigo-200">
                                            {{ __('Today') }}
                                        </span>
                                    @else
                                        <span
                                            class="font-bold text-xs sm:text-sm {{ $cell['hasBlock'] ? 'text-rose-700 dark:text-rose-300 font-extrabold' : ($cell['isCurrentMonth'] ? 'text-slate-700 dark:text-zinc-300' : 'text-slate-400 dark:text-zinc-600') }}">
                                            {{ $cell['dayNumber'] }}
                                        </span>
                                    @endif

                                    @if ($cell['hasBlock'] && !$cell['isToday'])
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"
                                            title="{{ __('Blackout Block Active') }}"></span>
                                    @endif
                                </div>

                                @if ($cell['hasBlock'])
                                    <span
                                        class="px-1.5 py-0.5 rounded-md bg-rose-200/70 text-rose-800 dark:bg-rose-900/80 dark:text-rose-200 text-[10px] font-extrabold flex items-center gap-1 shrink-0"
                                        title="{{ implode('; ', $cell['blockLabels']) }} — {{ $cell['blockReason'] ?? __('Blocked Date') }}">
                                        <i class="fa-solid fa-ban text-[9px]"></i>
                                        @if ($cell['blocksCount'] > 1)
                                            <span>{{ $cell['blocksCount'] }}</span>
                                        @endif
                                    </span>
                                @endif
                            </div>

                            <!-- Day Content / Booking & Blackout Pills -->
                            <div class="space-y-1 my-1">
                                @if ($cell['reservationsCount'] > 0)
                                    <div
                                        class="px-2 py-1 rounded-lg bg-indigo-100 text-indigo-900 dark:bg-indigo-900/60 dark:text-indigo-200 font-bold text-[10px] sm:text-[11px] truncate flex items-center justify-between gap-1">
                                        <span class="truncate flex items-center gap-1.5">
                                            <i
                                                class="fa-solid fa-calendar-check text-[10px] text-indigo-600 dark:text-indigo-400"></i>
                                            <span class="font-bold">{{ $cell['reservationsCount'] }}
                                                {{ $cell['reservationsCount'] === 1 ? __('Trip') : __('Trips') }}</span>
                                        </span>
                                        <span
                                            class="font-black text-[9px] bg-indigo-600 text-white dark:bg-indigo-500 px-1.5 py-0.5 rounded">
                                            {{ $cell['totalPax'] }}p
                                        </span>
                                    </div>
                                @endif

                                @if ($cell['hasBlock'])
                                    <div class="px-2 py-1 rounded-lg bg-rose-100 text-rose-800 dark:bg-rose-900/70 dark:text-rose-200 text-[10px] font-extrabold truncate flex items-center gap-1.5"
                                        title="{{ implode('; ', $cell['blockLabels']) }}">
                                        <i
                                            class="fa-solid fa-ban text-[8px] text-rose-600 dark:text-rose-400 shrink-0"></i>
                                        <span class="truncate">
                                            @if ($cell['isOperatorWideBlock'])
                                                {{ __('Blackout (All)') }}
                                            @else
                                                {{ $cell['blockLabels'][0] ?? __('Blackout') }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <!-- Subtle Footer / Hover Hint -->
                            <div
                                class="flex items-center justify-between text-[9px] font-semibold text-slate-400 dark:text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity pt-0.5">
                                <span
                                    class="text-[8px] uppercase tracking-wider text-indigo-500 dark:text-indigo-400 font-bold">{{ __('Details') }}</span>
                                <i class="fa-solid fa-arrow-right text-[7px] text-indigo-500 dark:text-indigo-400"></i>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Quick Monthly Summary Footer Strip -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 p-3.5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 text-xs font-semibold text-slate-600 dark:text-slate-400 shadow-2xs">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-check text-emerald-500"></i>
                        <span><strong>{{ $this->monthSummary['tripsCount'] }}</strong>
                            {{ __('Confirmed Departures') }}</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-users text-indigo-500"></i>
                        <span><strong>{{ $this->monthSummary['totalPax'] }}</strong>
                            {{ __('Total Guests (Pax)') }}</span>
                    </span>
                </div>
                @if ($this->monthSummary['blackoutDaysCount'] > 0)
                    <span class="flex items-center gap-1.5 text-rose-600 dark:text-rose-400 font-bold">
                        <i class="fa-solid fa-ban text-rose-500"></i>
                        <span>{{ $this->monthSummary['blackoutDaysCount'] }} {{ __('Blackout Days') }}</span>
                    </span>
                @endif
            </div>
        </div>

        <!-- Right Inspector Column (4 Cols on Desktop, sticky) -->
        <div class="lg:col-span-4 lg:sticky lg:top-6 space-y-4">
            <div
                class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-sm space-y-5">
                <!-- Inspector Header -->
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 dark:border-zinc-800 pb-4">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span
                                class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400">
                                @if ($selectedDate === now()->toDateString())
                                    {{ __('Today\'s Schedule') }}
                                @else
                                    {{ __('Day Schedule') }}
                                @endif
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold">
                                {{ count($this->selectedReservations) }}
                                {{ count($this->selectedReservations) === 1 ? __('Trip') : __('Trips') }}
                            </span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">
                            {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('l, M d, Y') }}
                        </h3>
                    </div>

                    <button type="button" wire:click="openBlockModal('{{ $selectedDate }}')"
                        class="h-8 px-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 font-bold text-xs transition flex items-center gap-1 cursor-pointer shadow-2xs border border-rose-200/60 dark:border-rose-900/40 shrink-0"
                        title="{{ __('Add Blackout Block on this Date') }}">
                        <i class="fa-solid fa-lock text-[10px]"></i>
                        <span>{{ __('Block') }}</span>
                    </button>
                </div>

                <!-- Active Blackout Blocks on this Selected Date -->
                @if ($this->selectedDayBlocks->isNotEmpty())
                    <div class="space-y-2.5">
                        <h4
                            class="text-[11px] font-extrabold uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-ban"></i>
                            <span>{{ __('Active Blackout Blocks') }}</span>
                        </h4>
                        <div class="space-y-2">
                            @foreach ($this->selectedDayBlocks as $b)
                                <div
                                    class="p-3.5 rounded-2xl bg-rose-50/70 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-900/60 flex items-start justify-between gap-3 text-xs shadow-2xs">
                                    <div class="space-y-1">
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-rose-100 text-rose-800 dark:bg-rose-900/80 dark:text-rose-200">
                                            <i class="fa-solid fa-lock text-[8px]"></i>
                                            {{ $b->getTargetLabel() }}
                                        </span>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white">
                                            {{ $b->reason ?: __('Scheduled Blackout') }}
                                        </p>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                            {{ $b->date_start->format('M d') }} &rarr;
                                            {{ $b->date_end->format('M d, Y') }}
                                        </p>
                                    </div>
                                    <button type="button"
                                        wire:click="confirmDeleteBlackoutBlock('{{ $b->id }}')"
                                        class="h-7 px-2 rounded-lg bg-white dark:bg-zinc-800 text-rose-600 hover:bg-rose-100 dark:hover:bg-rose-900/60 border border-rose-200 dark:border-rose-800 font-bold text-[11px] transition shadow-2xs cursor-pointer shrink-0">
                                        {{ __('Remove') }}
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Scheduled Departures List -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4
                            class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-list-check text-indigo-500"></i>
                            <span>{{ __('Guest Departures') }}</span>
                        </h4>
                        <span class="text-[11px] font-black text-slate-400 dark:text-slate-500">
                            {{ $this->selectedReservations->sum('pax_count') }} {{ __('Pax Total') }}
                        </span>
                    </div>

                    @if ($this->selectedReservations->isEmpty())
                        <div
                            class="p-6 text-center rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-100 dark:border-zinc-800 space-y-2">
                            <div
                                class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-400 flex items-center justify-center mx-auto text-sm">
                                <i class="fa-solid fa-calendar-day"></i>
                            </div>
                            <p class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                                {{ __('No departures scheduled for this day.') }}
                            </p>
                        </div>
                    @else
                        <div class="space-y-2.5 max-h-[480px] overflow-y-auto pr-0.5">
                            @foreach ($this->selectedReservations as $res)
                                <div
                                    class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/70 dark:border-zinc-800 space-y-2.5 hover:border-indigo-300 dark:hover:border-indigo-700 transition">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="space-y-0.5">
                                            <span
                                                class="font-mono text-[9px] font-bold text-slate-400">#{{ $res->code }}</span>
                                            <h5 class="font-bold text-xs text-slate-900 dark:text-white leading-tight">
                                                {{ $res->guest_name }}
                                            </h5>
                                        </div>
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase
                                            {{ $res->status === \App\Enums\ReservationStatus::Confirmed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                            {{ $res->status->label() }}
                                        </span>
                                    </div>

                                    <div class="text-[11px] space-y-1 text-slate-600 dark:text-slate-300">
                                        <p
                                            class="font-semibold text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                                            <i class="fa-solid fa-cube text-[9px]"></i>
                                            <span
                                                class="truncate">{{ $res->bookable?->name ?? ($res->bookable?->title ?? __('Tour Package')) }}</span>
                                        </p>
                                        <div
                                            class="flex items-center gap-2 text-[10px] text-slate-500 dark:text-slate-400">
                                            <span class="font-bold"><i
                                                    class="fa-solid fa-users mr-1"></i>{{ $res->pax_count }}
                                                Pax</span>
                                            <span>&bull;</span>
                                            @if ($res->guest_contact)
                                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $res->guest_contact) }}"
                                                    target="_blank"
                                                    class="text-emerald-600 hover:underline flex items-center gap-1 font-semibold">
                                                    <i class="fa-brands fa-whatsapp text-[10px]"></i>
                                                    <span>{{ $res->guest_contact }}</span>
                                                </a>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div
                                        class="pt-2 border-t border-slate-200/60 dark:border-zinc-700/60 flex items-center justify-between text-xs">
                                        <span class="font-black text-slate-900 dark:text-white">
                                            Rp
                                            {{ number_format((float) ($res->terms_snapshot['price'] ?? 0), 0, ',', '.') }}
                                        </span>
                                        <a href="{{ route('reservations.index') }}" wire:navigate
                                            class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                                            <span>{{ __('Details') }}</span>
                                            <i class="fa-solid fa-arrow-right text-[8px]"></i>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

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
                        {{ __('Prevent online guest bookings and checkout during scheduled maintenance, off-season, or holidays.') }}
                    </p>
                </div>
            </div>

            <!-- Target Scope Selection -->
            <div class="space-y-2">
                <x-label :value="__('Target Inventory Scope')" required />
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <label
                        class="p-3 rounded-xl border cursor-pointer transition flex flex-col justify-between text-xs font-semibold
                        {{ $blockTargetType === 'all' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 text-slate-700 dark:text-slate-300 hover:bg-slate-100' }}">
                        <input type="radio" wire:model.live="blockTargetType" value="all" class="sr-only" />
                        <div class="flex items-center justify-between w-full mb-1">
                            <i class="fa-solid fa-globe text-base text-indigo-500"></i>
                            @if ($blockTargetType === 'all')
                                <i class="fa-solid fa-circle-check text-indigo-600"></i>
                            @endif
                        </div>
                        <div>
                            <span class="font-bold block">{{ __('Entire Catalog') }}</span>
                            <span
                                class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">{{ __('All Packages & Activities') }}</span>
                        </div>
                    </label>

                    <label
                        class="p-3 rounded-xl border cursor-pointer transition flex flex-col justify-between text-xs font-semibold
                        {{ $blockTargetType === 'package' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 text-slate-700 dark:text-slate-300 hover:bg-slate-100' }}">
                        <input type="radio" wire:model.live="blockTargetType" value="package" class="sr-only" />
                        <div class="flex items-center justify-between w-full mb-1">
                            <i class="fa-solid fa-cubes text-base text-purple-500"></i>
                            @if ($blockTargetType === 'package')
                                <i class="fa-solid fa-circle-check text-indigo-600"></i>
                            @endif
                        </div>
                        <div>
                            <span class="font-bold block">{{ __('Tour Packages') }}</span>
                            <span
                                class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">{{ __('Specific Packages') }}</span>
                        </div>
                    </label>

                    <label
                        class="p-3 rounded-xl border cursor-pointer transition flex flex-col justify-between text-xs font-semibold
                        {{ $blockTargetType === 'product' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-600' : 'border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 text-slate-700 dark:text-slate-300 hover:bg-slate-100' }}">
                        <input type="radio" wire:model.live="blockTargetType" value="product" class="sr-only" />
                        <div class="flex items-center justify-between w-full mb-1">
                            <i class="fa-solid fa-compass text-base text-emerald-500"></i>
                            @if ($blockTargetType === 'product')
                                <i class="fa-solid fa-circle-check text-indigo-600"></i>
                            @endif
                        </div>
                        <div>
                            <span class="font-bold block">{{ __('Single Activities') }}</span>
                            <span
                                class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">{{ __('Specific Single Items') }}</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Specific Package Checklist -->
            @if ($blockTargetType === 'package')
                <div
                    class="space-y-2 p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700">
                    <span
                        class="text-xs font-bold text-slate-800 dark:text-slate-200 block">{{ __('Select Tour Packages to Block:') }}</span>
                    @if ($this->operatorPackages->isEmpty())
                        <p class="text-xs text-slate-400">{{ __('No tour packages found.') }}</p>
                    @else
                        <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1">
                            @foreach ($this->operatorPackages as $pkg)
                                <label
                                    class="flex items-center gap-2.5 p-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 text-xs font-semibold text-slate-800 dark:text-slate-200 cursor-pointer hover:border-indigo-300">
                                    <input type="checkbox" wire:model="blockPackageIds" value="{{ $pkg->id }}"
                                        class="rounded text-indigo-600 focus:ring-indigo-500 border-slate-300" />
                                    <span class="truncate">{{ $pkg->title }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('blockPackageIds')" />
                </div>
            @endif

            <!-- Specific Activity Checklist -->
            @if ($blockTargetType === 'product')
                <div
                    class="space-y-2 p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700">
                    <span
                        class="text-xs font-bold text-slate-800 dark:text-slate-200 block">{{ __('Select Single Activities to Block:') }}</span>
                    @if ($this->operatorProducts->isEmpty())
                        <p class="text-xs text-slate-400">{{ __('No single activities found.') }}</p>
                    @else
                        <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1">
                            @foreach ($this->operatorProducts as $prod)
                                <label
                                    class="flex items-center gap-2.5 p-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 text-xs font-semibold text-slate-800 dark:text-slate-200 cursor-pointer hover:border-indigo-300">
                                    <input type="checkbox" wire:model="blockProductIds" value="{{ $prod->id }}"
                                        class="rounded text-indigo-600 focus:ring-indigo-500 border-slate-300" />
                                    <span class="truncate">{{ $prod->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('blockProductIds')" />
                </div>
            @endif

            <!-- Quick Presets -->
            <div class="space-y-1.5">
                <span
                    class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{{ __('Quick Range Presets:') }}</span>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" wire:click="setQuickBlock('today')"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                        {{ __('Today') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('tomorrow')"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                        {{ __('Tomorrow') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('weekend')"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                        {{ __('This Weekend') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('next_week')"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                        {{ __('Next Week') }}
                    </button>
                </div>
            </div>

            <!-- Date Range Inputs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-label for="blockStartDate" :value="__('Start Date')" required />
                    <x-date-picker id="blockStartDate" wire:model="blockStartDate" :blackout-dates="$this->existingBlackoutDates" />
                    <x-input-error :messages="$errors->get('blockStartDate')" />
                </div>
                <div>
                    <x-label for="blockEndDate" :value="__('End Date')" required />
                    <x-date-picker id="blockEndDate" wire:model="blockEndDate" :blackout-dates="$this->existingBlackoutDates" />
                    <x-input-error :messages="$errors->get('blockEndDate')" />
                </div>
            </div>

            <!-- Reason Input -->
            <div>
                <x-label for="blockReason" :value="__('Blackout Reason (Internal / Customer Note)')" />
                <x-input id="blockReason" type="text" wire:model="blockReason"
                    placeholder="{{ __('e.g. Scheduled Maintenance, National Holiday, Monsoon Break') }}" />
                <x-input-error :messages="$errors->get('blockReason')" />
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                <x-button type="button" variant="secondary"
                    x-on:click="$dispatch('close-modal', 'block-inventory-dates')">
                    {{ __('Cancel') }}
                </x-button>
                <button type="submit"
                    class="inline-flex items-center justify-center font-bold rounded-xl transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer gap-2 select-none whitespace-nowrap shrink-0 h-9 px-4 text-xs bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 shadow-xs">
                    <i class="fa-solid fa-lock text-xs"></i>
                    <span>{{ __('Confirm Blackout Block') }}</span>
                </button>
            </div>
        </form>
    </x-modal>

    <!-- Blackout Removal Confirmation Modal -->
    <x-modal name="confirm-blackout-removal" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div
                class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ __('Remove Blackout Block?') }}
                </h3>
                <p class="text-xs text-slate-600 dark:text-slate-300 max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to remove the blackout block for') }}
                    <span
                        class="font-bold text-slate-900 dark:text-white block mt-0.5">{{ $deletingBlockLabel ?? __('this item') }}</span>
                    @if ($deletingBlockDates)
                        <span
                            class="inline-block text-[11px] font-mono text-slate-400 dark:text-slate-500 mt-1">({{ $deletingBlockDates }})</span>
                    @endif
                </p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 max-w-xs mx-auto">
                    {{ __('Once removed, online guests will be able to book and reserve dates covered by this block again.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-3">
                <x-button type="button" variant="secondary"
                    x-on:click="$dispatch('close-modal', 'confirm-blackout-removal')" class="font-semibold text-xs">
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="button" variant="danger" wire:click="deleteBlackoutBlock"
                    class="font-semibold text-xs shadow-xs">
                    <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                    {{ __('Confirm Removal') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
