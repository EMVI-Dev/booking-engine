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
            class="p-3.5 rounded-[8px] bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-medium flex items-center gap-2 animate-fade-in shadow-none">
            <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 text-sm"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Month Navigation & Controls Toolbar -->
    <div
        class="grid grid-cols-1 gap-3 p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none lg:flex lg:flex-row lg:items-center lg:justify-between lg:gap-4">
        <div class="flex min-w-0 items-center gap-2.5">
            <span
                class="w-9 h-9 shrink-0 rounded-[8px] bg-[#FFEF4D] text-[#12181E] font-medium flex items-center justify-center text-sm shadow-none">
                <i class="fa-solid fa-calendar-days"></i>
            </span>
            <h2 class="min-w-0 truncate text-lg sm:text-xl font-medium text-[#12181E] dark:text-white tracking-tight">
                {{ \Illuminate\Support\Carbon::createFromDate($year, $month, 1)->format('F Y') }}
            </h2>
        </div>

        <!-- Month Quick Nav Buttons -->
        <div class="grid w-full grid-cols-[2.75rem_minmax(0,1fr)_2.75rem] items-center gap-1 bg-[#F9FAFB] dark:bg-[#151a26] p-1 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] lg:w-auto lg:flex">
            <button type="button" wire:click="prevMonth"
                class="h-10 w-full lg:h-7 lg:w-7 rounded-[4px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-[#10141d] shadow-none transition cursor-pointer"
                title="{{ __('Previous Month') }}">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </button>
            <button type="button" wire:click="currentMonth"
                class="h-10 w-full lg:h-7 lg:w-auto px-3 rounded-[4px] text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-[#10141d] transition cursor-pointer shadow-none flex items-center justify-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-900 dark:bg-white"></span>
                <span>{{ __('Today') }}</span>
            </button>
            <button type="button" wire:click="nextMonth"
                class="h-10 w-full lg:h-7 lg:w-7 rounded-[4px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-[#10141d] shadow-none transition cursor-pointer"
                title="{{ __('Next Month') }}">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </button>
        </div>

        <div class="grid w-full grid-cols-1 gap-2.5 lg:w-auto lg:grid-cols-[minmax(220px,280px)_auto] lg:items-center">
            <div class="w-full min-w-0">
                <x-select wire:model.live="filterExperience" :options="$this->experienceFilterOptions" class="h-10 w-full text-xs font-medium lg:h-9 rounded-[6px]" />
            </div>

            <button type="button" wire:click="openBlockModal"
                class="h-11 w-full px-4 rounded-[6px] bg-[#12181E] hover:bg-black dark:bg-white dark:hover:bg-slate-100 text-white dark:text-[#12181E] font-medium text-xs shadow-none transition flex items-center justify-center gap-2 cursor-pointer lg:h-9 lg:w-auto shrink-0">
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
                class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
                <!-- Weekday Headers -->
                <div
                    class="grid grid-cols-7 border-b border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] text-center text-xs font-medium uppercase tracking-wider py-2.5">
                    <div class="text-rose-500/90 dark:text-rose-400/90">{{ __('Sun') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Mon') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Tue') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Wed') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Thu') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Fri') }}</div>
                    <div class="text-slate-600 dark:text-slate-300">{{ __('Sat') }}</div>
                </div>

                <!-- Days Grid -->
                <div class="grid grid-cols-7 divide-x divide-y divide-[#E4E5E9] dark:divide-[#1E2433] text-xs">
                    @foreach ($this->calendarDays as $cell)
                        @php
                            $isSelected = $selectedDate === $cell['date'];
                        @endphp
                        <div wire:click="selectDate('{{ $cell['date'] }}')"
                            class="min-h-[4.5rem] md:min-h-[100px] lg:min-h-[110px] p-1.5 md:p-2.5 transition-colors duration-150 cursor-pointer relative flex flex-col justify-between group
                                {{ !$cell['isCurrentMonth'] ? 'bg-[#F9FAFB]/50 dark:bg-[#151a26]/40 text-slate-400 dark:text-zinc-600 hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433]' : 'bg-white dark:bg-[#10141d] text-slate-800 dark:text-zinc-200 hover:bg-[#F9FAFB] dark:hover:bg-[#151a26]' }}
                                {{ $cell['hasBlock'] ? '!bg-rose-50/70 dark:!bg-rose-950/30' : '' }}
                                {{ $cell['isToday'] ? '!bg-[#FFEF4D]/15 dark:!bg-[#FFEF4D]/10' : '' }}
                                {{ $isSelected ? '!bg-[#FFEF4D]/25 dark:!bg-[#FFEF4D]/20' : '' }}">
                            <!-- Day Header & Indicators -->
                            <div class="flex items-center justify-between gap-1">
                                <div class="flex items-center gap-1.5">
                                    @if ($cell['isToday'])
                                        <span
                                            class="w-6 h-6 sm:w-6.5 sm:h-6.5 rounded-[4px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center font-medium text-xs">
                                            {{ $cell['dayNumber'] }}
                                        </span>
                                        <span
                                            class="hidden sm:inline-flex px-1.5 py-0.5 rounded-[3px] text-[9px] font-medium uppercase tracking-wider bg-[#FFEF4D]/40 text-[#12181E] dark:text-[#FFEF4D]">
                                            {{ __('Today') }}
                                        </span>
                                    @else
                                        <span
                                            class="font-medium text-xs sm:text-sm {{ $cell['hasBlock'] ? 'text-rose-700 dark:text-rose-300' : ($cell['isCurrentMonth'] ? 'text-slate-700 dark:text-zinc-300' : 'text-slate-400 dark:text-zinc-600') }}">
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
                                        class="hidden md:flex px-1.5 py-0.5 rounded-[4px] bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200 text-[10px] font-medium items-center gap-1 shrink-0"
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
                                    <div class="md:hidden flex items-center justify-center gap-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-900 dark:bg-[#FFEF4D]"></span>
                                        <span class="text-[9px] font-medium text-slate-800 dark:text-slate-200">{{ $cell['reservationsCount'] }}</span>
                                    </div>
                                    <div
                                        class="hidden md:flex px-2 py-1 rounded-[4px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] text-[#12181E] dark:text-white font-medium text-[10px] sm:text-[11px] truncate items-center justify-between gap-1">
                                        <span class="truncate flex items-center gap-1.5">
                                            <i
                                                class="fa-solid fa-calendar-check text-[10px] text-slate-500"></i>
                                            <span class="font-medium">{{ $cell['reservationsCount'] }}
                                                {{ $cell['reservationsCount'] === 1 ? __('Trip') : __('Trips') }}</span>
                                        </span>
                                        <span
                                            class="font-medium text-[9px] bg-[#FFEF4D] text-[#12181E] px-1.5 py-0.5 rounded-[3px]">
                                            {{ $cell['totalPax'] }}p
                                        </span>
                                    </div>
                                @endif

                                @if ($cell['hasBlock'])
                                    <div class="md:hidden flex items-center justify-center">
                                        <i class="fa-solid fa-ban text-[8px] text-rose-600 dark:text-rose-400"></i>
                                    </div>
                                    <div class="hidden md:flex px-2 py-1 rounded-[4px] bg-rose-50 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200 text-[10px] font-medium truncate items-center gap-1.5"
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
                                class="flex items-center justify-between text-[9px] font-medium text-slate-400 dark:text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity pt-0.5">
                                <span
                                    class="text-[8px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-medium">{{ __('Details') }}</span>
                                <i class="fa-solid fa-arrow-right text-[7px] text-slate-500 dark:text-slate-400"></i>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Quick Monthly Summary Footer Strip -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 p-3.5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] text-xs font-medium text-slate-600 dark:text-slate-400 shadow-none">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-check text-emerald-500"></i>
                        <span><strong>{{ $this->monthSummary['tripsCount'] }}</strong>
                            {{ __('Confirmed Departures') }}</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-users text-slate-500"></i>
                        <span><strong>{{ $this->monthSummary['totalPax'] }}</strong>
                            {{ __('Total Guests (Pax)') }}</span>
                    </span>
                </div>
                @if ($this->monthSummary['blackoutDaysCount'] > 0)
                    <span class="flex items-center gap-1.5 text-rose-600 dark:text-rose-400 font-medium">
                        <i class="fa-solid fa-ban text-rose-500"></i>
                        <span>{{ $this->monthSummary['blackoutDaysCount'] }} {{ __('Blackout Days') }}</span>
                    </span>
                @endif
            </div>
        </div>

        <!-- Right Inspector Column (4 Cols on Desktop, sticky) -->
        <div class="lg:col-span-4 lg:sticky lg:top-6 space-y-4">
            <div
                class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <!-- Inspector Header -->
                <div class="flex items-start justify-between gap-3 border-b border-[#E4E5E9] dark:border-[#1E2433] pb-3.5">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span
                                class="px-2 py-0.5 rounded-[4px] text-[10px] font-medium uppercase tracking-wider bg-[#FFEF4D] text-[#12181E]">
                                @if ($selectedDate === now()->toDateString())
                                    {{ __('Today\'s Schedule') }}
                                @else
                                    {{ __('Day Schedule') }}
                                @endif
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                {{ count($this->selectedReservations) }}
                                {{ count($this->selectedReservations) === 1 ? __('Trip') : __('Trips') }}
                            </span>
                        </div>
                        <h3 class="text-base sm:text-lg font-medium text-[#12181E] dark:text-white tracking-tight">
                            {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('l, M d, Y') }}
                        </h3>
                    </div>

                    <button type="button" wire:click="openBlockModal('{{ $selectedDate }}')"
                        class="h-8 px-2.5 rounded-[6px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 font-medium text-xs transition flex items-center gap-1 cursor-pointer shadow-none border border-rose-200 dark:border-rose-900/40 shrink-0"
                        title="{{ __('Add Blackout Block on this Date') }}">
                        <i class="fa-solid fa-lock text-[10px]"></i>
                        <span>{{ __('Block') }}</span>
                    </button>
                </div>

                <!-- Active Blackout Blocks on this Selected Date -->
                @if ($this->selectedDayBlocks->isNotEmpty())
                    <div class="space-y-2">
                        <h4
                            class="text-[11px] font-medium uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-ban"></i>
                            <span>{{ __('Active Blackout Blocks') }}</span>
                        </h4>
                        <div class="space-y-2">
                            @foreach ($this->selectedDayBlocks as $b)
                                <div
                                    class="p-3 rounded-[8px] bg-rose-50/70 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 flex items-start justify-between gap-3 text-xs shadow-none">
                                    <div class="space-y-1">
                                        <span
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-[4px] text-[10px] font-medium uppercase bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200">
                                            <i class="fa-solid fa-lock text-[8px]"></i>
                                            {{ $b->getTargetLabel() }}
                                        </span>
                                        <p class="text-xs font-medium text-[#12181E] dark:text-white">
                                            {{ $b->reason ?: __('Scheduled Blackout') }}
                                        </p>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                            {{ $b->date_start->format('M d') }} &rarr;
                                            {{ $b->date_end->format('M d, Y') }}
                                        </p>
                                    </div>
                                    <button type="button"
                                        wire:click="confirmDeleteBlackoutBlock('{{ $b->id }}')"
                                        class="h-7 px-2 rounded-[4px] bg-white dark:bg-[#10141d] text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-800 font-medium text-[11px] transition shadow-none cursor-pointer shrink-0">
                                        {{ __('Remove') }}
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Scheduled Departures List -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <h4
                            class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-list-check text-slate-400"></i>
                            <span>{{ __('Guest Departures') }}</span>
                        </h4>
                        <span class="text-[11px] font-medium text-slate-400 dark:text-slate-500">
                            {{ $this->selectedReservations->sum('pax_count') }} {{ __('Pax Total') }}
                        </span>
                    </div>

                    @if ($this->selectedReservations->isEmpty())
                        <div
                            class="p-5 text-center rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-1.5">
                            <div
                                class="w-8 h-8 rounded-[6px] bg-slate-100 dark:bg-[#1E2433] text-slate-400 flex items-center justify-center mx-auto text-xs">
                                <i class="fa-solid fa-calendar-day"></i>
                            </div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ __('No departures scheduled for this day.') }}
                            </p>
                        </div>
                    @else
                        <div class="space-y-2 max-h-[480px] overflow-y-auto pr-0.5">
                            @foreach ($this->selectedReservations as $res)
                                <div
                                    class="p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-2 hover:border-slate-300 dark:hover:border-zinc-700 transition">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="space-y-0.5">
                                            <span
                                                class="font-mono text-[9px] font-medium text-slate-400">#{{ $res->code }}</span>
                                            <h5 class="font-medium text-xs text-[#12181E] dark:text-white leading-tight">
                                                {{ $res->guest_name }}
                                            </h5>
                                        </div>
                                        <x-status-badge :status="$res->status" />
                                    </div>

                                    <div class="text-[11px] space-y-1 text-slate-600 dark:text-slate-300">
                                        <p
                                            class="font-medium text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                            <i class="fa-solid fa-cube text-[9px] text-slate-400"></i>
                                            <span
                                                class="truncate">{{ $res->bookable?->name ?? ($res->bookable?->title ?? __('Tour Package')) }}</span>
                                        </p>
                                        <div
                                            class="flex items-center gap-2 text-[10px] text-slate-500 dark:text-slate-400">
                                            <span class="font-medium"><i
                                                    class="fa-solid fa-users mr-1"></i>{{ $res->pax_count }}
                                                Pax</span>
                                            <span>&bull;</span>
                                            @if ($res->guest_contact)
                                                <a href="https://wa.me/{{ \App\Services\PhoneNumber::normalize($res->guest_contact) }}"
                                                    target="_blank"
                                                    class="text-emerald-600 hover:underline flex items-center gap-1 font-medium">
                                                    <i class="fa-brands fa-whatsapp text-[10px]"></i>
                                                    <span>{{ $res->guest_contact }}</span>
                                                </a>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div
                                        class="pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between text-xs">
                                        <span class="font-medium text-[#12181E] dark:text-white">
                                            Rp
                                            {{ number_format((float) ($res->terms_snapshot['price'] ?? 0), 0, ',', '.') }}
                                        </span>
                                        <a href="{{ route('reservations.index') }}" wire:navigate
                                            class="text-[11px] font-medium text-slate-700 dark:text-slate-300 hover:text-black dark:hover:text-white hover:underline flex items-center gap-1">
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
                <span class="w-9 h-9 rounded-[8px] bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </span>
                <div>
                    <h3 class="text-[16px] sm:text-[18px] font-medium text-[#12181E] dark:text-white">
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
                        class="p-3 rounded-[8px] border cursor-pointer transition flex flex-col justify-between text-xs font-medium
                        {{ $blockTargetType === 'all' ? 'border-[#12181E] dark:border-white bg-[#FFEF4D]/10 text-[#12181E] dark:text-white ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] text-slate-700 dark:text-slate-300 hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433]' }}">
                        <input type="radio" wire:model.live="blockTargetType" value="all" class="sr-only" />
                        <div class="flex items-center justify-between w-full mb-1">
                            <i class="fa-solid fa-globe text-base text-slate-600 dark:text-slate-300"></i>
                            @if ($blockTargetType === 'all')
                                <i class="fa-solid fa-circle-check text-[#12181E] dark:text-white"></i>
                            @endif
                        </div>
                        <div>
                            <span class="font-medium block">{{ __('Entire Catalog') }}</span>
                            <span
                                class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">{{ __('All Packages & Activities') }}</span>
                        </div>
                    </label>

                    <label
                        class="p-3 rounded-[8px] border cursor-pointer transition flex flex-col justify-between text-xs font-medium
                        {{ $blockTargetType === 'package' ? 'border-[#12181E] dark:border-white bg-[#FFEF4D]/10 text-[#12181E] dark:text-white ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] text-slate-700 dark:text-slate-300 hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433]' }}">
                        <input type="radio" wire:model.live="blockTargetType" value="package" class="sr-only" />
                        <div class="flex items-center justify-between w-full mb-1">
                            <i class="fa-solid fa-cubes text-base text-slate-600 dark:text-slate-300"></i>
                            @if ($blockTargetType === 'package')
                                <i class="fa-solid fa-circle-check text-[#12181E] dark:text-white"></i>
                            @endif
                        </div>
                        <div>
                            <span class="font-medium block">{{ __('Tour Packages') }}</span>
                            <span
                                class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">{{ __('Specific Packages') }}</span>
                        </div>
                    </label>

                    <label
                        class="p-3 rounded-[8px] border cursor-pointer transition flex flex-col justify-between text-xs font-medium
                        {{ $blockTargetType === 'product' ? 'border-[#12181E] dark:border-white bg-[#FFEF4D]/10 text-[#12181E] dark:text-white ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] text-slate-700 dark:text-slate-300 hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433]' }}">
                        <input type="radio" wire:model.live="blockTargetType" value="product" class="sr-only" />
                        <div class="flex items-center justify-between w-full mb-1">
                            <i class="fa-solid fa-compass text-base text-slate-600 dark:text-slate-300"></i>
                            @if ($blockTargetType === 'product')
                                <i class="fa-solid fa-circle-check text-[#12181E] dark:text-white"></i>
                            @endif
                        </div>
                        <div>
                            <span class="font-medium block">{{ __('Single Activities') }}</span>
                            <span
                                class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">{{ __('Specific Single Items') }}</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Specific Package Checklist -->
            @if ($blockTargetType === 'package')
                <div
                    class="space-y-2 p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="text-xs font-medium text-[#12181E] dark:text-white block">{{ __('Select Tour Packages to Block:') }}</span>
                    @if ($this->operatorPackages->isEmpty())
                        <p class="text-xs text-slate-400">{{ __('No tour packages found.') }}</p>
                    @else
                        <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1">
                            @foreach ($this->operatorPackages as $pkg)
                                <label
                                    class="flex items-center gap-2.5 p-2 rounded-[6px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] text-xs font-medium text-[#12181E] dark:text-slate-200 cursor-pointer hover:border-slate-400">
                                    <input type="checkbox" wire:model="blockPackageIds" value="{{ $pkg->id }}"
                                        class="rounded-[4px] text-slate-900 focus:ring-0 border-slate-300" />
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
                    class="space-y-2 p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="text-xs font-medium text-[#12181E] dark:text-white block">{{ __('Select Single Activities to Block:') }}</span>
                    @if ($this->operatorProducts->isEmpty())
                        <p class="text-xs text-slate-400">{{ __('No single activities found.') }}</p>
                    @else
                        <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1">
                            @foreach ($this->operatorProducts as $prod)
                                <label
                                    class="flex items-center gap-2.5 p-2 rounded-[6px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] text-xs font-medium text-[#12181E] dark:text-slate-200 cursor-pointer hover:border-slate-400">
                                    <input type="checkbox" wire:model="blockProductIds" value="{{ $prod->id }}"
                                        class="rounded-[4px] text-slate-900 focus:ring-0 border-slate-300" />
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
                    class="text-[10px] uppercase font-medium text-slate-400 tracking-wider">{{ __('Quick Range Presets:') }}</span>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" wire:click="setQuickBlock('today')"
                        class="px-2.5 py-1 rounded-[6px] text-xs font-medium bg-[#F9FAFB] hover:bg-[#F3F4F6] dark:bg-[#151a26] dark:hover:bg-[#1E2433] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer">
                        {{ __('Today') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('tomorrow')"
                        class="px-2.5 py-1 rounded-[6px] text-xs font-medium bg-[#F9FAFB] hover:bg-[#F3F4F6] dark:bg-[#151a26] dark:hover:bg-[#1E2433] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer">
                        {{ __('Tomorrow') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('weekend')"
                        class="px-2.5 py-1 rounded-[6px] text-xs font-medium bg-[#F9FAFB] hover:bg-[#F3F4F6] dark:bg-[#151a26] dark:hover:bg-[#1E2433] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer">
                        {{ __('This Weekend') }}
                    </button>
                    <button type="button" wire:click="setQuickBlock('next_week')"
                        class="px-2.5 py-1 rounded-[6px] text-xs font-medium bg-[#F9FAFB] hover:bg-[#F3F4F6] dark:bg-[#151a26] dark:hover:bg-[#1E2433] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer">
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
                <x-input id="blockReason" type="text" wire:model="blockReason" class="rounded-[6px]"
                    placeholder="{{ __('e.g. Scheduled Maintenance, National Holiday, Monsoon Break') }}" />
                <x-input-error :messages="$errors->get('blockReason')" />
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                <x-button type="button" variant="secondary" size="sm" class="rounded-[6px] font-medium"
                    x-on:click="$dispatch('close-modal', 'block-inventory-dates')">
                    {{ __('Cancel') }}
                </x-button>
                <button type="submit"
                    class="inline-flex items-center justify-center font-medium rounded-[6px] transition-all duration-150 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer gap-2 select-none whitespace-nowrap shrink-0 h-9 px-4 text-xs bg-rose-600 text-white hover:bg-rose-700 shadow-none">
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
                class="w-10 h-10 rounded-[8px] bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-base">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base font-medium text-[#12181E] dark:text-white">
                    {{ __('Remove Blackout Block?') }}
                </h3>
                <p class="text-xs text-slate-600 dark:text-slate-300 max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to remove the blackout block for') }}
                    <span
                        class="font-medium text-[#12181E] dark:text-white block mt-0.5">{{ $deletingBlockLabel ?? __('this item') }}</span>
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
                <x-button type="button" variant="secondary" size="sm" class="rounded-[6px] font-medium"
                    x-on:click="$dispatch('close-modal', 'confirm-blackout-removal')">
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="button" variant="danger" size="sm" class="rounded-[6px] font-medium shadow-none" wire:click="deleteBlackoutBlock">
                    <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                    {{ __('Confirm Removal') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
