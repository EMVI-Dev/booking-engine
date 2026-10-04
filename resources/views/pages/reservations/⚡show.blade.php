<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\DokuPaymentService;
use App\Services\GoogleCalendarService;
use App\Services\ReservationLifecycleService;
use App\Services\WhatsAppDispatchService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reservation Details')] class extends Component {
    use ResolvesCurrentOperator;

    public Reservation $reservation;

    public bool $isEditingNotes = false;

    public string $agentNote = '';

    public bool $showConfirmStatusModal = false;

    #[Locked]
    public ?string $pendingStatusValue = null;

    public ?string $actionMessage = null;

    public bool $actionSuccess = true;

    public bool $showTripInfoModal = false;

    public function openTripInfoModal(): void
    {
        $this->showTripInfoModal = true;
    }

    public function closeTripInfoModal(): void
    {
        $this->showTripInfoModal = false;
    }

    public function mount(Reservation $reservation): void
    {
        if (!$this->currentOperator || $reservation->operator_id !== $this->currentOperator->id) {
            abort(404);
        }

        $reservation->loadMissing(['bookable', 'latestPayment', 'payments', 'guest', 'walletTransactions']);

        $this->reservation = $reservation;
        $this->agentNote = (string) ($reservation->notes ?? '');
    }

    public function startEditingNotes(): void
    {
        $this->agentNote = (string) ($this->reservation->notes ?? '');
        $this->isEditingNotes = true;
    }

    public function cancelEditingNotes(): void
    {
        $this->agentNote = (string) ($this->reservation->notes ?? '');
        $this->isEditingNotes = false;
    }

    public function saveNotes(): void
    {
        $this->authorizeAbility('manageReservations');

        $this->reservation->update([
            'notes' => trim($this->agentNote) !== '' ? trim($this->agentNote) : null,
        ]);

        $this->reservation->refresh();
        $this->isEditingNotes = false;
        $this->dispatch('toast', message: __('Internal reservation notes saved.'), type: 'success');
    }

    public function syncPaymentStatus(): void
    {
        $this->authorizeAbility('manageReservations');

        $payment = $this->reservation->latestPayment;

        if (!$payment) {
            $this->dispatch('toast', message: __('No payment record found for this reservation.'), type: 'warning');

            return;
        }

        if ($payment->isPaid()) {
            $this->dispatch('toast', message: __('This payment is already confirmed as paid.'), type: 'info');

            return;
        }

        try {
            app(DokuPaymentService::class)->syncPaymentStatus($payment);

            $this->reservation->refresh()->load('latestPayment', 'walletTransactions');

            if ($this->reservation->latestPayment?->isPaid()) {
                $this->dispatch('toast', message: __('Payment confirmed! Status updated to Paid.'), type: 'success');
            } else {
                $this->dispatch('toast', message: __('Payment is still pending on the payment gateway.'), type: 'info');
            }
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: __('Could not reach the payment gateway. Please try again in a few minutes.'), type: 'danger');
        }
    }

    public function confirmStatusTransition(string $statusValue): void
    {
        $this->authorizeAbility('manageReservations');

        $target = ReservationStatus::tryFrom($statusValue);
        if (!$target) {
            return;
        }

        if ($reason = app(ReservationLifecycleService::class)->operatorBlockReason($this->reservation, $target)) {
            $this->dispatch('toast', message: $reason, type: 'warning');

            return;
        }

        $this->pendingStatusValue = $statusValue;
        $this->showConfirmStatusModal = true;
    }

    public function closeConfirmStatusModal(): void
    {
        $this->showConfirmStatusModal = false;
        $this->pendingStatusValue = null;
    }

    public function executeStatusTransition(): void
    {
        if (!$this->pendingStatusValue) {
            return;
        }

        $this->authorizeAbility('manageReservations');

        $status = ReservationStatus::tryFrom($this->pendingStatusValue);
        if (!$status) {
            return;
        }

        $this->showConfirmStatusModal = false;
        $this->pendingStatusValue = null;

        try {
            $result = app(ReservationLifecycleService::class)->transitionByOperator($this->reservation, $status);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->reservation->refresh()->loadMissing(['latestPayment', 'walletTransactions']);
            $this->dispatch('toast', message: (string) collect($e->errors())->flatten()->first(), type: 'warning');

            return;
        }

        $this->reservation->refresh()->loadMissing(['latestPayment', 'walletTransactions']);

        $message = match (true) {
            $status === ReservationStatus::Cancelled && $result['refunded'] => __('Reservation cancelled and full refund of :amount issued to guest.', ['amount' => 'Rp ' . number_format($result['refund_amount'], 0, ',', '.')]),
            $status === ReservationStatus::Cancelled => __('Reservation cancelled and inventory released.'),
            default => __('Reservation status updated to :status', ['status' => $status->label()]),
        };

        $this->dispatch('toast', message: $message, type: 'success');
    }
}; ?>

<div class="space-y-6">
    @php
        $res = $this->reservation;
        $bookable = $res->bookable;
        $latestPayment = $res->latestPayment;
        $cleanPhone = \App\Services\PhoneNumber::normalize($res->guest_contact);
        $resCode = $res->code ?? 'RSV-' . strtoupper(substr($res->id, -8));

        $directWaUrl =
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

        $waService = app(WhatsAppDispatchService::class);
        $voucherWaUrl = $waService->getConfirmationUrl($res);
        $reminderWaUrl = $waService->getReminderUrl($res);
        $meetingWaUrl = $waService->getMeetingPointUrl($res);
        $paymentHoldWaUrl = $waService->getPaymentHoldLinkUrl($res);

        $gcalUrl = app(GoogleCalendarService::class)->buildGoogleCalendarUrl($res);
        $eTicketUrl = route('storefront.reservation.ticket', $res);
        $receiptUrl = route('storefront.reservation.receipt', $res);
        $paymentUrl = route('storefront.reservation.pay', $res);

        $earningTx = $res->walletTransactions->firstWhere('type', \App\Enums\WalletTransactionType::BookingEarning);
        $isPaid = (bool) $latestPayment?->isPaid();
        $isPendingHold = $res->status === ReservationStatus::PaymentPending;
        $frozenPrice = (float) $res->getFrozenPrice();
        $totalCalculated = (float) $res->getTotalAmount();
        $appliedCouponCode = $res->terms_snapshot['coupon_code'] ?? ($latestPayment?->split_details['coupon_code'] ?? null);
        $couponDiscount = (float) ($res->terms_snapshot['discount_amount'] ?? ($latestPayment?->split_details['discount_amount'] ?? 0));
        $originalSubtotal = (float) ($res->terms_snapshot['subtotal'] ?? ($frozenPrice * $res->pax_count));
    @endphp

    <!-- Top Breadcrumb & Quick Actions Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-3 min-w-0">
            <x-back-link :href="route('reservations.index')">
                {{ __('All Reservations') }}
            </x-back-link>

            <div class="flex items-start gap-3 sm:gap-4 min-w-0">
                <div
                    class="w-10 h-10 rounded-[8px] bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 font-medium text-base flex items-center justify-center shrink-0 shadow-none">
                    <i class="fa-solid fa-receipt"></i>
                </div>

                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-[20px] font-medium tracking-tight text-op-ink truncate">
                            {{ $res->guest_name }}
                        </h1>
                        <span
                            class="font-mono text-xs font-medium text-op-ink px-2 py-0.5 rounded-[4px] bg-[#F7F8F9] dark:bg-[#1A2030] border border-[#E4E5E9] dark:border-[#1E2433]">
                            #{{ $resCode }}
                        </span>
                        <x-status-badge :status="$res->status" />
                    </div>

                    <p class="text-xs text-op-subtle flex items-center gap-2">
                        <span><i
                                class="fa-regular fa-clock mr-1"></i>{{ __('Booked on :date', ['date' => $res->created_at?->format('M d, Y · H:i') ?? '—']) }}</span>
                        <span>·</span>
                        <span>{{ __(':count Guests (Pax)', ['count' => $res->pax_count]) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Action Header Buttons -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @if ($isPaid)
                <x-button :href="$eTicketUrl" target="_blank" rel="noopener" variant="secondary" size="sm">
                    <i class="fa-solid fa-ticket text-xs text-indigo-500"></i>
                    <span>{{ __('E-Ticket') }}</span>
                </x-button>
            @endif

            <x-button :href="$receiptUrl" target="_blank" rel="noopener" variant="secondary" size="sm">
                <i class="fa-solid fa-file-invoice text-xs text-op-subtle"></i>
                <span>{{ __('Receipt') }}</span>
            </x-button>

            <!-- Status Controls -->
            @if (in_array($res->status, [ReservationStatus::PaymentPending, ReservationStatus::PendingConfirmation], true))
                <x-button type="button" variant="success" wire:click="confirmStatusTransition('confirmed')" size="sm">
                    <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                    {{ __('Confirm Booking') }}
                </x-button>

                @if (!$isPaid)
                    <x-button type="button" variant="danger" wire:click="confirmStatusTransition('declined')"
                        size="sm">
                        <i class="fa-solid fa-ban mr-1.5 text-xs"></i>
                        {{ __('Decline') }}
                    </x-button>
                @endif
            @endif

            @if (
                $res->status === ReservationStatus::Confirmed &&
                    ($res->requested_date->isToday() || $res->requested_date->isPast()))
                <x-button type="button" variant="primary" wire:click="confirmStatusTransition('completed')" size="sm">
                    <i class="fa-solid fa-flag-checkered mr-1.5 text-xs"></i>
                    {{ __('Mark Completed') }}
                </x-button>
            @endif

            @if (
                !in_array(
                    $res->status,
                    [ReservationStatus::Cancelled, ReservationStatus::Declined, ReservationStatus::Completed],
                    true))
                <x-button type="button" variant="danger" wire:click="confirmStatusTransition('cancelled')"
                    size="sm">
                    <i class="fa-solid fa-xmark mr-1.5 text-xs"></i>
                    {{ __('Cancel Booking') }}
                </x-button>
            @endif
        </div>
    </div>

    <!-- Active Payment Hold Notice (If Payment Pending) -->
    @if ($isPendingHold)
        <div
            class="p-3.5 sm:p-4 rounded-[12px] bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-none">
            <div class="flex items-center gap-3">
                <div
                    class="w-8 h-8 rounded-[6px] bg-amber-500 text-white flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-hourglass-half animate-pulse"></i>
                </div>
                <div>
                    <h4 class="font-medium text-sm text-amber-900 dark:text-amber-200">
                        {{ __('Reservation on 30-Minute Payment Hold') }}
                    </h4>
                    <p class="text-xs text-amber-800/80 dark:text-amber-300/80">
                        @if ($res->hold_expires_at)
                            {{ __('Time remaining: :diff (Expires at :time)', ['diff' => $res->hold_expires_at->diffForHumans(), 'time' => $res->hold_expires_at->format('H:i')]) }}
                        @else
                            {{ __('Awaiting guest online payment completion.') }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ $paymentHoldWaUrl }}" target="_blank"
                    class="h-8 px-3 rounded-[6px] bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs transition flex items-center gap-1.5">
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                    <span>{{ __('Send Pay Link') }}</span>
                </a>

                <button type="button" wire:click="syncPaymentStatus"
                    class="h-8 px-3 rounded-[6px] bg-white dark:bg-[#151a26] hover:bg-amber-50 dark:hover:bg-zinc-800 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-800 font-medium text-xs transition flex items-center gap-1.5 cursor-pointer shadow-none">
                    <i class="fa-solid fa-arrows-rotate text-xs" wire:loading.class="animate-spin"
                        wire:target="syncPaymentStatus"></i>
                    <span>{{ __('Check Gateway') }}</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Two-Column Master Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- LEFT COLUMN (2 Cols): Tour Details, Guest Profile, WhatsApp Dispatch, Notes, Terms -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Card 1: Experience & Schedule Overview -->
            <div
                class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-op-line">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#FFEF4D]"></span>
                        <h2 class="text-xs font-medium uppercase tracking-wider text-op-subtle">
                            {{ __('Booked Experience') }}
                        </h2>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openTripInfoModal"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-op-muted hover:bg-op-line text-op-ink border border-op-line font-medium text-xs transition cursor-pointer">
                            <i class="fa-solid fa-circle-info text-xs"></i>
                            <span>{{ __('Trip Info') }}</span>
                        </button>

                        <span
                            class="px-2 py-0.5 rounded-[4px] text-[11px] font-medium uppercase {{ $res->bookable_type === 'package' ? 'bg-[#FFEF4D]/20 text-[#12181E] dark:bg-[#FFEF4D]/15 dark:text-[#FFEF4D]' : 'bg-op-muted text-op-ink border border-op-line' }}">
                            {{ $res->bookable_type === 'package' ? __('Tour Package') : __('Standalone Product') }}
                        </span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-lg font-medium text-op-ink leading-snug">
                            {{ $bookable->name ?? ($bookable->title ?? __('Direct Experience Item')) }}
                        </h3>
                        @if ($appliedCouponCode)
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-xs font-medium bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60">
                                <i class="fa-solid fa-tag text-[10px] text-amber-600 dark:text-amber-400"></i>
                                <span>{{ __('Promo: :code', ['code' => $appliedCouponCode]) }}</span>
                                @if ($couponDiscount > 0)
                                    <span class="font-medium text-amber-700 dark:text-amber-300">(-Rp {{ number_format($couponDiscount, 0, ',', '.') }})</span>
                                @endif
                            </span>
                        @endif
                    </div>

                    @if ($bookable && !empty($bookable->description))
                        <p class="text-xs text-op-subtle line-clamp-3 leading-relaxed">
                            {{ $bookable->description }}
                        </p>
                    @endif
                </div>

                <!-- Schedule Specs Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                    <div
                        class="p-3 rounded-[8px] bg-[#F7F8F9] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                        <span
                            class="text-[10px] font-medium text-op-subtle uppercase tracking-wider">{{ __('Trip Date') }}</span>
                        <p class="font-medium text-sm text-op-ink">
                            {{ $res->requested_date->format('l, M d, Y') }}
                        </p>
                        <span class="text-[11px] text-op-subtle">
                            {{ $res->requested_date->isToday() ? __('Today') : $res->requested_date->diffForHumans() }}
                        </span>
                    </div>

                    <div
                        class="p-3 rounded-[8px] bg-[#F7F8F9] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                        <span
                            class="text-[10px] font-medium text-op-subtle uppercase tracking-wider">{{ __('Party Size') }}</span>
                        <p class="font-medium text-sm text-op-ink flex items-center gap-1.5">
                            <i class="fa-solid fa-users text-op-subtle text-xs"></i>
                            {{ __(':count Guests (Pax)', ['count' => $res->pax_count]) }}
                        </p>
                        <span class="text-[11px] text-op-subtle">
                            Rp {{ number_format($frozenPrice, 0, ',', '.') }} / pax
                        </span>
                    </div>

                    <div
                        class="p-3 rounded-[8px] bg-[#F7F8F9] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5 flex flex-col justify-between">
                        <div>
                            <span
                                class="text-[10px] font-medium text-op-subtle uppercase tracking-wider">{{ __('Calendar Sync') }}</span>
                            <p class="font-medium text-sm text-op-ink">{{ __('Google Cal') }}</p>
                        </div>
                        <a href="{{ $gcalUrl }}" target="_blank"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            <i class="fa-brands fa-google text-xs"></i>
                            <span>{{ __('Add to Schedule') }}</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 2: Guest Details & CRM Connection -->
            <div
                class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-op-line">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-user text-op-subtle text-xs"></i>
                        <h2 class="text-xs font-medium uppercase tracking-wider text-op-subtle">
                            {{ __('Guest Profile & Contact') }}
                        </h2>
                    </div>

                    @if ($res->guest)
                        <a href="{{ route('guests.show', $res->guest) }}" wire:navigate
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            <i class="fa-solid fa-address-book text-xs"></i>
                            <span>{{ __('View CRM History') }} &rarr;</span>
                        </a>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="w-10 h-10 rounded-[8px] bg-op-muted text-op-ink font-medium text-sm flex items-center justify-center shrink-0 border border-op-line">
                            {{ strtoupper(substr($res->guest_name, 0, 2)) }}
                        </div>
                        <div class="space-y-0.5 min-w-0">
                            <h4 class="font-medium text-sm sm:text-base text-op-ink leading-tight">
                                {{ $res->guest_name }}
                            </h4>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-op-subtle">
                                @if ($res->guest_email)
                                    <a href="mailto:{{ $res->guest_email }}"
                                        class="flex items-center gap-1.5 hover:text-op-ink">
                                        <i class="fa-solid fa-envelope text-op-subtle"></i>
                                        <span>{{ $res->guest_email }}</span>
                                    </a>
                                @endif
                                <a href="{{ $directWaUrl }}" target="_blank"
                                    class="flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400 hover:underline">
                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                    <span>{{ $res->guest_contact }}</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <a href="{{ $directWaUrl }}" target="_blank"
                        class="h-8 px-3 rounded-[6px] bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-medium text-xs transition flex items-center justify-center gap-1.5 shrink-0">
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>{{ __('Direct WhatsApp Chat') }}</span>
                    </a>
                </div>
            </div>

            <!-- Card 3: 1-Click WhatsApp Dispatch Center -->
            @if ($this->currentOperator?->hasFeature('whatsapp_dispatch'))
                <div
                    class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                    <div
                        class="flex items-center justify-between pb-3 border-b border-op-line">
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-whatsapp text-emerald-500 text-sm"></i>
                            <h2 class="text-xs font-medium uppercase tracking-wider text-op-subtle">
                                {{ __('1-Click WhatsApp Guest Dispatch') }}
                            </h2>
                        </div>

                        <span
                            class="text-[10px] font-medium text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
                            {{ __('Pre-Formatted Guest Messages') }}
                        </span>
                    </div>

                    <p class="text-xs text-op-subtle">
                        {{ __('Trigger formatted notifications directly into your WhatsApp web or mobile client. All booking vouchers, departure reminders, and meeting points are auto-populated.') }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                        <a href="{{ $voucherWaUrl }}" target="_blank"
                            class="h-8 px-3 rounded-[6px] bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs transition flex items-center justify-center gap-1.5 text-center">
                            <i class="fa-solid fa-ticket text-xs"></i>
                            <span class="truncate">{{ __('Send E-Voucher') }}</span>
                        </a>

                        <a href="{{ $reminderWaUrl }}" target="_blank"
                            class="h-8 px-3 rounded-[6px] bg-white dark:bg-[#151a26] hover:bg-emerald-50 dark:hover:bg-zinc-800 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/80 font-medium text-xs transition flex items-center justify-center gap-1.5 text-center">
                            <i class="fa-solid fa-bell text-xs"></i>
                            <span class="truncate">{{ __('Send 24h Reminder') }}</span>
                        </a>

                        <a href="{{ $meetingWaUrl }}" target="_blank"
                            class="h-8 px-3 rounded-[6px] bg-white dark:bg-[#151a26] hover:bg-emerald-50 dark:hover:bg-zinc-800 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/80 font-medium text-xs transition flex items-center justify-center gap-1.5 text-center">
                            <i class="fa-solid fa-location-dot text-xs"></i>
                            <span class="truncate">{{ __('Send Meeting Pin') }}</span>
                        </a>
                    </div>
                </div>
            @else
                <div
                    class="p-4 sm:p-5 rounded-[12px] bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/70 dark:border-amber-900/50 shadow-none space-y-2">
                    <div class="flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp text-amber-600 dark:text-amber-400 text-base"></i>
                        <h4 class="font-medium text-sm text-amber-900 dark:text-amber-200">
                            {{ __('Ready-made WhatsApp messages are on Growth') }}
                        </h4>
                    </div>
                    <p class="text-xs text-amber-800 dark:text-amber-300/80">
                        {{ __('Upgrade your plan to send 1-click booking vouchers, departure reminders, and meeting locations directly to guests via WhatsApp.') }}
                    </p>
                </div>
            @endif

            <!-- Card 4: Internal Driver & Operational Notes -->
            <div
                class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-op-line">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-note-sticky text-amber-500 text-xs"></i>
                        <h2 class="text-xs font-medium uppercase tracking-wider text-op-subtle">
                            {{ __('Internal Operational Notes') }}
                        </h2>
                    </div>

                    @if (!$isEditingNotes)
                        <button type="button" wire:click="startEditingNotes"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                            <span>{{ __('Edit Notes') }}</span>
                        </button>
                    @endif
                </div>

                @if ($isEditingNotes)
                    <div class="space-y-3">
                        <x-textarea id="agentNote" wire:model="agentNote" rows="4"
                            placeholder="{{ __('Add hotel room number, guide assignment, diet requests, or vehicle plate numbers...') }}"
                            class="text-xs" />
                        <div class="flex items-center justify-end gap-2">
                            <x-button size="sm" variant="secondary" wire:click="cancelEditingNotes">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button size="sm" variant="primary" wire:click="saveNotes">
                                <i class="fa-solid fa-floppy-disk mr-1.5"></i>
                                <span>{{ __('Save Notes') }}</span>
                            </x-button>
                        </div>
                    </div>
                @else
                    <div
                        class="p-3.5 rounded-[8px] bg-[#F7F8F9] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] text-xs text-op-ink leading-relaxed min-h-16">
                        {{ !empty($res->notes) ? $res->notes : __('No operational notes recorded yet. Click Edit Notes to add driver pickup notes or dietary preferences.') }}
                    </div>
                @endif
            </div>

            <!-- Card 5: Frozen Terms & Policy Snapshot -->
            @if (!empty($res->terms_snapshot))
                <div x-data="{ openTerms: false }"
                    class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-3">
                    <button type="button" @click="openTerms = !openTerms"
                        class="w-full flex items-center justify-between text-xs font-medium text-op-ink cursor-pointer">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-shield-halved text-op-subtle"></i>
                            {{ __('Frozen Terms & Cancellation Policy Snapshot') }}
                        </span>
                        <div class="flex items-center gap-2 text-op-subtle">
                            <span
                                class="text-[11px] font-normal">{{ __('Cutoff: :hours hrs', ['hours' => $res->getFrozenFreeCancellationHours()]) }}</span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200"
                                :class="openTerms ? 'rotate-180' : ''"></i>
                        </div>
                    </button>

                    <div x-show="openTerms" x-cloak
                        class="pt-3 border-t border-op-line space-y-3 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-op-subtle">
                            <div>
                                <span
                                    class="font-medium text-op-ink block mb-1">{{ __('Cancellation Terms:') }}</span>
                                <p>{{ $res->terms_snapshot['cancellation_terms'] ?? __('Standard policy.') }}</p>
                            </div>
                            <div>
                                <span
                                    class="font-medium text-op-ink block mb-1">{{ __('Frozen At:') }}</span>
                                <p class="font-mono">{{ $res->terms_snapshot['frozen_at'] ?? '—' }}</p>
                            </div>
                        </div>

                        <div
                            class="p-3 rounded-[6px] bg-[#F7F8F9] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] font-mono text-[11px] text-op-subtle overflow-x-auto max-h-48">
                            {{ json_encode($res->terms_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
                        </div>
                    </div>
                </div>
            @endif

        </div>

        <!-- RIGHT COLUMN (1 Col): Financial Breakdown, Escrow Status, Quick Links, Timeline -->
        <div class="space-y-5">

            <!-- Card 1: Payment & Settlement Summary -->
            <div
                class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-op-line">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-op-subtle text-xs"></i>
                        <h2 class="text-xs font-medium uppercase tracking-wider text-op-subtle">
                            {{ __('Financial Settlement') }}
                        </h2>
                    </div>

                    <x-status-badge :status="$latestPayment ? $latestPayment->status : 'pending'" />
                </div>

                <!-- Big Amount Display -->
                <div class="space-y-0.5">
                    <span
                        class="text-[10px] font-medium uppercase tracking-wider text-op-subtle">{{ __('Booking Total Amount') }}</span>
                    <p class="text-xl sm:text-2xl font-medium text-op-ink">
                        Rp
                        {{ number_format($latestPayment ? (float) $latestPayment->amount : $totalCalculated, 0, ',', '.') }}
                    </p>
                    <span class="text-xs text-op-subtle">
                        {{ $res->pax_count }} pax &times; Rp {{ number_format($frozenPrice, 0, ',', '.') }}
                    </span>
                </div>

                <!-- Escrow Status Callout -->
                @if ($earningTx)
                    <div
                        class="p-3.5 rounded-[8px] {{ $earningTx->status->value === 'cleared' ? 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60' : ($earningTx->status->value === 'pending_escrow' ? 'bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60' : 'bg-[#F7F8F9] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433]') }} space-y-1">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xs font-medium {{ $earningTx->status->value === 'cleared' ? 'text-emerald-900 dark:text-emerald-200' : ($earningTx->status->value === 'pending_escrow' ? 'text-amber-900 dark:text-amber-200' : 'text-op-ink') }} flex items-center gap-1.5">
                                @if ($earningTx->status->value === 'cleared')
                                    <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                                    {{ __('Funds Cleared & Available') }}
                                @elseif ($earningTx->status->value === 'pending_escrow')
                                    <i class="fa-solid fa-lock text-amber-500"></i>
                                    {{ __('In Escrow Safe') }}
                                @else
                                    <i class="fa-solid fa-circle-xmark text-op-subtle"></i>
                                    {{ __('Escrow Voided / Cancelled') }}
                                @endif
                            </span>

                            <span
                                class="font-medium text-xs {{ $earningTx->status->value === 'cleared' ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">
                                Rp {{ number_format((float) $earningTx->net_amount, 0, ',', '.') }}
                            </span>
                        </div>

                        <p
                            class="text-[11px] leading-relaxed {{ $earningTx->status->value === 'cleared' ? 'text-emerald-800/90 dark:text-emerald-300/90' : ($earningTx->status->value === 'pending_escrow' ? 'text-amber-800/90 dark:text-amber-300/90' : 'text-op-subtle') }}">
                            @if ($earningTx->status->value === 'cleared')
                                {{ __('Net earnings are cleared and ready for payout withdrawal.') }}
                            @elseif ($earningTx->status->value === 'pending_escrow')
                                {{ __('Held safely until departure date: :date', ['date' => $earningTx->available_at?->format('M d, Y') ?? 'departure']) }}
                            @else
                                {{ __('Transaction was cancelled or refunded.') }}
                            @endif
                        </p>
                    </div>
                @endif

                <!-- Payment Details Rows -->
                <div class="space-y-2 pt-2 border-t border-op-line text-xs">
                    <div class="flex items-center justify-between text-op-subtle">
                        <span>{{ __('Gateway Provider') }}</span>
                        <strong
                            class="font-medium text-op-ink uppercase">{{ $latestPayment?->gateway ?? 'DOKU' }}</strong>
                    </div>

                    <div class="flex items-center justify-between text-op-subtle">
                        <span>{{ __('Gateway Reference') }}</span>
                        <span
                            class="font-mono text-op-ink">{{ $latestPayment?->gateway_ref ?? '—' }}</span>
                    </div>

                    @if ($appliedCouponCode && $couponDiscount > 0)
                        <div class="flex items-center justify-between text-op-subtle">
                            <span>{{ __('Experience Subtotal') }}</span>
                            <span>Rp {{ number_format($originalSubtotal, 0, ',', '.') }}</span>
                        </div>

                        <div class="flex items-center justify-between text-amber-600 dark:text-amber-400 font-medium">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-tag text-[10px]"></i>
                                {{ __('Promo Discount (:code)', ['code' => $appliedCouponCode]) }}
                            </span>
                            <span>-Rp {{ number_format($couponDiscount, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    @if ($latestPayment && !empty($latestPayment->split_details))
                        <div class="flex items-center justify-between text-op-subtle">
                            <span>{{ __('Guest Service Fee (5%)') }}</span>
                            <span class="text-rose-500 font-medium">
                                Rp {{ number_format((float) ($latestPayment->split_details['guest_service_fee'] ?? 0), 0, ',', '.') }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-op-subtle">
                            <span>{{ __('Net Operator Earning') }}</span>
                            <strong class="font-medium text-emerald-600 dark:text-emerald-400">
                                Rp {{ number_format((float) ($latestPayment->split_details['operator_amount'] ?? ($earningTx?->net_amount ?? $totalCalculated)), 0, ',', '.') }}
                            </strong>
                        </div>
                    @endif
                </div>

                @if ($latestPayment && !$latestPayment->isPaid())
                    <button type="button" wire:click="syncPaymentStatus"
                        class="w-full h-8 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-none">
                        <i class="fa-solid fa-arrows-rotate text-xs" wire:loading.class="animate-spin"
                            wire:target="syncPaymentStatus"></i>
                        <span>{{ __('Sync with Payment Gateway') }}</span>
                    </button>
                @endif
            </div>

            <!-- Card 2: Quick Links & Sharing -->
            <div
                class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-3">
                <div class="flex items-center gap-2 pb-2 border-b border-op-line">
                    <i class="fa-solid fa-share-nodes text-op-subtle text-xs"></i>
                    <h2 class="text-xs font-medium uppercase tracking-wider text-op-subtle">
                        {{ __('Customer Links') }}
                    </h2>
                </div>

                <div class="space-y-2">
                    <a href="{{ $receiptUrl }}" target="_blank"
                        class="w-full p-2.5 rounded-[8px] bg-[#F7F8F9] dark:bg-[#151a26] hover:bg-[#EEF0F2] dark:hover:bg-[#1A2030] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between text-xs transition">
                        <span class="flex items-center gap-2 font-medium text-op-ink">
                            <i class="fa-solid fa-file-invoice text-op-subtle"></i>
                            {{ __('Online Receipt & Portal') }}
                        </span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-op-subtle text-[10px]"></i>
                    </a>

                    @if ($isPaid)
                        <a href="{{ $eTicketUrl }}" target="_blank"
                            class="w-full p-2.5 rounded-[8px] bg-[#F7F8F9] dark:bg-[#151a26] hover:bg-[#EEF0F2] dark:hover:bg-[#1A2030] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between text-xs transition">
                            <span class="flex items-center gap-2 font-medium text-op-ink">
                                <i class="fa-solid fa-ticket text-emerald-500"></i>
                                {{ __('Printable E-Ticket') }}
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-op-subtle text-[10px]"></i>
                        </a>
                    @else
                        <div x-data="{ copied: false }" class="space-y-1.5 pt-1">
                            <span
                                class="text-[10px] font-medium text-op-subtle uppercase tracking-wider">{{ __('Direct Checkout Link') }}</span>
                            <div class="flex items-center gap-1.5">
                                <input type="text" readonly value="{{ $paymentUrl }}"
                                    class="w-full h-8 px-2.5 rounded-[6px] bg-[#F7F8F9] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] text-op-ink font-mono text-xs truncate" />
                                <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $paymentUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="h-8 px-2.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-xs shrink-0 cursor-pointer transition">
                                    <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Card 3: Audit Trail & Timeline -->
            <div
                class="p-4 sm:p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div class="flex items-center gap-2 pb-2 border-b border-op-line">
                    <i class="fa-solid fa-clock-rotate-left text-op-subtle text-xs"></i>
                    <h2 class="text-xs font-medium uppercase tracking-wider text-op-subtle">
                        {{ __('Booking Timeline') }}
                    </h2>
                </div>

                <div
                    class="relative pl-6 space-y-3.5 before:content-[''] before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-op-line text-xs">
                    <!-- Completed / Cancelled -->
                    @if ($res->status === ReservationStatus::Completed)
                        <div class="relative">
                            <div
                                class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-emerald-600">
                            </div>
                            <p class="font-medium text-emerald-600 dark:text-emerald-400">{{ __('Trip Completed') }}
                            </p>
                            <span
                                class="text-[11px] text-op-subtle">{{ $res->updated_at?->format('d M Y, H:i') }}</span>
                        </div>
                    @elseif ($res->status === ReservationStatus::Cancelled)
                        <div class="relative">
                            <div
                                class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-rose-600">
                            </div>
                            <p class="font-medium text-rose-600 dark:text-rose-400">{{ __('Cancelled') }}</p>
                            <span
                                class="text-[11px] text-op-subtle">{{ $res->updated_at?->format('d M Y, H:i') }}</span>
                        </div>
                    @endif

                    <!-- Departure -->
                    <div class="relative">
                        <div
                            class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full {{ $res->requested_date->isPast() ? 'bg-op-subtle' : 'bg-amber-500' }}">
                        </div>
                        <p class="font-medium text-op-ink">{{ __('Scheduled Departure') }}</p>
                        <span class="text-[11px] text-op-subtle">{{ $res->requested_date->format('d M Y') }}</span>
                    </div>

                    <!-- Payment -->
                    @if ($isPaid)
                        <div class="relative">
                            <div
                                class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-emerald-600">
                            </div>
                            <p class="font-medium text-op-ink">{{ __('Payment Confirmed') }}</p>
                            <span
                                class="text-[11px] text-op-subtle">{{ $latestPayment->updated_at?->format('d M Y, H:i') }}</span>
                        </div>
                    @endif

                    <!-- Created -->
                    <div class="relative">
                        <div
                            class="absolute -left-6 top-1 w-2 h-2 rounded-full bg-op-subtle">
                        </div>
                        <p class="font-medium text-op-ink">{{ __('Reservation Created') }}</p>
                        <span class="text-[11px] text-op-subtle">{{ $res->created_at?->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Confirmation Modal for Status Transitions -->
    @if ($showConfirmStatusModal && $pendingStatusValue)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs animate-fade-in"
                wire:keydown.escape="closeConfirmStatusModal">
                <div
                    class="w-full max-w-lg rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden animate-scale-up">

                    <div class="p-4 sm:p-5 border-b border-op-line flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            @if ($pendingStatusValue === 'confirmed')
                                <div
                                    class="w-9 h-9 rounded-[8px] bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center justify-center shrink-0 border border-emerald-200 dark:border-emerald-800">
                                    <i class="fa-solid fa-check text-xs"></i>
                                </div>
                            @elseif ($pendingStatusValue === 'declined')
                                <div
                                    class="w-9 h-9 rounded-[8px] bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 flex items-center justify-center shrink-0 border border-rose-200 dark:border-rose-800">
                                    <i class="fa-solid fa-ban text-xs"></i>
                                </div>
                            @elseif ($pendingStatusValue === 'completed')
                                <div
                                    class="w-9 h-9 rounded-[8px] bg-op-muted text-op-ink flex items-center justify-center shrink-0 border border-op-line">
                                    <i class="fa-solid fa-flag-checkered text-xs"></i>
                                </div>
                            @else
                                <div
                                    class="w-9 h-9 rounded-[8px] bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 flex items-center justify-center shrink-0 border border-rose-200 dark:border-rose-800">
                                    <i class="fa-solid fa-xmark text-xs"></i>
                                </div>
                            @endif

                            <div class="space-y-0.5">
                                <h3 class="font-medium text-base text-op-ink">
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

                                <p class="text-xs text-op-subtle">
                                    @if ($pendingStatusValue === 'confirmed')
                                        {{ __('This will officially confirm the booking and dispatch confirmation notifications.') }}
                                    @elseif ($pendingStatusValue === 'declined')
                                        {{ __('This will decline the reservation and release any reserved inventory.') }}
                                    @elseif ($pendingStatusValue === 'completed')
                                        {{ __('This will mark the excursion as finished and release escrow funds to your available balance immediately.') }}
                                    @elseif ($isPaid)
                                        {{ __('This will cancel the booking, release calendar inventory, and automatically issue a full refund to the guest via the payment gateway.') }}
                                    @else
                                        {{ __('This will cancel the reservation and release calendar slots.') }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="closeConfirmStatusModal"
                            class="p-1.5 rounded-[6px] text-op-subtle hover:text-op-ink hover:bg-op-muted transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>

                    <div class="p-4 sm:p-5 space-y-3.5">
                        <div
                            class="p-3.5 rounded-[8px] bg-[#F7F8F9] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-mono font-medium text-op-ink">#{{ $resCode }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-medium uppercase bg-op-muted text-op-ink border border-op-line">
                                    {{ $res->status->label() }} &rarr; {{ ucfirst($pendingStatusValue) }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between text-op-subtle pt-1 border-t border-op-line">
                                <span>{{ __('Guest:') }}</span>
                                <strong class="font-medium text-op-ink">{{ $res->guest_name }}</strong>
                            </div>
                            <div class="flex items-center justify-between text-op-subtle">
                                <span>{{ __('Total:') }}</span>
                                <strong class="font-medium text-op-ink">Rp
                                    {{ number_format($latestPayment ? (float) $latestPayment->amount : $totalCalculated, 0, ',', '.') }}</strong>
                            </div>
                        </div>

                        @if ($pendingStatusValue === 'cancelled' && $isPaid)
                            <div
                                class="p-3.5 rounded-[8px] bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs flex items-start gap-3">
                                <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 text-xs shrink-0"></i>
                                <div class="space-y-0.5">
                                    <p class="font-medium text-amber-950 dark:text-amber-100">
                                        {{ __('Automated Gateway Refund') }}</p>
                                    <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">
                                        {{ __('This booking was paid (Rp :amount). Confirming will automatically refund the guest via DOKU and void your pending escrow hold.', ['amount' => number_format((float) $latestPayment->amount, 0, ',', '.')]) }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-op-line">
                            <x-button type="button" variant="secondary" wire:click="closeConfirmStatusModal"
                                size="sm">
                                {{ __('Cancel') }}
                            </x-button>

                            @if ($pendingStatusValue === 'confirmed')
                                <x-button type="button" variant="success" wire:click="executeStatusTransition" size="sm">
                                    <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                                    {{ __('Yes, Confirm Booking') }}
                                </x-button>
                            @elseif ($pendingStatusValue === 'declined')
                                <x-button type="button" variant="danger" wire:click="executeStatusTransition" size="sm">
                                    <i class="fa-solid fa-ban mr-1.5 text-xs"></i>
                                    {{ __('Yes, Decline Booking') }}
                                </x-button>
                            @elseif ($pendingStatusValue === 'completed')
                                <x-button type="button" variant="primary" wire:click="executeStatusTransition" size="sm">
                                    <i class="fa-solid fa-flag-checkered mr-1.5 text-xs"></i>
                                    {{ __('Yes, Mark as Completed') }}
                                </x-button>
                            @else
                                <x-button type="button" variant="danger" wire:click="executeStatusTransition" size="sm">
                                    <i class="fa-solid fa-xmark mr-1.5 text-xs"></i>
                                    {{ $isPaid ? __('Yes, Cancel & Refund') : __('Yes, Cancel Reservation') }}
                                </x-button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Trip & Experience Info Modal -->
    @if ($showTripInfoModal)
        @php
            $inclusions = !empty($res->terms_snapshot['inclusions'])
                ? (array) $res->terms_snapshot['inclusions']
                : (is_array($bookable?->inclusions)
                    ? $bookable->inclusions
                    : []);
            $exclusions = !empty($res->terms_snapshot['exclusions'])
                ? (array) $res->terms_snapshot['exclusions']
                : (is_array($bookable?->exclusions)
                    ? $bookable->exclusions
                    : []);
            $itinerary = $bookable instanceof \App\Models\Package ? $bookable->itinerary_text : null;
            $termsAndConditions =
                $res->terms_snapshot['terms_and_conditions'] ?? ($bookable?->terms_and_conditions ?? null);
            $cancellationTerms = $res->terms_snapshot['cancellation_terms'] ?? ($bookable?->cancellation_terms ?? null);
            $editRoute = null;
            if ($bookable instanceof \App\Models\Package && \Illuminate\Support\Facades\Route::has('packages.edit')) {
                $editRoute = route('packages.edit', $bookable);
            } elseif (
                $bookable instanceof \App\Models\Product &&
                \Illuminate\Support\Facades\Route::has('products.edit')
            ) {
                $editRoute = route('products.edit', $bookable);
            }
        @endphp
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-xs animate-fade-in"
                wire:keydown.escape="closeTripInfoModal">
                <div class="w-full max-w-2xl max-h-[90vh] flex flex-col rounded-t-[16px] sm:rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden animate-scale-up"
                    @click.outside="$wire.closeTripInfoModal()">

                    <div class="mx-auto my-2 h-1 w-10 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                    <!-- Modal Header -->
                    <div
                        class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-start justify-between gap-4 bg-[#F9FAFB] dark:bg-[#151a26]">
                        <div class="flex items-start gap-3 min-w-0">
                            <div
                                class="w-9 h-9 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm shrink-0 mt-0.5">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3
                                        class="font-medium text-[16px] sm:text-[18px] text-[#12181E] dark:text-white leading-tight truncate">
                                        {{ $bookable->name ?? ($bookable->title ?? __('Trip Information')) }}
                                    </h3>
                                    <span
                                        class="px-2 py-0.5 rounded-[4px] text-[10px] font-medium uppercase {{ $res->bookable_type === 'package' ? 'bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40' : 'bg-slate-100 text-slate-700 dark:bg-[#1E2433] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433]' }}">
                                        {{ $res->bookable_type === 'package' ? __('Tour Package') : __('Standalone Product') }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Complete operational details, inclusions, exclusions, and booking guidelines.') }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="closeTripInfoModal"
                            class="p-1.5 rounded-[6px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433] transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div class="p-4 sm:p-6 space-y-5 overflow-y-auto max-h-[calc(90vh-140px)]">

                        <!-- Trip Specs Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                            <div
                                class="p-3 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                                <span
                                    class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block">{{ __('Trip Date') }}</span>
                                <strong
                                    class="font-medium text-[#12181E] dark:text-white block">{{ $res->requested_date->format('M d, Y') }}</strong>
                                <span
                                    class="text-[11px] text-slate-500">{{ $res->requested_date->diffForHumans() }}</span>
                            </div>

                            <div
                                class="p-3 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                                <span
                                    class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block">{{ __('Party Size') }}</span>
                                <strong
                                    class="font-medium text-[#12181E] dark:text-white block">{{ __(':count Guests (Pax)', ['count' => $res->pax_count]) }}</strong>
                                <span class="text-[11px] text-slate-500">Rp
                                    {{ number_format($frozenPrice, 0, ',', '.') }} / pax</span>
                            </div>

                            <div
                                class="p-3 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                                <span
                                    class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block">{{ __('Location') }}</span>
                                <strong
                                    class="font-medium text-[#12181E] dark:text-white truncate block">{{ $bookable?->location ?: __('Storefront / Operator Location') }}</strong>
                                <span
                                    class="text-[11px] text-slate-500">{{ $bookable?->category ?: __('Standard Tour') }}</span>
                            </div>

                            <div
                                class="p-3 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-0.5">
                                <span
                                    class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block">{{ __('Free Cancellation') }}</span>
                                <strong
                                    class="font-medium text-[#12181E] dark:text-white block">{{ __(':hours Hours Cutoff', ['hours' => $res->getFrozenFreeCancellationHours()]) }}</strong>
                                <span class="text-[11px] text-slate-500">{{ __('Before Departure') }}</span>
                            </div>
                        </div>

                        <!-- Promotional Coupon Banner if applied -->
                        @if ($appliedCouponCode)
                            <div
                                class="p-3.5 rounded-[8px] bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="w-7 h-7 rounded-[6px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center font-medium shrink-0">
                                        <i class="fa-solid fa-tag text-xs"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-amber-950 dark:text-amber-200">
                                            {{ __('Promotional Coupon Applied') }}</p>
                                        <span
                                            class="text-[11px] text-amber-800 dark:text-amber-400 font-mono font-medium">{{ $appliedCouponCode }}</span>
                                    </div>
                                </div>
                                @if ($couponDiscount > 0)
                                    <div class="text-right">
                                        <span class="text-[10px] text-amber-700 dark:text-amber-400 block uppercase font-medium">{{ __('Discount') }}</span>
                                        <span class="font-medium text-sm text-amber-800 dark:text-amber-300">
                                            -Rp {{ number_format($couponDiscount, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Description -->
                        @if ($bookable && !empty($bookable->description))
                            <div class="space-y-1.5">
                                <h4
                                    class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-align-left text-slate-400"></i>
                                    {{ __('Experience Overview') }}
                                </h4>
                                <div
                                    class="p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] text-xs text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                    {{ $bookable->description }}
                                </div>
                            </div>
                        @endif

                        <!-- Inclusions & Exclusions Side-by-Side -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Inclusions -->
                            <div
                                class="p-3.5 rounded-[8px] bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 space-y-2">
                                <h4
                                    class="font-medium text-xs text-emerald-900 dark:text-emerald-300 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 text-sm"></i>
                                    {{ __('What is Included') }}
                                </h4>
                                @if (!empty($inclusions))
                                    <ul class="space-y-1.5 text-xs text-slate-700 dark:text-slate-300">
                                        @foreach ($inclusions as $inc)
                                            <li class="flex items-start gap-2">
                                                <i
                                                    class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-[11px] mt-0.5 shrink-0"></i>
                                                <span>{{ $inc }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-xs text-slate-400 italic">
                                        {{ __('No specific inclusions recorded for this experience.') }}
                                    </p>
                                @endif
                            </div>

                            <!-- Exclusions -->
                            <div
                                class="p-3.5 rounded-[8px] bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 space-y-2">
                                <h4 class="font-medium text-xs text-rose-900 dark:text-rose-300 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-xmark text-rose-600 dark:text-rose-400 text-sm"></i>
                                    {{ __('Not Included') }}
                                </h4>
                                @if (!empty($exclusions))
                                    <ul class="space-y-1.5 text-xs text-slate-700 dark:text-slate-300">
                                        @foreach ($exclusions as $exc)
                                            <li class="flex items-start gap-2">
                                                <i
                                                    class="fa-solid fa-xmark text-rose-600 dark:text-rose-400 text-[11px] mt-0.5 shrink-0"></i>
                                                <span>{{ $exc }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-xs text-slate-400 italic">
                                        {{ __('No specific exclusions recorded for this experience.') }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <!-- Itinerary if available -->
                        @if (!empty($itinerary))
                            <div class="space-y-1.5">
                                <h4
                                    class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-route text-slate-400"></i>
                                    {{ __('Trip Itinerary') }}
                                </h4>
                                <div
                                    class="p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] text-xs text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                    {{ $itinerary }}
                                </div>
                            </div>
                        @endif

                        <!-- Cancellation & Terms Policy -->
                        <div class="space-y-1.5">
                            <h4
                                class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-halved text-slate-400"></i>
                                {{ __('Cancellation Policy & Terms') }}
                            </h4>
                            <div
                                class="p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-2 text-xs text-slate-700 dark:text-slate-300">
                                @if (!empty($cancellationTerms))
                                    <div>
                                        <span
                                            class="font-medium text-[#12181E] dark:text-white block">{{ __('Cancellation Policy:') }}</span>
                                        <p class="leading-relaxed">{{ $cancellationTerms }}</p>
                                    </div>
                                @endif
                                @if (!empty($termsAndConditions))
                                    <div class="pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                                        <span
                                            class="font-medium text-[#12181E] dark:text-white block">{{ __('Terms & Conditions:') }}</span>
                                        <p class="leading-relaxed">{{ $termsAndConditions }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>

                    <!-- Modal Footer -->
                    <div
                        class="p-4 sm:p-5 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between gap-3 bg-[#F9FAFB] dark:bg-[#151a26]">
                        <div>
                            @if ($editRoute)
                                <a href="{{ $editRoute }}" wire:navigate
                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:text-black dark:hover:text-white transition">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    <span>{{ __('Edit Experience in Catalog') }}</span>
                                </a>
                            @endif
                        </div>

                        <x-button type="button" variant="secondary" size="sm" wire:click="closeTripInfoModal"
                            class="rounded-[6px] text-xs font-medium">
                            {{ __('Close') }}
                        </x-button>
                    </div>

                </div>
            </div>
        @endteleport
    @endif
</div>
