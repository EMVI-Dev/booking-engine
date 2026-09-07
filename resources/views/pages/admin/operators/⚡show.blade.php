<?php

use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
use App\Services\DomainResolverService;
use App\Services\OperatorActivitySlackNotifier;
use App\Services\SubscriptionProrationService;
use App\Services\WalletService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Operator Details & Insights')] #[Layout('layouts.admin')] class extends Component {
    public Operator $operator;

    public string $selected_plan_id = '';

    public string $complimentary_term = 'forever';

    public bool $confirming_plan_change = false;

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
        session(['admin_impersonated_operator_id' => $this->operator->id]);
        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Update operator approval status.
     */
    public function updateStatus(string $status): void
    {
        $operatorStatus = match ($status) {
            'approved' => OperatorStatus::Approved,
            'suspended' => OperatorStatus::Suspended,
            default => OperatorStatus::Pending,
        };

        $fromStatus = $this->operator->status->label();
        $this->operator->update(['status' => $operatorStatus]);
        app(DomainResolverService::class)->clearOperatorDomainCache($this->operator);
        Cache::flush();
        $this->operator->refresh();
        app(OperatorActivitySlackNotifier::class)->statusChanged($this->operator, $fromStatus, $operatorStatus->label());
        $this->dispatch('operator-status-updated', ['name' => $this->operator->name, 'status' => $operatorStatus->label()]);
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
        if (! $this->confirming_plan_change) {
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
        if (! $planId) {
            return;
        }

        $plan = Plan::query()->whereKey($planId)->where('is_active', true)->first();

        if (! $plan) {
            return;
        }

        app(SubscriptionProrationService::class)->grantComplimentaryPlan($this->operator, $plan, $days);
        app(DomainResolverService::class)->clearOperatorDomainCache($this->operator);
        Cache::flush();
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

        if (! $reservation) {
            return;
        }

        $payment = $reservation->latestPayment;

        if ($payment === null || $payment->status !== PaymentStatus::Paid) {
            session()->flash('error', __('This booking has no paid guest payment to hold.'));

            return;
        }

        app(WalletService::class)->holdDispute($reservation, (float) $payment->amount);
        unset($this->recentReservations);
        session()->flash('success', __('Money held until the card fight is finished.'));
    }

    /**
     * Give held booking money back to the operator wallet.
     */
    public function releaseBookingMoney(string $reservationId): void
    {
        $reservation = $this->operator->reservations()->find($reservationId);

        if (! $reservation) {
            return;
        }

        app(WalletService::class)->releaseDispute($reservation);
        unset($this->recentReservations);
        session()->flash('success', __('Held money is back in the operator wallet.'));
    }

    public function heldMoneyFor(Reservation $reservation): float
    {
        return app(WalletService::class)->outstandingDisputeHold($reservation);
    }

    #[Computed]
    public function totalRevenue(): float
    {
        return (float) Payment::where('status', PaymentStatus::Paid)->whereHas('reservation', fn($q) => $q->where('operator_id', $this->operator->id))->sum('amount');
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
        return Plan::where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function planSelectOptions(): array
    {
        return $this->allPlans
            ->map(function (Plan $plan): array {
                $price = $plan->isFree()
                    ? __('Free')
                    : __('Complimentary').' · Rp '.number_format((float) $plan->price_monthly, 0, ',', '.').'/'.__('mo');

                return [
                    'value' => (string) $plan->id,
                    'label' => $plan->name.' — '.$price,
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
        return [
            ['value' => 'forever', 'label' => __('No end date')],
            ['value' => '30', 'label' => __('30 days')],
            ['value' => '90', 'label' => __('90 days')],
            ['value' => '365', 'label' => __('1 year')],
        ];
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
    <!-- Breadcrumb & Page Header -->
    <div class="space-y-3">
        <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <a href="{{ route('admin.operators.index') }}" wire:navigate
                class="hover:text-[#8a7808] dark:hover:text-[#FFEF4D] font-semibold transition">
                <i class="fa-solid fa-users-gear mr-1"></i>
                {{ __('Operators') }}
            </a>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-300 dark:text-slate-600"></i>
            <span class="font-bold text-slate-900 dark:text-white truncate">{{ $operator->name }}</span>
        </nav>

        <div
            class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Left: Identity -->
            <div class="flex items-start sm:items-center gap-4">
                <div
                    class="w-16 h-16 rounded-2xl bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-2xl font-black shadow-md shrink-0 uppercase">
                    {{ substr($operator->name, 0, 2) }}
                </div>

                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white truncate">
                            {{ $operator->name }}
                        </h1>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider {{ $operator->status === OperatorStatus::Approved ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : ($operator->status === OperatorStatus::Suspended ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300') }}">
                            {{ $operator->status->label() }}
                        </span>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
                            {{ $operator->plan?->name ?? __('Free Plan') }}
                        </span>
                        @if ($operator->hasFeature('priority_support'))
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                {{ __('Faster help') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <span
                            class="font-mono text-[#8a7808] dark:text-[#FFEF4D] font-semibold">{{ $operator->slug }}.{{ $this->platformDomain }}</span>
                        <span>&bull;</span>
                        <span>{{ __('Registered') }} {{ $operator->created_at?->diffForHumans() }}</span>
                    </div>
                </div>
            </div>

            <!-- Right: Primary Actions -->
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <x-button type="button" size="sm" wire:click="manageOperator">
                    <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                    <span>{{ __('Open their dashboard') }}</span>
                </x-button>

                <a href="{{ $this->storefrontUrl }}" target="_blank"
                    class="h-9 px-3.5 inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-[#141821] border border-slate-200 dark:border-[#1e2433] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-slate-200 font-bold text-xs shadow-xs transition">
                    <span>{{ __('Visit Storefront') }}</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>

                <!-- Status Action Dropdown/Buttons -->
                <div class="flex items-center gap-1 pl-2 border-l border-slate-200 dark:border-[#1e2433]">
                    @if ($operator->status !== OperatorStatus::Approved)
                        <x-button size="sm" type="button" wire:click="updateStatus('approved')"
                            class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs"
                            title="{{ __('Approve Operator') }}">
                            <i class="fa-solid fa-check mr-1 text-xs"></i>
                            {{ __('Approve') }}
                        </x-button>
                    @endif

                    @if ($operator->status !== OperatorStatus::Suspended)
                        <x-button size="sm" type="button" wire:click="updateStatus('suspended')" variant="danger"
                            class="text-xs font-bold" title="{{ __('Suspend Operator') }}">
                            <i class="fa-solid fa-ban mr-1 text-xs"></i>
                            {{ __('Suspend') }}
                        </x-button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Insights Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div
            class="p-5 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-1">
            <span
                class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Guest payments') }}</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white">
                Rp {{ number_format($this->totalRevenue, 0, ',', '.') }}
            </div>
        </div>

        <div
            class="p-5 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-1">
            <span
                class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Total Reservations') }}</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $this->totalReservations }}</span>
                <span class="text-xs text-slate-400">({{ $this->completedReservations }} {{ __('completed') }})</span>
            </div>
        </div>

        <div
            class="p-5 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-1">
            <span
                class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Listed Experiences') }}</span>
            <div class="flex items-baseline gap-2">
                <span
                    class="text-2xl font-black text-slate-900 dark:text-white">{{ $operator->packages->count() }}</span>
                <span class="text-xs text-slate-400">{{ __('packages') }} &bull; {{ $operator->products->count() }}
                    {{ __('products') }}</span>
            </div>
        </div>

        <div
            class="p-5 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-1">
            <span
                class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Guest Directory') }}</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $this->totalGuests }}</div>
        </div>
    </div>

    <!-- Main Content Layout (2 Columns) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2-Column Section: Tables & Subscriptions -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Subscription Plan Assignment Card -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span
                            class="p-1.5 rounded-lg bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                            <i class="fa-solid fa-layer-group"></i>
                        </span>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Plan') }}
                        </h3>
                    </div>
                    <span
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
                        {{ __('Listed price stays with the operator') }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start pt-1">
                    <div class="space-y-1.5">
                        <x-label :value="__('Current plan')" class="text-xs" />
                        <div class="h-10 px-3.5 rounded-xl bg-slate-50 dark:bg-[#141821] border border-slate-200/80 dark:border-[#1e2433] flex items-center justify-between">
                            <span class="font-black text-sm text-slate-900 dark:text-white">
                                {{ $operator->plan?->name ?? __('Free Tier') }}
                            </span>
                            <span class="text-[11px] font-medium text-slate-400">
                                @if ($operator->plan_expires_at)
                                    {{ __('Until') }} {{ $operator->plan_expires_at->format('M d, Y') }}
                                @elseif ($operator->subscribed_at)
                                    {{ __('Since') }} {{ $operator->subscribed_at->format('M d, Y') }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="plan_switch" :value="__('Change plan')" class="text-xs" />
                        <x-select
                            id="plan_switch"
                            wire:model.live="selected_plan_id"
                            :options="$this->planSelectOptions"
                            class="w-full text-xs font-semibold"
                        />
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ __('Admin changes are complimentary. No invoice is collected.') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Packages & Combos Listed -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span
                            class="p-1.5 rounded-lg bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </span>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Tour Packages & Combos (:count)', ['count' => $this->packages->count()]) }}
                        </h3>
                    </div>
                </div>

                @if ($this->packages->isEmpty())
                    <p class="text-xs text-slate-400 py-4 text-center">
                        {{ __('No tour packages created by this operator yet.') }}</p>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-[#1e2433]">
                        <table class="w-full text-left text-xs sm:text-sm">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    <th class="py-3.5 px-4">{{ __('Package Title') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Category') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Price') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Items Included') }}</th>
                                    <th class="py-3.5 px-4 text-right">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                                @foreach ($this->packages as $pkg)
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group">
                                        <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                            {{ $pkg->title }}
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $pkg->category }}
                                        </td>
                                        <td class="py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                            Rp {{ number_format((float) $pkg->price, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $pkg->products_count }} {{ __('products') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $pkg->status === ListingStatus::Published ? 'bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30' : 'bg-slate-100 text-slate-600 dark:bg-[#141821] dark:text-slate-400 border border-slate-200 dark:border-[#1e2433]' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $pkg->status === ListingStatus::Published ? 'bg-[#FFEF4D]' : 'bg-slate-400' }}"></span>
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

            <!-- Activities & Inventory Items Listed -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span
                            class="p-1.5 rounded-lg bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </span>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Activities & Inventory Items (:count)', ['count' => $this->products->count()]) }}
                        </h3>
                    </div>
                </div>

                @if ($this->products->isEmpty())
                    <p class="text-xs text-slate-400 py-4 text-center">
                        {{ __('No inventory items created by this operator yet.') }}</p>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-[#1e2433]">
                        <table class="w-full text-left text-xs sm:text-sm">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    <th class="py-3.5 px-4">{{ __('Item Name') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Category') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Daily Capacity') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Standalone Sale') }}</th>
                                    <th class="py-3.5 px-4 text-right">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                                @foreach ($this->products as $prod)
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group">
                                        <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                            {{ $prod->name }}
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $prod->category }}
                                        </td>
                                        <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                                            {{ $prod->capacity_per_day }} {{ __('guests / day') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400">
                                            @if ($prod->sellable_standalone)
                                                <span class="font-mono font-bold text-[#FFEF4D]">Rp
                                                    {{ number_format((float) $prod->price, 0, ',', '.') }}</span>
                                            @else
                                                <span class="text-slate-400">{{ __('Package Only') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $prod->status === ListingStatus::Published ? 'bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30' : 'bg-slate-100 text-slate-600 dark:bg-[#141821] dark:text-slate-400 border border-slate-200 dark:border-[#1e2433]' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $prod->status === ListingStatus::Published ? 'bg-[#FFEF4D]' : 'bg-slate-400' }}"></span>
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

            <!-- Recent Reservations Stream -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2">
                        <span
                            class="p-1.5 rounded-lg bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Recent bookings') }}
                        </h3>
                    </div>
                </div>

                @if (session('success'))
                    <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ session('success') }}</p>
                @endif

                @if (session('error'))
                    <p class="text-xs font-semibold text-rose-600 dark:text-rose-400">{{ session('error') }}</p>
                @endif

                @if ($this->recentReservations->isEmpty())
                    <p class="text-xs text-slate-400 py-4 text-center">
                        {{ __('No reservations processed for this operator yet.') }}</p>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-[#1e2433]">
                        <table class="w-full text-left text-xs sm:text-sm">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    <th class="py-3.5 px-4">{{ __('Code') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Guest') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Bookable Item') }}</th>
                                    <th class="py-3.5 px-4">{{ __('Amount') }}</th>
                                    <th class="py-3.5 px-4 text-right">{{ __('Status') }}</th>
                                    <th class="py-3.5 px-4 text-right">{{ __('Money') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                                @foreach ($this->recentReservations as $res)
                                    @php
                                        $heldMoney = $this->heldMoneyFor($res);
                                        $paidAmount = (float) ($res->latestPayment?->amount ?? $res->getTotalAmount());
                                    @endphp
                                    <tr wire:key="recent-reservation-{{ $res->id }}" class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group">
                                        <td class="py-3.5 px-4">
                                            <span class="font-mono text-[10px] font-bold text-[#FFEF4D] px-2 py-0.5 rounded-lg bg-[#FFEF4D]/10 border border-[#FFEF4D]/30">
                                                #{{ $res->code }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-900 dark:text-white font-medium">
                                            {{ $res->guest_name }}
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $res->bookable?->title ?? ($res->bookable?->name ?? 'Item') }}
                                        </td>
                                        <td class="py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                            Rp {{ number_format($paidAmount, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-[#141821] dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]">
                                                {{ $res->status->label() }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            @if ($heldMoney > 0)
                                                <button
                                                    type="button"
                                                    wire:click="releaseBookingMoney('{{ $res->id }}')"
                                                    class="h-8 px-2.5 rounded-lg text-[11px] font-bold bg-slate-100 dark:bg-[#141821] text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-[#1e2433] hover:bg-slate-200 dark:hover:bg-[#1e2433] cursor-pointer"
                                                >
                                                    {{ __('Give money back') }}
                                                </button>
                                            @elseif ($res->latestPayment?->status === PaymentStatus::Paid)
                                                <button
                                                    type="button"
                                                    wire:click="holdBookingMoney('{{ $res->id }}')"
                                                    class="h-8 px-2.5 rounded-lg text-[11px] font-bold bg-white dark:bg-[#0C0E13] text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-[#1e2433] hover:bg-slate-50 dark:hover:bg-[#141821] cursor-pointer"
                                                >
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

        <!-- Right 1-Column Section: Owner, Contacts, Settlement -->
        <div class="space-y-6">
            <!-- Owner & Contact Routing -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Who to email') }}
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Account Owner Name') }}</span>
                        <span
                            class="font-bold text-slate-900 dark:text-white text-sm">{{ $this->owner?->name ?? '-' }}</span>
                    </div>

                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Owner Account Email') }}</span>
                        <span
                            class="font-mono text-slate-700 dark:text-slate-300">{{ $this->owner?->email ?? '-' }}</span>
                    </div>

                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Booking Notifications Email') }}</span>
                        <span
                            class="font-mono text-slate-700 dark:text-slate-300">{{ $operator->booking_notification_email ?? '-' }}</span>
                    </div>

                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Billing & Settlement Email') }}</span>
                        <span
                            class="font-mono text-slate-700 dark:text-slate-300">{{ $operator->billing_email ?? '-' }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Domain') }}</span>
                        <div class="space-y-1">
                            <a href="{{ $this->storefrontUrl }}" target="_blank"
                                class="font-mono text-[#FFEF4D] dark:text-[#FFEF4D] hover:underline text-[11px] flex items-center gap-1">
                                {{ $operator->slug }}.{{ $this->platformDomain }}
                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                            </a>
                            @php
                                $customDomain = $operator->domains()->where('type', \App\Enums\DomainType::Custom)->first();
                            @endphp
                            @if ($customDomain)
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-slate-700 dark:text-slate-300 text-[11px]">{{ $customDomain->domain }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase {{ $customDomain->status->value === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
                                        {{ $customDomain->status->value }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Direct Bank Settlement Details -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Payout bank account') }}
                </h3>

                <div
                    class="p-4 rounded-2xl bg-slate-50 dark:bg-[#141821]/50 border border-slate-200/80 dark:border-[#1e2433] space-y-3 text-xs">
                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Bank') }}</span>
                        <span
                            class="font-bold text-slate-900 dark:text-white text-sm">{{ $operator->bank_provider ?? __('Not Set') }}</span>
                    </div>

                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Account Number') }}</span>
                        <span
                            class="font-mono font-bold text-[#FFEF4D] dark:text-[#FFEF4D] text-sm">{{ $operator->bank_account_number ?? '-' }}</span>
                    </div>

                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Name on the account') }}</span>
                        <span
                            class="font-bold text-slate-900 dark:text-white">{{ $operator->bank_account_name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Support Hours -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('WhatsApp Support & Schedule') }}
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('WhatsApp Number') }}</span>
                        <span
                            class="font-bold text-slate-900 dark:text-white">{{ $operator->contact_whatsapp ?? __('None') }}</span>
                    </div>

                    <div>
                        <span
                            class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Live Schedule Summary') }}</span>
                        <span
                            class="font-semibold text-[#FFEF4D] dark:text-[#FFEF4D]">{{ $operator->getWhatsAppScheduleSummary() }}</span>
                    </div>
                </div>
            </div>

            <!-- Storefront Capabilities & Features -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Storefront') }}
                </h3>

                @php
                    $storeSettings = $operator->settings['storefront'] ?? [];
                @endphp

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-600 dark:text-slate-400">{{ __('Standalone Selling:') }}</span>
                        <span
                            class="font-bold text-slate-900 dark:text-white">{{ $storeSettings['allow_standalone_products'] ?? true ? __('Enabled') : __('Disabled') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-slate-600 dark:text-slate-400">{{ __('Guest Reviews Display:') }}</span>
                        <span
                            class="font-bold text-slate-900 dark:text-white">{{ $storeSettings['show_reviews'] ?? true ? __('Enabled') : __('Hidden') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-slate-600 dark:text-slate-400">{{ __('How guests pay:') }}</span>
                        <span
                            class="font-bold text-slate-900 dark:text-white">{{ __('Through EMVI') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-modal name="confirm-complimentary-plan" :show="$confirming_plan_change" maxWidth="md">
        <div class="p-6 space-y-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 flex items-center justify-center mx-auto text-lg">
                <i class="fa-solid fa-gift"></i>
            </div>

            <div class="space-y-1.5 text-center">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ __('Confirm plan change') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    {{ __('Review the complimentary grant before it applies.') }}
                </p>
            </div>

            <dl class="rounded-2xl border border-slate-200/80 dark:border-[#1e2433] bg-slate-50 dark:bg-[#141821] divide-y divide-slate-200/80 dark:divide-[#1e2433] text-left text-xs">
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('Operator') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white text-right">{{ $operator->name }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('From') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white text-right">{{ $operator->getPlan()->name }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('To') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white text-right">{{ $this->pendingComplimentaryPlan?->name ?? __('this plan') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('Charge') }}</dt>
                    <dd class="font-bold text-emerald-700 dark:text-emerald-300 text-right">{{ __('Rp 0 — complimentary') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('Term') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white text-right">
                        {{ $this->pendingComplimentaryPlan?->isFree() ? __('No end date') : $this->complimentaryTermLabel() }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                    <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ __('Auto-renew') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white text-right">{{ __('Off') }}</dd>
                </div>
            </dl>

            @if ($this->pendingComplimentaryPlan && ! $this->pendingComplimentaryPlan->isFree())
                <div class="space-y-1.5">
                    <x-label for="complimentary_term" :value="__('How long?')" class="text-xs" />
                    <x-select
                        id="complimentary_term"
                        wire:model.live="complimentary_term"
                        :options="$this->complimentaryTermOptions()"
                    />
                </div>
            @endif

            <div class="flex items-center justify-center gap-3 pt-1">
                <x-button
                    type="button"
                    variant="secondary"
                    wire:click="cancelPlanChange"
                    class="font-semibold text-xs"
                >
                    {{ __('Cancel') }}
                </x-button>
                <x-button
                    type="button"
                    variant="primary"
                    wire:click="confirmComplimentaryPlan"
                    class="font-semibold text-xs shadow-xs"
                >
                    {{ __('Grant plan') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
