<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\DokuPaymentService;
use App\Services\GoogleCalendarService;
use App\Services\VendorDispatchService;
use App\Services\WalletService;
use App\Services\WhatsAppDispatchService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reservation Details')] class extends Component {
    use ResolvesCurrentOperator;

    public Reservation $reservation;

    public bool $isEditingNotes = false;

    public string $agentNote = '';

    public bool $showConfirmStatusModal = false;

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
        $this->reservation->update([
            'notes' => trim($this->agentNote) !== '' ? trim($this->agentNote) : null,
        ]);

        $this->reservation->refresh();
        $this->isEditingNotes = false;
        $this->dispatch('toast', message: __('Internal reservation notes saved.'), type: 'success');
    }

    public function syncPaymentStatus(): void
    {
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
            $dokuService = app(DokuPaymentService::class);
            $result = $dokuService->checkPaymentStatus($payment);

            $this->reservation->load('latestPayment', 'walletTransactions');

            if ($result['paid'] ?? false) {
                $this->dispatch('toast', message: __('Payment confirmed! Status updated to Paid.'), type: 'success');
            } else {
                $this->dispatch('toast', message: __('Payment is still pending on the payment gateway.'), type: 'info');
            }
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: __('Unable to connect to payment gateway: :msg', ['msg' => $e->getMessage()]), type: 'danger');
        }
    }

    public function confirmStatusTransition(string $statusValue): void
    {
        $target = ReservationStatus::tryFrom($statusValue);
        if (!$target) {
            return;
        }

        // Prevent declining paid bookings
        if ($target === ReservationStatus::Declined && $this->reservation->latestPayment?->isPaid()) {
            $this->dispatch('toast', message: __('Cannot decline a paid reservation. Please cancel and refund instead.'), type: 'warning');

            return;
        }

        // Prevent premature completion
        if ($target === ReservationStatus::Completed) {
            if ($this->reservation->status !== ReservationStatus::Confirmed) {
                $this->dispatch('toast', message: __('Only confirmed reservations can be marked as completed.'), type: 'warning');

                return;
            }

            if ($this->reservation->requested_date && $this->reservation->requested_date->isFuture() && !$this->reservation->requested_date->isToday()) {
                $this->dispatch('toast', message: __('Cannot mark a reservation completed before its scheduled trip date.'), type: 'warning');

                return;
            }
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

        $status = ReservationStatus::tryFrom($this->pendingStatusValue);
        if (!$status) {
            return;
        }

        $this->showConfirmStatusModal = false;

        $wasPaid = false;
        $refundAmount = 0.0;
        if ($status === ReservationStatus::Cancelled) {
            $this->reservation->loadMissing('latestPayment');
            $payment = $this->reservation->latestPayment;
            $wasPaid = (bool) $payment?->isPaid();

            if ($wasPaid) {
                $refundAmount = (float) $payment->amount;
                $refundSuccess = app(DokuPaymentService::class)->refundPayment($payment);
                if (!$refundSuccess) {
                    $this->dispatch('toast', message: __('Could not process automated refund with the payment gateway. Please check gateway connection or refund manually.'), type: 'danger');

                    return;
                }
            }
        }

        $this->reservation->update([
            'status' => $status,
            'hold_expires_at' => $status === ReservationStatus::PaymentPending ? now()->addMinutes(30) : null,
        ]);

        if ($status === ReservationStatus::Confirmed) {
            if (!empty($this->reservation->guest_email)) {
                try {
                    \Illuminate\Support\Facades\Mail::to($this->reservation->guest_email)->send(new \App\Mail\GuestBookingConfirmedMail($this->reservation));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            try {
                app(VendorDispatchService::class)->dispatchBookingConfirmation($this->reservation);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($status === ReservationStatus::Completed) {
            app(WalletService::class)->releaseReservationEscrow($this->reservation);
        }

        if ($status === ReservationStatus::Cancelled) {
            app(WalletService::class)->cancelBookingEarning($this->reservation, 'Cancelled and refunded by operator');

            try {
                app(VendorDispatchService::class)->dispatchBookingCancellation($this->reservation);
            } catch (\Throwable $e) {
                report($e);
            }

            $message = $wasPaid ? __('Reservation cancelled and full refund of :amount issued to guest.', ['amount' => 'Rp ' . number_format($refundAmount, 0, ',', '.')]) : __('Reservation cancelled and inventory released.');

            $this->reservation->refresh()->loadMissing(['latestPayment', 'walletTransactions']);
            $this->dispatch('toast', message: $message, type: 'success');

            return;
        }

        $this->reservation->refresh()->loadMissing(['latestPayment', 'walletTransactions']);
        $this->dispatch('toast', message: __('Reservation status updated to :status', ['status' => $status->label()]), type: 'success');
    }
}; ?>

<div class="space-y-6">
    @php
        $res = $this->reservation;
        $bookable = $res->bookable;
        $latestPayment = $res->latestPayment;
        $cleanPhone = preg_replace('/[^0-9]/', '', $res->guest_contact);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }
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

            <div class="flex items-start gap-4 min-w-0">
                <div
                    class="w-12 h-12 rounded-2xl bg-indigo-600 text-white font-black text-lg flex items-center justify-center shrink-0 shadow-xs">
                    <i class="fa-solid fa-receipt"></i>
                </div>

                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white truncate">
                            {{ $res->guest_name }}
                        </h1>
                        <span
                            class="font-mono text-xs font-bold text-indigo-700 dark:text-indigo-300 px-2.5 py-1 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 border border-indigo-200/80 dark:border-indigo-800/60 shadow-2xs">
                            #{{ $resCode }}
                        </span>
                        <x-status-badge :status="$res->status" />
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
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
                <i class="fa-solid fa-file-invoice text-xs text-slate-400"></i>
                <span>{{ __('Receipt') }}</span>
            </x-button>

            <!-- Status Controls -->
            @if (in_array($res->status, [ReservationStatus::PaymentPending, ReservationStatus::PendingConfirmation], true))
                <x-button type="button" variant="primary" wire:click="confirmStatusTransition('confirmed')"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold" size="sm">
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
                <x-button type="button" variant="primary" wire:click="confirmStatusTransition('completed')"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold" size="sm">
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
            class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-center gap-3">
                <div
                    class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-hourglass-half animate-pulse"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-amber-900 dark:text-amber-200">
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
                    class="h-9 px-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5">
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                    <span>{{ __('Send Pay Link') }}</span>
                </a>

                <button type="button" wire:click="syncPaymentStatus"
                    class="h-9 px-3.5 rounded-xl bg-white dark:bg-zinc-800 hover:bg-amber-50 dark:hover:bg-zinc-700 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-800 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                    <i class="fa-solid fa-arrows-rotate text-xs" wire:loading.class="animate-spin"
                        wire:target="syncPaymentStatus"></i>
                    <span>{{ __('Check Gateway') }}</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Two-Column Master Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT COLUMN (2 Cols): Tour Details, Guest Profile, WhatsApp Dispatch, Notes, Terms -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Card 1: Experience & Schedule Overview -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-5">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {{ __('Booked Experience') }}
                        </h2>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openTripInfoModal"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60 font-bold text-xs transition cursor-pointer shadow-2xs">
                            <i class="fa-solid fa-circle-info text-xs"></i>
                            <span>{{ __('Trip Info') }}</span>
                        </button>

                        <span
                            class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase {{ $res->bookable_type === 'package' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/60' }}">
                            {{ $res->bookable_type === 'package' ? __('Tour Package') : __('Standalone Product') }}
                        </span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white leading-snug">
                            {{ $bookable->name ?? ($bookable->title ?? __('Direct Experience Item')) }}
                        </h3>
                        @if ($appliedCouponCode)
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-750 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60 shadow-2xs">
                                <i class="fa-solid fa-tag text-[10px] text-amber-600 dark:text-amber-400"></i>
                                <span>{{ __('Promo Code: :code', ['code' => $appliedCouponCode]) }}</span>
                                @if ($couponDiscount > 0)
                                    <span class="font-extrabold text-amber-700 dark:text-amber-300">(-Rp {{ number_format($couponDiscount, 0, ',', '.') }})</span>
                                @endif
                            </span>
                        @endif
                    </div>

                    @if ($bookable && !empty($bookable->description))
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-3 leading-relaxed">
                            {{ $bookable->description }}
                        </p>
                    @endif
                </div>

                <!-- Schedule Specs Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-2">
                    <div
                        class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] space-y-1">
                        <span
                            class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Trip Date') }}</span>
                        <p class="font-bold text-sm text-slate-900 dark:text-white">
                            {{ $res->requested_date->format('l, M d, Y') }}
                        </p>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ $res->requested_date->isToday() ? __('Today') : $res->requested_date->diffForHumans() }}
                        </span>
                    </div>

                    <div
                        class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] space-y-1">
                        <span
                            class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Party Size') }}</span>
                        <p class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
                            <i class="fa-solid fa-users text-indigo-500 text-xs"></i>
                            {{ __(':count Guests (Pax)', ['count' => $res->pax_count]) }}
                        </p>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">
                            Rp {{ number_format($frozenPrice, 0, ',', '.') }} / pax
                        </span>
                    </div>

                    <div
                        class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] space-y-1 flex flex-col justify-between">
                        <div>
                            <span
                                class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Calendar Sync') }}</span>
                            <p class="font-bold text-sm text-slate-900 dark:text-white">{{ __('Google Cal') }}</p>
                        </div>
                        <a href="{{ $gcalUrl }}" target="_blank"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            <i class="fa-brands fa-google text-xs"></i>
                            <span>{{ __('Add to Schedule') }}</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 2: Guest Details & CRM Connection -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-user text-slate-400 text-xs"></i>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {{ __('Guest Profile & Contact') }}
                        </h2>
                    </div>

                    @if ($res->guest)
                        <a href="{{ route('guests.show', $res->guest) }}" wire:navigate
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            <i class="fa-solid fa-address-book text-xs"></i>
                            <span>{{ __('View CRM History') }} &rarr;</span>
                        </a>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div
                            class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 font-black text-lg flex items-center justify-center shrink-0 border border-emerald-300 dark:border-emerald-800/60">
                            {{ strtoupper(substr($res->guest_name, 0, 2)) }}
                        </div>
                        <div class="space-y-1 min-w-0">
                            <h4 class="font-bold text-base text-slate-900 dark:text-white leading-tight">
                                {{ $res->guest_name }}
                            </h4>
                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
                                @if ($res->guest_email)
                                    <a href="mailto:{{ $res->guest_email }}"
                                        class="flex items-center gap-1.5 hover:text-indigo-600 dark:hover:text-indigo-400">
                                        <i class="fa-solid fa-envelope text-slate-400"></i>
                                        <span>{{ $res->guest_email }}</span>
                                    </a>
                                @endif
                                <a href="{{ $directWaUrl }}" target="_blank"
                                    class="flex items-center gap-1.5 font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                    <span>{{ $res->guest_contact }}</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <a href="{{ $directWaUrl }}" target="_blank"
                        class="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2 shrink-0">
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>{{ __('Direct WhatsApp Chat') }}</span>
                    </a>
                </div>
            </div>

            <!-- Card 3: 1-Click WhatsApp Dispatch Center -->
            @if ($this->currentOperator?->hasFeature('whatsapp_dispatch'))
                <div
                    class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-4">
                    <div
                        class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-whatsapp text-emerald-500 text-sm"></i>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                {{ __('1-Click WhatsApp Guest Dispatch') }}
                            </h2>
                        </div>

                        <span
                            class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
                            {{ __('Pre-Formatted Guest Messages') }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Trigger formatted notifications directly into your WhatsApp web or mobile client. All booking vouchers, departure reminders, and meeting points are auto-populated.') }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                        <a href="{{ $voucherWaUrl }}" target="_blank"
                            class="h-10 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2 text-center">
                            <i class="fa-solid fa-ticket text-xs"></i>
                            <span class="truncate">{{ __('Send E-Voucher') }}</span>
                        </a>

                        <a href="{{ $reminderWaUrl }}" target="_blank"
                            class="h-10 px-3 rounded-xl bg-white dark:bg-[#141821] hover:bg-emerald-50 dark:hover:bg-zinc-800 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/80 font-bold text-xs transition flex items-center justify-center gap-2 text-center shadow-2xs">
                            <i class="fa-solid fa-bell text-xs"></i>
                            <span class="truncate">{{ __('Send 24h Reminder') }}</span>
                        </a>

                        <a href="{{ $meetingWaUrl }}" target="_blank"
                            class="h-10 px-3 rounded-xl bg-white dark:bg-[#141821] hover:bg-emerald-50 dark:hover:bg-zinc-800 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/80 font-bold text-xs transition flex items-center justify-center gap-2 text-center shadow-2xs">
                            <i class="fa-solid fa-location-dot text-xs"></i>
                            <span class="truncate">{{ __('Send Meeting Pin') }}</span>
                        </a>
                    </div>
                </div>
            @else
                <div
                    class="p-6 rounded-3xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/70 dark:border-amber-900/50 shadow-2xs space-y-2">
                    <div class="flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp text-amber-600 dark:text-amber-400 text-base"></i>
                        <h4 class="font-bold text-sm text-amber-900 dark:text-amber-200">
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
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-note-sticky text-amber-500 text-xs"></i>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {{ __('Internal Operational Notes') }}
                        </h2>
                    </div>

                    @if (!$isEditingNotes)
                        <button type="button" wire:click="startEditingNotes"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer">
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
                            <x-button size="sm" variant="primary" wire:click="saveNotes"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold">
                                <i class="fa-solid fa-floppy-disk mr-1.5"></i>
                                {{ __('Save Operational Notes') }}
                            </x-button>
                        </div>
                    </div>
                @else
                    <div
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] text-xs text-slate-700 dark:text-slate-300 leading-relaxed min-h-16">
                        {{ !empty($res->notes) ? $res->notes : __('No operational notes recorded yet. Click Edit Notes to add driver pickup notes or dietary preferences.') }}
                    </div>
                @endif
            </div>

            <!-- Card 5: Frozen Terms & Policy Snapshot -->
            @if (!empty($res->terms_snapshot))
                <div x-data="{ openTerms: false }"
                    class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-3">
                    <button type="button" @click="openTerms = !openTerms"
                        class="w-full flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-shield-halved text-indigo-500"></i>
                            {{ __('Frozen Terms & Cancellation Policy Snapshot') }}
                        </span>
                        <div class="flex items-center gap-2 text-slate-400">
                            <span
                                class="text-[11px] font-normal">{{ __('Cutoff: :hours hrs', ['hours' => $res->getFrozenFreeCancellationHours()]) }}</span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200"
                                :class="openTerms ? 'rotate-180' : ''"></i>
                        </div>
                    </button>

                    <div x-show="openTerms" x-cloak
                        class="pt-3 border-t border-slate-100 dark:border-[#1e2433] space-y-3 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-slate-600 dark:text-slate-400">
                            <div>
                                <span
                                    class="font-bold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Cancellation Terms:') }}</span>
                                <p>{{ $res->terms_snapshot['cancellation_terms'] ?? __('Standard policy.') }}</p>
                            </div>
                            <div>
                                <span
                                    class="font-bold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Frozen At:') }}</span>
                                <p class="font-mono">{{ $res->terms_snapshot['frozen_at'] ?? '—' }}</p>
                            </div>
                        </div>

                        <div
                            class="p-3.5 rounded-xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] font-mono text-[11px] text-slate-600 dark:text-slate-400 overflow-x-auto max-h-48">
                            {{ json_encode($res->terms_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
                        </div>
                    </div>
                </div>
            @endif

        </div>

        <!-- RIGHT COLUMN (1 Col): Financial Breakdown, Escrow Status, Quick Links, Timeline -->
        <div class="space-y-6">

            <!-- Card 1: Payment & Settlement Summary -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-indigo-500 text-xs"></i>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {{ __('Financial Settlement') }}
                        </h2>
                    </div>

                    <x-status-badge :status="$latestPayment ? $latestPayment->status : 'pending'" />
                </div>

                <!-- Big Amount Display -->
                <div class="space-y-1">
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Booking Total Amount') }}</span>
                    <p class="text-2xl font-extrabold text-slate-900 dark:text-white">
                        Rp
                        {{ number_format($latestPayment ? (float) $latestPayment->amount : $totalCalculated, 0, ',', '.') }}
                    </p>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $res->pax_count }} pax &times; Rp {{ number_format($frozenPrice, 0, ',', '.') }}
                    </span>
                </div>

                <!-- Escrow Status Callout -->
                @if ($earningTx)
                    <div
                        class="p-4 rounded-2xl {{ $earningTx->status->value === 'cleared' ? 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60' : ($earningTx->status->value === 'pending_escrow' ? 'bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60' : 'bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700') }} space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xs font-bold {{ $earningTx->status->value === 'cleared' ? 'text-emerald-900 dark:text-emerald-200' : ($earningTx->status->value === 'pending_escrow' ? 'text-amber-900 dark:text-amber-200' : 'text-slate-700 dark:text-slate-300') }} flex items-center gap-1.5">
                                @if ($earningTx->status->value === 'cleared')
                                    <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                                    {{ __('Funds Cleared & Available') }}
                                @elseif ($earningTx->status->value === 'pending_escrow')
                                    <i class="fa-solid fa-lock text-amber-500"></i>
                                    {{ __('In Escrow Safe') }}
                                @else
                                    <i class="fa-solid fa-circle-xmark text-slate-400"></i>
                                    {{ __('Escrow Voided / Cancelled') }}
                                @endif
                            </span>

                            <span
                                class="font-bold text-xs {{ $earningTx->status->value === 'cleared' ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">
                                Rp {{ number_format((float) $earningTx->net_amount, 0, ',', '.') }}
                            </span>
                        </div>

                        <p
                            class="text-[11px] leading-relaxed {{ $earningTx->status->value === 'cleared' ? 'text-emerald-800/90 dark:text-emerald-300/90' : ($earningTx->status->value === 'pending_escrow' ? 'text-amber-800/90 dark:text-amber-300/90' : 'text-slate-500') }}">
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
                <div class="space-y-2.5 pt-2 border-t border-slate-100 dark:border-[#1e2433] text-xs">
                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                        <span>{{ __('Gateway Provider') }}</span>
                        <strong
                            class="font-bold text-slate-900 dark:text-white uppercase">{{ $latestPayment?->gateway ?? 'DOKU' }}</strong>
                    </div>

                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                        <span>{{ __('Gateway Reference') }}</span>
                        <span
                            class="font-mono text-slate-800 dark:text-slate-200">{{ $latestPayment?->gateway_ref ?? '—' }}</span>
                    </div>

                    @if ($appliedCouponCode && $couponDiscount > 0)
                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                            <span>{{ __('Experience Subtotal') }}</span>
                            <span>Rp {{ number_format($originalSubtotal, 0, ',', '.') }}</span>
                        </div>

                        <div class="flex items-center justify-between text-amber-600 dark:text-amber-400 font-semibold">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-tag text-[10px]"></i>
                                {{ __('Promo Discount (:code)', ['code' => $appliedCouponCode]) }}
                            </span>
                            <span>-Rp {{ number_format($couponDiscount, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    @if ($latestPayment && !empty($latestPayment->split_details))
                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                            <span>{{ __('Guest Service Fee (5%)') }}</span>
                            <span class="text-rose-500 font-medium">
                                Rp {{ number_format((float) ($latestPayment->split_details['guest_service_fee'] ?? 0), 0, ',', '.') }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                            <span>{{ __('Net Operator Earning') }}</span>
                            <strong class="font-bold text-emerald-600 dark:text-emerald-400">
                                Rp {{ number_format((float) ($latestPayment->split_details['operator_amount'] ?? ($earningTx?->net_amount ?? $totalCalculated)), 0, ',', '.') }}
                            </strong>
                        </div>
                    @endif
                </div>

                @if ($latestPayment && !$latestPayment->isPaid())
                    <button type="button" wire:click="syncPaymentStatus"
                        class="w-full h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-arrows-rotate text-xs" wire:loading.class="animate-spin"
                            wire:target="syncPaymentStatus"></i>
                        <span>{{ __('Sync with Payment Gateway') }}</span>
                    </button>
                @endif
            </div>

            <!-- Card 2: Quick Links & Sharing -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-3.5">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 dark:border-[#1e2433]">
                    <i class="fa-solid fa-share-nodes text-slate-400 text-xs"></i>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Customer Links') }}
                    </h2>
                </div>

                <div class="space-y-2">
                    <a href="{{ $receiptUrl }}" target="_blank"
                        class="w-full p-3 rounded-2xl bg-slate-50 dark:bg-[#141821] hover:bg-slate-100 dark:hover:bg-zinc-800 border border-slate-200/60 dark:border-[#1e2433] flex items-center justify-between text-xs transition">
                        <span class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-200">
                            <i class="fa-solid fa-file-invoice text-indigo-500"></i>
                            {{ __('Online Receipt & Portal') }}
                        </span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-slate-400 text-[10px]"></i>
                    </a>

                    @if ($isPaid)
                        <a href="{{ $eTicketUrl }}" target="_blank"
                            class="w-full p-3 rounded-2xl bg-slate-50 dark:bg-[#141821] hover:bg-slate-100 dark:hover:bg-zinc-800 border border-slate-200/60 dark:border-[#1e2433] flex items-center justify-between text-xs transition">
                            <span class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-200">
                                <i class="fa-solid fa-ticket text-emerald-500"></i>
                                {{ __('Printable E-Ticket') }}
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-slate-400 text-[10px]"></i>
                        </a>
                    @else
                        <div x-data="{ copied: false }" class="space-y-1.5 pt-1">
                            <span
                                class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Direct Checkout Link') }}</span>
                            <div class="flex items-center gap-1.5">
                                <input type="text" readonly value="{{ $paymentUrl }}"
                                    class="w-full h-9 px-3 rounded-xl bg-slate-50 dark:bg-[#141821] border border-slate-200/80 dark:border-[#1e2433] text-slate-600 dark:text-slate-400 font-mono text-xs truncate" />
                                <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $paymentUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="h-9 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shrink-0 cursor-pointer transition">
                                    <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Card 3: Audit Trail & Timeline -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-4">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 dark:border-[#1e2433]">
                    <i class="fa-solid fa-clock-rotate-left text-slate-400 text-xs"></i>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Booking Timeline') }}
                    </h2>
                </div>

                <div
                    class="relative pl-6 space-y-4 before:content-[''] before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 dark:before:bg-[#1e2433] text-xs">
                    <!-- Completed / Cancelled -->
                    @if ($res->status === ReservationStatus::Completed)
                        <div class="relative">
                            <div
                                class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-emerald-600 ring-4 ring-emerald-50 dark:ring-emerald-950">
                            </div>
                            <p class="font-bold text-emerald-600 dark:text-emerald-400">{{ __('Trip Completed') }}
                            </p>
                            <span
                                class="text-[11px] text-slate-400">{{ $res->updated_at?->format('d M Y, H:i') }}</span>
                        </div>
                    @elseif ($res->status === ReservationStatus::Cancelled)
                        <div class="relative">
                            <div
                                class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-rose-600 ring-4 ring-rose-50 dark:ring-rose-950">
                            </div>
                            <p class="font-bold text-rose-600 dark:text-rose-400">{{ __('Cancelled') }}</p>
                            <span
                                class="text-[11px] text-slate-400">{{ $res->updated_at?->format('d M Y, H:i') }}</span>
                        </div>
                    @endif

                    <!-- Departure -->
                    <div class="relative">
                        <div
                            class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full {{ $res->requested_date->isPast() ? 'bg-slate-400' : 'bg-amber-500 ring-4 ring-amber-50 dark:ring-amber-950' }}">
                        </div>
                        <p class="font-bold text-slate-900 dark:text-white">{{ __('Scheduled Departure') }}</p>
                        <span class="text-[11px] text-slate-400">{{ $res->requested_date->format('d M Y') }}</span>
                    </div>

                    <!-- Payment -->
                    @if ($isPaid)
                        <div class="relative">
                            <div
                                class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-emerald-600 ring-4 ring-emerald-50 dark:ring-emerald-950">
                            </div>
                            <p class="font-bold text-slate-900 dark:text-white">{{ __('Payment Confirmed') }}</p>
                            <span
                                class="text-[11px] text-slate-400">{{ $latestPayment->updated_at?->format('d M Y, H:i') }}</span>
                        </div>
                    @endif

                    <!-- Created -->
                    <div class="relative">
                        <div
                            class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-indigo-600 ring-4 ring-indigo-50 dark:ring-indigo-950">
                        </div>
                        <p class="font-bold text-slate-900 dark:text-white">{{ __('Reservation Created') }}</p>
                        <span class="text-[11px] text-slate-400">{{ $res->created_at?->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Confirmation Modal for Status Transitions -->
    @if ($showConfirmStatusModal && $pendingStatusValue)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-fade-in"
                wire:keydown.escape="closeConfirmStatusModal">
                <div
                    class="w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl overflow-hidden animate-scale-up">

                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            @if ($pendingStatusValue === 'confirmed')
                                <div
                                    class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                            @elseif ($pendingStatusValue === 'declined')
                                <div
                                    class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-ban"></i>
                                </div>
                            @elseif ($pendingStatusValue === 'completed')
                                <div
                                    class="w-10 h-10 rounded-2xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-flag-checkered"></i>
                                </div>
                            @else
                                <div
                                    class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-xmark"></i>
                                </div>
                            @endif

                            <div class="space-y-1">
                                <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">
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

                                <p class="text-xs text-slate-500 dark:text-slate-400">
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
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div
                            class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-mono font-bold text-indigo-600 dark:text-indigo-400">#{{ $resCode }}</span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-slate-200 dark:bg-zinc-700 text-slate-700 dark:text-slate-300">
                                    {{ $res->status->label() }} &rarr; {{ ucfirst($pendingStatusValue) }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between text-slate-600 dark:text-slate-400 pt-1 border-t border-slate-200/60 dark:border-zinc-700/60">
                                <span>{{ __('Guest:') }}</span>
                                <strong class="text-slate-900 dark:text-white">{{ $res->guest_name }}</strong>
                            </div>
                            <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                <span>{{ __('Total:') }}</span>
                                <strong class="text-slate-900 dark:text-white">Rp
                                    {{ number_format($latestPayment ? (float) $latestPayment->amount : $totalCalculated, 0, ',', '.') }}</strong>
                            </div>
                        </div>

                        @if ($pendingStatusValue === 'cancelled' && $isPaid)
                            <div
                                class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs flex items-start gap-3">
                                <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 text-sm shrink-0"></i>
                                <div class="space-y-1">
                                    <p class="font-bold text-amber-950 dark:text-amber-100">
                                        {{ __('Automated Gateway Refund') }}</p>
                                    <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">
                                        {{ __('This booking was paid (Rp :amount). Confirming will automatically refund the guest via DOKU and void your pending escrow hold.', ['amount' => number_format((float) $latestPayment->amount, 0, ',', '.')]) }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="flex items-center justify-end gap-3 pt-3">
                            <x-button type="button" variant="secondary" wire:click="closeConfirmStatusModal"
                                class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>

                            @if ($pendingStatusValue === 'confirmed')
                                <x-button type="button" variant="primary" wire:click="executeStatusTransition"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                                    <i class="fa-solid fa-check mr-1.5"></i>
                                    {{ __('Yes, Confirm Booking') }}
                                </x-button>
                            @elseif ($pendingStatusValue === 'declined')
                                <x-button type="button" variant="danger" wire:click="executeStatusTransition"
                                    class="text-xs font-bold">
                                    <i class="fa-solid fa-ban mr-1.5"></i>
                                    {{ __('Yes, Decline Booking') }}
                                </x-button>
                            @elseif ($pendingStatusValue === 'completed')
                                <x-button type="button" variant="primary" wire:click="executeStatusTransition"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                                    <i class="fa-solid fa-flag-checkered mr-1.5"></i>
                                    {{ __('Yes, Mark as Completed') }}
                                </x-button>
                            @else
                                <x-button type="button" variant="danger" wire:click="executeStatusTransition"
                                    class="text-xs font-bold">
                                    <i class="fa-solid fa-xmark mr-1.5"></i>
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
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-fade-in"
                wire:keydown.escape="closeTripInfoModal">
                <div class="w-full max-w-2xl max-h-[90vh] flex flex-col rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl overflow-hidden animate-scale-up"
                    @click.outside="$wire.closeTripInfoModal()">

                    <!-- Modal Header -->
                    <div
                        class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div
                                class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3
                                        class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                        {{ $bookable->name ?? ($bookable->title ?? __('Trip Information')) }}
                                    </h3>
                                    <span
                                        class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase {{ $res->bookable_type === 'package' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/60' }}">
                                        {{ $res->bookable_type === 'package' ? __('Tour Package') : __('Standalone Product') }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Complete operational details, inclusions, exclusions, and booking guidelines.') }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="closeTripInfoModal"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div class="p-6 space-y-6 overflow-y-auto max-h-[calc(90vh-140px)]">

                        <!-- Trip Specs Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div
                                class="p-3 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] space-y-0.5">
                                <span
                                    class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Trip Date') }}</span>
                                <strong
                                    class="font-bold text-slate-900 dark:text-white block">{{ $res->requested_date->format('M d, Y') }}</strong>
                                <span
                                    class="text-[11px] text-slate-500">{{ $res->requested_date->diffForHumans() }}</span>
                            </div>

                            <div
                                class="p-3 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] space-y-0.5">
                                <span
                                    class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Party Size') }}</span>
                                <strong
                                    class="font-bold text-slate-900 dark:text-white block">{{ __(':count Guests (Pax)', ['count' => $res->pax_count]) }}</strong>
                                <span class="text-[11px] text-slate-500">Rp
                                    {{ number_format($frozenPrice, 0, ',', '.') }} / pax</span>
                            </div>

                            <div
                                class="p-3 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] space-y-0.5">
                                <span
                                    class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Location') }}</span>
                                <strong
                                    class="font-bold text-slate-900 dark:text-white truncate block">{{ $bookable?->location ?: __('Storefront / Operator Location') }}</strong>
                                <span
                                    class="text-[11px] text-slate-500">{{ $bookable?->category ?: __('Standard Tour') }}</span>
                            </div>

                            <div
                                class="p-3 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] space-y-0.5">
                                <span
                                    class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Free Cancellation') }}</span>
                                <strong
                                    class="font-bold text-slate-900 dark:text-white block">{{ __(':hours Hours Cutoff', ['hours' => $res->getFrozenFreeCancellationHours()]) }}</strong>
                                <span class="text-[11px] text-slate-500">{{ __('Before Departure') }}</span>
                            </div>
                        </div>

                        <!-- Promotional Coupon Banner if applied -->
                        @if ($appliedCouponCode)
                            <div
                                class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/50 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-xl bg-amber-400 text-amber-950 flex items-center justify-center font-bold shrink-0">
                                        <i class="fa-solid fa-tag text-xs"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-amber-950 dark:text-amber-200">
                                            {{ __('Promotional Coupon Applied') }}</p>
                                        <span
                                            class="text-[11px] text-amber-800 dark:text-amber-400 font-mono font-bold">{{ $appliedCouponCode }}</span>
                                    </div>
                                </div>
                                @if ($couponDiscount > 0)
                                    <div class="text-right">
                                        <span class="text-[10px] text-amber-700 dark:text-amber-400 block uppercase font-bold">{{ __('Discount') }}</span>
                                        <span class="font-black text-sm text-amber-800 dark:text-amber-300">
                                            -Rp {{ number_format($couponDiscount, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Description -->
                        @if ($bookable && !empty($bookable->description))
                            <div class="space-y-2">
                                <h4
                                    class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                                    <i class="fa-solid fa-align-left text-indigo-500"></i>
                                    {{ __('Experience Overview') }}
                                </h4>
                                <div
                                    class="p-4 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] text-xs text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                    {{ $bookable->description }}
                                </div>
                            </div>
                        @endif

                        <!-- Inclusions & Exclusions Side-by-Side -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Inclusions -->
                            <div
                                class="p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/70 dark:border-emerald-900/50 space-y-2.5">
                                <h4
                                    class="font-bold text-xs text-emerald-900 dark:text-emerald-200 flex items-center gap-1.5">
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
                                class="p-4 rounded-2xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/70 dark:border-rose-900/50 space-y-2.5">
                                <h4 class="font-bold text-xs text-rose-900 dark:text-rose-200 flex items-center gap-1.5">
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
                            <div class="space-y-2">
                                <h4
                                    class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                                    <i class="fa-solid fa-route text-indigo-500"></i>
                                    {{ __('Trip Itinerary') }}
                                </h4>
                                <div
                                    class="p-4 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] text-xs text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                    {{ $itinerary }}
                                </div>
                            </div>
                        @endif

                        <!-- Cancellation & Terms Policy -->
                        <div class="space-y-2">
                            <h4
                                class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-halved text-indigo-500"></i>
                                {{ __('Cancellation Policy & Terms') }}
                            </h4>
                            <div
                                class="p-4 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/60 dark:border-[#1e2433] space-y-2 text-xs text-slate-700 dark:text-slate-300">
                                @if (!empty($cancellationTerms))
                                    <div>
                                        <span
                                            class="font-bold text-slate-900 dark:text-white block">{{ __('Cancellation Policy:') }}</span>
                                        <p class="leading-relaxed">{{ $cancellationTerms }}</p>
                                    </div>
                                @endif
                                @if (!empty($termsAndConditions))
                                    <div class="pt-2 border-t border-slate-200/60 dark:border-zinc-700/60">
                                        <span
                                            class="font-bold text-slate-900 dark:text-white block">{{ __('Terms & Conditions:') }}</span>
                                        <p class="leading-relaxed">{{ $termsAndConditions }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>

                    <!-- Modal Footer -->
                    <div
                        class="p-4 sm:p-5 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-3 bg-slate-50/50 dark:bg-zinc-800/40">
                        <div>
                            @if ($editRoute)
                                <a href="{{ $editRoute }}" wire:navigate
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    <span>{{ __('Edit Experience in Catalog') }}</span>
                                </a>
                            @endif
                        </div>

                        <x-button type="button" variant="secondary" wire:click="closeTripInfoModal"
                            class="text-xs font-bold">
                            {{ __('Close') }}
                        </x-button>
                    </div>

                </div>
            </div>
        @endteleport
    @endif
</div>
