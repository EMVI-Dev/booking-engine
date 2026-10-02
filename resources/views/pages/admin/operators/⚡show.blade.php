<?php

use App\Concerns\RecordsAdminActions;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
use App\Services\DomainResolverService;
use App\Services\OperatorAccountService;
use App\Services\ReservationLifecycleService;
use App\Services\SubscriptionProrationService;
use App\Services\WalletService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Operator Details & Insights')] #[Layout('layouts.admin')] class extends Component {
    use RecordsAdminActions;

    public Operator $operator;

    public string $selected_plan_id = '';

    public string $complimentary_term = 'forever';

    public bool $confirming_plan_change = false;

    public string $adjustmentAmount = '';

    public string $adjustmentReason = '';

    public bool $confirming_suspend = false;

    public function mount(Operator $operator): void
    {
        $this->operator = $operator->load(['users', 'packages', 'products', 'plan']);
        $this->selected_plan_id = (string) ($this->operator->plan_id ?: $this->operator->getPlan()->id);
    }

    /**
     * Switch context to manage the specified operator in the operator portal.
     */
    public function manageOperator(): void
    {
        app(OperatorAccountService::class)->startManaging($this->operator);
        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Update operator approval status.
     */
    public function updateStatus(string $status): void
    {
        $operatorStatus = OperatorAccountService::statusFromInput($status);

        app(OperatorAccountService::class)->changeStatus($this->operator, $operatorStatus);
        $this->operator->refresh();
        $this->dispatch('operator-status-updated', ['name' => $this->operator->name, 'status' => $operatorStatus->label()]);
    }

    /**
     * Ask for confirmation before suspending an operator.
     */
    public function requestSuspend(): void
    {
        $this->confirming_suspend = true;
        $this->dispatch('open-modal', 'confirm-suspend-operator');
    }

    /**
     * Cancel the suspend operator confirmation.
     */
    public function cancelSuspend(): void
    {
        $this->confirming_suspend = false;
        $this->dispatch('close-modal', 'confirm-suspend-operator');
    }

    /**
     * Confirm operator suspension.
     */
    public function confirmSuspend(): void
    {
        $this->updateStatus('suspended');
        $this->confirming_suspend = false;
        $this->dispatch('close-modal', 'confirm-suspend-operator');
    }

    /**
     * Ask before granting a complimentary plan change.
     */
    public function updatedSelectedPlanId(string $value): void
    {
        if ($value === '' || $value === (string) $this->operator->plan_id) {
            $this->confirming_plan_change = false;

            return;
        }

        $this->complimentary_term = 'forever';
        $this->confirming_plan_change = true;
        $this->dispatch('open-modal', 'confirm-complimentary-plan');
    }

    /**
     * Close the complimentary plan modal without changing the operator.
     */
    public function cancelPlanChange(): void
    {
        $this->selected_plan_id = (string) ($this->operator->plan_id ?: $this->operator->getPlan()->id);
        $this->confirming_plan_change = false;
        $this->dispatch('close-modal', 'confirm-complimentary-plan');
    }

    /**
     * Apply the selected plan at no charge.
     */
    public function confirmComplimentaryPlan(): void
    {
        if (!$this->confirming_plan_change) {
            return;
        }

        $this->assignPlan($this->selected_plan_id, $this->resolvedComplimentaryDays());
        $this->confirming_plan_change = false;
        $this->dispatch('close-modal', 'confirm-complimentary-plan');
    }

    /**
     * Assign a plan at no charge. Null $days means no end date.
     */
    public function assignPlan(?string $planId, ?int $days = null): void
    {
        if (!$planId) {
            return;
        }

        $plan = Plan::query()->whereKey($planId)->where('is_active', true)->first();

        if (!$plan) {
            return;
        }

        app(SubscriptionProrationService::class)->grantComplimentaryPlan($this->operator, $plan, $days);
        $this->audit('plan.complimentary_granted', $this->operator, ['plan' => $plan->slug, 'days' => $days]);
        app(OperatorAccountService::class)->forgetCachedLookups($this->operator);
        $this->operator->refresh();
        $this->operator->unsetRelation('plan');
        $this->selected_plan_id = (string) $this->operator->plan_id;
        $this->dispatch('operator-status-updated', ['name' => $this->operator->name, 'status' => 'Plan Updated']);
    }

    /**
     * Hold wallet money on a booking while a card fight is open.
     */
    public function holdBookingMoney(string $reservationId): void
    {
        $reservation = $this->operator->reservations()->with('latestPayment')->find($reservationId);

        if (!$reservation) {
            return;
        }

        $hold = app(WalletService::class)->openCardDispute($reservation);

        if (!$hold) {
            session()->flash('error', __('This booking has no paid guest payment to hold.'));

            return;
        }

        $this->audit('dispute.hold_opened', $reservation, ['amount' => abs((float) $hold->net_amount)]);
        unset($this->recentReservations);
        session()->flash('success', __('Money held until the card fight is finished.'));
    }

    /**
     * Give held booking money back to the operator wallet.
     */
    public function releaseBookingMoney(string $reservationId): void
    {
        $reservation = $this->operator->reservations()->find($reservationId);

        if (!$reservation) {
            return;
        }

        app(WalletService::class)->releaseDispute($reservation);
        $this->audit('dispute.hold_released', $reservation);
        unset($this->recentReservations);
        session()->flash('success', __('Held money is back in the operator wallet.'));
    }

    /**
     * The card dispute was lost: keep the held money as a final deduction and cancel the booking.
     */
    public function markDisputeLost(string $reservationId): void
    {
        $reservation = $this->operator->reservations()->find($reservationId);

        if (!$reservation) {
            return;
        }

        try {
            app(ReservationLifecycleService::class)->resolveCardDispute($reservation, won: false);
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->audit('dispute.lost', $reservation);
        unset($this->recentReservations);
        session()->flash('success', __('Dispute closed as lost. The held money stays deducted.'));
    }

    /**
     * Cancel a booking on the platform's side (refunds the guest if paid).
     */
    public function cancelBooking(string $reservationId): void
    {
        $reservation = $this->operator->reservations()->find($reservationId);

        if (!$reservation) {
            return;
        }

        try {
            $result = app(ReservationLifecycleService::class)->cancel($reservation, 'Cancelled by platform admin');
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->audit('reservation.cancelled_by_admin', $reservation, $result);
        unset($this->recentReservations);
        session()->flash('success', __('Booking cancelled.'));
    }

    /**
     * Credit or debit the operator wallet with a written reason.
     */
    public function adjustWallet(): void
    {
        $this->validate([
            'adjustmentAmount' => ['required', 'numeric', 'not_in:0', 'between:-100000000,100000000'],
            'adjustmentReason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $entry = app(WalletService::class)->recordManualAdjustment($this->operator, (float) $this->adjustmentAmount, $this->adjustmentReason, auth()->user()?->email);

        $this->audit('wallet.adjusted', $this->operator, ['amount' => (float) $entry->net_amount, 'reason' => $this->adjustmentReason, 'entry' => $entry->id]);
        $this->reset('adjustmentAmount', 'adjustmentReason');
        session()->flash('success', __('Wallet adjustment recorded.'));
    }

    public function heldMoneyFor(Reservation $reservation): float
    {
        return app(WalletService::class)->outstandingDisputeHold($reservation);
    }

    #[Computed]
    public function totalRevenue(): float
    {
        return $this->operator->paidGuestPaymentsTotal();
    }

    #[Computed]
    public function totalReservations(): int
    {
        return $this->operator->reservations()->count();
    }

    #[Computed]
    public function completedReservations(): int
    {
        return $this->operator->reservations()->where('status', ReservationStatus::Completed)->count();
    }

    #[Computed]
    public function totalGuests(): int
    {
        return $this->operator->guests()->count();
    }

    #[Computed]
    public function recentReservations()
    {
        return $this->operator
            ->reservations()
            ->with(['bookable', 'latestPayment', 'walletTransactions'])
            ->latest('created_at')
            ->take(8)
            ->get();
    }

    #[Computed]
    public function packages()
    {
        return $this->operator->packages()->withCount('products')->latest('created_at')->get();
    }

    #[Computed]
    public function products()
    {
        return $this->operator->products()->latest('created_at')->get();
    }

    #[Computed]
    public function allPlans()
    {
        return Plan::catalog();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function planSelectOptions(): array
    {
        return $this->allPlans
            ->map(function (Plan $plan): array {
                $price = $plan->isFree() ? __('Free') : __('Complimentary') . ' · Rp ' . number_format((float) $plan->price_monthly, 0, ',', '.') . '/' . __('mo');

                return [
                    'value' => (string) $plan->id,
                    'label' => $plan->name . ' — ' . $price,
                ];
            })
            ->values()
            ->all();
    }

    #[Computed]
    public function pendingComplimentaryPlan(): ?Plan
    {
        if ($this->selected_plan_id === '') {
            return null;
        }

        return $this->allPlans->firstWhere('id', $this->selected_plan_id);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function complimentaryTermOptions(): array
    {
        return [['value' => 'forever', 'label' => __('No end date')], ['value' => '30', 'label' => __('30 days')], ['value' => '90', 'label' => __('90 days')], ['value' => '365', 'label' => __('1 year')]];
    }

    public function resolvedComplimentaryDays(): ?int
    {
        if ($this->complimentary_term === 'forever') {
            return null;
        }

        $days = (int) $this->complimentary_term;

        return $days > 0 ? $days : null;
    }

    public function complimentaryTermLabel(): string
    {
        foreach ($this->complimentaryTermOptions() as $option) {
            if ($option['value'] === $this->complimentary_term) {
                return $option['label'];
            }
        }

        return __('No end date');
    }

    #[Computed]
    public function platformDomain(): string
    {
        return app(DomainResolverService::class)->getPlatformDomain();
    }

    #[Computed]
    public function storefrontUrl(): string
    {
        return request()->getScheme() . '://' . $this->operator->slug . '.' . $this->platformDomain;
    }

    #[Computed]
    public function owner()
    {
        return $this->operator->users->first();
    }
}; ?>

<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Breadcrumb & Page Header --}}
    <div class="space-y-3">
        <nav class="flex items-center gap-2 text-[12px] text-[#60646C] dark:text-slate-400">
            <a href="{{ route('admin.operators.index') }}" wire:navigate
                class="hover:text-[#1C2024] dark:hover:text-white font-medium transition">
                <i class="fa-solid fa-users-gear mr-1 text-[11px] text-[#8B8D98]"></i>
                {{ __('Operators') }}
            </a>
            <i class="fa-solid fa-chevron-right text-[9px] text-[#8B8D98]"></i>
            <span class="font-medium text-[#1C2024] dark:text-white truncate">{{ $operator->name }}</span>
        </nav>

        <div
            class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none flex flex-col md:flex-row md:items-center justify-between gap-6">
            {{-- Left: Identity --}}
            <div class="flex items-start sm:items-center gap-4">
                <div
                    class="w-12 h-12 rounded-[6px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-lg font-bold shadow-none shrink-0 uppercase">
                    {{ substr($operator->name, 0, 2) }}
                </div>

                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-[20px] font-medium leading-[1.6] text-[#1C2024] dark:text-white truncate">
                            {{ $operator->name }}
                        </h1>
                        <span
                            class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium {{ $operator->status === OperatorStatus::Approved ? 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]' : ($operator->status === OperatorStatus::Suspended ? 'bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]' : 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]') }}">
                            {{ $operator->status->label() }}
                        </span>
                        <span
                            class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                            {{ $operator->plan?->name ?? __('Free Plan') }}
                        </span>
                        @if ($operator->hasFeature('priority_support'))
                            <span
                                class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]">
                                {{ __('Faster help') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-[12px] text-[#60646C] dark:text-slate-400">
                        <span
                            class="font-mono text-[#856404] dark:text-[#FFEF4D] font-medium">{{ $operator->slug }}.{{ $this->platformDomain }}</span>
                        <span>&bull;</span>
                        <span>{{ __('Registered') }} {{ $operator->created_at?->diffForHumans() }}</span>
                        <span>&bull;</span>
                        <span>
                            {{ __('Last Active') }}:
                            @if ($operator->last_active_at)
                                <strong
                                    class="{{ $operator->last_active_at->diffInDays(now()) >= 30 ? 'text-[#F59E0B]' : 'text-[#1C2024] dark:text-slate-200' }}">
                                    {{ $operator->last_active_at->diffForHumans() }}
                                </strong>
                            @else
                                <span class="text-[#8B8D98]">{{ __('Never') }}</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Right: Primary Actions --}}
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button type="button" wire:click="manageOperator"
                    class="h-8 px-3 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-[12px] font-medium transition inline-flex items-center gap-1.5 shadow-none cursor-pointer">
                    <i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i>
                    <span>{{ __('Open their dashboard') }}</span>
                </button>

                <a href="{{ $this->storefrontUrl }}" target="_blank"
                    class="h-8 px-3 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#F4F5F6] border border-[#E4E5E9] dark:border-[#1e2433] text-[#1C2024] dark:text-slate-200 text-[12px] font-normal transition inline-flex items-center gap-1.5 shadow-none">
                    <span>{{ __('Visit Storefront') }}</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-[#8B8D98]"></i>
                </a>

                {{-- Status Actions --}}
                <div class="flex items-center gap-1.5 pl-2 border-l border-[#E4E5E9] dark:border-[#1e2433]">
                    @if ($operator->status !== OperatorStatus::Approved)
                        <x-button variant="success" size="sm" wire:click="updateStatus('approved')" title="{{ __('Approve Operator') }}">
                            <i class="fa-solid fa-check text-[10px]"></i>
                            <span>{{ __('Approve') }}</span>
                        </x-button>
                    @endif

                    @if ($operator->status !== OperatorStatus::Suspended)
                        <x-button variant="danger" size="sm" wire:click="requestSuspend" title="{{ __('Suspend Operator') }}">
                            <i class="fa-solid fa-ban text-[10px]"></i>
                            <span>{{ __('Suspend') }}</span>
                        </x-button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- KPI Insights Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div
            class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-1">
            <span
                class="text-[12px] font-normal text-[#60646C] dark:text-slate-400">{{ __('Guest payments') }}</span>
            <div class="text-[24px] font-medium text-[#1C2024] dark:text-white">
                Rp {{ number_format($this->totalRevenue, 0, ',', '.') }}
            </div>
        </div>

        <div
            class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-1">
            <span
                class="text-[12px] font-normal text-[#60646C] dark:text-slate-400">{{ __('Total Reservations') }}</span>
            <div class="flex items-baseline gap-2">
                <span class="text-[24px] font-medium text-[#1C2024] dark:text-white">{{ $this->totalReservations }}</span>
                <span class="text-[12px] text-[#60646C] dark:text-slate-400">({{ $this->completedReservations }} {{ __('completed') }})</span>
            </div>
        </div>

        <div
            class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-1">
            <span
                class="text-[12px] font-normal text-[#60646C] dark:text-slate-400">{{ __('Listed Experiences') }}</span>
            <div class="flex items-baseline gap-2">
                <span
                    class="text-[24px] font-medium text-[#1C2024] dark:text-white">{{ $operator->packages->count() }}</span>
                <span class="text-[12px] text-[#60646C] dark:text-slate-400">{{ __('packages') }} &bull; {{ $operator->products->count() }}
                    {{ __('products') }}</span>
            </div>
        </div>

        <div
            class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-1">
            <span
                class="text-[12px] font-normal text-[#60646C] dark:text-slate-400">{{ __('Guest Directory') }}</span>
            <div class="text-[24px] font-medium text-[#1C2024] dark:text-white">{{ $this->totalGuests }}</div>
        </div>
    </div>

    {{-- Main Content Layout (2 Columns) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2-Column Section: Tables & Subscriptions --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Subscription Plan Assignment Card --}}
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span
                            class="p-1 rounded-[4px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] text-xs">
                            <i class="fa-solid fa-layer-group"></i>
                        </span>
                        <h3 class="text-[14px] font-medium text-[#1C2024] dark:text-white">
                            {{ __('Plan') }}
                        </h3>
                    </div>
                    <span
                        class="px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                        {{ __('Listed price stays with the operator') }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start pt-1">
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-[#60646C] dark:text-slate-400">{{ __('Current plan') }}</label>
                        <div
                            class="h-9 px-3 rounded-[6px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1e2433] flex items-center justify-between">
                            <span class="font-medium text-[13px] text-[#1C2024] dark:text-white">
                                {{ $operator->plan?->name ?? __('Free Tier') }}
                            </span>
                            <span class="text-[11px] text-[#8B8D98]">
                                @if ($operator->plan_expires_at)
                                    {{ __('Until') }} {{ $operator->plan_expires_at->format('M d, Y') }}
                                @elseif ($operator->subscribed_at)
                                    {{ __('Since') }} {{ $operator->subscribed_at->format('M d, Y') }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="plan_switch" class="text-[12px] font-medium text-[#60646C] dark:text-slate-400">{{ __('Change plan') }}</label>
                        <x-select id="plan_switch" wire:model.live="selected_plan_id" :options="$this->planSelectOptions" class="w-full text-xs" />
                        <p class="text-[11px] text-[#8B8D98]">
                            {{ __('Admin changes are complimentary. No invoice is collected.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Packages & Combos Listed --}}
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span
                            class="p-1 rounded-[4px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] text-xs">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </span>
                        <h3 class="text-[14px] font-medium text-[#1C2024] dark:text-white">
                            {{ __('Tour Packages & Combos (:count)', ['count' => $this->packages->count()]) }}
                        </h3>
                    </div>
                </div>

                @if ($this->packages->isEmpty())
                    <p class="text-[12px] text-[#8B8D98] py-4 text-center">
                        {{ __('No tour packages created by this operator yet.') }}</p>
                @else
                    <div class="overflow-x-auto rounded-[6px] border border-[#E4E5E9] dark:border-[#1e2433]">
                        <table class="w-full text-left text-xs sm:text-sm">
                            <thead>
                                <tr
                                    class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1e2433] text-[11px] font-medium uppercase tracking-wider text-[#8B8D98]">
                                    <th class="py-3 px-4">{{ __('Package Title') }}</th>
                                    <th class="py-3 px-4">{{ __('Category') }}</th>
                                    <th class="py-3 px-4">{{ __('Price') }}</th>
                                    <th class="py-3 px-4">{{ __('Items Included') }}</th>
                                    <th class="py-3 px-4 text-right">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1e2433]">
                                @foreach ($this->packages as $pkg)
                                    <tr class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/60 transition group">
                                        <td class="py-3 px-4 font-medium text-[13px] text-[#1C2024] dark:text-white">
                                            {{ $pkg->title }}
                                        </td>
                                        <td class="py-3 px-4 text-[12px] text-[#60646C] dark:text-slate-400">
                                            {{ $pkg->category }}
                                        </td>
                                        <td class="py-3 px-4 font-mono font-medium text-[13px] text-[#1C2024] dark:text-white">
                                            Rp {{ number_format((float) $pkg->price, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3 px-4 text-[12px] text-[#60646C] dark:text-slate-400">
                                            {{ $pkg->products_count }} {{ __('products') }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] text-[11px] font-medium {{ $pkg->status === ListingStatus::Published ? 'bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40' : 'bg-[#EFEFF0] text-[#60646C] dark:bg-[#141821] dark:text-slate-400 border border-[#E4E5E9]' }}">
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full {{ $pkg->status === ListingStatus::Published ? 'bg-[#F59E0B]' : 'bg-[#8B8D98]' }}"></span>
                                                {{ $pkg->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Activities & Inventory Items Listed --}}
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span
                            class="p-1 rounded-[4px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] text-xs">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </span>
                        <h3 class="text-[14px] font-medium text-[#1C2024] dark:text-white">
                            {{ __('Activities & Inventory Items (:count)', ['count' => $this->products->count()]) }}
                        </h3>
                    </div>
                </div>

                @if ($this->products->isEmpty())
                    <p class="text-[12px] text-[#8B8D98] py-4 text-center">
                        {{ __('No inventory items created by this operator yet.') }}</p>
                @else
                    <div class="overflow-x-auto rounded-[6px] border border-[#E4E5E9] dark:border-[#1e2433]">
                        <table class="w-full text-left text-xs sm:text-sm">
                            <thead>
                                <tr
                                    class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1e2433] text-[11px] font-medium uppercase tracking-wider text-[#8B8D98]">
                                    <th class="py-3 px-4">{{ __('Item Name') }}</th>
                                    <th class="py-3 px-4">{{ __('Category') }}</th>
                                    <th class="py-3 px-4">{{ __('Daily Capacity') }}</th>
                                    <th class="py-3 px-4">{{ __('Standalone Sale') }}</th>
                                    <th class="py-3 px-4 text-right">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1e2433]">
                                @foreach ($this->products as $prod)
                                    <tr class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/60 transition group">
                                        <td class="py-3 px-4 font-medium text-[13px] text-[#1C2024] dark:text-white">
                                            {{ $prod->name }}
                                        </td>
                                        <td class="py-3 px-4 text-[12px] text-[#60646C] dark:text-slate-400">
                                            {{ $prod->category }}
                                        </td>
                                        <td class="py-3 px-4 font-normal text-[13px] text-[#1C2024] dark:text-white">
                                            {{ $prod->capacity_per_day }} {{ __('guests / day') }}
                                        </td>
                                        <td class="py-3 px-4 text-[12px] text-[#60646C] dark:text-slate-400">
                                            @if ($prod->sellable_standalone)
                                                <span class="font-mono font-medium text-[#1C2024] dark:text-white">Rp
                                                    {{ number_format((float) $prod->price, 0, ',', '.') }}</span>
                                            @else
                                                <span class="text-[#8B8D98]">{{ __('Package Only') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] text-[11px] font-medium {{ $prod->status === ListingStatus::Published ? 'bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40' : 'bg-[#EFEFF0] text-[#60646C] dark:bg-[#141821] dark:text-slate-400 border border-[#E4E5E9]' }}">
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full {{ $prod->status === ListingStatus::Published ? 'bg-[#F59E0B]' : 'bg-[#8B8D98]' }}"></span>
                                                {{ $prod->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Recent Reservations Stream --}}
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span
                            class="p-1 rounded-[4px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] text-xs">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <h3 class="text-[14px] font-medium text-[#1C2024] dark:text-white">
                            {{ __('Recent bookings') }}
                        </h3>
                    </div>
                </div>

                @if (session('success'))
                    <p class="text-[12px] font-medium text-emerald-600 dark:text-emerald-400">{{ session('success') }}
                    </p>
                @endif

                @if (session('error'))
                    <p class="text-[12px] font-medium text-rose-600 dark:text-rose-400">{{ session('error') }}</p>
                @endif

                @if ($this->recentReservations->isEmpty())
                    <p class="text-[12px] text-[#8B8D98] py-4 text-center">
                        {{ __('No reservations processed for this operator yet.') }}</p>
                @else
                    <div class="overflow-x-auto rounded-[6px] border border-[#E4E5E9] dark:border-[#1e2433]">
                        <table class="w-full text-left text-xs sm:text-sm">
                            <thead>
                                <tr
                                    class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1e2433] text-[11px] font-medium uppercase tracking-wider text-[#8B8D98]">
                                    <th class="py-3 px-4">{{ __('Code') }}</th>
                                    <th class="py-3 px-4">{{ __('Guest') }}</th>
                                    <th class="py-3 px-4">{{ __('Bookable Item') }}</th>
                                    <th class="py-3 px-4">{{ __('Amount') }}</th>
                                    <th class="py-3 px-4 text-right">{{ __('Status') }}</th>
                                    <th class="py-3 px-4 text-right">{{ __('Money') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1e2433]">
                                @foreach ($this->recentReservations as $res)
                                    @php
                                        $heldMoney = $this->heldMoneyFor($res);
                                        $paidAmount = $res->getChargedAmount();
                                    @endphp
                                    <tr wire:key="recent-reservation-{{ $res->id }}"
                                        class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/60 transition group">
                                        <td class="py-3 px-4">
                                            <span
                                                class="font-mono text-[11px] font-medium text-[#1C2024] dark:text-white px-1.5 py-0.5 rounded-[4px] bg-[#EFEFF0] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1e2433]">
                                                #{{ $res->code }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-[13px] text-[#1C2024] dark:text-white font-medium">
                                            {{ $res->guest_name }}
                                        </td>
                                        <td class="py-3 px-4 text-[12px] text-[#60646C] dark:text-slate-400">
                                            {{ $res->bookable?->title ?? ($res->bookable?->name ?? 'Item') }}
                                        </td>
                                        <td class="py-3 px-4 font-mono font-medium text-[13px] text-[#1C2024] dark:text-white">
                                            Rp {{ number_format($paidAmount, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#EFEFF0] text-[#1C2024] dark:bg-[#141821] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1e2433]">
                                                {{ $res->status->label() }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            @if ($heldMoney > 0)
                                                <button type="button"
                                                    wire:click="releaseBookingMoney('{{ $res->id }}')"
                                                    class="h-7 px-2.5 rounded-[6px] text-[11px] font-medium bg-white dark:bg-[#141821] text-[#1C2024] dark:text-slate-200 border border-[#E4E5E9] dark:border-[#1e2433] hover:bg-[#F4F5F6] cursor-pointer transition shadow-none">
                                                    {{ __('Give money back') }}
                                                </button>
                                            @elseif ($res->latestPayment?->status === PaymentStatus::Paid)
                                                <button type="button"
                                                    wire:click="holdBookingMoney('{{ $res->id }}')"
                                                    class="h-7 px-2.5 rounded-[6px] text-[11px] font-medium bg-white dark:bg-[#141821] text-[#1C2024] dark:text-slate-200 border border-[#E4E5E9] dark:border-[#1e2433] hover:bg-[#F4F5F6] cursor-pointer transition shadow-none">
                                                    {{ __('Hold money') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right 1-Column Section: Owner, Contacts, Settlement --}}
        <div class="space-y-6">
            {{-- Owner & Contact Routing --}}
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-[#8B8D98]">
                    {{ __('Who to email') }}
                </h3>

                <div class="space-y-2.5 text-xs">
                    <div>
                        <span
                            class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Account Owner Name') }}</span>
                        <span
                            class="font-medium text-[#1C2024] dark:text-white text-[13px]">{{ $this->owner?->name ?? '-' }}</span>
                    </div>

                    <div>
                        <span
                            class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Owner Account Email') }}</span>
                        <span
                            class="font-mono text-[#60646C] dark:text-slate-300 text-[12px]">{{ $this->owner?->email ?? '-' }}</span>
                    </div>

                    <div>
                        <span
                            class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Booking Notifications Email') }}</span>
                        <span
                            class="font-mono text-[#60646C] dark:text-slate-300 text-[12px]">{{ $operator->booking_notification_email ?? '-' }}</span>
                    </div>

                    <div>
                        <span
                            class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Billing & Settlement Email') }}</span>
                        <span
                            class="font-mono text-[#60646C] dark:text-slate-300 text-[12px]">{{ $operator->billing_email ?? '-' }}</span>
                    </div>

                    <div>
                        <span class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Domain') }}</span>
                        <div class="space-y-1">
                            <a href="{{ $this->storefrontUrl }}" target="_blank"
                                class="font-mono text-[#856404] dark:text-[#FFEF4D] hover:underline text-[11px] flex items-center gap-1">
                                {{ $operator->slug }}.{{ $this->platformDomain }}
                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                            </a>
                            @php
                                $customDomain = $operator
                                    ->domains()
                                    ->where('type', \App\Enums\DomainType::Custom)
                                    ->first();
                            @endphp
                            @if ($customDomain)
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="font-mono text-[#60646C] dark:text-slate-300 text-[11px]">{{ $customDomain->domain }}</span>
                                    <span
                                        class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-medium uppercase {{ $customDomain->status->value === 'active' ? 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]' : 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]' }}">
                                        {{ $customDomain->status->value }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Direct Bank Settlement Details --}}
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-[#8B8D98]">
                    {{ __('Payout bank account') }}
                </h3>

                <div
                    class="p-3.5 rounded-[6px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1e2433] space-y-2.5 text-xs">
                    <div>
                        <span class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Bank') }}</span>
                        <span
                            class="font-medium text-[#1C2024] dark:text-white text-[13px]">{{ $operator->bank_provider ?? __('Not Set') }}</span>
                    </div>

                    <div>
                        <span
                            class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Account Number') }}</span>
                        <span
                            class="font-mono font-medium text-[#1C2024] dark:text-white text-[13px]">{{ $operator->bank_account_number ?? '-' }}</span>
                    </div>

                    <div>
                        <span
                            class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Name on the account') }}</span>
                        <span
                            class="font-medium text-[#1C2024] dark:text-white text-[13px]">{{ $operator->bank_account_name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            {{-- WhatsApp Support Hours --}}
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-[#8B8D98]">
                    {{ __('WhatsApp Support & Schedule') }}
                </h3>

                <div class="space-y-2.5 text-xs">
                    <div>
                        <span
                            class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('WhatsApp Number') }}</span>
                        <span
                            class="font-medium text-[#1C2024] dark:text-white text-[13px]">{{ $operator->contact_whatsapp ?? __('None') }}</span>
                    </div>

                    <div>
                        <span
                            class="text-[#8B8D98] block text-[10px] uppercase font-medium">{{ __('Live Schedule Summary') }}</span>
                        <span
                            class="font-normal text-[#60646C] dark:text-slate-300 text-[12px]">{{ $operator->getWhatsAppScheduleSummary() }}</span>
                    </div>
                </div>
            </div>

            {{-- Storefront Capabilities & Features --}}
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none space-y-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-[#8B8D98]">
                    {{ __('Storefront') }}
                </h3>

                @php
                    $storeSettings = $operator->settings['storefront'] ?? [];
                @endphp

                <div class="space-y-2.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[#60646C] dark:text-slate-400 text-[12px]">{{ __('Standalone Selling:') }}</span>
                        <span
                            class="font-medium text-[#1C2024] dark:text-white text-[12px]">{{ $storeSettings['allow_standalone_products'] ?? true ? __('Enabled') : __('Disabled') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[#60646C] dark:text-slate-400 text-[12px]">{{ __('How guests pay:') }}</span>
                        <span class="font-medium text-[#1C2024] dark:text-white text-[12px]">{{ __('Through EMVI') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-modal name="confirm-complimentary-plan" :show="$confirming_plan_change" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div
                class="w-10 h-10 rounded-[8px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] flex items-center justify-center mx-auto text-base">
                <i class="fa-solid fa-gift"></i>
            </div>

            <div class="space-y-1 text-center">
                <h3 class="text-sm font-semibold text-[#1C2024] dark:text-white">
                    {{ __('Confirm plan change') }}
                </h3>
                <p class="text-xs text-[#60646C] dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    {{ __('Review the complimentary grant before it applies.') }}
                </p>
            </div>

            <dl
                class="rounded-[8px] border border-[#E4E5E9] dark:border-[#1e2433] bg-[#FAFAFB] dark:bg-[#141821] divide-y divide-[#E4E5E9] dark:divide-[#1e2433] text-left text-xs">
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-normal text-[#60646C] dark:text-slate-400">{{ __('Operator') }}</dt>
                    <dd class="font-medium text-[#1C2024] dark:text-white text-right">{{ $operator->name }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-normal text-[#60646C] dark:text-slate-400">{{ __('From') }}</dt>
                    <dd class="font-medium text-[#1C2024] dark:text-white text-right">{{ $operator->getPlan()->name }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-normal text-[#60646C] dark:text-slate-400">{{ __('To') }}</dt>
                    <dd class="font-medium text-[#1C2024] dark:text-white text-right">
                        {{ $this->pendingComplimentaryPlan?->name ?? __('this plan') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-normal text-[#60646C] dark:text-slate-400">{{ __('Charge') }}</dt>
                    <dd class="font-medium text-emerald-600 dark:text-emerald-400 text-right">
                        {{ __('Rp 0 — complimentary') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-normal text-[#60646C] dark:text-slate-400">{{ __('Term') }}</dt>
                    <dd class="font-medium text-[#1C2024] dark:text-white text-right">
                        {{ $this->pendingComplimentaryPlan?->isFree() ? __('No end date') : $this->complimentaryTermLabel() }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-normal text-[#60646C] dark:text-slate-400">{{ __('Auto-renew') }}</dt>
                    <dd class="font-medium text-[#1C2024] dark:text-white text-right">{{ __('Off') }}</dd>
                </div>
            </dl>

            @if ($this->pendingComplimentaryPlan && !$this->pendingComplimentaryPlan->isFree())
                <div class="space-y-1.5 text-left">
                    <label for="complimentary_term" class="text-[12px] font-medium text-[#60646C] dark:text-slate-400">{{ __('How long?') }}</label>
                    <x-select id="complimentary_term" wire:model.live="complimentary_term" :options="$this->complimentaryTermOptions()" class="w-full text-xs" />
                </div>
            @endif

            <div class="flex items-center justify-center gap-2 pt-2">
                <x-button variant="secondary" size="sm" wire:click="cancelPlanChange">
                    {{ __('Cancel') }}
                </x-button>
                <x-button variant="primary" size="sm" wire:click="confirmComplimentaryPlan">
                    <i class="fa-solid fa-gift text-xs"></i>
                    <span>{{ __('Grant plan') }}</span>
                </x-button>
            </div>
        </div>
    </x-modal>

    {{-- Suspend Operator Confirmation Modal --}}
    <x-modal name="confirm-suspend-operator" :show="$confirming_suspend" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div
                class="w-10 h-10 rounded-[8px] bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 flex items-center justify-center mx-auto text-base">
                <i class="fa-solid fa-ban"></i>
            </div>

            <div class="space-y-1">
                <h3 class="text-sm font-semibold text-[#1C2024] dark:text-white">
                    {{ __('Suspend operator account?') }}
                </h3>
                <p class="text-xs text-[#60646C] dark:text-zinc-400 max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to suspend :name? The operator and their team members will immediately lose access to the portal, and their storefront bookings will be paused.', ['name' => $operator->name]) }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-2 pt-2">
                <x-button variant="secondary" size="sm" wire:click="cancelSuspend">
                    {{ __('Cancel') }}
                </x-button>
                <x-button variant="danger" size="sm" wire:click="confirmSuspend">
                    <i class="fa-solid fa-ban text-[10px]"></i>
                    <span>{{ __('Suspend Operator') }}</span>
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
