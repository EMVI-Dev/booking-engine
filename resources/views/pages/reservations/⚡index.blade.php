<?php

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Reservation;
use App\Concerns\ResolvesCurrentOperator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bookings & Reservations')] class extends Component {
    use ResolvesCurrentOperator;
    use WithPagination;

    #[Url]
    public string $search = '';
    public string $statusFilter = 'all';
    public string $dateFilter = 'all'; // all, upcoming, past, this_month

    // Detail Modal / Slide-over State
    public bool $showDetailModal = false;
    public ?string $selectedReservationId = null;
    public string $agentNote = '';
    public bool $isEditingNotes = false;

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
    public bool $createShowExtras = false;

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


    /**
     * @return array{title: ?string, unit: float, pax: int, subtotal: float, fee: float, total: float, remaining: ?int}
     */
    #[Computed]
    public function createQuote(): array
    {
        $pax = max(1, $this->createPaxCount);
        $empty = [
            'title' => null,
            'unit' => 0.0,
            'pax' => $pax,
            'subtotal' => 0.0,
            'fee' => 0.0,
            'total' => 0.0,
            'remaining' => null,
        ];

        $bookable = $this->resolveCreateBookable();

        if (! $bookable) {
            return $empty;
        }

        $unitPrice = (float) $bookable->price;
        $subtotal = $unitPrice * $pax;
        $serviceFee = \App\Models\PlatformSetting::current()->calculateGuestServiceFee($subtotal, $this->currentOperator);
        $remaining = null;

        if ($this->createRequestedDate !== '') {
            $remaining = app(\App\Services\CapacityService::class)->remainingCapacity($bookable, $this->createRequestedDate);
        }

        return [
            'title' => $bookable instanceof \App\Models\Package ? $bookable->title : $bookable->name,
            'unit' => $unitPrice,
            'pax' => $pax,
            'subtotal' => $subtotal,
            'fee' => $serviceFee,
            'total' => $subtotal + $serviceFee,
            'remaining' => $remaining,
        ];
    }

    #[Computed]
    public function estimatedTotal(): float
    {
        return (float) $this->createQuote['total'];
    }

    /**
     * @return Collection<int, \App\Models\Guest>
     */
    #[Computed]
    public function recentGuests(): Collection
    {
        if (! $this->currentOperator) {
            return new Collection;
        }

        return $this->currentOperator->guests()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();
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
     * @return list<array{value: string, label: string, icon: string, hint: string}>
     */
    #[Computed]
    public function experienceOptions(): array
    {
        $options = [];

        foreach ($this->availablePackages as $pkg) {
            $options[] = [
                'value' => "package:{$pkg->id}",
                'label' => $pkg->title,
                'icon' => 'fa-solid fa-cubes',
                'hint' => __('Package').' · Rp '.number_format((float) $pkg->price, 0, ',', '.').'/pax',
            ];
        }

        foreach ($this->availableProducts as $prod) {
            $options[] = [
                'value' => "product:{$prod->id}",
                'label' => $prod->name,
                'icon' => 'fa-solid fa-compass',
                'hint' => __('Activity').' · Rp '.number_format((float) $prod->price, 0, ',', '.').'/pax',
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
            session(['operator.create_link.experience' => $value]);
        }
    }

    public function incrementCreatePax(): void
    {
        $this->createPaxCount = min($this->createPaxMax(), $this->createPaxCount + 1);
    }

    public function decrementCreatePax(): void
    {
        $this->createPaxCount = max(1, $this->createPaxCount - 1);
    }

    public function setCreateDatePreset(string $preset): void
    {
        $date = match ($preset) {
            'today' => now(),
            'tomorrow' => now()->addDay(),
            'soon' => now()->addDays(3),
            default => null,
        };

        if ($date === null) {
            return;
        }

        $this->createRequestedDate = $date->toDateString();
    }

    public function fillGuestFromCrm(string $guestId): void
    {
        $guest = $this->currentOperator?->guests()->find($guestId);

        if (! $guest) {
            return;
        }

        $this->createGuestName = (string) $guest->name;
        $this->createGuestContact = (string) ($guest->phone ?? '');
        $this->createGuestEmail = (string) ($guest->email ?? '');
        $this->createShowExtras = filled($this->createGuestEmail);
    }

    public function toggleCreateExtras(): void
    {
        $this->createShowExtras = ! $this->createShowExtras;
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
        $this->createShowExtras = false;

        $options = $this->experienceOptions;
        $last = (string) session('operator.create_link.experience', '');
        $match = collect($options)->firstWhere('value', $last);
        $chosen = is_array($match) ? (string) $match['value'] : (string) ($options[0]['value'] ?? '');

        if ($chosen !== '' && str_contains((string) $chosen, ':')) {
            $this->createExperienceSelection = (string) $chosen;
            [$this->createBookableType, $this->createBookableId] = explode(':', (string) $chosen, 2);
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
     * @return \App\Models\Package|\App\Models\Product|null
     */
    protected function resolveCreateBookable(): \App\Models\Package|\App\Models\Product|null
    {
        if (! $this->currentOperator || empty($this->createBookableId)) {
            return null;
        }

        return $this->createBookableType === 'package'
            ? $this->currentOperator->packages()->find($this->createBookableId)
            : $this->currentOperator->products()->find($this->createBookableId);
    }

    protected function createPaxMax(): int
    {
        $bookable = $this->resolveCreateBookable();
        $remaining = null;

        if ($bookable && $this->createRequestedDate !== '') {
            $remaining = app(\App\Services\CapacityService::class)->remainingCapacity($bookable, $this->createRequestedDate);
        }

        if (is_int($remaining)) {
            return max(1, min(50, $remaining));
        }

        return 50;
    }

    #[Computed]
    public function createBookableBlackoutDates(): array
    {
        if (!$this->currentOperator || empty($this->createExperienceSelection)) {
            return [];
        }

        if (str_contains($this->createExperienceSelection, ':')) {
            [$type, $id] = explode(':', $this->createExperienceSelection, 2);
            $bookable = $type === 'package'
                ? $this->currentOperator->packages()->find($id)
                : $this->currentOperator->products()->find($id);

            if ($bookable && method_exists($bookable, 'getBlackoutDates')) {
                return $bookable->getBlackoutDates();
            }
        }

        return [];
    }

    /**
     * Generate reservation, 30-min hold session, and WhatsApp payment invitation.
     */
    public function generateBookingLink(\App\Services\DokuPaymentService $paymentService, \App\Services\WhatsAppDispatchService $waService): void
    {
        if (!$this->currentOperator) {
            return;
        }

        $this->currentOperator->assertCheckoutAllowed();

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

        /** @var \App\Models\Package|\App\Models\Product|null $bookable */
        $bookable = $this->createBookableType === 'package' ? $this->currentOperator->packages()->find($this->createBookableId) : $this->currentOperator->products()->find($this->createBookableId);

        if (!$bookable) {
            $this->addError('createBookableId', __('Please select a valid experience.'));
            return;
        }

        if (method_exists($bookable, 'isBlackedOutOn') && $bookable->isBlackedOutOn($this->createRequestedDate)) {
            $this->addError('createRequestedDate', __('The selected date (:date) is blocked by an active blackout block for this experience.', ['date' => $this->createRequestedDate]));
            return;
        }

        $unitPrice = (float) ($bookable->price ?? 0);
        $subtotal = $unitPrice * $this->createPaxCount;
        $platform = \App\Models\PlatformSetting::current();
        $serviceFeeRate = $platform->getGuestServiceFeeRate();
        $serviceFee = $platform->calculateGuestServiceFee($subtotal, $this->currentOperator);
        $totalPrice = $subtotal + $serviceFee;

        $termsSnapshot = $bookable->generateTermsSnapshot();
        $termsSnapshot['unit_price'] = $unitPrice;
        $termsSnapshot['pax_count'] = $this->createPaxCount;
        $termsSnapshot['subtotal'] = $subtotal;
        $termsSnapshot['service_fee'] = $serviceFee;
        $termsSnapshot['service_fee_rate'] = $serviceFeeRate;
        $termsSnapshot['total_price'] = $totalPrice;

        try {
            /** @var Reservation $reservation */
            $reservation = app(\App\Services\CapacityService::class)->reserve(
                $bookable,
                $this->createRequestedDate,
                $this->createPaxCount,
                fn (): Reservation => Reservation::query()->create([
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
                ]),
            );
        } catch (\App\Exceptions\CapacityUnavailableException $e) {
            $this->addError('createRequestedDate', $e->getMessage());

            return;
        }

        $session = $paymentService->createPaymentSession($reservation, $totalPrice);
        $this->generatedPaymentUrl = $session['checkout_url'];
        $this->generatedReservationCode = $reservation->code ?? 'RSV-' . strtoupper(substr($reservation->id, -8));

        $itemTitle = $bookable instanceof \App\Models\Package ? $bookable->title : $bookable->name;
        $dateFormatted = Carbon::parse($this->createRequestedDate)->format('d M Y');
        $this->generatedWhatsAppUrl = null;

        if ($this->currentOperator->hasFeature('whatsapp_dispatch')) {
            $waText = "Halo Kak {$this->createGuestName}, berikut link pesanan & pembayaran untuk *{$itemTitle}* tanggal *{$dateFormatted}* ({$this->createPaxCount} pax).\n\nTotal: Rp " . number_format($totalPrice, 0, ',', '.') . "\n\nSilakan cek detail dan selesaikan pembayaran sebelum slot hold 30 menit berakhir:\n{$this->generatedPaymentUrl}";
            $this->generatedWhatsAppUrl = $waService->buildWhatsAppUrl($this->createGuestContact, $waText);
        }

        $this->linkCreatedSuccessfully = true;
        $this->actionSuccess = true;
        $this->actionMessage = __('Booking link created successfully.');
        session(['operator.create_link.experience' => $this->createExperienceSelection]);
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Get filtered reservations for the agent.
     *
     * @return LengthAwarePaginator<int, Reservation>
     */
    #[Computed]
    public function reservations(): LengthAwarePaginator
    {
        if (!$this->currentOperator) {
            return new Paginator([], 0, 20);
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

        return $query->paginate(20);
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
        $this->isEditingNotes = false;
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
        $this->isEditingNotes = false;
    }

    public function startEditingNotes(): void
    {
        $this->isEditingNotes = true;
    }

    public function cancelEditingNotes(): void
    {
        $this->isEditingNotes = false;
        if ($this->selectedReservation) {
            $this->agentNote = (string) ($this->selectedReservation->notes ?? '');
        }
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
        if (!$this->currentOperator) {
            return;
        }

        $res = $this->currentOperator->reservations()->with('latestPayment')->find($reservationId);

        if (!$res) {
            return;
        }

        // Safeguard 1: Cannot decline if payment has already been completed
        if ($statusValue === 'declined' && $res->latestPayment?->isPaid()) {
            $this->actionSuccess = false;
            $this->actionMessage = __('Cannot decline a paid reservation. Please process a cancellation and refund instead.');
            return;
        }

        // Safeguard 2: Cannot mark completed prematurely if trip date is in the future
        if ($statusValue === 'completed') {
            if ($res->status !== ReservationStatus::Confirmed) {
                $this->actionSuccess = false;
                $this->actionMessage = __('Only confirmed reservations can be marked as completed.');
                return;
            }

            if ($res->requested_date && $res->requested_date->isFuture() && !$res->requested_date->isToday()) {
                $this->actionSuccess = false;
                $this->actionMessage = __('Cannot mark a reservation completed before its scheduled trip date (:date).', [
                    'date' => $res->requested_date->format('M d, Y'),
                ]);
                return;
            }
        }

        // Safeguard 3: Cannot transition already cancelled or declined reservations directly to confirmed or completed
        if (in_array($res->status, [ReservationStatus::Cancelled, ReservationStatus::Declined], true) && in_array($statusValue, ['confirmed', 'completed'], true)) {
            $this->actionSuccess = false;
            $this->actionMessage = __('Cancelled or declined reservations cannot be transitioned directly.');
            return;
        }

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

        $res = $this->currentOperator->reservations()->with('latestPayment')->find($reservationId);

        if (!$res) {
            return;
        }

        $status = ReservationStatus::tryFrom($statusValue);

        if (!$status) {
            return;
        }

        // Safeguard 1: Cannot decline if paid
        if ($status === ReservationStatus::Declined && $res->latestPayment?->isPaid()) {
            $this->actionSuccess = false;
            $this->actionMessage = __('Cannot decline a paid reservation. Please process a cancellation and refund instead.');
            return;
        }

        // Safeguard 2: Cannot mark completed prematurely
        if ($status === ReservationStatus::Completed) {
            if ($res->status !== ReservationStatus::Confirmed) {
                $this->actionSuccess = false;
                $this->actionMessage = __('Only confirmed reservations can be marked as completed.');
                return;
            }

            if ($res->requested_date && $res->requested_date->isFuture() && !$res->requested_date->isToday()) {
                $this->actionSuccess = false;
                $this->actionMessage = __('Cannot mark a reservation completed before its scheduled trip date.');
                return;
            }
        }

        $res->update([
            'status' => $status,
            'hold_expires_at' => $status === ReservationStatus::PaymentPending ? now()->addMinutes(30) : null,
        ]);

        if ($status === ReservationStatus::Confirmed) {
            if (!empty($res->guest_email)) {
                try {
                    \Illuminate\Support\Facades\Mail::to($res->guest_email)->send(new \App\Mail\GuestBookingConfirmedMail($res));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            try {
                app(\App\Services\VendorDispatchService::class)->dispatchBookingConfirmation($res);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($status === ReservationStatus::Cancelled) {
            app(\App\Services\WalletService::class)->cancelBookingEarning($res, 'Cancelled by operator');

            try {
                app(\App\Services\VendorDispatchService::class)->dispatchBookingCancellation($res);
            } catch (\Throwable $e) {
                report($e);
            }
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
        $this->isEditingNotes = false;

        $this->actionSuccess = true;
        $this->actionMessage = __('Reservation notes saved successfully.');
    }
}; ?>

<div class="animate-fade-in space-y-6">
    <x-page-header
        :title="__('Bookings & Reservations')"
        :subtitle="__('Track, confirm, and manage direct guest reservations, payment receipts, and trip schedules.')"
        icon="fa-calendar-check"
    >
        <x-slot:actions>
            <span class="hidden text-xs font-semibold text-op-subtle sm:inline">
                {{ __('Showing :count reservations', ['count' => $this->reservations->total()]) }}
            </span>
            <div class="hidden lg:block">
                <x-button type="button" wire:click="openCreateLinkModal">
                    <i class="fa-solid fa-link text-xs"></i>
                    <span>{{ __('Create Booking Link') }}</span>
                </x-button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-metric-card
            :label="__('Confirmed Trips')"
            :value="number_format($this->metrics['confirmed'])"
            :hint="__('Active & upcoming reservations')"
            icon="fa-circle-check"
            tone="success"
        />
        <x-metric-card
            :label="__('Pending Holds')"
            :value="number_format($this->metrics['pending'])"
            :hint="__('Awaiting checkout payment (30m hold)')"
            icon="fa-clock"
            tone="warning"
        />
        <x-metric-card
            :label="__('Completed')"
            :value="number_format($this->metrics['completed'])"
            :hint="__('Fulfilled experiences & reviews ready')"
            icon="fa-flag-checkered"
            tone="info"
        />
        <x-metric-card
            :label="__('Paid Revenue')"
            :value="'Rp '.number_format($this->metrics['revenue'], 0, ',', '.')"
            :hint="__('Direct guest payments collected')"
            icon="fa-rupiah-sign"
            tone="brand"
        />
    </div>

    <x-toolbar>
        <div class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-center">
            <div class="relative flex-1">
                <x-search-input
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('Search by guest name, phone, email, or reservation ID...')"
                />
                @if ($search !== '')
                    <button wire:click="$set('search', '')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-op-subtle hover:text-op-ink">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                @endif
            </div>
            <div class="w-full sm:w-52">
                <x-select wire:model.live="dateFilter" :options="[
                    'all' => __('All Trip Dates'),
                    'upcoming' => __('Upcoming Trips Only'),
                    'this_month' => __('This Month'),
                    'past' => __('Past / Completed'),
                ]" />
            </div>
        </div>

        <x-filter-tabs class="border-t border-op-line pt-3">
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
                <x-filter-tab :active="$statusFilter === $tabKey" wire:click="$set('statusFilter', '{{ $tabKey }}')">
                    {{ $tabLabel }}
                </x-filter-tab>
            @endforeach
        </x-filter-tabs>
    </x-toolbar>

    <!-- Reservations Section -->
    <div
        class="rounded-2xl bg-white dark:bg-zinc-900 border border-stone-200 dark:border-zinc-800 shadow-xs overflow-hidden">
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
                <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-stone-200 dark:border-zinc-800 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="font-extrabold text-xs text-slate-900 dark:text-white truncate">
                                {{ $res->guest_name }}
                            </span>
                            <span class="font-mono text-[10px] font-semibold text-stone-700 dark:text-zinc-200 px-1.5 py-0.5 rounded bg-stone-100 dark:bg-zinc-800 shrink-0">
                                #{{ $resCode }}
                            </span>
                        </div>
                        <div class="shrink-0">
                            @if ($res->status->value === 'confirmed')
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60">Confirmed</span>
                            @elseif ($res->status->value === 'pending_confirmation')
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800/60">Pending Confirmation</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase bg-stone-100 text-stone-700 dark:bg-zinc-800 dark:text-zinc-300">{{ ucfirst($res->status->value) }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-stone-100 dark:border-zinc-800">
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

                    <div class="flex items-center justify-between pt-2 border-t border-stone-100 dark:border-zinc-800 text-[11px]">
                        <div>
                            @if ($res->guest_contact)
                                <a href="{{ $waUrl }}" target="_blank" class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                                    <i class="fa-brands fa-whatsapp text-xs"></i>
                                    <span>{{ __('Chat') }}</span>
                                </a>
                            @endif
                        </div>
                        <button type="button" wire:click="viewDetails('{{ $res->id }}')" class="h-9 px-3 rounded-xl bg-stone-100 dark:bg-zinc-800 text-stone-700 dark:text-zinc-300 font-semibold text-xs">
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
                    class="bg-stone-50 dark:bg-zinc-800 border-b border-stone-200 dark:border-zinc-700 text-[11px] font-semibold uppercase tracking-wider text-stone-400 dark:text-zinc-400">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Guest & Contact') }}</th>
                        <th class="px-4 py-3.5">{{ __('Booked Experience') }}</th>
                        <th class="px-4 py-3.5">{{ __('Trip Date') }}</th>
                        <th class="px-4 py-3.5">{{ __('Payment') }}</th>
                        <th class="px-4 py-3.5">{{ __('Status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 dark:divide-zinc-700">
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
                        <tr class="hover:bg-stone-50/70 dark:hover:bg-zinc-800/80 transition group">
                            <!-- Guest Info -->
                            <td class="px-5 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 dark:text-white">
                                            {{ $res->guest_name }}
                                        </span>
                                        <span
                                            class="font-mono text-[10px] font-semibold text-stone-700 dark:text-zinc-200 px-2 py-0.5 rounded-lg bg-stone-100 dark:bg-zinc-800">
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
                                                class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-amber-50 text-amber-800 dark:bg-amber-400/10 dark:text-amber-200">
                                                {{ __('Package') }}
                                            </span>
                                        @else
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-slate-100 text-slate-800 dark:bg-[#141821] dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]">
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
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 dark:bg-[#181d2a] dark:text-slate-400">
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

                                        @if ($res->guest_contact && $this->currentOperator?->hasFeature('whatsapp_dispatch'))
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
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-[#181d2a] dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]">
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
                                        @if (!($res->latestPayment?->isPaid()))
                                            <button type="button"
                                                wire:click="confirmStatusTransition('{{ $res->id }}', 'declined')"
                                                class="h-8 px-2 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/50 font-bold text-xs transition cursor-pointer"
                                                title="{{ __('Decline Booking') }}">
                                                <i class="fa-solid fa-xmark text-[10px]"></i>
                                            </button>
                                        @endif
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
                                            @if ($res->status === ReservationStatus::PaymentPending && $res->guest_contact && $this->currentOperator?->hasFeature('whatsapp_dispatch'))
                                                <a href="{{ app(\App\Services\WhatsAppDispatchService::class)->getPaymentHoldLinkUrl($res) }}"
                                                    target="_blank" @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-amber-50 dark:hover:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                                                    <i class="fa-brands fa-whatsapp w-4 text-emerald-500"></i>
                                                    {{ __('Send Payment Link (WA)') }}
                                                </a>
                                            @endif
                                            @if (in_array($res->status, [ReservationStatus::PaymentPending, ReservationStatus::PendingConfirmation], true))
                                                <button type="button"
                                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'confirmed')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                                                    <i class="fa-solid fa-circle-check w-4"></i>
                                                    {{ __('Mark Confirmed') }}
                                                </button>
                                            @endif
                                            @if (!in_array($res->status, [ReservationStatus::Declined, ReservationStatus::Completed, ReservationStatus::Cancelled], true) && !($res->latestPayment?->isPaid()))
                                                <button type="button"
                                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'declined')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-rose-600 dark:text-rose-400">
                                                    <i class="fa-solid fa-ban w-4"></i>
                                                    {{ __('Decline Booking') }}
                                                </button>
                                            @endif
                                            @if ($res->status === ReservationStatus::Confirmed && ($res->requested_date->isToday() || $res->requested_date->isPast()))
                                                <button type="button"
                                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'completed')"
                                                    @click="open = false"
                                                    class="w-full px-3.5 py-1.5 text-left flex items-center gap-2 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">
                                                    <i class="fa-solid fa-flag-checkered w-4"></i>
                                                    {{ __('Mark Completed') }}
                                                </button>
                                            @endif
                                            @if (!in_array($res->status, [ReservationStatus::Cancelled, ReservationStatus::Declined, ReservationStatus::Completed], true))
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

        @if ($this->reservations->hasPages())
            <div class="px-4 py-4 border-t border-slate-100 dark:border-[#1e2433]">
                {{ $this->reservations->onEachSide(1)->links() }}
            </div>
        @endif
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
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 overflow-y-auto bg-slate-900/60 backdrop-blur-xs">
                <div @click.away="$wire.closeDetails()"
                    class="w-full max-w-2xl rounded-t-3xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl flex flex-col my-0 sm:my-8 animate-scale-up max-h-[92vh]">
                    <!-- Modal Header -->
                    <div
                        class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div
                                class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
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
                        @if ($this->currentOperator?->hasFeature('whatsapp_dispatch'))
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
                        @else
                        <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900/60 space-y-2">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                {{ __('Ready-made WhatsApp messages are on Growth') }}
                            </p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('You can still copy the payment link. Pro writes the guest message for you.') }}
                            </p>
                            <a href="{{ route('settings.plan') }}" wire:navigate class="inline-flex text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ __('See Pro') }}
                            </a>
                        </div>
                        @endif

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
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200 dark:border-zinc-800 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <i class="fa-solid fa-note-sticky text-amber-500"></i>
                                    {{ __('Internal Reservation Notes') }}
                                </span>
                                @if (!$isEditingNotes)
                                    <button type="button" wire:click="startEditingNotes"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer">
                                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                        <span>{{ __('Edit Notes') }}</span>
                                    </button>
                                @endif
                            </div>

                            @if ($isEditingNotes)
                                <div class="space-y-2">
                                    <x-textarea id="agentNote" wire:model="agentNote" rows="3"
                                        placeholder="{{ __('Add special dietary requests, pickup instructions, guide assignments, etc...') }}"
                                        class="text-xs" />
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button size="sm" variant="secondary" wire:click="cancelEditingNotes"
                                            class="font-bold text-xs">
                                            {{ __('Cancel') }}
                                        </x-button>
                                        <x-button size="sm" variant="primary" wire:click="saveNotes"
                                            class="font-bold text-xs bg-indigo-600 hover:bg-indigo-700 text-white">
                                            <i class="fa-solid fa-floppy-disk mr-1"></i>
                                            {{ __('Save Notes') }}
                                        </x-button>
                                    </div>
                                </div>
                            @else
                                <div class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-wrap">
                                    {{ !empty($res->notes) ? $res->notes : __('No internal notes added yet.') }}
                                </div>
                            @endif
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
                            @if (in_array($res->status, [ReservationStatus::PaymentPending, ReservationStatus::PendingConfirmation], true))
                                <x-button size="sm" variant="primary"
                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'confirmed')"
                                    class="font-bold text-xs bg-emerald-600 hover:bg-emerald-700 text-white">
                                    <i class="fa-solid fa-circle-check mr-1 text-xs"></i>
                                    {{ __('Mark Confirmed') }}
                                </x-button>
                            @endif
                            @if (!in_array($res->status, [ReservationStatus::Declined, ReservationStatus::Completed, ReservationStatus::Cancelled], true) && !($latestPayment?->isPaid()))
                                <x-button size="sm" variant="danger"
                                    wire:click="confirmStatusTransition('{{ $res->id }}', 'declined')"
                                    class="font-bold text-xs">
                                    <i class="fa-solid fa-ban mr-1 text-xs"></i>
                                    {{ __('Decline') }}
                                </x-button>
                            @endif
                            @if ($res->status === ReservationStatus::Confirmed && ($res->requested_date->isToday() || $res->requested_date->isPast()))
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
        @php
            $dateToday = now()->toDateString();
            $dateTomorrow = now()->addDay()->toDateString();
            $dateSoon = now()->addDays(3)->toDateString();
        @endphp
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/75 backdrop-blur-xs overflow-y-auto"
                wire:keydown.escape="closeCreateLinkModal">
                <div class="relative flex w-full max-w-xl max-h-[92vh] sm:max-h-[88vh] flex-col overflow-hidden rounded-t-3xl border border-op-line bg-op-surface shadow-2xl sm:rounded-3xl"
                    @click.outside="if (!$event.target.closest('[data-date-picker-popover]')) $wire.closeCreateLinkModal()">

                    <div class="mx-auto my-2.5 h-1 w-12 shrink-0 rounded-full bg-op-line sm:hidden"></div>

                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-op-line bg-op-muted/70 px-5 py-4 sm:px-6">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-brand-400 text-brand-foreground">
                                <i class="fa-solid fa-link text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-bold leading-tight text-op-ink">
                                    {{ $linkCreatedSuccessfully ? __('Link ready') : __('Create a pay link') }}
                                </h3>
                                <p class="truncate text-xs text-op-subtle">
                                    {{ $linkCreatedSuccessfully ? __('Copy it, then paste in chat.') : __('Trip, guests, name and WhatsApp — then copy the link.') }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="closeCreateLinkModal"
                            class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl text-op-subtle hover:bg-op-muted hover:text-op-ink">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    @if ($linkCreatedSuccessfully)
                        <div class="flex-1 space-y-4 overflow-y-auto p-5 sm:p-6"
                            x-data="{ copied: false }"
                            x-init="$nextTick(() => $refs.payLink?.select())">
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-center dark:border-emerald-800 dark:bg-emerald-950/40">
                                <p class="text-sm font-bold text-emerald-900 dark:text-emerald-200">
                                    {{ __('Hold is on for 30 minutes') }}
                                </p>
                                <p class="mt-0.5 font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                    #{{ $generatedReservationCode }}
                                    @if ($this->createQuote['title'])
                                        · {{ $this->createQuote['title'] }}
                                    @endif
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-op-subtle">{{ __('Pay link') }}</label>
                                <div class="flex items-center gap-2">
                                    <input x-ref="payLink" type="text" readonly value="{{ $generatedPaymentUrl }}"
                                        class="op-input min-w-0 flex-1 font-mono text-xs" />
                                    <button type="button"
                                        @click="navigator.clipboard.writeText($refs.payLink.value); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="inline-flex h-10 shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-brand-400 px-4 text-xs font-semibold text-brand-foreground hover:bg-brand-500">
                                        <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                                        <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
                                    </button>
                                </div>
                            </div>

                            @if ($generatedWhatsAppUrl)
                                <a href="{{ $generatedWhatsAppUrl }}" target="_blank" rel="noopener"
                                    class="inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-op-line bg-op-muted text-sm font-semibold text-op-ink hover:bg-op-line">
                                    <i class="fa-brands fa-whatsapp text-base text-emerald-600"></i>
                                    <span>{{ __('Open WhatsApp to paste') }}</span>
                                </a>
                            @endif

                            <div class="flex items-center justify-between gap-3 border-t border-op-line pt-3">
                                <button type="button" wire:click="openCreateLinkModal"
                                    class="cursor-pointer text-xs font-semibold text-op-ink hover:underline">
                                    {{ __('Make another') }}
                                </button>
                                <x-button size="sm" variant="secondary" wire:click="closeCreateLinkModal">
                                    {{ __('Done') }}
                                </x-button>
                            </div>
                        </div>
                    @else
                        <form wire:submit="generateBookingLink" class="flex flex-1 flex-col overflow-hidden">
                            <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain p-5 sm:p-6">
                                @if ($this->currentOperator?->isDemo())
                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
                                        <p class="font-semibold">{{ __('Sample shop') }}</p>
                                        <p class="mt-1 text-xs text-amber-800 dark:text-amber-200/80">
                                            {{ __('Pay links stay off here so visitors are never charged. This is not platform maintenance.') }}
                                        </p>
                                    </div>
                                @else
                                    <x-input-error :messages="$errors->get('checkout')" />
                                @endif

                                @if ($this->experienceOptions === [])
                                    <div class="rounded-2xl border border-op-line bg-op-muted p-4 text-sm text-op-ink">
                                        <p class="font-semibold">{{ __('Publish a trip first') }}</p>
                                        <p class="mt-1 text-xs text-op-subtle">{{ __('This link needs a published package or activity.') }}</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <x-button size="xs" :href="route('products.create')" wire:navigate>{{ __('Add activity') }}</x-button>
                                            <x-button size="xs" variant="secondary" :href="route('packages.create')" wire:navigate>{{ __('Add package') }}</x-button>
                                        </div>
                                    </div>
                                @else
                                    <div>
                                        <x-label for="createExperienceSelection" :value="__('Trip')" required />
                                        <x-select id="createExperienceSelection" wire:model.live="createExperienceSelection"
                                            :options="$this->experienceOptions" :placeholder="__('Package or activity...')" :error="$errors->has('createBookableId')" />
                                        <x-input-error :messages="$errors->get('createBookableId')" />
                                    </div>
                                @endif

                                <div class="space-y-3">
                                    <div class="flex h-8 w-full overflow-hidden rounded-xl border border-op-line p-0.5">
                                        <button type="button" wire:click="setCreateDatePreset('today')"
                                            @class(['min-w-0 flex-1 rounded-lg text-[11px] font-semibold cursor-pointer', $createRequestedDate === $dateToday ? 'bg-brand-400 text-brand-foreground' : 'text-op-subtle hover:bg-op-muted hover:text-op-ink'])>
                                            {{ __('Today') }}
                                        </button>
                                        <button type="button" wire:click="setCreateDatePreset('tomorrow')"
                                            @class(['min-w-0 flex-1 rounded-lg text-[11px] font-semibold cursor-pointer', $createRequestedDate === $dateTomorrow ? 'bg-brand-400 text-brand-foreground' : 'text-op-subtle hover:bg-op-muted hover:text-op-ink'])>
                                            {{ __('Tomorrow') }}
                                        </button>
                                        <button type="button" wire:click="setCreateDatePreset('soon')"
                                            @class(['min-w-0 flex-1 rounded-lg text-[11px] font-semibold cursor-pointer', $createRequestedDate === $dateSoon ? 'bg-brand-400 text-brand-foreground' : 'text-op-subtle hover:bg-op-muted hover:text-op-ink'])>
                                            {{ __('In 3 days') }}
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_8.75rem]">
                                        <div>
                                            <x-label for="createRequestedDate" :value="__('Date')" required />
                                            <x-date-picker id="createRequestedDate" class="h-10 py-0 leading-5" wire:model.live="createRequestedDate"
                                                min="{{ now()->format('Y-m-d') }}" :blackout-dates="$this->createBookableBlackoutDates" :placeholder="__('Select date...')" :error="$errors->has('createRequestedDate')" />
                                            <x-input-error :messages="$errors->get('createRequestedDate')" />
                                        </div>
                                        <div>
                                            <x-label for="createPaxCount" :value="__('Guests')" required />
                                            <div class="flex h-10 items-center overflow-hidden rounded-xl border border-op-line bg-op-inset shadow-xs">
                                                <button type="button" wire:click="decrementCreatePax"
                                                    class="flex h-10 w-9 shrink-0 cursor-pointer items-center justify-center text-op-ink hover:bg-op-muted disabled:cursor-not-allowed disabled:opacity-40"
                                                    @disabled($createPaxCount <= 1)>
                                                    <i class="fa-solid fa-minus text-xs"></i>
                                                </button>
                                                <input id="createPaxCount" wire:model.live="createPaxCount" type="number"
                                                    min="1" max="50"
                                                    class="h-10 min-w-0 flex-1 border-0 bg-transparent text-center text-sm font-semibold text-op-ink focus:ring-0" />
                                                <button type="button" wire:click="incrementCreatePax"
                                                    class="flex h-10 w-9 shrink-0 cursor-pointer items-center justify-center text-op-ink hover:bg-op-muted">
                                                    <i class="fa-solid fa-plus text-xs"></i>
                                                </button>
                                            </div>
                                            <x-input-error :messages="$errors->get('createPaxCount')" />
                                            @if (is_int($this->createQuote['remaining']))
                                                <p class="mt-1 truncate text-[11px] text-op-subtle">
                                                    {{ trans_choice(':count spot left|:count spots left', $this->createQuote['remaining'], ['count' => $this->createQuote['remaining']]) }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    @if ($this->recentGuests->isNotEmpty())
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-[11px] font-semibold text-op-subtle">{{ __('Recent') }}</span>
                                            @foreach ($this->recentGuests as $recentGuest)
                                                <button type="button" wire:click="fillGuestFromCrm('{{ $recentGuest->id }}')"
                                                    class="h-7 cursor-pointer rounded-full border border-op-line bg-op-muted px-2.5 text-[11px] font-semibold text-op-ink hover:border-brand-400">
                                                    {{ $recentGuest->name }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <x-label for="createGuestName" :value="__('Name')" required />
                                            <x-input id="createGuestName" wire:model="createGuestName" type="text"
                                                placeholder="{{ __('Guest name') }}" autofocus :error="$errors->has('createGuestName')" />
                                            <x-input-error :messages="$errors->get('createGuestName')" />
                                        </div>
                                        <div>
                                            <x-label for="createGuestContact" :value="__('WhatsApp')" required />
                                            <x-input id="createGuestContact" wire:model="createGuestContact" type="tel"
                                                placeholder="081234567890" :error="$errors->has('createGuestContact')" />
                                            <x-input-error :messages="$errors->get('createGuestContact')" />
                                        </div>
                                    </div>
                                </div>

                                <button type="button" wire:click="toggleCreateExtras"
                                    class="cursor-pointer text-xs font-semibold text-op-subtle hover:text-op-ink">
                                    <i class="fa-solid {{ $createShowExtras ? 'fa-chevron-up' : 'fa-chevron-down' }} mr-1 text-[10px]"></i>
                                    {{ $createShowExtras ? __('Hide email & notes') : __('Add email or pickup notes') }}
                                </button>

                                @if ($createShowExtras)
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <x-label for="createGuestEmail" :value="__('Email')" />
                                            <x-input id="createGuestEmail" wire:model="createGuestEmail" type="email"
                                                placeholder="guest@email.com" :error="$errors->has('createGuestEmail')" />
                                            <x-input-error :messages="$errors->get('createGuestEmail')" />
                                        </div>
                                        <div>
                                            <x-label for="createNotes" :value="__('Pickup notes')" />
                                            <x-input id="createNotes" wire:model="createNotes" type="text"
                                                placeholder="{{ __('Hotel, 07:30') }}" :error="$errors->has('createNotes')" />
                                            <x-input-error :messages="$errors->get('createNotes')" />
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center justify-between gap-3 border-t border-op-line bg-op-surface px-5 py-3 pb-6 sm:px-6 sm:pb-4">
                                <div class="min-w-0">
                                    @if ($this->createQuote['total'] > 0)
                                        <p class="text-sm font-bold text-op-ink">
                                            Rp {{ number_format($this->createQuote['total'], 0, ',', '.') }}
                                        </p>
                                        <p class="truncate text-[11px] text-op-subtle">
                                            {{ $this->createQuote['pax'] }} × Rp {{ number_format($this->createQuote['unit'], 0, ',', '.') }}
                                            @if ($this->createQuote['fee'] > 0)
                                                + {{ __('fee') }}
                                            @endif
                                        </p>
                                    @else
                                        <p class="text-xs text-op-subtle">{{ __('Pick a trip to see the total') }}</p>
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <x-button type="button" size="sm" variant="ghost" wire:click="closeCreateLinkModal">
                                        {{ __('Cancel') }}
                                    </x-button>
                                    <x-button type="submit" size="sm" :disabled="$this->experienceOptions === [] || $this->currentOperator?->isDemo()">
                                        <span wire:loading.remove wire:target="generateBookingLink">{{ __('Create link') }}</span>
                                        <span wire:loading wire:target="generateBookingLink">{{ __('Creating…') }}</span>
                                    </x-button>
                                </div>
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
                                    class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200 flex items-center justify-center text-base shrink-0 mt-0.5">
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
