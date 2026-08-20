<?php

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Agent;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bookings & Reservations')] class extends Component {
    public string $search = '';
    public string $statusFilter = 'all';
    public string $dateFilter = 'all'; // all, upcoming, past, this_month

    // Detail Modal / Slide-over State
    public bool $showDetailModal = false;
    public ?string $selectedReservationId = null;
    public string $agentNote = '';

    public bool $actionSuccess = false;
    public string $actionMessage = '';

    #[Computed]
    public function currentAgent(): ?Agent
    {
        return Auth::user()?->currentAgent();
    }

    /**
     * Get real-time metric counters for current agent.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function metrics(): array
    {
        if (! $this->currentAgent) {
            return [
                'total' => 0,
                'confirmed' => 0,
                'pending' => 0,
                'completed' => 0,
                'revenue' => 0,
            ];
        }

        $base = $this->currentAgent->reservations();

        $total = (clone $base)->count();
        $confirmed = (clone $base)->where('status', ReservationStatus::Confirmed->value)->count();
        $pendingConfirmation = (clone $base)->where('status', ReservationStatus::PendingConfirmation->value)->count();
        $pending = (clone $base)->where('status', ReservationStatus::PaymentPending->value)->count();
        $completed = (clone $base)->where('status', ReservationStatus::Completed->value)->count();

        // Calculate revenue from paid payments attached to this agent's reservations
        $revenue = (float) \App\Models\Payment::query()
            ->whereHas('reservation', fn (Builder $q) => $q->where('agent_id', $this->currentAgent->id))
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        return [
            'total' => $total,
            'confirmed' => $confirmed,
            'pending_confirmation' => $pendingConfirmation,
            'pending' => $pending,
            'completed' => $completed,
            'revenue' => $revenue,
        ];
    }

    /**
     * Get filtered reservations for the agent.
     *
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function reservations(): Collection
    {
        if (! $this->currentAgent) {
            return new Collection();
        }

        $query = $this->currentAgent->reservations()
            ->with(['bookable', 'latestPayment', 'payments'])
            ->latest('requested_date');

        // Status Filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Date Filter
        if ($this->dateFilter === 'upcoming') {
            $query->where('requested_date', '>=', now()->toDateString());
        } elseif ($this->dateFilter === 'past') {
            $query->where('requested_date', '<', now()->toDateString());
        } elseif ($this->dateFilter === 'this_month') {
            $query->whereMonth('requested_date', now()->month)
                ->whereYear('requested_date', now()->year);
        }

        // Search Filter
        if (trim($this->search) !== '') {
            $search = '%' . trim($this->search) . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('code', 'like', $search)
                    ->orWhere('guest_name', 'like', $search)
                    ->orWhere('guest_contact', 'like', $search)
                    ->orWhere('guest_email', 'like', $search)
                    ->orWhere('id', 'like', $search)
                    ->orWhere('notes', 'like', $search);
            });
        }

        return $query->get();
    }

    #[Computed]
    public function selectedReservation(): ?Reservation
    {
        if (! $this->selectedReservationId || ! $this->currentAgent) {
            return null;
        }

        return $this->currentAgent->reservations()
            ->with(['bookable', 'latestPayment', 'payments'])
            ->find($this->selectedReservationId);
    }

    /**
     * Open reservation details drawer.
     */
    public function viewDetails(string $reservationId): void
    {
        $this->selectedReservationId = $reservationId;
        $res = $this->selectedReservation;

        if ($res) {
            $this->agentNote = (string) ($res->notes ?? '');
            $this->showDetailModal = true;
        }
    }

    /**
     * Close reservation details drawer.
     */
    public function closeDetails(): void
    {
        $this->showDetailModal = false;
        $this->selectedReservationId = null;
    }

    /**
     * Transition reservation status.
     */
    public function updateStatus(string $reservationId, string $statusValue): void
    {
        if (! $this->currentAgent) {
            return;
        }

        $res = $this->currentAgent->reservations()->find($reservationId);

        if (! $res) {
            return;
        }

        $status = ReservationStatus::tryFrom($statusValue);

        if (! $status) {
            return;
        }

        $res->update([
            'status' => $status,
            'hold_expires_at' => $status === ReservationStatus::PaymentPending ? now()->addMinutes(30) : null,
        ]);

        $this->actionSuccess = true;
        $this->actionMessage = __('Reservation status updated to :status', ['status' => $status->label()]);
        $this->dispatch('reservation-updated');
    }

    /**
     * Save internal notes for reservation.
     */
    public function saveNotes(): void
    {
        $res = $this->selectedReservation;

        if (! $res) {
            return;
        }

        $res->update(['notes' => $this->agentNote]);

        $this->actionSuccess = true;
        $this->actionMessage = __('Reservation notes saved successfully.');
    }
}; ?>

<div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400">
                    <i class="fa-solid fa-calendar-check text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Bookings & Reservations') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Track, confirm, and manage direct guest reservations, payment receipts, and trip schedules.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Status Filter Pills -->
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                {{ __('Showing :count reservations', ['count' => $this->reservations->count()]) }}
            </span>
        </div>
    </div>

    <!-- Metric Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Confirmed -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Confirmed Trips') }}
                </span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-xs">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
                {{ number_format($this->metrics['confirmed']) }}
            </p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ __('Active & upcoming reservations') }}
            </p>
        </div>

        <!-- Card 2: Pending Holds -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Pending Holds') }}
                </span>
                <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 text-xs">
                    <i class="fa-solid fa-clock"></i>
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400">
                {{ number_format($this->metrics['pending']) }}
            </p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ __('Awaiting checkout payment (30m hold)') }}
            </p>
        </div>

        <!-- Card 3: Completed Trips -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Completed') }}
                </span>
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 text-xs">
                    <i class="fa-solid fa-flag-checkered"></i>
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
                {{ number_format($this->metrics['completed']) }}
            </p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ __('Fulfilled experiences & reviews ready') }}
            </p>
        </div>

        <!-- Card 4: Total Revenue -->
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Paid Revenue') }}
                </span>
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 text-xs">
                    <i class="fa-solid fa-rupiah-sign"></i>
                </span>
            </div>
            <p class="text-xl sm:text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 truncate">
                Rp {{ number_format($this->metrics['revenue'], 0, ',', '.') }}
            </p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ __('Direct guest payments collected') }}
            </p>
        </div>
    </div>

    <!-- Search & Filter Controls Card -->
    <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <!-- Search Bar -->
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </div>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="{{ __('Search by guest name, phone, email, or reservation ID...') }}"
                    class="h-10 w-full pl-9 pr-4 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                />
                @if ($search !== '')
                    <button
                        wire:click="$set('search', '')"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                @endif
            </div>

            <!-- Date Filter Dropdown -->
            <div class="flex items-center gap-2">
                <select
                    wire:model.live="dateFilter"
                    class="h-10 px-3 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800 text-xs font-semibold text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                >
                    <option value="all">{{ __('All Trip Dates') }}</option>
                    <option value="upcoming">{{ __('Upcoming Trips Only') }}</option>
                    <option value="this_month">{{ __('This Month') }}</option>
                    <option value="past">{{ __('Past / Completed') }}</option>
                </select>
            </div>
        </div>

        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 border-t border-slate-100 dark:border-zinc-800 pt-3">
            @php
                $statusTabs = [
                    'all' => __('All (:count)', ['count' => $this->metrics['total']]),
                    'pending_confirmation' => __('Needs Confirmation (:count)', ['count' => $this->metrics['pending_confirmation']]),
                    'confirmed' => __('Confirmed (:count)', ['count' => $this->metrics['confirmed']]),
                    'payment_pending' => __('Pending Payment (:count)', ['count' => $this->metrics['pending']]),
                    'completed' => __('Completed (:count)', ['count' => $this->metrics['completed']]),
                    'declined' => __('Declined'),
                    'cancelled' => __('Cancelled'),
                    'expired' => __('Expired'),
                ];
            @endphp

            @foreach ($statusTabs as $tabKey => $tabLabel)
                <button
                    type="button"
                    wire:click="$set('statusFilter', '{{ $tabKey }}')"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer {{ $statusFilter === $tabKey ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800' }}"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Reservations Data Table -->
    <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-zinc-800/60 border-b border-slate-200/80 dark:border-zinc-800 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Guest & Contact') }}</th>
                        <th class="px-4 py-3.5">{{ __('Booked Experience') }}</th>
                        <th class="px-4 py-3.5">{{ __('Trip Date') }}</th>
                        <th class="px-4 py-3.5">{{ __('Payment') }}</th>
                        <th class="px-4 py-3.5">{{ __('Status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($this->reservations as $res)
                        @php
                            $bookable = $res->bookable;
                            $latestPayment = $res->latestPayment;
                            $resCode = $res->code ?? ('RSV-' . strtoupper(substr($res->id, -8)));
                            $cleanPhone = preg_replace('/[^0-9]/', '', $res->guest_contact);
                            if (str_starts_with($cleanPhone, '0')) {
                                $cleanPhone = '62' . substr($cleanPhone, 1);
                            }
                            $waUrl = 'https://wa.me/' . $cleanPhone . '?text=' . urlencode(__('Hello :name, reaching out regarding your reservation (:code) with :agent', ['name' => $res->guest_name, 'code' => $resCode, 'agent' => $this->currentAgent->name]));
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/40 transition group">
                            <!-- Guest Info -->
                            <td class="px-5 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 dark:text-white">
                                            {{ $res->guest_name }}
                                        </span>
                                        <span class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60">
                                            #{{ $resCode }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        @if ($res->guest_contact)
                                            <a href="{{ $waUrl }}" target="_blank" class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 hover:underline font-semibold" title="{{ __('Chat on WhatsApp') }}">
                                                <i class="fa-brands fa-whatsapp text-xs"></i>
                                                {{ $res->guest_contact }}
                                            </a>
                                        @endif
                                        @if ($res->guest_email)
                                            <span class="text-slate-400 truncate max-w-[140px]">{{ $res->guest_email }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Booked Experience -->
                            <td class="px-4 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5">
                                        @if ($res->bookable_type === 'package' || $res->bookable_type === \App\Models\Package::class)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                                {{ __('Package') }}
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                                {{ __('Product') }}
                                            </span>
                                        @endif
                                        <p class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[200px]">
                                            {{ $bookable->name ?? ($bookable->title ?? __('Custom Booking')) }}
                                        </p>
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                        <i class="fa-solid fa-users text-[10px]"></i>
                                        {{ __(':count Guests (Pax)', ['count' => $res->pax_count]) }}
                                    </p>
                                </div>
                            </td>

                            <!-- Trip Date -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="space-y-0.5">
                                    <p class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ $res->requested_date->format('M d, Y') }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ $res->requested_date->format('l') }}
                                    </p>
                                </div>
                            </td>

                            <!-- Payment Status -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="space-y-1">
                                    @if ($latestPayment && $latestPayment->isPaid())
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                                            {{ __('Paid') }}
                                        </span>
                                        <p class="font-bold text-xs text-slate-900 dark:text-white">
                                            Rp {{ number_format((float) $latestPayment->amount, 0, ',', '.') }}
                                        </p>
                                    @elseif ($latestPayment && $latestPayment->status->value === 'pending')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            <i class="fa-solid fa-clock text-[10px]"></i>
                                            {{ __('Unpaid') }}
                                        </span>
                                        <p class="font-bold text-xs text-slate-500">
                                            Rp {{ number_format((float) $latestPayment->amount, 0, ',', '.') }}
                                        </p>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-slate-400">
                                            {{ __('No Payment') }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Reservation Status -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if ($res->status === ReservationStatus::Confirmed)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ __('Confirmed') }}
                                    </span>
                                @elseif ($res->status === ReservationStatus::PendingConfirmation)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-200 dark:border-amber-800 animate-pulse">
                                        <i class="fa-solid fa-hourglass-half text-[10px]"></i>
                                        {{ __('Needs Confirmation') }}
                                    </span>
                                @elseif ($res->status === ReservationStatus::PaymentPending)
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300 border border-slate-200 dark:border-zinc-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-pulse"></span>
                                            {{ __('Payment Hold') }}
                                        </span>
                                        @if ($res->hold_expires_at && $res->hold_expires_at->isFuture())
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono pl-1">
                                                {{ __('Expires in :mins min', ['mins' => now()->diffInMinutes($res->hold_expires_at)]) }}
                                            </p>
                                        @endif
                                    </div>
                                @elseif ($res->status === ReservationStatus::Completed)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                        <i class="fa-solid fa-flag-checkered text-[10px]"></i>
                                        {{ __('Completed') }}
                                    </span>
                                @elseif ($res->status === ReservationStatus::Declined)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        <i class="fa-solid fa-xmark text-[10px]"></i>
                                        {{ __('Declined') }}
                                    </span>
                                @elseif ($res->status === ReservationStatus::Cancelled)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        <i class="fa-solid fa-ban text-[10px]"></i>
                                        {{ __('Cancelled') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-slate-400">
                                        {{ $res->status->label() }}
                                    </span>
                                @endif
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if ($res->status === ReservationStatus::PendingConfirmation)
                                        <button
                                            type="button"
                                            wire:click="updateStatus('{{ $res->id }}', 'confirmed')"
                                            class="h-8 px-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1 cursor-pointer"
                                            title="{{ __('Confirm Booking') }}"
                                        >
                                            <i class="fa-solid fa-check text-[10px]"></i>
                                            <span>{{ __('Confirm') }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="updateStatus('{{ $res->id }}', 'declined')"
                                            class="h-8 px-2 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/50 font-bold text-xs transition cursor-pointer"
                                            title="{{ __('Decline Booking') }}"
                                        >
                                            <i class="fa-solid fa-xmark text-[10px]"></i>
                                        </button>
                                    @endif

                                    <x-button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        wire:click="viewDetails('{{ $res->id }}')"
                                        class="h-8 text-xs font-bold"
                                    >
                                        <i class="fa-solid fa-eye mr-1 text-[10px]"></i>
                                        {{ __('Details') }}
                                    </x-button>

                                    <!-- Quick Action Dropdown Menu -->
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition cursor-pointer"
                                        >
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <div
                                            x-show="open"
                                            @click.away="open = false"
                                            x-cloak
                                            class="absolute right-0 z-20 mt-1 w-44 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-lg py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            @if ($res->status !== ReservationStatus::Confirmed)
                                                <button
                                                    type="button"
                                                    wire:click="updateStatus('{{ $res->id }}', 'confirmed')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400"
                                                >
                                                    <i class="fa-solid fa-circle-check w-4"></i>
                                                    {{ __('Mark Confirmed') }}
                                                </button>
                                            @endif
                                            @if ($res->status !== ReservationStatus::Declined)
                                                <button
                                                    type="button"
                                                    wire:click="updateStatus('{{ $res->id }}', 'declined')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-rose-600 dark:text-rose-400"
                                                >
                                                    <i class="fa-solid fa-ban w-4"></i>
                                                    {{ __('Decline Booking') }}
                                                </button>
                                            @endif
                                            @if ($res->status !== ReservationStatus::Completed)
                                                <button
                                                    type="button"
                                                    wire:click="updateStatus('{{ $res->id }}', 'completed')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400"
                                                >
                                                    <i class="fa-solid fa-flag-checkered w-4"></i>
                                                    {{ __('Mark Completed') }}
                                                </button>
                                            @endif
                                            @if ($res->status !== ReservationStatus::Cancelled)
                                                <button
                                                    type="button"
                                                    wire:click="updateStatus('{{ $res->id }}', 'cancelled')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-slate-100 dark:hover:bg-zinc-800 text-slate-600 dark:text-slate-400"
                                                >
                                                    <i class="fa-solid fa-xmark w-4"></i>
                                                    {{ __('Cancel Reservation') }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-12 text-center">
                                <div class="max-w-sm mx-auto space-y-3">
                                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto text-xl">
                                        <i class="fa-solid fa-calendar-xmark"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ __('No reservations found') }}
                                    </h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $search !== '' || $statusFilter !== 'all' || $dateFilter !== 'all' ? __('Try clearing filters or search queries to see other bookings.') : __('When guests book packages or standalone items from your storefront, they will appear here.') }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Slide-over / Detail Modal -->
    @if ($showDetailModal && $this->selectedReservation)
        @php
            $res = $this->selectedReservation;
            $bookable = $res->bookable;
            $latestPayment = $res->latestPayment;
            $cleanPhone = preg_replace('/[^0-9]/', '', $res->guest_contact);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62' . substr($cleanPhone, 1);
            }
            $resCode = $res->code ?? ('RSV-' . strtoupper(substr($res->id, -8)));
            $waUrl = 'https://wa.me/' . $cleanPhone . '?text=' . urlencode(__('Hello :name, reaching out regarding your reservation (:code) with :agent', ['name' => $res->guest_name, 'code' => $resCode, 'agent' => $this->currentAgent->name]));
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto bg-slate-900/60 backdrop-blur-xs">
            <div
                @click.away="$wire.closeDetails()"
                class="w-full max-w-2xl rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden space-y-6 animate-scale-up"
            >
                <!-- Modal Header -->
                <div class="p-6 bg-slate-50 dark:bg-zinc-800/60 border-b border-slate-200 dark:border-zinc-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="p-2.5 rounded-xl bg-indigo-600 text-white text-base">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                                    {{ __('Reservation Details') }}
                                </h3>
                                <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60">
                                    #{{ $resCode }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Created on :date', ['date' => $res->created_at?->format('M d, Y H:i') ?? '—']) }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeDetails"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-200/50 dark:hover:bg-zinc-800 transition cursor-pointer"
                    >
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                    <!-- Guest & Experience Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Guest Info Box -->
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-800 space-y-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Guest Contact') }}</span>
                            <p class="font-bold text-sm text-slate-900 dark:text-white">{{ $res->guest_name }}</p>
                            <div class="space-y-1 text-xs">
                                <p class="text-slate-600 dark:text-slate-300 flex items-center gap-2">
                                    <i class="fa-solid fa-envelope text-slate-400 w-4"></i>
                                    {{ $res->guest_email ?? __('No email provided') }}
                                </p>
                                <p class="text-slate-600 dark:text-slate-300 flex items-center gap-2">
                                    <i class="fa-brands fa-whatsapp text-emerald-500 w-4"></i>
                                    <a href="{{ $waUrl }}" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:underline font-bold">
                                        {{ $res->guest_contact }}
                                    </a>
                                </p>
                            </div>
                        </div>

                        <!-- Trip Schedule Box -->
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-800 space-y-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Schedule & Guests') }}</span>
                            <p class="font-bold text-sm text-slate-900 dark:text-white">
                                {{ $res->requested_date->format('l, F d, Y') }}
                            </p>
                            <p class="text-xs text-slate-600 dark:text-slate-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-users text-indigo-500"></i>
                                {{ __(':count Guests (Pax)', ['count' => $res->pax_count]) }}
                            </p>
                            <div class="pt-1">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $res->status === ReservationStatus::Confirmed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                    {{ $res->status->label() }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Booked Package / Product Overview -->
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-zinc-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Booked Entity') }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $res->bookable_type === 'package' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
                                {{ $res->bookable_type === 'package' ? 'Package Itinerary' : 'Standalone Product' }}
                            </span>
                        </div>
                        <h4 class="font-bold text-base text-slate-900 dark:text-white">
                            {{ $bookable->name ?? ($bookable->title ?? __('Direct Experience Item')) }}
                        </h4>
                        @if ($bookable && !empty($bookable->description))
                            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
                                {{ $bookable->description }}
                            </p>
                        @endif
                    </div>

                    <!-- Payment Details Card -->
                    <div class="p-4 rounded-2xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/60 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-credit-card text-indigo-600 dark:text-indigo-400"></i>
                                {{ __('Payment & Settlement') }}
                            </span>
                            @if ($latestPayment && $latestPayment->isPaid())
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ __('Paid & Settled') }}
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                    {{ __('Payment Pending') }}
                                </span>
                            @endif
                        </div>

                        @if ($latestPayment)
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-1 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400">{{ __('Total Amount') }}</span>
                                    <p class="font-extrabold text-sm text-slate-900 dark:text-white">
                                        Rp {{ number_format((float) $latestPayment->amount, 0, ',', '.') }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400">{{ __('Payment Gateway') }}</span>
                                    <p class="font-bold text-slate-800 dark:text-slate-200 uppercase">
                                        {{ $latestPayment->gateway }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400">{{ __('Gateway Ref') }}</span>
                                    <p class="font-mono text-[11px] text-slate-600 dark:text-slate-400 truncate">
                                        {{ $latestPayment->gateway_ref ?? '—' }}
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Internal Agent Notes -->
                    <div class="space-y-2">
                        <x-label for="agentNote" :value="__('Internal Reservation Notes')" />
                        <x-textarea id="agentNote" wire:model="agentNote" rows="3" placeholder="{{ __('Add special dietary requests, pickup instructions, boat assignments...') }}" class="text-xs" />
                        <div class="flex justify-end">
                            <x-button size="sm" variant="secondary" wire:click="saveNotes" class="font-bold text-xs">
                                <i class="fa-solid fa-floppy-disk mr-1"></i>
                                {{ __('Save Notes') }}
                            </x-button>
                        </div>
                    </div>

                    <!-- Agreed Terms Snapshot -->
                    @if (!empty($res->terms_snapshot))
                        <div x-data="{ openTerms: false }" class="pt-3 border-t border-slate-100 dark:border-zinc-800 space-y-2">
                            <button type="button" @click="openTerms = !openTerms" class="w-full flex items-center justify-between text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                                <span class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-indigo-500"></i>
                                    {{ __('Agreed Storefront Terms Snapshot (Frozen at booking)') }}
                                </span>
                                <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" :class="openTerms ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="openTerms" x-cloak class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200 dark:border-zinc-800 text-xs text-slate-600 dark:text-slate-400 font-mono whitespace-pre-wrap max-h-40 overflow-y-auto">
                                {{ is_array($res->terms_snapshot) ? json_encode($res->terms_snapshot, JSON_PRETTY_PRINT) : $res->terms_snapshot }}
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="p-5 bg-slate-50 dark:bg-zinc-800/60 border-t border-slate-200 dark:border-zinc-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        @if ($res->status !== ReservationStatus::Confirmed)
                            <x-button size="sm" variant="primary" wire:click="updateStatus('{{ $res->id }}', 'confirmed')" class="font-bold text-xs bg-emerald-600 hover:bg-emerald-700 text-white">
                                <i class="fa-solid fa-circle-check mr-1"></i>
                                {{ __('Mark Confirmed') }}
                            </x-button>
                        @endif
                        @if ($res->status !== ReservationStatus::Declined && $res->status !== ReservationStatus::Completed)
                            <x-button size="sm" variant="danger" wire:click="updateStatus('{{ $res->id }}', 'declined')" class="font-bold text-xs">
                                <i class="fa-solid fa-ban mr-1"></i>
                                {{ __('Decline') }}
                            </x-button>
                        @endif
                        @if ($res->status !== ReservationStatus::Completed)
                            <x-button size="sm" variant="secondary" wire:click="updateStatus('{{ $res->id }}', 'completed')" class="font-bold text-xs">
                                <i class="fa-solid fa-flag-checkered mr-1 text-indigo-500"></i>
                                {{ __('Mark Completed') }}
                            </x-button>
                        @endif
                    </div>

                    <x-button size="sm" variant="secondary" wire:click="closeDetails" class="font-bold text-xs">
                        {{ __('Close') }}
                    </x-button>
                </div>
            </div>
        </div>
    @endif
</div>
