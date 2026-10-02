<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Guest Profile')] class extends Component {
    use ResolvesCurrentOperator;
    use WithPagination;

    public Guest $guest;

    public string $historyFilter = 'all'; // all, upcoming, past

    public bool $showEditModal = false;

    public string $editName = '';

    public ?string $editEmail = null;

    public ?string $editPhone = null;

    public ?string $editNotes = null;

    public string $editTagsInput = '';

    public function mount(Guest $guest): void
    {
        abort_unless($this->currentOperator?->hasFeature('guest_crm') ?? false, 403);

        if (!$this->currentOperator || $guest->operator_id !== $this->currentOperator->id) {
            abort(404);
        }

        $this->guest = $guest;
    }

    public function updatedHistoryFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function reservations(): LengthAwarePaginator
    {
        $query = $this->guest
            ->reservations()
            ->with(['bookable', 'latestPayment', 'payments'])
            ->latest('requested_date');

        if ($this->historyFilter === 'upcoming') {
            $query->whereDate('requested_date', '>=', now()->toDateString())->whereIn('status', [ReservationStatus::PaymentPending, ReservationStatus::PendingConfirmation, ReservationStatus::Confirmed]);
        } elseif ($this->historyFilter === 'past') {
            $query->where(function ($q): void {
                $q->whereDate('requested_date', '<', now()->toDateString())->orWhereIn('status', [ReservationStatus::Completed, ReservationStatus::Cancelled, ReservationStatus::Declined, ReservationStatus::Expired]);
            });
        }

        return $query->paginate(10);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Guest>
     */
    #[Computed]
    public function duplicates()
    {
        return $this->guest->possibleDuplicates()->loadCount('reservations');
    }

    public function openEdit(): void
    {
        $this->editName = $this->guest->name;
        $this->editEmail = $this->guest->email;
        $this->editPhone = $this->guest->phone;
        $this->editNotes = $this->guest->notes;
        $this->editTagsInput = is_array($this->guest->tags) ? implode(', ', $this->guest->tags) : '';
        $this->showEditModal = true;
    }

    public function closeEdit(): void
    {
        $this->showEditModal = false;
    }

    public function saveGuest(): void
    {
        $this->authorizeAbility('manageReservations');

        abort_unless($this->currentOperator?->hasFeature('guest_crm') ?? false, 403);

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['nullable', 'email', 'max:255'],
            'editPhone' => ['nullable', 'string', 'max:50'],
            'editNotes' => ['nullable', 'string', 'max:1000'],
            'editTagsInput' => ['nullable', 'string', 'max:255'],
        ]);

        $tags = null;
        if (trim($this->editTagsInput) !== '') {
            $tags = array_values(array_filter(array_map('trim', explode(',', $this->editTagsInput))));
        }

        $this->guest->update([
            'name' => $this->editName,
            'email' => $this->editEmail ? strtolower(trim($this->editEmail)) : null,
            'phone' => $this->editPhone ? trim($this->editPhone) : null,
            'notes' => $this->editNotes ? trim($this->editNotes) : null,
            'tags' => $tags,
        ]);

        $this->guest->refresh();
        $this->showEditModal = false;
        unset($this->duplicates);
        $this->dispatch('toast', message: __('Guest profile saved.'), type: 'success');
    }

    public function mergeDuplicate(string $sourceGuestId): void
    {
        $this->authorizeAbility('manageReservations');

        abort_unless($this->currentOperator?->hasFeature('guest_crm') ?? false, 403);

        $source = $this->currentOperator->guests()->findOrFail($sourceGuestId);

        DB::transaction(function () use ($source): void {
            $this->guest->mergeFrom($source);
        });

        $this->guest->refresh();
        unset($this->duplicates);
        unset($this->reservations);
        $this->dispatch('toast', message: __('Guests merged.'), type: 'success');
    }
}; ?>

<div class="space-y-6">
    @php
        $waUrl = $guest->getWhatsAppUrl($this->currentOperator->name ?? '');
        $bookingsUrl = route(
            'reservations.index',
            array_filter([
                'search' => $guest->email ?: $guest->name,
            ]),
        );
        $initials = strtoupper(substr($guest->name, 0, 2));
        $guest->loadMissing(['reservations.payments']);
        $totalSpent = $guest->total_spent;
        $isRepeat = $guest->reservations()->count() > 1;
        $firstTripDate = $guest->reservations->min('requested_date')
            ? \Carbon\Carbon::parse($guest->reservations->min('requested_date'))
            : $guest->created_at;
        $lastTrip = $guest->lastTripDate();
        $nextTrip = $guest->nextTripDate();
    @endphp

    <!-- Top Navigation & Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-3 min-w-0">
            <x-back-link :href="route('guests.index')">
                {{ __('All Guests') }}
            </x-back-link>

            <div class="flex items-start gap-3.5 min-w-0">
                <div
                    class="w-12 h-12 rounded-[8px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] font-mono font-semibold text-lg flex items-center justify-center shrink-0 border border-[#FFEF4D]/40">
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white truncate">
                            {{ $guest->name }}
                        </h1>
                        @if ($isRepeat)
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-[4px] text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/50">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('Repeat Guest') }}
                            </span>
                        @else
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-[4px] text-xs font-semibold bg-[#F4F5F7] text-slate-600 dark:bg-[#1E2433] dark:text-zinc-300 border border-[#E4E5E9] dark:border-[#1E2433]">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                {{ __('First-time Guest') }}
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-mono">{{ __('ID: :id', ['id' => substr($guest->id, -8)]) }}</span>
                        <span>&bull;</span>
                        <span>{{ __('Customer since :date', ['date' => $firstTripDate ? $firstTripDate->format('M Y') : $guest->created_at->format('M Y')]) }}</span>
                        @if ($guest->phone)
                            <span>&bull;</span>
                            <span>{{ $guest->phone }}</span>
                        @endif
                        @if ($guest->email)
                            <span>&bull;</span>
                            <span>{{ $guest->email }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto">
            <x-button type="button" wire:click="openEdit" variant="secondary" size="sm" class="flex-1 sm:flex-none justify-center">
                <i class="fa-solid fa-pen text-xs mr-1.5"></i>
                <span>{{ __('Edit Profile') }}</span>
            </x-button>

            @if ($guest->phone && $waUrl !== '#')
                <a href="{{ $waUrl }}" target="_blank" rel="noopener"
                    class="h-9 px-3.5 rounded-[6px] bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs flex items-center justify-center gap-1.5 transition shadow-none cursor-pointer flex-1 sm:flex-none"
                    title="{{ __('Chat on WhatsApp') }}">
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                    <span>{{ __('WhatsApp') }}</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Main CRM Layout: Profile Sidebar (Left) & Transactions Hub (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- LEFT COLUMN: Profile Sidebar -->
        <div class="lg:col-span-4 xl:col-span-4 space-y-5">
            <!-- Guest Contact & Identity Card -->
            <div
                class="rounded-[12px] border border-[#E4E5E9] bg-white p-5 dark:border-[#1E2433] dark:bg-[#10141d] shadow-none space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('Contact & Identity') }}
                    </span>
                    <button type="button" wire:click="openEdit"
                        class="text-xs font-semibold text-[#12181E] dark:text-[#FFEF4D] hover:underline cursor-pointer">
                        {{ __('Edit') }}
                    </button>
                </div>

                <!-- Contact & Identity Details List -->
                <div class="space-y-3 text-xs">
                    <!-- Email Row -->
                    <div
                        class="flex items-start justify-between gap-2 p-2.5 rounded-[8px] bg-[#F4F5F7]/70 dark:bg-[#141821]/70 border border-[#E4E5E9] dark:border-[#1E2433] transition group">
                        <div class="min-w-0 flex items-start gap-2.5">
                            <div
                                class="w-7 h-7 rounded-[6px] bg-white dark:bg-[#10141d] text-[#5A6578] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa-solid fa-envelope text-[11px]"></i>
                            </div>
                            <div class="min-w-0">
                                <span
                                    class="text-[10px] font-semibold uppercase text-[#5A6578] dark:text-[#9DA4B2] tracking-wider block">{{ __('Email Address') }}</span>
                                @if ($guest->email)
                                    <a href="mailto:{{ $guest->email }}"
                                        class="font-medium text-slate-800 dark:text-slate-200 hover:underline break-all">
                                        {{ $guest->email }}
                                    </a>
                                @else
                                    <span class="text-[#5A6578] dark:text-[#9DA4B2] italic">{{ __('Not provided') }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($guest->email)
                            <button type="button" x-data="{ copied: false }"
                                @click="navigator.clipboard.writeText('{{ $guest->email }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-[#5A6578] hover:text-slate-900 dark:text-[#9DA4B2] dark:hover:text-white p-1 rounded-[4px] transition cursor-pointer"
                                title="{{ __('Copy email') }}">
                                <i
                                    :class="copied ? 'fa-solid fa-check text-emerald-500' : 'fa-regular fa-clone text-[11px]'"></i>
                            </button>
                        @endif
                    </div>

                    <!-- Phone / WhatsApp Row -->
                    <div
                        class="flex items-start justify-between gap-2 p-2.5 rounded-[8px] bg-[#F4F5F7]/70 dark:bg-[#141821]/70 border border-[#E4E5E9] dark:border-[#1E2433] transition group">
                        <div class="min-w-0 flex items-start gap-2.5">
                            <div
                                class="w-7 h-7 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-900/50 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa-brands fa-whatsapp text-xs"></i>
                            </div>
                            <div class="min-w-0">
                                <span
                                    class="text-[10px] font-semibold uppercase text-[#5A6578] dark:text-[#9DA4B2] tracking-wider block">{{ __('WhatsApp / Phone') }}</span>
                                @if ($guest->phone)
                                    <span class="font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $guest->phone }}
                                    </span>
                                @else
                                    <span class="text-[#5A6578] dark:text-[#9DA4B2] italic">{{ __('Not provided') }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($guest->phone)
                            <button type="button" x-data="{ copied: false }"
                                @click="navigator.clipboard.writeText('{{ $guest->phone }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-[#5A6578] hover:text-slate-900 dark:text-[#9DA4B2] dark:hover:text-white p-1 rounded-[4px] transition cursor-pointer"
                                title="{{ __('Copy phone') }}">
                                <i
                                    :class="copied ? 'fa-solid fa-check text-emerald-500' : 'fa-regular fa-clone text-[11px]'"></i>
                            </button>
                        @endif
                    </div>

                    <!-- Last Trip Date -->
                    <div
                        class="flex items-start justify-between gap-2 p-2.5 rounded-[8px] bg-[#F4F5F7]/70 dark:bg-[#141821]/70 border border-[#E4E5E9] dark:border-[#1E2433] transition">
                        <div class="flex items-start gap-2.5">
                            <div
                                class="w-7 h-7 rounded-[6px] bg-white dark:bg-[#10141d] text-[#5A6578] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa-solid fa-clock-rotate-left text-[11px]"></i>
                            </div>
                            <div>
                                <span
                                    class="text-[10px] font-semibold uppercase text-[#5A6578] dark:text-[#9DA4B2] tracking-wider block">{{ __('Last Trip') }}</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">
                                    {{ $lastTrip ? $lastTrip->format('d M Y') : __('No trips yet') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Next Trip Date -->
                    <div
                        class="flex items-start justify-between gap-2 p-2.5 rounded-[8px] bg-[#F4F5F7]/70 dark:bg-[#141821]/70 border border-[#E4E5E9] dark:border-[#1E2433] transition">
                        <div class="flex items-start gap-2.5">
                            <div
                                class="w-7 h-7 rounded-[6px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa-solid fa-plane-departure text-[11px]"></i>
                            </div>
                            <div>
                                <span
                                    class="text-[10px] font-semibold uppercase text-[#5A6578] dark:text-[#9DA4B2] tracking-wider block">{{ __('Next Upcoming') }}</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">
                                    {{ $nextTrip ? $nextTrip->format('d M Y') : __('None scheduled') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="h-px bg-[#E4E5E9] dark:bg-[#1E2433]"></div>

                <!-- Tags Section -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('Profile Tags') }}
                        </span>
                        <button type="button" wire:click="openEdit"
                            class="text-[11px] font-semibold text-[#12181E] dark:text-[#FFEF4D] hover:underline cursor-pointer">
                            {{ __('Edit') }}
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @if (is_array($guest->tags) && count($guest->tags) > 0)
                            @foreach ($guest->tags as $tag)
                                <span
                                    class="px-2 py-0.5 rounded-[6px] text-xs font-medium bg-[#F4F5F7] dark:bg-[#141821] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] inline-flex items-center gap-1">
                                    <i class="fa-solid fa-tag text-[9px] opacity-50"></i>
                                    <span>{{ $tag }}</span>
                                </span>
                            @endforeach
                        @else
                            <span class="text-xs text-[#5A6578] dark:text-[#9DA4B2] italic">{{ __('No tags assigned yet.') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- CRM Private Notes Card -->
            <div
                class="rounded-[12px] border border-[#E4E5E9] bg-white p-5 dark:border-[#1E2433] dark:bg-[#10141d] shadow-none space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-6 h-6 rounded-[6px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] flex items-center justify-center text-xs">
                            <i class="fa-solid fa-note-sticky"></i>
                        </div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('CRM Notes') }}
                        </h3>
                    </div>
                    <button type="button" wire:click="openEdit"
                        class="text-xs font-semibold text-[#12181E] dark:text-[#FFEF4D] hover:underline cursor-pointer">
                        {{ __('Edit') }}
                    </button>
                </div>

                @if ($guest->notes)
                    <div
                        class="p-3.5 rounded-[8px] bg-[#F4F5F7]/70 dark:bg-[#141821]/70 border border-[#E4E5E9] dark:border-[#1E2433] text-xs text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">
                        {{ $guest->notes }}
                    </div>
                @else
                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] italic">
                        {{ __('No notes recorded yet. Add internal remarks regarding guest preferences, VIP handling, or special requirements.') }}
                    </p>
                @endif
            </div>

            <!-- Possible Duplicates Section -->
            @if ($this->duplicates->isNotEmpty())
                <div class="rounded-[12px] border border-amber-300/80 bg-amber-50/70 p-5 dark:border-amber-900/50 dark:bg-amber-950/30 space-y-3 shadow-none"
                    role="status">
                    <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                        <i class="fa-solid fa-triangle-exclamation text-sm text-amber-600 dark:text-amber-400"></i>
                        <h4 class="text-xs font-semibold uppercase tracking-wider">
                            {{ __('Possible duplicates') }}
                        </h4>
                    </div>
                    <p class="text-xs text-amber-800/80 dark:text-amber-300/80 leading-relaxed">
                        {{ __('Found profiles with identical email or phone number. Merging moves all bookings into this profile and removes duplicates.') }}
                    </p>
                    <ul class="space-y-2">
                        @foreach ($this->duplicates as $duplicate)
                            <li
                                class="flex flex-col gap-2 rounded-[8px] border border-amber-200/80 bg-white/80 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/50 dark:bg-[#10141d]/80 shadow-none">
                                <div class="min-w-0 text-xs">
                                    <p class="font-semibold text-slate-900 dark:text-white truncate">
                                        {{ $duplicate->name }}</p>
                                    <p class="text-[#5A6578] dark:text-[#9DA4B2] truncate text-[11px] mt-0.5">
                                        {{ $duplicate->email ?: ($duplicate->phone ?: __('No contact')) }}
                                        &bull; {{ __(':count bookings', ['count' => $duplicate->reservations_count]) }}
                                    </p>
                                </div>
                                <x-button type="button" size="xs" variant="secondary"
                                    wire:click="mergeDuplicate('{{ $duplicate->id }}')"
                                    wire:confirm="{{ __('Merge this guest into :name? Their bookings move here and the duplicate is removed.', ['name' => $guest->name]) }}"
                                    class="shrink-0 font-semibold w-full sm:w-auto justify-center">
                                    {{ __('Merge into this guest') }}
                                </x-button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- RIGHT COLUMN: Metrics & Itemized Transactions Hub -->
        <div class="lg:col-span-8 xl:col-span-8 space-y-6">
            <!-- 4 KPI Metrics Row -->
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <x-metric-card :label="__('Lifetime Spend')" :value="'Rp ' . number_format($totalSpent, 0, ',', '.')" :hint="__('Paid bookings total')" icon="fa-rupiah-sign"
                    tone="warning" />
                <x-metric-card :label="__('Total Bookings')" :value="number_format($guest->reservations()->count())" :hint="__('All reservation trips')" icon="fa-ticket"
                    tone="neutral" />
                <x-metric-card :label="__('Total Pax')" :value="number_format($guest->total_pax)" :hint="__('Lifetime passenger headcount')" icon="fa-users" tone="info" />
                <x-metric-card :label="__('Paid Trips')" :value="number_format($guest->confirmed_bookings_count)" :hint="__('Confirmed or completed')" icon="fa-circle-check"
                    tone="success" />
            </div>

            <!-- Transactions & Trip History Hub -->
            <div
                class="rounded-[12px] border border-[#E4E5E9] bg-white dark:border-[#1E2433] dark:bg-[#10141d] shadow-none overflow-hidden">
                <!-- Header with Tabs -->
                <div
                    class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-[#F4F5F7]/50 dark:bg-[#141821]/50">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-9 h-9 rounded-[8px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] flex items-center justify-center text-sm font-semibold border border-[#FFEF4D]/40">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-900 dark:text-white">
                                    {{ __('Trip history') }}
                                </h2>
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-xs font-mono font-semibold bg-[#E4E5E9] dark:bg-[#1E2433] text-slate-700 dark:text-slate-300">
                                    {{ $this->reservations->total() }}
                                </span>
                            </div>
                            <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                                {{ __('Transactions, bookings & itinerary details') }}
                            </p>
                        </div>
                    </div>

                    <x-filter-tabs>
                        @foreach (['all' => __('All'), 'upcoming' => __('Upcoming'), 'past' => __('Past')] as $key => $label)
                            <x-filter-tab :active="$historyFilter === $key"
                                wire:click="$set('historyFilter', '{{ $key }}')">
                                {{ $label }}
                            </x-filter-tab>
                        @endforeach
                    </x-filter-tabs>
                </div>

                <!-- Streamlined Trip History List -->
                <div class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                    @forelse ($this->reservations as $res)
                        @php
                            $bookable = $res->bookable;
                            $payment = $res->latestPayment;
                            $resCode = $res->code ?? 'RSV-' . strtoupper(substr($res->id, -8));
                            $snap = $res->terms_snapshot ?? [];
                            $discountAmount = (float) ($snap['discount_amount'] ?? 0);
                            $couponCode = $snap['coupon_code'] ?? null;
                            $subtotal = (float) ($snap['subtotal'] ?? 0);
                            $totalAmount = $res->getChargedAmount();
                            $paidAmount = (float) $res->payments->where('status', PaymentStatus::Paid)->sum('amount');
                            $isPaid = $paidAmount >= $totalAmount && $totalAmount > 0;
                        @endphp
                        <div class="p-4 sm:p-4.5 hover:bg-[#F4F5F7]/60 dark:hover:bg-[#141821]/60 transition flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 group">
                            <!-- Left: Date Badge & Trip Details -->
                            <div class="flex items-start gap-3.5 min-w-0">
                                <!-- Calendar Date Block -->
                                <div class="w-11 h-11 rounded-[8px] bg-[#FFEF4D]/15 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 flex flex-col items-center justify-center shrink-0 font-mono text-[10px] leading-tight">
                                    <span class="font-bold text-xs">{{ $res->requested_date?->format('d') }}</span>
                                    <span class="text-[9px] uppercase font-semibold opacity-70">{{ $res->requested_date?->format('M') }}</span>
                                </div>

                                <!-- Information -->
                                <div class="min-w-0 space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ route('reservations.show', $res) }}" wire:navigate
                                            class="font-mono text-xs font-semibold text-slate-900 dark:text-[#FFEF4D] hover:underline">
                                            #{{ $resCode }}
                                        </a>
                                        <span class="text-slate-300 dark:text-zinc-600">&bull;</span>
                                        <a href="{{ route('reservations.show', $res) }}" wire:navigate
                                            class="font-semibold text-sm text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-[#FFEF4D] transition truncate max-w-xs sm:max-w-md"
                                            title="{{ $bookable->name ?? ($bookable->title ?? __('Direct Booking')) }}">
                                            {{ $bookable->name ?? ($bookable->title ?? __('Direct Booking')) }}
                                        </a>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                        <span><i class="fa-solid fa-users text-[10px] mr-1 text-[#5A6578] dark:text-[#9DA4B2]"></i>{{ __(':count Guests', ['count' => $res->pax_count]) }}</span>
                                        <span>&bull;</span>
                                        <span>{{ __('Booked :date', ['date' => $res->created_at->format('d M Y')]) }}</span>
                                        @if ($couponCode)
                                            <span>&bull;</span>
                                            <span class="inline-flex items-center gap-1 font-mono text-[11px] text-amber-600 dark:text-amber-400 font-semibold">
                                                <i class="fa-solid fa-tag text-[9px]"></i>
                                                {{ $couponCode }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Pricing, Status Badge & Action -->
                            <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2.5 sm:pt-0 border-t sm:border-t-0 border-[#E4E5E9] dark:border-[#1E2433]">
                                <div class="text-left sm:text-right font-mono min-w-0">
                                    <div class="font-bold text-sm text-slate-900 dark:text-white truncate">
                                        Rp {{ number_format($totalAmount, 0, ',', '.') }}
                                    </div>
                                    <div class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] flex items-center gap-1">
                                        @if ($discountAmount > 0)
                                            <span class="text-amber-600 dark:text-amber-400 font-semibold">-Rp {{ number_format($discountAmount, 0, ',', '.') }}</span>
                                            <span>&bull;</span>
                                        @endif
                                        <span class="{{ $isPaid ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-amber-600 dark:text-amber-400 font-medium' }}">
                                            {{ $isPaid ? __('Paid') : __('Unpaid') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <x-status-badge :status="$res->status" />

                                    <x-button size="xs" variant="secondary" :href="route('reservations.show', $res)" wire:navigate class="shrink-0 font-semibold">
                                        <span>{{ __('View') }}</span>
                                        <i class="fa-solid fa-chevron-right text-[9px] ml-1"></i>
                                    </x-button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                            <i class="fa-solid fa-calendar-xmark text-3xl mb-2 block opacity-40"></i>
                            <span
                                class="font-semibold text-sm text-slate-700 dark:text-slate-300 block">{{ __('No trips in this filter.') }}</span>
                            <p class="text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">{{ __('No bookings match the selected status filter.') }}
                            </p>
                        </div>
                    @endforelse
                </div>

                @if ($this->reservations->hasPages())
                    <div
                        class="border-t border-[#E4E5E9] px-5 py-3.5 dark:border-[#1E2433] bg-[#F4F5F7]/50 dark:bg-[#141821]/50">
                        {{ $this->reservations->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($showEditModal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-xs">
                <div @click.away="$wire.closeEdit()"
                    class="w-full max-w-lg rounded-t-[16px] sm:rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                    <!-- Mobile drawer top bar indicator -->
                    <div class="mx-auto my-1 h-1 w-10 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">{{ __('Edit guest') }}</h3>
                        <button type="button" wire:click="closeEdit"
                            class="h-8 w-8 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] text-[#5A6578] hover:text-slate-900 dark:text-[#9DA4B2] dark:hover:text-white flex items-center justify-center cursor-pointer transition">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                    <form wire:submit="saveGuest" class="space-y-4">
                        <div>
                            <x-label for="editName" :value="__('Name')" required />
                            <x-input id="editName" wire:model="editName" type="text" class="rounded-[6px] h-9 text-xs" />
                            <x-input-error :messages="$errors->get('editName')" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <x-label for="editEmail" :value="__('Email')" />
                                <x-input id="editEmail" wire:model="editEmail" type="email" class="rounded-[6px] h-9 text-xs" />
                                <x-input-error :messages="$errors->get('editEmail')" />
                            </div>
                            <div>
                                <x-label for="editPhone" :value="__('WhatsApp / Phone')" />
                                <x-input id="editPhone" wire:model="editPhone" type="text" class="rounded-[6px] h-9 text-xs" />
                                <x-input-error :messages="$errors->get('editPhone')" />
                            </div>
                        </div>
                        <div>
                            <x-label for="editTagsInput" :value="__('Tags (comma separated)')" />
                            <x-input id="editTagsInput" wire:model="editTagsInput" type="text" class="rounded-[6px] h-9 text-xs"
                                placeholder="VIP, Vegetarian" />
                        </div>
                        <div>
                            <x-label for="editNotes" :value="__('CRM Notes')" />
                            <x-textarea id="editNotes" wire:model="editNotes" rows="4" class="rounded-[6px] text-xs" />
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <x-button type="button" variant="secondary" size="sm"
                                wire:click="closeEdit">{{ __('Cancel') }}</x-button>
                            <x-button type="submit" variant="primary" size="sm">{{ __('Save') }}</x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
