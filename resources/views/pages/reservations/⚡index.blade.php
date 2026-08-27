<?php

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Reservation;
use App\Concerns\ResolvesCurrentOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bookings & Reservations')] class extends Component {
    use ResolvesCurrentOperator;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $dateFilter = 'all'; // all, upcoming, past, this_month

    // Detail Modal / Slide-over State
    public bool $showDetailModal = false;
    public ?string $selectedReservationId = null;
    public string $agentNote = '';

    public bool $actionSuccess = false;
    public string $actionMessage = '';

    // Payment Link Creation Form State
    public bool $showCreateLinkModal = false;
    public string $createExperienceSelection = '';
    public string $createBookableType = 'package'; // 'package' or 'product'
    public ?string $createBookableId = null;
    public string $createRequestedDate = '';
    public int $createPaxCount = 1;
    public string $createGuestName = '';
    public string $createGuestContact = '';
    public string $createGuestEmail = '';
    public string $createNotes = '';
    public ?string $generatedPaymentUrl = null;
    public ?string $generatedWhatsAppUrl = null;
    public ?string $generatedReservationCode = null;
    public bool $linkCreatedSuccessfully = false;

    public function mount(): void
    {
        if (request()->has('create') || request()->boolean('create_link')) {
            $this->openCreateLinkModal();
        }
    }

    #[On('open-create-booking-link')]
    public function handleOpenCreateLink(): void
    {
        $this->openCreateLinkModal();
    }


    #[Computed]
    public function estimatedTotal(): float
    {
        if (!$this->currentOperator || empty($this->createBookableId)) {
            return 0.0;
        }

        $bookable = $this->createBookableType === 'package' ? $this->currentOperator->packages()->find($this->createBookableId) : $this->currentOperator->products()->find($this->createBookableId);

        if (!$bookable) {
            return 0.0;
        }

        $unitPrice = (float) $bookable->price;
        $subtotal = $unitPrice * max(1, $this->createPaxCount);
        $serviceFee = $subtotal * 0.05;

        return $subtotal + $serviceFee;
    }

    /**
     * @return Collection<int, \App\Models\Package>
     */
    #[Computed]
    public function availablePackages(): Collection
    {
        return $this->currentOperator?->packages()->where('status', 'published')->get() ?? new Collection();
    }

    /**
     * @return Collection<int, \App\Models\Product>
     */
    #[Computed]
    public function availableProducts(): Collection
    {
        return $this->currentOperator?->products()->where('status', 'published')->get() ?? new Collection();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function experienceOptions(): array
    {
        $options = [];

        foreach ($this->availablePackages as $pkg) {
            $options[] = [
                'value' => "package:{$pkg->id}",
                'label' => "{$pkg->title} — Rp " . number_format((float) $pkg->price, 0, ',', '.') . '/pax',
            ];
        }

        foreach ($this->availableProducts as $prod) {
            $options[] = [
                'value' => "product:{$prod->id}",
                'label' => "{$prod->name} — Rp " . number_format((float) $prod->price, 0, ',', '.') . '/pax',
            ];
        }

        return $options;
    }

    public function updatedCreateExperienceSelection(string $value): void
    {
        if (str_contains($value, ':')) {
            [$type, $id] = explode(':', $value, 2);
            $this->createBookableType = $type;
            $this->createBookableId = $id;
        }
    }

    /**
     * Open Quick Booking & Payment Link Creator Modal.
     */
    public function openCreateLinkModal(): void
    {
        $this->showCreateLinkModal = true;
        $this->linkCreatedSuccessfully = false;
        $this->generatedPaymentUrl = null;
        $this->generatedWhatsAppUrl = null;
        $this->generatedReservationCode = null;
        $this->createRequestedDate = now()->addDay()->toDateString();
        $this->createPaxCount = 1;
        $this->createGuestName = '';
        $this->createGuestContact = '';
        $this->createGuestEmail = '';
        $this->createNotes = '';

        $options = $this->experienceOptions;
        if (!empty($options)) {
            $this->createExperienceSelection = (string) $options[0]['value'];
            [$this->createBookableType, $this->createBookableId] = explode(':', $options[0]['value'], 2);
        } else {
            $this->createExperienceSelection = '';
            $this->createBookableType = 'package';
            $this->createBookableId = null;
        }
    }

    /**
     * Close Quick Booking Link Modal.
     */
    public function closeCreateLinkModal(): void
    {
        $this->showCreateLinkModal = false;
        $this->linkCreatedSuccessfully = false;
    }

    /**
     * Generate reservation, 30-min hold session, and WhatsApp payment invitation.
     */
    public function generateBookingLink(\App\Services\DokuPaymentService $paymentService, \App\Services\WhatsAppDispatchService $waService): void
    {
        if ($this->createExperienceSelection && str_contains($this->createExperienceSelection, ':')) {
            [$type, $id] = explode(':', $this->createExperienceSelection, 2);
            $this->createBookableType = $type;
            $this->createBookableId = $id;
        }

        $this->validate([
            'createBookableType' => ['required', 'in:package,product'],
            'createBookableId' => ['required', 'string'],
            'createRequestedDate' => ['required', 'date', 'after_or_equal:today'],
            'createPaxCount' => ['required', 'integer', 'min:1', 'max:50'],
            'createGuestName' => ['required', 'string', 'max:255'],
            'createGuestContact' => ['required', 'string', 'min:8', 'max:30'],
            'createGuestEmail' => ['nullable', 'email', 'max:255'],
            'createNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!$this->currentOperator) {
            return;
        }

        /** @var \App\Models\Package|\App\Models\Product|null $bookable */
        $bookable = $this->createBookableType === 'package' ? $this->currentOperator->packages()->find($this->createBookableId) : $this->currentOperator->products()->find($this->createBookableId);

        if (!$bookable) {
            $this->addError('createBookableId', __('Please select a valid experience.'));
            return;
        }

        $unitPrice = (float) ($bookable->price ?? 0);
        $subtotal = $unitPrice * $this->createPaxCount;
        $serviceFeeRate = \App\Models\PlatformSetting::current()->getGuestServiceFeeRate();
        $isEnterprise = $this->currentOperator->plan?->slug === 'enterprise';
        $serviceFee = $isEnterprise ? 0 : round($subtotal * $serviceFeeRate);
        $totalPrice = $subtotal + $serviceFee;

        $termsSnapshot = $bookable->generateTermsSnapshot();
        $termsSnapshot['unit_price'] = $unitPrice;
        $termsSnapshot['pax_count'] = $this->createPaxCount;
        $termsSnapshot['subtotal'] = $subtotal;
        $termsSnapshot['service_fee'] = $serviceFee;
        $termsSnapshot['service_fee_rate'] = $isEnterprise ? 0 : $serviceFeeRate;
        $termsSnapshot['total_price'] = $totalPrice;

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->create([
            'bookable_type' => $this->createBookableType,
            'bookable_id' => $bookable->id,
            'operator_id' => $this->currentOperator->id,
            'guest_name' => $this->createGuestName,
            'guest_contact' => $this->createGuestContact,
            'guest_email' => $this->createGuestEmail ?: null,
            'requested_date' => $this->createRequestedDate,
            'pax_count' => $this->createPaxCount,
            'notes' => $this->createNotes ?: null,
            'terms_snapshot' => $termsSnapshot,
            'status' => ReservationStatus::PaymentPending,
            'hold_expires_at' => now()->addMinutes(30),
        ]);

        $session = $paymentService->createPaymentSession($reservation, $totalPrice);
        $this->generatedPaymentUrl = $session['checkout_url'];
        $this->generatedReservationCode = $reservation->code ?? 'RSV-' . strtoupper(substr($reservation->id, -8));

        $itemTitle = $bookable instanceof \App\Models\Package ? $bookable->title : $bookable->name;
        $dateFormatted = Carbon::parse($this->createRequestedDate)->format('d M Y');
        $waText = "Halo Kak {$this->createGuestName}, berikut link pesanan & pembayaran untuk *{$itemTitle}* tanggal *{$dateFormatted}* ({$this->createPaxCount} pax).\n\nTotal: Rp " . number_format($totalPrice, 0, ',', '.') . "\n\nSilakan cek detail dan selesaikan pembayaran sebelum slot hold 30 menit berakhir:\n{$this->generatedPaymentUrl}";
        $this->generatedWhatsAppUrl = $waService->buildWhatsAppUrl($this->createGuestContact, $waText);

        $this->linkCreatedSuccessfully = true;
        $this->actionSuccess = true;
        $this->actionMessage = __('Booking link created successfully.');
        $this->dispatch('reservation-updated');
    }

    /**
     * Get real-time metric counters for current agent.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function metrics(): array
    {
        if (!$this->currentOperator) {
            return [
                'total' => 0,
                'confirmed' => 0,
                'pending' => 0,
                'completed' => 0,
                'revenue' => 0,
            ];
        }

        $base = $this->currentOperator->reservations();

        $total = (clone $base)->count();
        $confirmed = (clone $base)->where('status', ReservationStatus::Confirmed->value)->count();
        $pendingConfirmation = (clone $base)->where('status', ReservationStatus::PendingConfirmation->value)->count();
        $pending = (clone $base)->where('status', ReservationStatus::PaymentPending->value)->count();
        $completed = (clone $base)->where('status', ReservationStatus::Completed->value)->count();

        // Calculate revenue from paid payments attached to this operator's reservations
        $revenue = (float) \App\Models\Payment::query()->whereHas('reservation', fn(Builder $q) => $q->where('operator_id', $this->currentOperator->id))->where('status', PaymentStatus::Paid->value)->sum('amount');

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
        if (!$this->currentOperator) {
            return new Collection();
        }

        $query = $this->currentOperator->reservations()->with(['bookable', 'latestPayment', 'payments']);

        // Status Filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $today = now()->toDateString();

        // Date Filter & Incoming Trip Sorting
        if ($this->dateFilter === 'upcoming') {
            $query->where('requested_date', '>=', $today)->orderBy('requested_date', 'asc')->orderBy('created_at', 'desc');
        } elseif ($this->dateFilter === 'past') {
            $query->where('requested_date', '<', $today)->orderBy('requested_date', 'desc')->orderBy('created_at', 'desc');
        } elseif ($this->dateFilter === 'this_month') {
            $query->whereMonth('requested_date', now()->month)->whereYear('requested_date', now()->year)->orderBy('requested_date', 'asc');
        } else {
            // 'all': prioritize upcoming incoming trips first, then past trips
            $query
                ->orderByRaw("CASE WHEN requested_date >= '{$today}' THEN 0 ELSE 1 END")
                ->orderBy('requested_date', 'asc')
                ->orderBy('created_at', 'desc');
        }

        // Search Filter
        if (trim($this->search) !== '') {
            $search = '%' . trim($this->search) . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('code', 'like', $search)->orWhere('guest_name', 'like', $search)->orWhere('guest_contact', 'like', $search)->orWhere('guest_email', 'like', $search)->orWhere('id', 'like', $search);
            });
        }

        return $query->get();
    }

    #[Computed]
    public function selectedReservation(): ?Reservation
    {
        if (!$this->selectedReservationId || !$this->currentOperator) {
            return null;
        }

        return $this->currentOperator
            ->reservations()
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
     * Synchronize live payment status with DOKU payment gateway.
     */
    public function syncPaymentStatus(string $reservationId): void
    {
        if (!$this->currentOperator) {
            return;
        }

        $res = $this->currentOperator->reservations()->with('latestPayment')->find($reservationId);

        if (!$res || !$res->latestPayment) {
            return;
        }

        $synced = app(\App\Services\DokuPaymentService::class)->syncPaymentStatus($res->latestPayment);

        if ($synced) {
            session()->flash('success', __('Payment status verified with DOKU and reservation updated!'));
        } else {
            session()->flash('info', __('Queried DOKU: No payment capture update yet or status unchanged.'));
        }
    }

    public bool $showConfirmStatusModal = false;
    public ?string $statusActionReservationId = null;
    public ?string $pendingStatusValue = null;

    #[Computed]
    public function pendingStatusReservation(): ?\App\Models\Reservation
    {
        if (!$this->statusActionReservationId || !$this->currentOperator) {
            return null;
        }

        return $this->currentOperator->reservations()->with('bookable')->find($this->statusActionReservationId);
    }

    public function confirmStatusTransition(string $reservationId, string $statusValue): void
    {
        $this->statusActionReservationId = $reservationId;
        $this->pendingStatusValue = $statusValue;
        $this->showConfirmStatusModal = true;
    }

    public function closeConfirmStatusModal(): void
    {
        $this->showConfirmStatusModal = false;
        $this->statusActionReservationId = null;
        $this->pendingStatusValue = null;
    }

    public function executeStatusTransition(): void
    {
        if ($this->statusActionReservationId && $this->pendingStatusValue) {
            $this->updateStatus($this->statusActionReservationId, $this->pendingStatusValue);
        }

        $this->closeConfirmStatusModal();
    }

    /**
     * Transition reservation status.
     */
    public function updateStatus(string $reservationId, string $statusValue): void
    {
        if (!$this->currentOperator) {
            return;
        }

        $res = $this->currentOperator->reservations()->find($reservationId);

        if (!$res) {
            return;
        }

        $status = ReservationStatus::tryFrom($statusValue);

        if (!$status) {
            return;
        }

        $res->update([
            'status' => $status,
            'hold_expires_at' => $status === ReservationStatus::PaymentPending ? now()->addMinutes(30) : null,
        ]);

        if ($status === ReservationStatus::Confirmed && !empty($res->guest_email)) {
            try {
                \Illuminate\Support\Facades\Mail::to($res->guest_email)->send(new \App\Mail\GuestBookingConfirmedMail($res));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($status === ReservationStatus::Cancelled) {
            app(\App\Services\WalletService::class)->cancelBookingEarning($res, 'Cancelled by operator');
        }

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

        if (!$res) {
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

        <!-- Actions & Counter -->
        <div class="flex items-center gap-3">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 hidden sm:inline">
                {{ __('Showing :count reservations', ['count' => $this->reservations->count()]) }}
            </span>
            <x-button type="button" variant="primary" wire:click="openCreateLinkModal" class="text-xs font-bold">
                <i class="fa-solid fa-link text-xs"></i>
                <span>{{ __('Create Booking Link') }}</span>
            </x-button>
        </div>
    </div>

    <!-- Metric Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Confirmed -->
        <div
            class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Confirmed Trips') }}
                </span>
                <span
                    class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-xs">
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
        <div
            class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Pending Holds') }}
                </span>
                <span
                    class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 text-xs">
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
        <div
            class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Completed') }}
                </span>
                <span
                    class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 text-xs">
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
        <div
            class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Paid Revenue') }}
                </span>
                <span
                    class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 text-xs">
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
    <div
        class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <!-- Search Bar -->
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="{{ __('Search by guest name, phone, email, or reservation ID...') }}"
                    class="h-10 w-full pl-9 pr-4 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" />
                @if ($search !== '')
                    <button wire:click="$set('search', '')"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                @endif
            </div>

            <!-- Date Filter Dropdown -->
            <div class="w-full sm:w-52">
                <x-select wire:model.live="dateFilter" :options="[
                    'all' => __('All Trip Dates'),
                    'upcoming' => __('Upcoming Trips Only'),
                    'this_month' => __('This Month'),
                    'past' => __('Past / Completed'),
                ]" />
            </div>
        </div>

        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 border-t border-slate-100 dark:border-zinc-800 pt-3">
            @php
                $statusTabs = [
                    'all' => __('All (:count)', ['count' => $this->metrics['total']]),
                    'pending_confirmation' => __('Needs Confirmation (:count)', [
                        'count' => $this->metrics['pending_confirmation'],
                    ]),
                    'confirmed' => __('Confirmed (:count)', ['count' => $this->metrics['confirmed']]),
                    'payment_pending' => __('Pending Payment (:count)', ['count' => $this->metrics['pending']]),
                    'completed' => __('Completed (:count)', ['count' => $this->metrics['completed']]),
                    'declined' => __('Declined'),
                    'cancelled' => __('Cancelled'),
                    'expired' => __('Expired'),
                ];
            @endphp

            @foreach ($statusTabs as $tabKey => $tabLabel)
                <button type="button" wire:click="$set('statusFilter', '{{ $tabKey }}')"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer {{ $statusFilter === $tabKey ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Reservations Section -->
    <div
        class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden">
        <!-- Reservations Mobile Responsive Card List (md:hidden) -->
        <div class="md:hidden space-y-3 p-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($this->reservations as $res)
                @php
                    $bookable = $res->bookable;
                    $latestPayment = $res->latestPayment;
                    $resCode = $res->code ?? 'RSV-' . strtoupper(substr($res->id, -8));
                    $cleanPhone = preg_replace('/[^0-9]/', '', $res->guest_contact);
                    if (str_starts_with($cleanPhone, '0')) {
                        $cleanPhone = '62' . substr($cleanPhone, 1);
                    }
                    $waUrl = 'https://wa.me/' . $cleanPhone . '?text=' . urlencode(__('Hello :name, reaching out regarding your reservation (:code) with :agent', ['name' => $res->guest_name, 'code' => $resCode, 'agent' => $this->currentOperator->name]));
                @endphp
                <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="font-extrabold text-xs text-slate-900 dark:text-white truncate">
                                {{ $res->guest_name }}
                            </span>
                            <span class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 shrink-0">
                                #{{ $resCode }}
                            </span>
                        </div>
                        <div class="shrink-0">
                            @if ($res->status->value === 'confirmed')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Confirmed</span>
                            @elseif ($res->status->value === 'pending_confirmation')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">Pending Confirmation</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300">{{ ucfirst($res->status->value) }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100 dark:border-zinc-800">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Experience') }}</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 block truncate">{{ $bookable->name ?? ($bookable->title ?? __('Custom Booking')) }}</span>
                            <span class="text-[10px] text-slate-500 block">{{ __(':count Pax', ['count' => $res->pax_count]) }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Trip Date') }}</span>
                            <span class="font-bold text-slate-900 dark:text-white block">{{ $res->requested_date->format('M d, Y') }}</span>
                            <span class="text-[10px] font-mono font-black text-slate-900 dark:text-white block">Rp {{ number_format((float) $res->total_price, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800 text-[11px]">
                        <div>
                            @if ($res->guest_contact)
                                <a href="{{ $waUrl }}" target="_blank" class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                                    <i class="fa-brands fa-whatsapp text-xs"></i>
                                    <span>{{ __('Chat') }}</span>
                                </a>
                            @endif
                        </div>
                        <button type="button" wire:click="viewReservation('{{ $res->id }}')" class="px-3 py-1 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 font-bold text-xs">
                            {{ __('Details') }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs text-slate-400">
                    {{ __('No reservations found') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop Reservations Table (hidden on mobile) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead
                    class="bg-slate-50 dark:bg-zinc-800/60 border-b border-slate-200/80 dark:border-zinc-800 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
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
                            $resCode = $res->code ?? 'RSV-' . strtoupper(substr($res->id, -8));
                            $cleanPhone = preg_replace('/[^0-9]/', '', $res->guest_contact);
                            if (str_starts_with($cleanPhone, '0')) {
                                $cleanPhone = '62' . substr($cleanPhone, 1);
                            }
                            $waUrl =
                                'https://wa.me/' .
                                $cleanPhone .
                                '?text=' .
                                urlencode(
                                    __('Hello :name, reaching out regarding your reservation (:code) with :agent', [
                                        'name' => $res->guest_name,
                                        'code' => $resCode,
                                        'agent' => $this->currentOperator->name,
                                    ]),
                                );
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/40 transition group">
                            <!-- Guest Info -->
                            <td class="px-5 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 dark:text-white">
                                            {{ $res->guest_name }}
                                        </span>
                                        <span
                                            class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60">
                                            #{{ $resCode }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex items-center gap-2.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        @if ($res->guest_contact)
                                            <a href="{{ $waUrl }}" target="_blank"
                                                class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 hover:underline font-semibold"
                                                title="{{ __('Chat on WhatsApp') }}">
                                                <i class="fa-brands fa-whatsapp text-xs"></i>
                                                {{ $res->guest_contact }}
                                            </a>
                                        @endif
                                        @if ($res->guest_email)
                                            <span
                                                class="text-slate-400 truncate max-w-[140px]">{{ $res->guest_email }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Booked Experience -->
                            <td class="px-4 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5">
                                        @if ($res->bookable_type === 'package' || $res->bookable_type === \App\Models\Package::class)
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                                {{ __('Package') }}
                                            </span>
                                        @else
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
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
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                                            {{ __('Paid') }}
                                        </span>
                                        <p class="font-bold text-xs text-slate-900 dark:text-white">
                                            Rp {{ number_format((float) $latestPayment->amount, 0, ',', '.') }}
                                        </p>
                                    @elseif ($latestPayment && $latestPayment->status->value === 'pending')
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            <i class="fa-solid fa-clock text-[10px]"></i>
                                            {{ __('Unpaid') }}
                                        </span>
                                        <p class="font-bold text-xs text-slate-500">
                                            Rp {{ number_format((float) $latestPayment->amount, 0, ',', '.') }}
                                        </p>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-slate-400">
                                            {{ __('No Payment') }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Reservation Status -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if ($res->status === ReservationStatus::Confirmed)
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ __('Confirmed') }}
                                    </span>
                                @elseif ($res->status === ReservationStatus::PendingConfirmation)
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-200 dark:border-amber-800 animate-pulse">
                                        <i class="fa-solid fa-hourglass-half text-[10px]"></i>
                                        {{ __('Needs Confirmation') }}
                                    </span>
                                @elseif ($res->status === ReservationStatus::PaymentPending)
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                {{ __('Payment Hold') }}
                                            </span>
                                            @if ($res->hold_expires_at && $res->hold_expires_at->isFuture())
                                                <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                                                    ({{ now()->diffInMinutes($res->hold_expires_at) }}m)
                                                </span>
                                            @endif
                                        </div>

                                        @if ($res->guest_contact)
                                            <div>
                                                <a href="{{ app(\App\Services\WhatsAppDispatchService::class)->getPaymentHoldLinkUrl($res) }}"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800 text-[11px] font-bold transition shadow-2xs cursor-pointer"
                                                    title="{{ __('Send Direct Payment Link to Guest via WhatsApp') }}">
                                                    <i
                                                        class="fa-brands fa-whatsapp text-xs text-emerald-600 dark:text-emerald-400"></i>
                                                    <span>{{ __('Send Payment Link') }}</span>
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @elseif ($res->status === ReservationStatus::Completed)
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                        <i class="fa-solid fa-flag-checkered text-[10px]"></i>
                                        {{ __('Completed') }}
                                    </span>
                                @elseif ($res->status === ReservationStatus::Declined)
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        <i class="fa-solid fa-xmark text-[10px]"></i>
                                        {{ __('Declined') }}
                                    </span>
                                @elseif ($res->status === ReservationStatus::Cancelled)
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        <i class="fa-solid fa-ban text-[10px]"></i>
                                        {{ __('Cancelled') }}
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-slate-400">
                                        {{ $res->status->label() }}
                                    </span>
                                @endif
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if ($res->status === ReservationStatus::PendingConfirmation)
                                        <button type="button"
                                            wire:click="confirmStatusTransition('{{ $res->id }}', 'confirmed')"
                                            class="h-8 px-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1 cursor-pointer"
                                            title="{{ __('Confirm Booking') }}">
                                            <i class="fa-solid fa-check text-[10px]"></i>
                                            <span>{{ __('Confirm') }}</span>
                                        </button>
                                        <button type="button"
                                            wire:click="confirmStatusTransition('{{ $res->id }}', 'declined')"
                                            class="h-8 px-2 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/50 font-bold text-xs transition cursor-pointer"
                                            title="{{ __('Decline Booking') }}">
                                            <i class="fa-solid fa-xmark text-[10px]"></i>
                                        </button>
                                    @endif

                                    <x-button type="button" size="sm" variant="secondary"
                                        wire:click="viewDetails('{{ $res->id }}')"
                                        class="h-8 text-xs font-bold">
                                        <i class="fa-solid fa-eye mr-1 text-[10px]"></i>
                                        {{ __('Details') }}
                                    </x-button>

                                    <!-- Quick Action Dropdown Menu -->
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button type="button" @click="open = !open"
                                            class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition cursor-pointer">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <div x-show="open" @click.away="open = false" x-cloak
                                            class="absolute right-0 z-20 mt-1 w-48 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-lg py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                            @if ($res->status === ReservationStatus::PaymentPending && $res->guest_contact)
                                                <a href="{{ app(\App\Services\WhatsAppDispatchService::class)->getPaymentHoldLinkUrl($res) }}"
                                                    target="_blank" @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-amber-50 dark:hover:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                                                    <i class="fa-brands fa-whatsapp w-4 text-emerald-500"></i>
                                                    {{ __('Send Payment Link (WA)') }}
                                                </a>
                                            @endif
                                            @if ($res->status !== ReservationStatus::Confirmed)
                                                <button type="button"
                                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'confirmed')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                                                    <i class="fa-solid fa-circle-check w-4"></i>
                                                    {{ __('Mark Confirmed') }}
                                                </button>
                                            @endif
                                            @if ($res->status !== ReservationStatus::Declined)
                                                <button type="button"
                                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'declined')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-rose-600 dark:text-rose-400">
                                                    <i class="fa-solid fa-ban w-4"></i>
                                                    {{ __('Decline Booking') }}
                                                </button>
                                            @endif
                                            @if ($res->status !== ReservationStatus::Completed)
                                                <button type="button"
                                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'completed')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">
                                                    <i class="fa-solid fa-flag-checkered w-4"></i>
                                                    {{ __('Mark Completed') }}
                                                </button>
                                            @endif
                                            @if ($res->status !== ReservationStatus::Cancelled)
                                                <button type="button"
                                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'cancelled')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-slate-100 dark:hover:bg-zinc-800 text-slate-600 dark:text-slate-400">
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
                                    <div
                                        class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto text-xl">
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
        @teleport('body')
            @php
                $res = $this->selectedReservation;
                $bookable = $res->bookable;
                $latestPayment = $res->latestPayment;
                $cleanPhone = preg_replace('/[^0-9]/', '', $res->guest_contact);
                if (str_starts_with($cleanPhone, '0')) {
                    $cleanPhone = '62' . substr($cleanPhone, 1);
                }
                $resCode = $res->code ?? 'RSV-' . strtoupper(substr($res->id, -8));
                $waUrl =
                    'https://wa.me/' .
                    $cleanPhone .
                    '?text=' .
                    urlencode(
                        __('Hello :name, reaching out regarding your reservation (:code) with :agent', [
                            'name' => $res->guest_name,
                            'code' => $resCode,
                            'agent' => $this->currentOperator->name,
                        ]),
                    );
            @endphp
            <div
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto bg-slate-900/60 backdrop-blur-xs">
                <div @click.away="$wire.closeDetails()"
                    class="w-full max-w-2xl rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl flex flex-col my-8 animate-scale-up">
                    <!-- Modal Header -->
                    <div
                        class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div
                                class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-700 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3
                                        class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight">
                                        {{ __('Reservation Details') }}
                                    </h3>
                                    <span
                                        class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60">
                                        #{{ $resCode }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __('Created on :date', ['date' => $res->created_at?->format('M d, Y H:i') ?? '—']) }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="closeDetails"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                        <!-- Guest & Experience Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Guest Info Box -->
                            <div
                                class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-800 space-y-2">
                                <span
                                    class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Guest Contact') }}</span>
                                <p class="font-bold text-sm text-slate-900 dark:text-white">{{ $res->guest_name }}</p>
                                <div class="space-y-1 text-xs">
                                    <p class="text-slate-600 dark:text-slate-300 flex items-center gap-2">
                                        <i class="fa-solid fa-envelope text-slate-400 w-4"></i>
                                        {{ $res->guest_email ?? __('No email provided') }}
                                    </p>
                                    <p class="text-slate-600 dark:text-slate-300 flex items-center gap-2">
                                        <i class="fa-brands fa-whatsapp text-emerald-500 w-4"></i>
                                        <a href="{{ $waUrl }}" target="_blank"
                                            class="text-emerald-600 dark:text-emerald-400 hover:underline font-bold">
                                            {{ $res->guest_contact }}
                                        </a>
                                    </p>
                                </div>
                            </div>

                            <!-- Trip Schedule Box -->
                            <div
                                class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-800 space-y-2 flex flex-col justify-between">
                                <div>
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Schedule & Guests') }}</span>
                                    <p class="font-bold text-sm text-slate-900 dark:text-white">
                                        {{ $res->requested_date->format('l, F d, Y') }}
                                    </p>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 flex items-center gap-1.5 mt-0.5">
                                        <i class="fa-solid fa-users text-indigo-500"></i>
                                        {{ __(':count Guests (Pax)', ['count' => $res->pax_count]) }}
                                    </p>
                                </div>
                                <div
                                    class="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-zinc-700/60">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $res->status === ReservationStatus::Confirmed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                        {{ $res->status->label() }}
                                    </span>
                                    @php
                                        $gcalUrl = app(
                                            \App\Services\GoogleCalendarService::class,
                                        )->buildGoogleCalendarUrl($res);
                                    @endphp
                                    <a href="{{ $gcalUrl }}" target="_blank"
                                        class="inline-flex items-center gap-1.5 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 hover:underline">
                                        <i class="fa-brands fa-google text-xs"></i>
                                        <span>{{ __('Add to Calendar') }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- 1-Click WhatsApp Dispatch Center -->
                        @php
                            $waService = app(\App\Services\WhatsAppDispatchService::class);
                            $voucherWaUrl = $waService->getConfirmationUrl($res);
                            $reminderWaUrl = $waService->getReminderUrl($res);
                            $meetingWaUrl = $waService->getMeetingPointUrl($res);
                            $paymentHoldWaUrl = $waService->getPaymentHoldLinkUrl($res);
                        @endphp
                        <div
                            class="p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/70 dark:border-emerald-900/50 space-y-3">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-bold text-emerald-900 dark:text-emerald-300 flex items-center gap-1.5">
                                    <i class="fa-brands fa-whatsapp text-emerald-600 dark:text-emerald-400 text-sm"></i>
                                    {{ __('1-Click WhatsApp Guest Dispatch') }}
                                </span>
                                <span
                                    class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
                                    {{ __('Pre-Formatted Messages') }}
                                </span>
                            </div>

                            @if ($res->status === ReservationStatus::PaymentPending)
                                <div
                                    class="p-3 rounded-xl bg-amber-100/70 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-800/80 space-y-2">
                                    <div
                                        class="flex items-center justify-between text-xs font-bold text-amber-900 dark:text-amber-200">
                                        <span class="flex items-center gap-1.5">
                                            <i class="fa-solid fa-hourglass-half text-amber-600 dark:text-amber-400"></i>
                                            {{ __('Reservation on 30-Minute Payment Hold') }}
                                        </span>
                                        @if ($res->hold_expires_at)
                                            <span
                                                class="font-mono text-[11px]">{{ $res->hold_expires_at->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                    <a href="{{ $paymentHoldWaUrl }}" target="_blank"
                                        class="w-full h-10 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2 text-center cursor-pointer">
                                        <i class="fa-brands fa-whatsapp text-sm"></i>
                                        <span>{{ __('Send Payment Link to Guest via WhatsApp') }}</span>
                                    </a>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-1">
                                <a href="{{ $voucherWaUrl }}" target="_blank"
                                    class="h-9 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-1.5 text-center">
                                    <i class="fa-solid fa-ticket text-xs"></i>
                                    <span class="truncate">{{ __('Send E-Voucher') }}</span>
                                </a>

                                <a href="{{ $reminderWaUrl }}" target="_blank"
                                    class="h-9 px-3 rounded-xl bg-white dark:bg-zinc-800 hover:bg-emerald-50 dark:hover:bg-zinc-700 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-bold text-xs transition flex items-center justify-center gap-1.5 text-center shadow-2xs">
                                    <i class="fa-solid fa-bell text-xs"></i>
                                    <span class="truncate">{{ __('Send 24h Reminder') }}</span>
                                </a>

                                <a href="{{ $meetingWaUrl }}" target="_blank"
                                    class="h-9 px-3 rounded-xl bg-white dark:bg-zinc-800 hover:bg-emerald-50 dark:hover:bg-zinc-700 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-bold text-xs transition flex items-center justify-center gap-1.5 text-center shadow-2xs">
                                    <i class="fa-solid fa-location-dot text-xs"></i>
                                    <span class="truncate">{{ __('Send Meeting Pin') }}</span>
                                </a>
                            </div>
                        </div>

                        <!-- Booked Package / Product Overview -->
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-zinc-800 space-y-3">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Booked Entity') }}</span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $res->bookable_type === 'package' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
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
                        <div
                            class="p-4 rounded-2xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/60 space-y-3">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <i class="fa-solid fa-credit-card text-indigo-600 dark:text-indigo-400"></i>
                                    {{ __('Payment & Settlement') }}
                                </span>
                                @if ($latestPayment && $latestPayment->isPaid())
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        {{ __('Paid & Settled') }}
                                    </span>
                                @else
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
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

                                @if (!$latestPayment->isPaid())
                                    <div
                                        class="pt-2 border-t border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between">
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ __('Payment completed on DOKU?') }}
                                        </span>
                                        <button type="button" wire:click="syncPaymentStatus('{{ $res->id }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs shadow-2xs transition cursor-pointer">
                                            <i class="fa-solid fa-arrows-rotate text-[10px]"
                                                wire:loading.class="animate-spin"
                                                wire:target="syncPaymentStatus('{{ $res->id }}')"></i>
                                            <span>{{ __('Sync with DOKU') }}</span>
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <!-- Internal Agent Notes -->
                        <div class="space-y-2">
                            <x-label for="agentNote" :value="__('Internal Reservation Notes')" />
                            <x-textarea id="agentNote" wire:model="agentNote" rows="3"
                                placeholder="{{ __('Add special dietary requests, pickup instructions, boat assignments...') }}"
                                class="text-xs" />
                            <div class="flex justify-end">
                                <x-button size="sm" variant="secondary" wire:click="saveNotes"
                                    class="font-bold text-xs">
                                    <i class="fa-solid fa-floppy-disk mr-1"></i>
                                    {{ __('Save Notes') }}
                                </x-button>
                            </div>
                        </div>

                        <!-- Agreed Terms Snapshot -->
                        @if (!empty($res->terms_snapshot))
                            <div x-data="{ openTerms: false }"
                                class="pt-3 border-t border-slate-100 dark:border-zinc-800 space-y-2">
                                <button type="button" @click="openTerms = !openTerms"
                                    class="w-full flex items-center justify-between text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-shield-halved text-indigo-500"></i>
                                        {{ __('Agreed Storefront Terms Snapshot (Frozen at booking)') }}
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200"
                                        :class="openTerms ? 'rotate-180' : ''"></i>
                                </button>
                                <div x-show="openTerms" x-cloak
                                    class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200 dark:border-zinc-800 text-xs text-slate-600 dark:text-slate-400 font-mono whitespace-pre-wrap max-h-40 overflow-y-auto">
                                    {{ is_array($res->terms_snapshot) ? json_encode($res->terms_snapshot, JSON_PRETTY_PRINT) : $res->terms_snapshot }}
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Modal Footer -->
                    <div
                        class="p-5 bg-slate-50/50 dark:bg-zinc-800/40 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-3 rounded-b-3xl">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if ($res->status !== ReservationStatus::Confirmed)
                                <x-button size="sm" variant="primary"
                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'confirmed')"
                                    class="font-bold text-xs bg-emerald-600 hover:bg-emerald-700 text-white">
                                    <i class="fa-solid fa-circle-check mr-1 text-xs"></i>
                                    {{ __('Mark Confirmed') }}
                                </x-button>
                            @endif
                            @if ($res->status !== ReservationStatus::Declined && $res->status !== ReservationStatus::Completed)
                                <x-button size="sm" variant="danger"
                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'declined')"
                                    class="font-bold text-xs">
                                    <i class="fa-solid fa-ban mr-1 text-xs"></i>
                                    {{ __('Decline') }}
                                </x-button>
                            @endif
                            @if ($res->status !== ReservationStatus::Completed)
                                <x-button size="sm" variant="secondary"
                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'completed')"
                                    class="font-bold text-xs">
                                    <i class="fa-solid fa-flag-checkered mr-1 text-xs text-indigo-500"></i>
                                    {{ __('Mark Completed') }}
                                </x-button>
                            @endif
                        </div>

                        <x-button size="sm" variant="secondary" wire:click="closeDetails"
                            class="font-bold text-xs">
                            {{ __('Close') }}
                        </x-button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Create Booking & Payment Link Modal (Mobile-First Bottom Sheet & Desktop Dialog) -->
    @if ($showCreateLinkModal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/75 backdrop-blur-xs overflow-y-auto"
                wire:keydown.escape="closeCreateLinkModal">
                <div class="relative w-full max-w-lg rounded-t-3xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl flex flex-col max-h-[92vh] sm:max-h-[85vh] overflow-hidden"
                    @click.outside="$wire.closeCreateLinkModal()">

                    <!-- Mobile Drag Handle Bar -->
                    <div class="w-12 h-1 rounded-full bg-slate-300 dark:bg-zinc-700 mx-auto my-2.5 sm:hidden shrink-0">
                    </div>

                    <!-- Modal Header -->
                    <div
                        class="px-5 py-4 sm:px-6 sm:py-5 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-3 bg-slate-50/70 dark:bg-zinc-800/50 shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-indigo-600 dark:bg-indigo-500 text-white flex items-center justify-center text-sm sm:text-base shadow-xs shrink-0">
                                <i class="fa-solid fa-link"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-extrabold text-base text-slate-900 dark:text-white leading-tight truncate">
                                    {{ __('Create Booking & Payment Link') }}
                                </h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                    {{ __('Lock 30-min hold & share direct checkout link') }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="closeCreateLinkModal"
                            class="h-8 w-8 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition flex items-center justify-center cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    @if ($linkCreatedSuccessfully)
                        <!-- Link Created Success Screen -->
                        <div class="p-5 sm:p-7 overflow-y-auto space-y-5 flex-1">
                            <div
                                class="p-4 sm:p-5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-center space-y-1.5">
                                <div
                                    class="w-11 h-11 rounded-full bg-emerald-500 text-white flex items-center justify-center text-lg mx-auto shadow-xs animate-bounce">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <h4 class="font-extrabold text-base text-emerald-900 dark:text-emerald-200">
                                    {{ __('Booking & Payment Link Generated!') }}
                                </h4>
                                <p class="text-xs text-emerald-700 dark:text-emerald-300 font-mono font-bold">
                                    #{{ $generatedReservationCode }} &bull; {{ __('30-Minute Hold Active') }}
                                </p>
                            </div>

                            <!-- Direct Link Copy Input -->
                            <div class="space-y-1.5" x-data="{ copied: false }">
                                <label
                                    class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Guest Payment Link') }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" readonly value="{{ $generatedPaymentUrl }}"
                                        class="flex-1 h-11 px-3.5 rounded-xl border border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 text-xs font-mono text-slate-900 dark:text-white select-all truncate" />
                                    <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $generatedPaymentUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="h-11 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-slate-100 text-white dark:text-slate-900 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer shrink-0 shadow-xs">
                                        <i class="fa-solid"
                                            :class="copied ? 'fa-check text-emerald-400 dark:text-emerald-600' : 'fa-copy'"></i>
                                        <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                                    </button>
                                </div>
                            </div>

                            <!-- WhatsApp Dispatch Button -->
                            @if ($generatedWhatsAppUrl)
                                <div>
                                    <a href="{{ $generatedWhatsAppUrl }}" target="_blank" rel="noopener"
                                        class="w-full h-12 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:scale-98 text-white font-extrabold text-xs sm:text-sm shadow-md shadow-emerald-500/20 transition flex items-center justify-center gap-2 cursor-pointer">
                                        <i class="fa-brands fa-whatsapp text-lg"></i>
                                        <span>{{ __('Send Payment Link on WhatsApp') }}</span>
                                    </a>
                                </div>
                            @endif

                            <div
                                class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-3">
                                <button type="button" wire:click="openCreateLinkModal"
                                    class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer">
                                    {{ __('+ Create Another Link') }}
                                </button>
                                <x-button size="sm" variant="secondary" wire:click="closeCreateLinkModal">
                                    {{ __('Done') }}
                                </x-button>
                            </div>
                        </div>
                    @else
                        <!-- Form View -->
                        <form wire:submit="generateBookingLink" class="flex flex-col flex-1 overflow-hidden">
                            <div class="p-5 sm:p-6 overflow-y-auto space-y-4 flex-1 overscroll-contain">
                                <!-- Experience Selection -->
                                <div>
                                    <x-label for="createExperienceSelection" :value="__('Select Tour Experience')" required />
                                    <x-select id="createExperienceSelection" wire:model.live="createExperienceSelection"
                                        :options="$this->experienceOptions" :placeholder="__('Select tour package or activity...')" :error="$errors->has('createBookableId')" />
                                    <x-input-error :messages="$errors->get('createBookableId')" />
                                </div>

                                <!-- Date & Pax Grid -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-label for="createRequestedDate" :value="__('Trip Date')" required />
                                        <x-date-picker id="createRequestedDate" wire:model.live="createRequestedDate"
                                            min="{{ now()->format('Y-m-d') }}" :placeholder="__('Select date...')" :error="$errors->has('createRequestedDate')" />
                                        <x-input-error :messages="$errors->get('createRequestedDate')" />
                                    </div>

                                    <div>
                                        <x-label for="createPaxCount" :value="__('Guests (Pax)')" required />
                                        <x-input id="createPaxCount" wire:model.live="createPaxCount" type="number"
                                            min="1" max="50" :error="$errors->has('createPaxCount')" />
                                        <x-input-error :messages="$errors->get('createPaxCount')" />
                                    </div>
                                </div>

                                <!-- Guest Details -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <x-label for="createGuestName" :value="__('Guest Full Name')" required />
                                        <x-input id="createGuestName" wire:model="createGuestName" type="text"
                                            placeholder="e.g. Budi Santoso" :error="$errors->has('createGuestName')" />
                                        <x-input-error :messages="$errors->get('createGuestName')" />
                                    </div>

                                    <div>
                                        <x-label for="createGuestContact" :value="__('WhatsApp / Phone Number')" required />
                                        <x-input id="createGuestContact" wire:model="createGuestContact" type="tel"
                                            placeholder="e.g. 081234567890" :error="$errors->has('createGuestContact')" />
                                        <x-input-error :messages="$errors->get('createGuestContact')" />
                                    </div>
                                </div>

                                <!-- Optional Email & Notes -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <x-label for="createGuestEmail" :value="__('Guest Email (Optional)')" />
                                        <x-input id="createGuestEmail" wire:model="createGuestEmail" type="email"
                                            placeholder="guest@example.com" :error="$errors->has('createGuestEmail')" />
                                        <x-input-error :messages="$errors->get('createGuestEmail')" />
                                    </div>

                                    <div>
                                        <x-label for="createNotes" :value="__('Notes / Pick-up Details')" />
                                        <x-input id="createNotes" wire:model="createNotes" type="text"
                                            placeholder="e.g. Hotel pickup at 07:30" :error="$errors->has('createNotes')" />
                                        <x-input-error :messages="$errors->get('createNotes')" />
                                    </div>
                                </div>

                                <!-- Real-Time Price Estimate Pill -->
                                @if ($this->estimatedTotal > 0)
                                    <div
                                        class="p-3 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                                            <i class="fa-solid fa-calculator text-indigo-500"></i>
                                            <span class="font-medium">{{ __('Total Booking Amount') }}</span>
                                        </div>
                                        <span class="font-black text-sm text-indigo-600 dark:text-indigo-400">
                                            Rp {{ number_format($this->estimatedTotal, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <!-- Modal Submit Footer -->
                            <div
                                class="px-5 py-4 pb-6 sm:pb-4 sm:px-6 border-t border-slate-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex items-center justify-between gap-3 shrink-0">
                                <button type="button" wire:click="closeCreateLinkModal"
                                    class="h-11 px-5 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900/60 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 text-xs sm:text-sm font-bold transition cursor-pointer">
                                    <i class="fa-solid fa-xmark text-sm"></i>
                                    <span class="font-bold">
                                        {{ __('Cancel') }}
                                    </span>
                                </button>

                                <button type="submit"
                                    class="h-11 px-5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:scale-98 text-white font-extrabold text-xs sm:text-sm shadow-md shadow-indigo-500/25 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                                    <i class="fa-solid fa-link text-xs" wire:loading.remove
                                        wire:target="generateBookingLink"></i>
                                    <i class="fa-solid fa-circle-notch fa-spin text-xs" wire:loading
                                        wire:target="generateBookingLink"></i>
                                    <span>{{ __('Generate Payment Link') }}</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endteleport
    @endif

    <!-- Confirm Reservation Status Transition Modal -->
    @if ($showConfirmStatusModal && $this->pendingStatusReservation)
        @php
            $pendingRes = $this->pendingStatusReservation;
            $targetStatus = \App\Enums\ReservationStatus::tryFrom($pendingStatusValue ?? '');
        @endphp
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
                wire:keydown.escape="closeConfirmStatusModal">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl border border-slate-200/80 dark:border-zinc-800 flex flex-col my-8"
                    @click.outside="$wire.closeConfirmStatusModal()">
                    <!-- Modal Header -->
                    <div
                        class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            @if ($pendingStatusValue === 'confirmed')
                                <div
                                    class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-circle-check"></i>
                                </div>
                            @elseif ($pendingStatusValue === 'declined')
                                <div
                                    class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-ban"></i>
                                </div>
                            @elseif ($pendingStatusValue === 'completed')
                                <div
                                    class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-flag-checkered"></i>
                                </div>
                            @else
                                <div
                                    class="w-10 h-10 rounded-2xl bg-amber-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                            @endif
                            <div class="space-y-0.5 min-w-0">
                                <h3
                                    class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    @if ($pendingStatusValue === 'confirmed')
                                        {{ __('Confirm Reservation') }}
                                    @elseif ($pendingStatusValue === 'declined')
                                        {{ __('Decline Reservation') }}
                                    @elseif ($pendingStatusValue === 'completed')
                                        {{ __('Mark Trip as Completed') }}
                                    @else
                                        {{ __('Cancel Reservation') }}
                                    @endif
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    @if ($pendingStatusValue === 'confirmed')
                                        {{ __('This will officially confirm the booking and send a confirmation email to the guest.') }}
                                    @elseif ($pendingStatusValue === 'declined')
                                        {{ __('This will decline the reservation and release any reserved inventory.') }}
                                    @elseif ($pendingStatusValue === 'completed')
                                        {{ __('This will mark the excursion as finished and schedule the 12-hour review request email.') }}
                                    @else
                                        {{ __('This will cancel the reservation and release calendar slots.') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeConfirmStatusModal"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body / Summary Card -->
                    <div class="p-6 space-y-4">
                        <div
                            class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-black text-indigo-600 dark:text-indigo-400">
                                    #{{ $pendingRes->code ?? $pendingRes->id }}
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-slate-200 dark:bg-zinc-700 text-slate-700 dark:text-slate-300">
                                    {{ $pendingRes->status->label() }} &rarr;
                                    {{ $targetStatus?->label() ?? ucfirst((string) $pendingStatusValue) }}
                                </span>
                            </div>
                            <div class="border-t border-slate-200/60 dark:border-zinc-700/60 pt-2 space-y-1 text-xs">
                                <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('Guest Name:') }}</span>
                                    <strong
                                        class="font-bold text-slate-900 dark:text-white">{{ $pendingRes->guest_name }}</strong>
                                </div>
                                <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('Tour Experience:') }}</span>
                                    <span
                                        class="font-semibold text-slate-800 dark:text-slate-200 truncate max-w-[200px] text-right">{{ $pendingRes->bookable?->name ?? ($pendingRes->bookable?->title ?? 'Custom Package') }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('Date & Pax:') }}</span>
                                    <span
                                        class="font-semibold text-slate-800 dark:text-slate-200">{{ $pendingRes->requested_date?->format('M d, Y') }}
                                        ({{ $pendingRes->pax }} pax)</span>
                                </div>
                                <div
                                    class="flex items-center justify-between text-slate-700 dark:text-slate-300 pt-1 border-t border-slate-200/40 dark:border-zinc-700/40">
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('Total Amount:') }}</span>
                                    <strong class="font-extrabold text-slate-900 dark:text-white">Rp
                                        {{ number_format($pendingRes->total_amount, 0, ',', '.') }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div
                            class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                            <x-button type="button" variant="secondary" wire:click="closeConfirmStatusModal"
                                class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>

                            @if ($pendingStatusValue === 'confirmed')
                                <x-button type="button" variant="primary" wire:click="executeStatusTransition"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                                    <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                                    {{ __('Yes, Confirm Booking') }}
                                </x-button>
                            @elseif ($pendingStatusValue === 'declined')
                                <x-button type="button" variant="danger" wire:click="executeStatusTransition"
                                    class="text-xs font-bold">
                                    <i class="fa-solid fa-ban mr-1.5 text-xs"></i>
                                    {{ __('Yes, Decline Booking') }}
                                </x-button>
                            @elseif ($pendingStatusValue === 'completed')
                                <x-button type="button" variant="primary" wire:click="executeStatusTransition"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                                    <i class="fa-solid fa-flag-checkered mr-1.5 text-xs"></i>
                                    {{ __('Yes, Mark as Completed') }}
                                </x-button>
                            @else
                                <x-button type="button" variant="danger" wire:click="executeStatusTransition"
                                    class="text-xs font-bold">
                                    <i class="fa-solid fa-xmark mr-1.5 text-xs"></i>
                                    {{ __('Yes, Cancel Reservation') }}
                                </x-button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
