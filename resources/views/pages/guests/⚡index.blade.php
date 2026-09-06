<?php

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Operator;
use App\Models\Reservation;
use App\Concerns\ResolvesCurrentOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Guest Directory & CRM')] class extends Component {
    use WithPagination;
    use ResolvesCurrentOperator;

    public string $search = '';
    public string $filter = 'all'; // all, repeat, vip, with_notes
    public string $sortBy = 'recent'; // recent, spent, bookings, name

    // Guest Drawer / Modal State
    public ?string $selectedGuestId = null;
    public bool $showHistoryModal = false;
    public bool $showEditModal = false;

    // Guest Edit Form Fields
    public string $editName = '';
    public ?string $editEmail = null;
    public ?string $editPhone = null;
    public ?string $editNotes = null;
    public string $editTagsInput = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    /**
     * Compute directory metrics.
     *
     * @return array{
     *     total_unique_guests: int,
     *     repeat_guests_count: int,
     *     repeat_rate: float,
     *     total_pax: int,
     *     total_revenue: float,
     *     avg_guest_value: float
     * }
     */
    #[Computed]
    public function metrics(): array
    {
        if (!$this->currentOperator) {
            return [
                'total_unique_guests' => 0,
                'repeat_guests_count' => 0,
                'repeat_rate' => 0.0,
                'total_pax' => 0,
                'total_revenue' => 0.0,
                'avg_guest_value' => 0.0,
            ];
        }

        $guests = $this->currentOperator
            ->guests()
            ->with(['reservations.payments'])
            ->withCount('reservations')
            ->get();

        $totalUnique = $guests->count();

        if ($totalUnique === 0) {
            return [
                'total_unique_guests' => 0,
                'repeat_guests_count' => 0,
                'repeat_rate' => 0.0,
                'total_pax' => 0,
                'total_revenue' => 0.0,
                'avg_guest_value' => 0.0,
            ];
        }

        $repeatCount = $guests->filter(fn(Guest $g) => $g->reservations_count > 1)->count();
        $totalPax = (int) $guests->sum(fn(Guest $g) => $g->total_pax);
        $totalRevenue = (float) $guests->sum(fn(Guest $g) => $g->total_spent);
        $repeatRate = round(($repeatCount / $totalUnique) * 100, 1);
        $avgValue = round($totalRevenue / $totalUnique, 0);

        return [
            'total_unique_guests' => $totalUnique,
            'repeat_guests_count' => $repeatCount,
            'repeat_rate' => $repeatRate,
            'total_pax' => $totalPax,
            'total_revenue' => $totalRevenue,
            'avg_guest_value' => $avgValue,
        ];
    }

    /**
     * Get paginated guests with active filtering.
     *
     * @return LengthAwarePaginator
     */
    #[Computed]
    public function guests(): LengthAwarePaginator
    {
        if (!$this->currentOperator) {
            return new LengthAwarePaginator([], 0, 15);
        }

        $query = $this->currentOperator
            ->guests()
            ->with(['reservations.bookable', 'reservations.latestPayment', 'reservations.payments'])
            ->withCount('reservations');

        // Search Filter
        if (trim($this->search) !== '') {
            $search = '%' . trim($this->search) . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search)->orWhere('notes', 'like', $search);
            });
        }

        // Category Filter
        if ($this->filter === 'repeat') {
            $query->has('reservations', '>', 1);
        } elseif ($this->filter === 'vip') {
            $query->where(function (Builder $q) {
                $q->whereJsonContains('tags', 'VIP')->orWhereJsonContains('tags', 'vip');
            });
        } elseif ($this->filter === 'with_notes') {
            $query->whereNotNull('notes')->where('notes', '!=', '');
        }

        // Sort By
        if ($this->sortBy === 'name') {
            $query->orderBy('name', 'asc');
        } elseif ($this->sortBy === 'bookings') {
            $query->orderByDesc('reservations_count');
        } else {
            $query->latest('updated_at');
        }

        $paginated = $query->paginate(15);

        // In-memory sort by total spend if requested
        if ($this->sortBy === 'spent') {
            $sortedItems = $paginated->getCollection()->sortByDesc(fn(Guest $g) => $g->total_spent)->values();
            $paginated->setCollection($sortedItems);
        }

        return $paginated;
    }

    #[Computed]
    public function selectedGuest(): ?Guest
    {
        if (!$this->selectedGuestId || !$this->currentOperator) {
            return null;
        }

        return $this->currentOperator
            ->guests()
            ->with(['reservations.bookable', 'reservations.latestPayment', 'reservations.payments'])
            ->find($this->selectedGuestId);
    }

    /**
     * Open booking history modal for a guest.
     */
    public function viewGuestHistory(string $guestId): void
    {
        $this->selectedGuestId = $guestId;
        $this->showHistoryModal = true;
    }

    /**
     * Alias for viewGuestHistory.
     */
    public function openHistory(string $guestId): void
    {
        $this->viewGuestHistory($guestId);
    }

    /**
     * Close history modal.
     */
    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->selectedGuestId = null;
    }

    /**
     * Open CRM Edit Modal.
     */
    public function editGuest(string $guestId): void
    {
        $this->selectedGuestId = $guestId;
        $guest = $this->selectedGuest;

        if ($guest) {
            $this->editName = $guest->name;
            $this->editEmail = $guest->email;
            $this->editPhone = $guest->phone;
            $this->editNotes = $guest->notes;
            $this->editTagsInput = is_array($guest->tags) ? implode(', ', $guest->tags) : '';
            $this->showEditModal = true;
        }
    }

    /**
     * Save CRM Guest Changes.
     */
    public function saveGuest(): void
    {
        abort_unless($this->currentOperator?->hasFeature('guest_crm') ?? false, 403);

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['nullable', 'email', 'max:255'],
            'editPhone' => ['nullable', 'string', 'max:50'],
            'editNotes' => ['nullable', 'string', 'max:1000'],
            'editTagsInput' => ['nullable', 'string', 'max:255'],
        ]);

        $guest = $this->selectedGuest;
        if (!$guest) {
            return;
        }

        $tags = null;
        if (trim($this->editTagsInput) !== '') {
            $tags = array_values(array_filter(array_map('trim', explode(',', $this->editTagsInput))));
        }

        $guest->update([
            'name' => $this->editName,
            'email' => $this->editEmail ? strtolower(trim($this->editEmail)) : null,
            'phone' => $this->editPhone ? trim($this->editPhone) : null,
            'notes' => $this->editNotes ? trim($this->editNotes) : null,
            'tags' => $tags,
        ]);

        $this->showEditModal = false;
        $this->dispatch('guest-saved');
    }

    /**
     * Close Edit Modal.
     */
    public function closeEdit(): void
    {
        $this->showEditModal = false;
    }
}; ?>

<div class="space-y-6">
    @if (!$this->currentOperator?->hasFeature('guest_crm'))
        <div class="py-6">
            <x-feature-gate :title="__('Guest Directory CRM & Lifetime Tracking')" :description="__(
                'Unlock comprehensive customer profiles, VIP tagging, repeat booking history, and direct WhatsApp re-engagement.',
            )" required-plan="Pro" plan-slug="growth"
                icon="fa-solid fa-address-book" :features="[
                    __('Lead guest profiles with automatic email and WhatsApp contact indexing'),
                    __('Calculated lifetime value (LTV) and total completed trip counts'),
                    __('Operator CRM notes, customized tags, and VIP categorization'),
                    __('Direct 1-click WhatsApp messaging and personalized guest rebooking'),
                ]" />
        </div>
    @else
        <x-page-header
            :title="__('Guest Directory & CRM')"
            :subtitle="__('Dedicated customer directory with contact references, CRM notes, tags, and lifetime trip history.')"
            icon="fa-address-book"
        >
            <x-slot:actions>
                <x-button :href="route('reservations.index')" variant="secondary" wire:navigate>
                    <i class="fa-solid fa-calendar-check text-xs"></i>
                    <span>{{ __('All Bookings') }}</span>
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <x-metric-card
                :label="__('Unique Guests')"
                :value="number_format($this->metrics['total_unique_guests'])"
                :hint="__('Dedicated customer profiles')"
                icon="fa-users"
                tone="brand"
            />
            <x-metric-card
                :label="__('Repeat Customers')"
                :value="number_format($this->metrics['repeat_guests_count'])"
                :suffix="'('.$this->metrics['repeat_rate'].'%)'"
                :hint="__('Customers with 2+ bookings')"
                icon="fa-repeat"
                tone="success"
            />
            <x-metric-card
                :label="__('Total Passengers')"
                :value="number_format($this->metrics['total_pax'])"
                :hint="__('Lifetime passenger headcount')"
                icon="fa-person-walking-luggage"
                tone="info"
            />
            <x-metric-card
                :label="__('Avg. Guest Value')"
                :value="'Rp '.number_format($this->metrics['avg_guest_value'], 0, ',', '.')"
                :hint="__('Total: Rp :amount', ['amount' => number_format($this->metrics['total_revenue'], 0, ',', '.')])"
                icon="fa-rupiah-sign"
                tone="info"
            />
        </div>
        <x-toolbar>
            <div class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-center">
                <div class="relative flex-1">
                    <x-search-input
                        wire:model.live.debounce.300ms="search"
                        :placeholder="__('Search by guest name, email, WhatsApp, or CRM notes...')"
                    />
                    @if ($search !== '')
                        <button wire:click="$set('search', '')"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-op-subtle hover:text-op-ink">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    @endif
                </div>
                <div class="w-full sm:w-56">
                    <x-select wire:model.live="sortBy" :options="[
                        'recent' => __('Recently Active'),
                        'spent' => __('Highest Lifetime Spend'),
                        'bookings' => __('Most Bookings Count'),
                        'name' => __('Guest Name (A-Z)'),
                    ]" />
                </div>
            </div>

            <x-filter-tabs class="border-t border-op-line pt-3">
                @php
                    $filterTabs = [
                        'all' => __('All Guests (:count)', ['count' => $this->metrics['total_unique_guests']]),
                        'repeat' => __('Repeat Customers (:count)', ['count' => $this->metrics['repeat_guests_count']]),
                        'vip' => __('VIP Tagged'),
                        'with_notes' => __('With CRM Notes'),
                    ];
                @endphp

                @foreach ($filterTabs as $tabKey => $tabLabel)
                    <x-filter-tab :active="$filter === $tabKey" wire:click="$set('filter', '{{ $tabKey }}')">
                        {{ $tabLabel }}
                    </x-filter-tab>
                @endforeach
            </x-filter-tabs>
        </x-toolbar>

        <!-- Guests List Section -->
        <div
            class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-hidden">
            <!-- Guests Mobile Responsive Card List (md:hidden) -->
            <div class="md:hidden space-y-3 p-3 transition-opacity duration-200" wire:loading.class="opacity-60">
                @forelse ($this->guests as $guest)
                    @php
                        $waUrl = $guest->getWhatsAppUrl($this->currentOperator->name ?? '');
                        $initials = strtoupper(substr($guest->name, 0, 2));
                        $totalSpent = $guest->total_spent;
                        $isRepeat = $guest->reservations_count > 1;
                    @endphp
                    <div
                        class="p-4 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div
                                    class="w-8 h-8 rounded-xl bg-[#FFEF4D] text-[#090d16] font-black text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ $initials }}
                                </div>
                                <div class="min-w-0">
                                    <span class="font-extrabold text-xs text-slate-900 dark:text-white block truncate">
                                        {{ $guest->name }}
                                    </span>
                                    @if ($isRepeat)
                                        <span
                                            class="px-1.5 py-0.2 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60">
                                            {{ __('Repeat') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                @if ($guest->phone)
                                    <a href="{{ $waUrl }}" target="_blank"
                                        class="h-8 px-2.5 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-400 font-bold text-xs inline-flex items-center gap-1 border border-emerald-300 dark:border-emerald-800/60">
                                        <i class="fa-brands fa-whatsapp text-xs"></i>
                                        <span>{{ __('Chat') }}</span>
                                    </a>
                                @endif
                                <button type="button" wire:click="openHistory('{{ $guest->id }}')"
                                    class="h-8 px-2.5 rounded-xl bg-slate-100 dark:bg-[#141821] text-slate-700 dark:text-slate-300 font-bold text-xs border border-slate-200 dark:border-[#1e2433]"
                                    title="{{ __('View Reservation History') }}">
                                    <i class="fa-solid fa-clock-rotate-left text-[10px]"></i>
                                </button>
                                <button type="button" wire:click="editGuest('{{ $guest->id }}')"
                                    class="h-8 px-2.5 rounded-xl bg-slate-100 dark:bg-[#141821] text-slate-700 dark:text-slate-300 font-bold text-xs border border-slate-200 dark:border-[#1e2433]"
                                    title="{{ __('Edit Profile') }}">
                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                </button>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100 dark:border-[#1e2433]">
                            <div>
                                <span
                                    class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Contact Info') }}</span>
                                @if ($guest->phone)
                                    <a href="{{ $waUrl }}" target="_blank"
                                        class="font-bold text-emerald-600 dark:text-emerald-400 text-xs truncate block">
                                        {{ $guest->phone }}
                                    </a>
                                @elseif ($guest->email)
                                    <span
                                        class="text-slate-700 dark:text-slate-300 truncate block">{{ $guest->email }}</span>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">{{ __('No contact') }}</span>
                                @endif
                            </div>
                            <div class="text-right">
                                <span
                                    class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Lifetime Spend') }}</span>
                                <span class="font-mono font-black text-slate-900 dark:text-white block">Rp
                                    {{ number_format($totalSpent, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">
                        {{ __('No guests found') }}
                    </div>
                @endforelse
            </div>

            <!-- Desktop Guests Table (hidden on mobile) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead
                        class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ __('Guest Profile & Tags') }}</th>
                            <th class="px-4 py-3.5">{{ __('Contact Reference') }}</th>
                            <th class="px-4 py-3.5 text-center">{{ __('Bookings & Pax') }}</th>
                            <th class="px-4 py-3.5">{{ __('Lifetime Spend') }}</th>
                            <th class="px-4 py-3.5">{{ __('CRM Notes') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                        @forelse ($this->guests as $guest)
                            @php
                                $waUrl = $guest->getWhatsAppUrl($this->currentOperator->name ?? '');
                                $initials = strtoupper(substr($guest->name, 0, 2));
                                $totalSpent = $guest->total_spent;
                                $isRepeat = $guest->reservations_count > 1;
                            @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group">
                                <!-- Guest Name & Tags -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black text-xs flex items-center justify-center shrink-0 shadow-xs">
                                            {{ $initials }}
                                        </div>
                                        <div class="space-y-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900 dark:text-white truncate">
                                                    {{ $guest->name }}
                                                </span>
                                                @if ($isRepeat)
                                                    <span
                                                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800/60 shrink-0">
                                                        <i class="fa-solid fa-repeat mr-0.5"></i>
                                                        {{ __('Repeat') }}
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Custom Tags -->
                                            @if (is_array($guest->tags) && count($guest->tags) > 0)
                                                <div class="flex flex-wrap items-center gap-1">
                                                    @foreach ($guest->tags as $tag)
                                                        <span
                                                            class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-[#141821] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]">
                                                            {{ $tag }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <!-- Contact Reference -->
                                <td class="px-4 py-4">
                                    <div class="space-y-1">
                                        @if ($guest->email)
                                            <a href="mailto:{{ $guest->email }}"
                                                class="text-xs text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-[#FFEF4D] flex items-center gap-1.5 font-medium truncate max-w-[200px]"
                                                title="{{ $guest->email }}">
                                                <i class="fa-solid fa-envelope text-slate-400 text-[10px]"></i>
                                                <span class="truncate">{{ $guest->email }}</span>
                                            </a>
                                        @else
                                            <span
                                                class="text-slate-400 text-xs italic">{{ __('No email recorded') }}</span>
                                        @endif

                                        @if ($guest->phone)
                                            <a href="{{ $waUrl }}" target="_blank"
                                                class="inline-flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-semibold"
                                                title="{{ __('Chat on WhatsApp') }}">
                                                <i class="fa-brands fa-whatsapp text-xs"></i>
                                                <span>{{ $guest->phone }}</span>
                                            </a>
                                        @endif
                                    </div>
                                </td>

                                <!-- Bookings & Pax -->
                                <td class="px-4 py-4 text-center whitespace-nowrap">
                                    <div class="space-y-0.5">
                                        <span class="font-extrabold text-xs text-slate-900 dark:text-white">
                                            {{ __(':count Bookings', ['count' => $guest->reservations_count]) }}
                                        </span>
                                        <p
                                            class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-center gap-1">
                                            <i class="fa-solid fa-users text-[10px]"></i>
                                            {{ __(':count Pax Total', ['count' => $guest->total_pax]) }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Lifetime Spend -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="space-y-0.5">
                                        <p class="font-bold text-xs text-slate-900 dark:text-white">
                                            Rp {{ number_format($totalSpent, 0, ',', '.') }}
                                        </p>
                                        <span
                                            class="inline-flex items-center gap-1 text-[10px] text-emerald-700 dark:text-emerald-400 font-semibold">
                                            <i class="fa-solid fa-circle-check text-[8px]"></i>
                                            {{ __(':count Paid Trips', ['count' => $guest->confirmed_bookings_count]) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- CRM Notes Snippet -->
                                <td class="px-4 py-4 max-w-[200px]">
                                    @if ($guest->notes)
                                        <div class="flex items-start gap-1.5 text-slate-600 dark:text-slate-300 text-xs line-clamp-2"
                                            title="{{ $guest->notes }}">
                                            <i
                                                class="fa-solid fa-note-sticky text-amber-500 text-[10px] mt-0.5 shrink-0"></i>
                                            <span class="line-clamp-2">{{ $guest->notes }}</span>
                                        </div>
                                    @else
                                        <button type="button" wire:click="editGuest('{{ $guest->id }}')"
                                            class="text-xs text-slate-400 hover:text-slate-900 dark:hover:text-[#FFEF4D] italic flex items-center gap-1 transition cursor-pointer">
                                            <i class="fa-solid fa-plus text-[10px]"></i>
                                            <span>{{ __('Add CRM Note') }}</span>
                                        </button>
                                    @endif
                                </td>

                                <!-- Action Buttons -->
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($guest->phone)
                                            <a href="{{ $waUrl }}" target="_blank"
                                                class="h-8 px-2.5 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-400 hover:bg-emerald-200 dark:hover:bg-emerald-900/60 font-bold text-xs transition inline-flex items-center gap-1 border border-emerald-300 dark:border-emerald-800/60 shadow-2xs"
                                                title="{{ __('Message Guest on WhatsApp') }}">
                                                <i class="fa-brands fa-whatsapp text-xs"></i>
                                                <span class="hidden sm:inline">{{ __('Chat') }}</span>
                                            </a>
                                        @endif

                                        <button type="button" wire:click="openHistory('{{ $guest->id }}')"
                                            class="h-8 px-2.5 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#1e2433] font-bold text-xs transition inline-flex items-center gap-1 shadow-2xs cursor-pointer"
                                            title="{{ __('View Reservation History') }}">
                                            <i class="fa-solid fa-clock-rotate-left text-[10px]"></i>
                                            <span class="hidden sm:inline">{{ __('History') }}</span>
                                        </button>

                                        <button type="button" wire:click="editGuest('{{ $guest->id }}')"
                                            class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#1e2433] inline-flex items-center justify-center transition shadow-2xs cursor-pointer"
                                            title="{{ __('Edit Profile') }}">
                                            <i class="fa-solid fa-pen text-[10px]"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-12 text-center text-slate-400">
                                    <div
                                        class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-[#141821] text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#1e2433] flex items-center justify-center mx-auto text-xl mb-3">
                                        <i class="fa-solid fa-address-book"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 dark:text-slate-300 text-sm">
                                        {{ __('No guests found') }}</p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ __('When guests book trips or make inquiries on your storefront, their profiles will automatically appear here.') }}
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->guests->hasPages())
                <div class="p-4 border-t border-slate-100 dark:border-[#1e2433] bg-slate-50/50 dark:bg-[#10141d]">
                    {{ $this->guests->links() }}
                </div>
            @endif
        </div>

        <!-- Modal: CRM Notes & Tags Editor -->
        @if ($showEditModal && $this->selectedGuest)
            @teleport('body')
                <div
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto bg-slate-900/60 backdrop-blur-xs">
                    <div @click.away="$wire.closeEdit()"
                        class="w-full max-w-lg rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xl flex flex-col my-8 animate-scale-up">
                        <!-- Modal Header -->
                        <div
                            class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
                            <div class="flex items-start gap-3.5 min-w-0">
                                <div
                                    class="w-10 h-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-user-pen"></i>
                                </div>
                                <div class="space-y-0.5 min-w-0">
                                    <h3
                                        class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                        {{ __('Edit CRM Profile: :name', ['name' => $this->selectedGuest->name]) }}
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                        {{ __('Manage internal customer notes, preferences, and tags.') }}
                                    </p>
                                </div>
                            </div>

                            <button type="button" wire:click="closeEdit"
                                class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                                <i class="fa-solid fa-xmark text-sm"></i>
                            </button>
                        </div>

                        <form wire:submit="saveGuest" class="p-6 space-y-4">
                            <div>
                                <x-label for="editName" :value="__('Guest Full Name')" required />
                                <x-input id="editName" wire:model="editName" type="text" required />
                                <x-input-error :messages="$errors->get('editName')" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-label for="editEmail" :value="__('Email Address')" />
                                    <x-input id="editEmail" wire:model="editEmail" type="email" />
                                    <x-input-error :messages="$errors->get('editEmail')" />
                                </div>
                                <div>
                                    <x-label for="editPhone" :value="__('WhatsApp / Phone')" />
                                    <x-input id="editPhone" wire:model="editPhone" type="text" />
                                    <x-input-error :messages="$errors->get('editPhone')" />
                                </div>
                            </div>

                            <div>
                                <x-label for="editTagsInput" :value="__('Custom Tags (comma separated)')" />
                                <x-input id="editTagsInput" wire:model="editTagsInput"
                                    placeholder="VIP, Certified Diver, Vegetarian, Corporate" type="text" />
                                <p class="text-[11px] text-slate-400 mt-1">
                                    {{ __('Example: VIP, Returning Guest, Vegetarian') }}</p>
                                <x-input-error :messages="$errors->get('editTagsInput')" />
                            </div>

                            <div>
                                <x-label for="editNotes" :value="__('Internal Operator CRM Notes')" />
                                <x-textarea id="editNotes" wire:model="editNotes" rows="4"
                                    placeholder="{{ __('Add internal notes about allergies, preferences, special requests, pickup locations, etc...') }}" />
                                <x-input-error :messages="$errors->get('editNotes')" />
                            </div>

                            <div
                                class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-[#1e2433]">
                                <x-button type="button" variant="secondary" wire:click="closeEdit"
                                    class="text-xs font-bold">
                                    {{ __('Cancel') }}
                                </x-button>
                                <x-button type="submit" variant="primary" class="text-xs font-bold">
                                    <i class="fa-solid fa-floppy-disk mr-1.5 text-xs"></i>
                                    {{ __('Save Profile') }}
                                </x-button>
                            </div>
                        </form>
                    </div>
                </div>
            @endteleport
        @endif

        <!-- Slide-over / Modal: Guest Profile & Booking History Timeline -->
        @if ($showHistoryModal && $this->selectedGuest)
            @teleport('body')
                @php
                    $guest = $this->selectedGuest;
                    $waUrl = $guest->getWhatsAppUrl($this->currentOperator->name ?? '');
                @endphp
                <div
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto bg-slate-900/60 backdrop-blur-xs">
                    <div @click.away="$wire.closeHistory()"
                        class="w-full max-w-2xl rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xl flex flex-col my-8 animate-scale-up">
                        <!-- Drawer Header -->
                        <div
                            class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
                            <div class="flex items-start gap-3.5 min-w-0">
                                <div
                                    class="w-10 h-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black text-base flex items-center justify-center shrink-0 shadow-xs mt-0.5">
                                    {{ strtoupper(substr($guest->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                                            {{ $guest->name }}
                                        </h3>
                                        @if ($guest->reservations->count() > 1)
                                            <span
                                                class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60">
                                                <i class="fa-solid fa-repeat mr-0.5"></i>
                                                {{ __('Repeat Guest') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        @if ($guest->email)
                                            <span class="flex items-center gap-1">
                                                <i class="fa-solid fa-envelope text-[10px]"></i>
                                                {{ $guest->email }}
                                            </span>
                                        @endif
                                        @if ($guest->phone)
                                            <span
                                                class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                                                <i class="fa-brands fa-whatsapp text-[11px]"></i>
                                                {{ $guest->phone }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <button type="button" wire:click="closeHistory"
                                class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                                <i class="fa-solid fa-xmark text-sm"></i>
                            </button>
                        </div>

                        <!-- Drawer Content -->
                        <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                            <!-- Guest Summary Stats Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div
                                    class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#10141d] border border-slate-200 dark:border-[#1e2433]">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Total Trips') }}</span>
                                    <p class="text-base font-extrabold text-slate-900 dark:text-white mt-0.5">
                                        {{ $guest->reservations->count() }} Bookings</p>
                                </div>
                                <div
                                    class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#10141d] border border-slate-200 dark:border-[#1e2433]">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Total Guests') }}</span>
                                    <p class="text-base font-extrabold text-slate-900 dark:text-white mt-0.5">
                                        {{ $guest->total_pax }} Pax</p>
                                </div>
                                <div
                                    class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#10141d] border border-slate-200 dark:border-[#1e2433] col-span-2">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Lifetime Spend') }}</span>
                                    <p class="text-base font-extrabold text-slate-900 dark:text-white mt-0.5">
                                        Rp {{ number_format($guest->total_spent, 0, ',', '.') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Internal CRM Notes Box (if any) -->
                            @if ($guest->notes)
                                <div
                                    class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-900/60 space-y-1">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
                                        <i class="fa-solid fa-note-sticky"></i>
                                        {{ __('Internal Operator Notes') }}
                                    </span>
                                    <p class="text-xs text-amber-900 dark:text-amber-200 whitespace-pre-line">
                                        {{ $guest->notes }}</p>
                                </div>
                            @endif

                            <!-- Booking History Timeline -->
                            <div class="space-y-3">
                                <div
                                    class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-[#1e2433]">
                                    <h4
                                        class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <i class="fa-solid fa-clock-rotate-left text-slate-600 dark:text-[#FFEF4D]"></i>
                                        {{ __('Reservation History (:count)', ['count' => $guest->reservations->count()]) }}
                                    </h4>
                                    <span class="text-[11px] text-slate-400">
                                        {{ __('Latest to oldest') }}
                                    </span>
                                </div>

                                <div class="space-y-3">
                                    @foreach ($guest->reservations->sortByDesc('requested_date') as $res)
                                        @php
                                            $bookable = $res->bookable;
                                            $payment = $res->latestPayment;
                                            $resCode = $res->code ?? 'RSV-' . strtoupper(substr($res->id, -8));
                                        @endphp
                                        <div
                                            class="p-4 rounded-2xl border border-slate-200 dark:border-[#1e2433] hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-[#141821]/60 transition space-y-2.5">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="space-y-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="font-mono text-[10px] font-bold text-slate-800 dark:text-[#FFEF4D] bg-slate-100 dark:bg-[#FFEF4D]/10 border border-slate-200 dark:border-[#FFEF4D]/30 px-2 py-0.5 rounded-lg">
                                                            #{{ $resCode }}
                                                        </span>
                                                        <span
                                                            class="font-bold text-sm text-slate-900 dark:text-white truncate">
                                                            {{ $bookable->name ?? ($bookable->title ?? __('Direct Booking')) }}
                                                        </span>
                                                    </div>
                                                    <div
                                                        class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                                        <span
                                                            class="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300">
                                                            <i
                                                                class="fa-solid fa-calendar-day text-slate-500 dark:text-[#FFEF4D] text-[10px]"></i>
                                                            {{ $res->requested_date->format('M d, Y') }}
                                                        </span>
                                                        <span>&bull;</span>
                                                        <span class="flex items-center gap-1">
                                                            <i class="fa-solid fa-users text-[10px]"></i>
                                                            {{ __(':count Guests', ['count' => $res->pax_count]) }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Status Badge -->
                                                <div>
                                                    @if ($res->status === ReservationStatus::Confirmed)
                                                        <span
                                                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60">
                                                            {{ __('Confirmed') }}
                                                        </span>
                                                    @elseif ($res->status === ReservationStatus::PendingConfirmation)
                                                        <span
                                                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800/60 animate-pulse">
                                                            {{ __('Needs Confirmation') }}
                                                        </span>
                                                    @elseif ($res->status === ReservationStatus::Completed)
                                                        <span
                                                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400">
                                                            {{ __('Completed') }}
                                                        </span>
                                                    @elseif ($res->status === ReservationStatus::Declined)
                                                        <span
                                                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300 dark:border-rose-800/60">
                                                            {{ __('Declined') }}
                                                        </span>
                                                    @elseif ($res->status === ReservationStatus::Cancelled)
                                                        <span
                                                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300 dark:border-rose-800/60">
                                                            {{ __('Cancelled') }}
                                                        </span>
                                                    @else
                                                        <span
                                                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 dark:bg-[#141821] dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]">
                                                            {{ $res->status->label() }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Bottom Details & Payment Amount -->
                                            <div
                                                class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-[#1e2433] text-xs">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[11px] text-slate-400">
                                                        {{ __('Booked on :date', ['date' => $res->created_at?->format('M d, Y H:i') ?? '—']) }}
                                                    </span>
                                                    @if ($guest->phone && $this->currentOperator?->hasFeature('whatsapp_dispatch'))
                                                        @php
                                                            $resWaUrl = app(
                                                                \App\Services\WhatsAppDispatchService::class,
                                                            )->getConfirmationUrl($res);
                                                        @endphp
                                                        <a href="{{ $resWaUrl }}" target="_blank"
                                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                                            <i class="fa-brands fa-whatsapp text-xs"></i>
                                                            <span>{{ __('Send Voucher') }}</span>
                                                        </a>
                                                    @endif
                                                </div>
                                                <div class="font-extrabold text-slate-900 dark:text-white">
                                                    @if ($payment)
                                                        <span
                                                            class="{{ $payment->isPaid() ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500' }}">
                                                            Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                                        </span>
                                                    @else
                                                        <span class="text-slate-400 font-normal">—</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Drawer Footer -->
                        <div
                            class="p-5 bg-slate-50 dark:bg-[#10141d] border-t border-slate-200 dark:border-[#1e2433] flex items-center justify-between gap-3">
                            @if ($guest->phone)
                                <a href="{{ $waUrl }}" target="_blank"
                                    class="h-9 px-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition">
                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                    <span>{{ __('Contact on WhatsApp') }}</span>
                                </a>
                            @else
                                <div></div>
                            @endif

                            <div class="flex items-center gap-2">
                                <x-button size="sm" variant="secondary"
                                    wire:click="editGuest('{{ $guest->id }}')" class="font-bold text-xs">
                                    <i class="fa-solid fa-pen mr-1"></i>
                                    {{ __('Edit CRM Profile') }}
                                </x-button>
                                <x-button size="sm" variant="secondary" wire:click="closeHistory"
                                    class="font-bold text-xs">
                                    {{ __('Close') }}
                                </x-button>
                            </div>
                        </div>
                    </div>
                </div>
            @endteleport
        @endif
    @endif
</div>
