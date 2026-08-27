<?php

use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionStatus;
use App\Models\Operator;
use App\Models\PayoutRequest;
use App\Models\WalletTransaction;
use App\Concerns\ResolvesCurrentOperator;
use App\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Wallet & Payouts')] class extends Component {
    use WithPagination;
    use ResolvesCurrentOperator;

    public string $activeTab = 'ledger'; // 'ledger' or 'payouts'
    public string $statusFilter = 'all';
    public string $search = '';

    // Payout Request Modal
    public bool $showPayoutModal = false;
    public string $payoutAmount = '';
    public string $payoutNotes = '';

    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Get wallet summary statistics.
     *
     * @return array{
     *     available: float,
     *     pending_escrow: float,
     *     gross_sales: float,
     *     lifetime_withdrawn: float,
     *     total_fees: float
     * }
     */
    #[Computed]
    public function metrics(): array
    {
        $agent = $this->currentOperator;
        if (!$agent) {
            return [
                'available' => 0.0,
                'pending_escrow' => 0.0,
                'gross_sales' => 0.0,
                'lifetime_withdrawn' => 0.0,
                'total_fees' => 0.0,
            ];
        }

        $totalFees = (float) $agent->walletTransactions()->where('status', '!=', WalletTransactionStatus::Cancelled)->sum('fee_amount');

        return [
            'available' => $agent->getAvailableBalance(),
            'pending_escrow' => $agent->getPendingEscrowBalance(),
            'gross_sales' => $agent->getTotalGrossSales(),
            'lifetime_withdrawn' => $agent->getTotalLifetimeWithdrawn(),
            'total_fees' => $totalFees,
        ];
    }

    /**
     * Get paginated ledger transactions.
     */
    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        $agent = $this->currentOperator;
        if (!$agent) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        return $agent
            ->walletTransactions()
            ->with(['reservation.bookable', 'payoutRequest'])
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', "%{$this->search}%")->orWhereHas('reservation', function ($resQuery) {
                        $resQuery->where('code', 'like', "%{$this->search}%")->orWhere('guest_name', 'like', "%{$this->search}%");
                    });
                });
            })
            ->latest()
            ->paginate(15);
    }

    /**
     * Get paginated payout withdrawal requests.
     */
    #[Computed]
    public function payoutRequests(): LengthAwarePaginator
    {
        $agent = $this->currentOperator;
        if (!$agent) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        return $agent
            ->payoutRequests()
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when(filled($this->search), function ($query) {
                $query->where('reference_number', 'like', "%{$this->search}%");
            })
            ->latest()
            ->paginate(15);
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->statusFilter = 'all';
        $this->search = '';
        $this->resetPage();
    }

    public function openPayoutModal(): void
    {
        $agent = $this->currentOperator;
        if (!$agent) {
            return;
        }

        $available = $agent->getAvailableBalance();
        $this->payoutAmount = $available > 0 ? (string) floor($available) : '';
        $this->payoutNotes = '';
        $this->resetErrorBag();
        $this->showPayoutModal = true;
    }

    public function quickFillAmount(int $percent): void
    {
        $agent = $this->currentOperator;
        if (!$agent) {
            return;
        }

        $available = $agent->getAvailableBalance();
        if ($available <= 0) {
            $this->payoutAmount = '0';
            return;
        }

        $calculated = floor(($available * $percent) / 100);
        $this->payoutAmount = (string) max(0, $calculated);
    }

    public function submitPayoutRequest(WalletService $walletService): void
    {
        $agent = $this->currentOperator;
        if (!$agent) {
            return;
        }

        $this->validate(
            [
                'payoutAmount' => ['required', 'numeric', 'min:50000'],
                'payoutNotes' => ['nullable', 'string', 'max:500'],
            ],
            [
                'payoutAmount.min' => __('Minimum payout withdrawal request is Rp 50.000.'),
            ],
        );

        try {
            $walletService->createPayoutRequest($agent, (float) $this->payoutAmount, $this->payoutNotes ?: null);

            $this->showPayoutModal = false;
            $this->activeTab = 'payouts';
            $this->reset(['payoutAmount', 'payoutNotes']);
            $this->dispatch('payout-requested');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }
}; ?>

<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400">
                    <i class="fa-solid fa-wallet text-lg"></i>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Wallet & Payouts') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Track gross booking earnings, automated escrow releases, platform commissions, and bank disbursements.') }}
            </p>
        </div>

        <!-- Header Actions -->
        <div class="flex items-center gap-2.5">
            <a href="{{ route('payments.edit') }}" wire:navigate
                class="h-10 px-3.5 inline-flex items-center gap-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:border-slate-300 dark:hover:border-zinc-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition shadow-2xs">
                <i class="fa-solid fa-building-columns text-slate-400 text-xs"></i>
                <span>{{ __('Bank Settings') }}</span>
            </a>

            <button type="button" wire:click="openPayoutModal"
                class="h-10 px-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold shadow-sm shadow-indigo-500/20 transition-all hover:scale-102 cursor-pointer">
                <i class="fa-solid fa-arrow-up-from-bracket text-xs"></i>
                <span>{{ __('Request Payout') }}</span>
            </button>
        </div>
    </div>

    <!-- Bank Account Status Banner -->
    @if ($this->currentOperator && !$this->currentOperator->hasValidBankAccount())
        <div
            class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-900/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-amber-900 dark:text-amber-200">
            <div class="flex items-center gap-3">
                <span
                    class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-400 shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
                <div>
                    <h4 class="font-bold text-xs sm:text-sm">{{ __('Bank Account Details Incomplete') }}</h4>
                    <p class="text-[11px] text-amber-700 dark:text-amber-300 mt-0.5">
                        {{ __('To withdraw your earnings, configure your Indonesian bank destination (BCA, Mandiri, BRI, BNI, etc.).') }}
                    </p>
                </div>
            </div>
            <a href="{{ route('payments.edit') }}" wire:navigate
                class="h-8 px-3 inline-flex items-center gap-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shrink-0 transition shadow-xs">
                <span>{{ __('Add Bank Details') }}</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    @else
        <div
            class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-900/70 border border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5">
            <div class="flex items-center gap-3">
                <span
                    class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs shrink-0">
                    <i class="fa-solid fa-building-columns"></i>
                </span>
                <div class="text-xs">
                    <span
                        class="text-slate-400 dark:text-zinc-500 font-semibold">{{ __('Payout Bank Account:') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white ml-1">
                        {{ $this->currentOperator?->bank_provider }} &bull;
                        {{ $this->currentOperator?->bank_account_number }} (a/n
                        {{ $this->currentOperator?->bank_account_name }})
                    </span>
                </div>
            </div>
            <a href="{{ route('payments.edit') }}" wire:navigate
                class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                {{ __('Change Bank Account') }} &rarr;
            </a>
        </div>
    @endif

    <!-- Metrics Cards Grid (4 Columns) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Available Balance (Hero) -->
        <div
            class="relative overflow-hidden p-5 rounded-3xl bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-900 text-white border border-indigo-800/50 shadow-md flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-200 flex items-center gap-1.5">
                    <i class="fa-solid fa-money-bill-transfer text-indigo-400"></i>
                    {{ __('Available Balance') }}
                </span>
                <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    {{ __('Ready') }}
                </span>
            </div>
            <div>
                <p class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                    Rp {{ number_format($this->metrics['available'], 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-indigo-200/70 mt-1">
                    {{ __('Cleared funds ready for bank transfer') }}
                </p>
            </div>
            <button type="button" wire:click="openPayoutModal"
                class="w-full h-8 rounded-xl bg-white text-indigo-950 font-bold text-xs hover:bg-indigo-50 transition flex items-center justify-center gap-1.5 shadow-sm cursor-pointer">
                <span>{{ __('Withdraw Funds') }}</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </button>
        </div>

        <!-- Card 2: Pending Escrow -->
        <div
            class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-lock text-amber-500"></i>
                    {{ __('In Escrow') }}
                </span>
                <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900">
                    {{ __('Pending Departure') }}
                </span>
            </div>
            <div>
                <p class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    Rp {{ number_format($this->metrics['pending_escrow'], 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Unlocks automatically on trip departure date') }}
                </p>
            </div>
            <div
                class="pt-2 border-t border-slate-100 dark:border-zinc-800 text-[11px] text-slate-400 flex items-center justify-between">
                <span>{{ __('Protection') }}</span>
                <span class="font-bold text-slate-600 dark:text-slate-300">{{ __('Escrow Safe') }}</span>
            </div>
        </div>

        <!-- Card 3: Gross Booking Sales -->
        <div
            class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-chart-line text-emerald-500"></i>
                    {{ __('Total Gross Sales') }}
                </span>
                <span
                    class="p-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-xs">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </span>
            </div>
            <div>
                <p class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    Rp {{ number_format($this->metrics['gross_sales'], 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Cumulative paid bookings on your storefront') }}
                </p>
            </div>
            <div
                class="pt-2 border-t border-slate-100 dark:border-zinc-800 text-[11px] text-slate-400 flex items-center justify-between">
                <span>{{ __('Platform Fees Deducted') }}</span>
                <span class="font-bold text-slate-600 dark:text-slate-300">Rp
                    {{ number_format($this->metrics['total_fees'], 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- Card 4: Lifetime Withdrawn -->
        <div
            class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check text-sky-500"></i>
                    {{ __('Lifetime Withdrawn') }}
                </span>
                <span class="p-1 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 text-xs">
                    <i class="fa-solid fa-receipt"></i>
                </span>
            </div>
            <div>
                <p class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    Rp {{ number_format($this->metrics['lifetime_withdrawn'], 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Transferred to your bank account') }}
                </p>
            </div>
            <div
                class="pt-2 border-t border-slate-100 dark:border-zinc-800 text-[11px] text-slate-400 flex items-center justify-between">
                <span>{{ __('Disbursement Rails') }}</span>
                <span class="font-bold text-slate-600 dark:text-slate-300">{{ __('DOKU / Central Bank') }}</span>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs Bar & Filters -->
    <div
        class="p-4 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
        <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-zinc-800">
            <!-- Tabs Switcher -->
            <div class="flex items-center gap-2">
                <button type="button" wire:click="switchTab('ledger')"
                    class="h-10 px-4 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $activeTab === 'ledger' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-zinc-700' }}">
                    <i class="fa-solid fa-list-check text-xs"></i>
                    <span>{{ __('Earnings & Ledger') }}</span>
                </button>

                <button type="button" wire:click="switchTab('payouts')"
                    class="h-10 px-4 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $activeTab === 'payouts' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-zinc-700' }}">
                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                    <span>{{ __('Payout Requests') }}</span>
                </button>
            </div>

            <!-- Search & Filters -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                <div class="relative flex-1 sm:w-64">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="{{ $activeTab === 'ledger' ? __('Search booking / guest...') : __('Search reference #...') }}"
                        class="h-10 w-full pl-9 pr-4 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" />
                </div>

                <div class="w-full sm:w-52">
                    @if ($activeTab === 'ledger')
                        <x-select wire:key="wallet-filter-ledger" wire:model.live="statusFilter" :options="[
                            'all' => __('All Statuses'),
                            'cleared' => __('Cleared / Available'),
                            'pending_escrow' => __('Pending Escrow'),
                        ]" />
                    @else
                        <x-select wire:key="wallet-filter-payouts" wire:model.live="statusFilter" :options="[
                            'all' => __('All Statuses'),
                            'pending' => __('Pending Approval'),
                            'processing' => __('Processing'),
                            'completed' => __('Completed'),
                            'rejected' => __('Rejected'),
                        ]" />
                    @endif
                </div>
            </div>
        </div>

        <!-- TAB 1: LEDGER TRANSACTIONS -->
        @if ($activeTab === 'ledger')
            <!-- Mobile Ledger Card List (md:hidden) -->
            <div class="md:hidden space-y-3 transition-opacity duration-200" wire:loading.class="opacity-60">
                @forelse ($this->transactions as $trx)
                    <div
                        class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-slate-500 font-medium">
                                {{ $trx->created_at?->format('d M Y, H:i') }}
                            </span>
                            @if ($trx->status->value === 'cleared')
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                                    {{ __('Cleared') }}
                                </span>
                            @elseif ($trx->status->value === 'pending_escrow')
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300">
                                    {{ __('Escrow Held') }}
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                    {{ __('Cancelled') }}
                                </span>
                            @endif
                        </div>

                        <div class="space-y-1">
                            <p class="font-bold text-xs text-slate-900 dark:text-white truncate">
                                {{ $trx->description }}
                            </p>
                            <span class="text-[10px] text-slate-400 uppercase font-semibold">
                                {{ $trx->type->label() }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800 text-xs">
                            <span class="text-slate-400 text-[10px] uppercase font-bold">{{ __('Net Amount') }}</span>
                            <span
                                class="font-mono font-black {{ $trx->net_amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white' }}">
                                {{ $trx->net_amount >= 0 ? '+' : '' }}Rp
                                {{ number_format((float) $trx->net_amount, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">
                        {{ __('No ledger transactions yet') }}
                    </div>
                @endforelse
            </div>

            <!-- Desktop Ledger Table (hidden on mobile) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-100 dark:border-zinc-800 text-[11px] uppercase font-bold text-slate-400 tracking-wider">
                            <th class="pb-3 px-3">{{ __('Date / Time') }}</th>
                            <th class="pb-3 px-3">{{ __('Description / Reference') }}</th>
                            <th class="pb-3 px-3">{{ __('Gross Sales') }}</th>
                            <th class="pb-3 px-3">{{ __('Platform Fee') }}</th>
                            <th class="pb-3 px-3">{{ __('Net Amount') }}</th>
                            <th class="pb-3 px-3">{{ __('Escrow Release / Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60">
                        @forelse ($this->transactions as $trx)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="py-3 px-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ $trx->created_at?->format('d M Y, H:i') }}
                                </td>

                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-2.5 min-w-[200px]">
                                        @if ($trx->type->value === 'booking_earning')
                                            <span
                                                class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs shrink-0">
                                                <i class="fa-solid fa-arrow-down"></i>
                                            </span>
                                        @elseif ($trx->type->value === 'payout_withdrawal')
                                            <span
                                                class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs shrink-0">
                                                <i class="fa-solid fa-arrow-up"></i>
                                            </span>
                                        @else
                                            <span
                                                class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs shrink-0">
                                                <i class="fa-solid fa-rotate"></i>
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-900 dark:text-white truncate">
                                                {{ $trx->description }}
                                            </p>
                                            <span class="text-[10px] text-slate-400 uppercase font-semibold">
                                                {{ $trx->type->label() }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap font-medium text-slate-600 dark:text-slate-300">
                                    @if ($trx->gross_amount > 0)
                                        Rp {{ number_format((float) $trx->gross_amount, 0, ',', '.') }}
                                    @else
                                        <span class="text-slate-400">&mdash;</span>
                                    @endif
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap font-medium text-rose-500 dark:text-rose-400">
                                    @if ($trx->fee_amount > 0)
                                        -Rp {{ number_format((float) $trx->fee_amount, 0, ',', '.') }}
                                    @else
                                        <span class="text-slate-400">&mdash;</span>
                                    @endif
                                </td>

                                <td
                                    class="py-3 px-3 whitespace-nowrap font-bold {{ $trx->net_amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ $trx->net_amount >= 0 ? '+' : '' }}Rp
                                    {{ number_format((float) $trx->net_amount, 0, ',', '.') }}
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if ($trx->status->value === 'cleared')
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            {{ __('Cleared / Available') }}
                                        </span>
                                    @elseif ($trx->status->value === 'pending_escrow')
                                        <div class="flex flex-col">
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 w-fit">
                                                <i class="fa-solid fa-lock text-[9px]"></i>
                                                {{ __('Escrow Held') }}
                                            </span>
                                            <span class="text-[10px] text-slate-400 mt-0.5">
                                                {{ __('Release: :date', ['date' => $trx->available_at?->format('d M Y') ?? 'Departure']) }}
                                            </span>
                                        </div>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400">
                                            {{ __('Cancelled') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-receipt text-3xl mb-2 block opacity-40"></i>
                                    <p class="font-bold text-sm text-slate-600 dark:text-slate-300">
                                        {{ __('No ledger transactions yet') }}</p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ __('Paid guest reservations on your storefront will automatically appear here.') }}
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800">
                {{ $this->transactions->links() }}
            </div>
        @endif

        <!-- TAB 2: PAYOUT REQUESTS HISTORY -->
        @if ($activeTab === 'payouts')
            <!-- Mobile Payouts Card List (md:hidden) -->
            <div class="md:hidden space-y-3 transition-opacity duration-200" wire:loading.class="opacity-60">
                @forelse ($this->payoutRequests as $payout)
                    <div
                        class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono font-extrabold text-xs text-slate-900 dark:text-white">
                                {{ $payout->reference_number }}
                            </span>
                            @if ($payout->status->value === 'approved' || $payout->status->value === 'completed')
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Paid</span>
                            @elseif ($payout->status->value === 'pending')
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">Pending</span>
                            @else
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">{{ ucfirst($payout->status->value) }}</span>
                            @endif
                        </div>

                        <div
                            class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100 dark:border-zinc-800">
                            <div>
                                <span
                                    class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Destination Bank') }}</span>
                                <span
                                    class="font-bold text-slate-800 dark:text-slate-200 block truncate">{{ $payout->bank_name }}
                                    ({{ $payout->account_number }})
                                </span>
                            </div>
                            <div class="text-right">
                                <span
                                    class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Payout Amount') }}</span>
                                <span class="font-mono font-black text-slate-900 dark:text-white block">Rp
                                    {{ number_format((float) $payout->amount, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800 text-[11px] text-slate-500">
                            <span>{{ $payout->created_at?->format('d M Y, H:i') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">
                        {{ __('No payout requests found') }}
                    </div>
                @endforelse
            </div>

            <!-- Desktop Payouts Table (hidden on mobile) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-100 dark:border-zinc-800 text-[11px] uppercase font-bold text-slate-400 tracking-wider">
                            <th class="pb-3 px-3">{{ __('Reference #') }}</th>
                            <th class="pb-3 px-3">{{ __('Requested Date') }}</th>
                            <th class="pb-3 px-3">{{ __('Amount') }}</th>
                            <th class="pb-3 px-3">{{ __('Destination Bank') }}</th>
                            <th class="pb-3 px-3">{{ __('Status') }}</th>
                            <th class="pb-3 px-3">{{ __('Proof / Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60">
                        @forelse ($this->payoutRequests as $payout)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors">
                                <td
                                    class="py-3 px-3 whitespace-nowrap font-mono font-bold text-slate-900 dark:text-white">
                                    {{ $payout->reference_number }}
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ $payout->created_at?->format('d M Y, H:i') }}
                                </td>

                                <td
                                    class="py-3 px-3 whitespace-nowrap font-extrabold text-sm text-slate-900 dark:text-white">
                                    Rp {{ number_format((float) $payout->amount, 0, ',', '.') }}
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ $payout->bank_provider }} &bull; {{ $payout->bank_account_number }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        a/n {{ $payout->bank_account_name }}
                                    </div>
                                </td>

                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if ($payout->status->value === 'completed')
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            <i class="fa-solid fa-circle-check text-[11px]"></i>
                                            {{ __('Completed / Paid') }}
                                        </span>
                                    @elseif ($payout->status->value === 'processing')
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                            <i class="fa-solid fa-spinner fa-spin text-[10px]"></i>
                                            {{ __('Processing Transfer') }}
                                        </span>
                                    @elseif ($payout->status->value === 'rejected')
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800"
                                            title="{{ $payout->rejection_reason }}">
                                            <i class="fa-solid fa-circle-xmark text-[11px]"></i>
                                            {{ __('Rejected') }}
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            <i class="fa-solid fa-clock text-[10px]"></i>
                                            {{ __('Pending Approval') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="py-3 px-3 text-slate-500 dark:text-slate-400">
                                    @if ($payout->proof_document_path)
                                        <a href="{{ Storage::url($payout->proof_document_path) }}" target="_blank"
                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                            <i class="fa-solid fa-file-invoice text-xs"></i>
                                            <span>{{ __('Transfer Receipt') }}</span>
                                        </a>
                                    @elseif ($payout->rejection_reason)
                                        <span
                                            class="text-[11px] text-rose-500 font-semibold truncate block max-w-[200px]"
                                            title="{{ $payout->rejection_reason }}">
                                            {{ $payout->rejection_reason }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-arrow-up-from-bracket text-3xl mb-2 block opacity-40"></i>
                                    <p class="font-bold text-sm text-slate-600 dark:text-slate-300">
                                        {{ __('No payout requests submitted yet') }}</p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ __('When you have available balance, click "Request Payout" to initiate a bank transfer.') }}
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800">
                {{ $this->payoutRequests->links() }}
            </div>
        @endif
    </div>

    <!-- Payout Request Slide-Over / Modal -->
    @if ($showPayoutModal)
        @teleport('body')
            <div
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div
                    class="w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl border border-slate-200/80 dark:border-zinc-800 flex flex-col my-8">
                    <!-- Modal Header -->
                    <div
                        class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div
                                class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-700 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3
                                    class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ __('Request Bank Payout') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __('Transfer cleared funds directly to your registered bank account.') }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="$set('showPayoutModal', false)"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-5">
                        <!-- Destination Summary Card -->
                        <div
                            class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 space-y-1.5">
                            <span
                                class="text-[10px] uppercase font-bold tracking-wider text-slate-400">{{ __('Destination Bank Account') }}</span>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-extrabold text-sm text-slate-900 dark:text-white">
                                        {{ $this->currentOperator?->bank_provider }} &bull;
                                        {{ $this->currentOperator?->bank_account_number }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        a/n {{ $this->currentOperator?->bank_account_name }}
                                    </p>
                                </div>
                                <a href="{{ route('payments.edit') }}" wire:navigate
                                    class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ __('Edit') }}
                                </a>
                            </div>
                        </div>

                        <!-- Form -->
                        <form wire:submit="submitPayoutRequest" class="space-y-4">
                            <!-- Amount Input -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <x-label for="payoutAmount" :value="__('Withdrawal Amount (IDR)')" required />
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Available:') }} <strong class="text-slate-900 dark:text-white">Rp
                                            {{ number_format($this->metrics['available'], 0, ',', '.') }}</strong>
                                    </span>
                                </div>

                                <div class="relative">
                                    <span
                                        class="absolute left-3.5 top-1/2 -translate-y-1/2 font-black text-xs text-slate-400">Rp</span>
                                    <x-input id="payoutAmount" type="number" step="1000" min="50000"
                                        wire:model="payoutAmount" placeholder="500000" class="pl-10 font-bold" />
                                </div>

                                <!-- Quick Fill Chips -->
                                <div class="flex items-center gap-1.5 mt-2">
                                    <span
                                        class="text-[10px] uppercase font-bold text-slate-400 mr-1">{{ __('Quick Fill:') }}</span>
                                    <button type="button" wire:click="quickFillAmount(25)"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                                        25%
                                    </button>
                                    <button type="button" wire:click="quickFillAmount(50)"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                                        50%
                                    </button>
                                    <button type="button" wire:click="quickFillAmount(100)"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/70 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 transition cursor-pointer">
                                        100% (Max)
                                    </button>
                                </div>
                                <x-input-error :messages="$errors->get('payoutAmount')" />
                                <x-input-error :messages="$errors->get('bank')" />
                                <x-input-error :messages="$errors->get('amount')" />
                            </div>

                            <!-- Notes Input -->
                            <div>
                                <x-label for="payoutNotes" :value="__('Optional Notes / Transfer Reference')" />
                                <x-textarea id="payoutNotes" wire:model="payoutNotes" rows="2"
                                    placeholder="e.g. Monthly crew operations disbursement..." />
                            </div>

                            <!-- Actions -->
                            <div
                                class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                                <x-button type="button" variant="secondary" wire:click="$set('showPayoutModal', false)"
                                    class="text-xs font-bold">
                                    {{ __('Cancel') }}
                                </x-button>
                                <x-button type="submit" variant="primary" class="text-xs font-bold">
                                    <i class="fa-solid fa-paper-plane mr-1.5 text-xs"></i>
                                    {{ __('Submit Payout Request') }}
                                </x-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
