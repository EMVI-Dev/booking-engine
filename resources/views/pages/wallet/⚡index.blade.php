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

    public function mount(): void
    {
        $this->authorizeAbility('manageWallet');
    }

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
        $this->authorizeAbility('manageWallet');

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

<div class="space-y-6">
    <x-page-header
        :title="__('Wallet & Payouts')"
        :subtitle="__('Track gross booking earnings, automated escrow releases, platform commissions, and bank disbursements.')"
        icon="fa-wallet"
    >
        <x-slot:actions>
            <x-button :href="route('payments.edit')" variant="secondary" wire:navigate>
                <i class="fa-solid fa-building-columns text-xs"></i>
                <span>{{ __('Bank Settings') }}</span>
            </x-button>
            <x-button type="button" wire:click="openPayoutModal">
                <i class="fa-solid fa-arrow-up-from-bracket text-xs"></i>
                <span>{{ __('Request Payout') }}</span>
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <!-- Bank Account Status Banner -->
    @if ($this->currentOperator && !$this->currentOperator->hasValidBankAccount())
        <div
            class="p-4 rounded-[12px] bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-amber-900 dark:text-amber-200 shadow-none">
            <div class="flex items-center gap-3">
                <span
                    class="w-8 h-8 rounded-[8px] bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
                <div>
                    <h4 class="font-medium text-xs sm:text-sm">{{ __('Bank Account Details Incomplete') }}</h4>
                    <p class="text-[11px] text-amber-700 dark:text-amber-300 mt-0.5">
                        {{ __('To withdraw your earnings, configure your Indonesian bank destination (BCA, Mandiri, BRI, BNI, etc.).') }}
                    </p>
                </div>
            </div>
            <a href="{{ route('payments.edit') }}" wire:navigate
                class="h-8 px-3 inline-flex items-center gap-1.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-xs font-medium shrink-0 transition shadow-none">
                <span>{{ __('Add Bank Details') }}</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    @else
        <div
            class="p-3.5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 shadow-none">
            <div class="flex items-center gap-3">
                <span
                    class="w-8 h-8 rounded-[8px] bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 font-medium flex items-center justify-center text-xs shrink-0 shadow-none">
                    <i class="fa-solid fa-building-columns"></i>
                </span>
                <div class="text-xs">
                    <span
                        class="text-slate-500 dark:text-slate-400 font-medium">{{ __('Payout Bank Account:') }}</span>
                    <span class="font-medium text-[#12181E] dark:text-white ml-1">
                        {{ $this->currentOperator?->bank_provider }} &bull;
                        {{ $this->currentOperator?->bank_account_number }} (a/n
                        {{ $this->currentOperator?->bank_account_name }})
                    </span>
                </div>
            </div>
            <a href="{{ route('payments.edit') }}" wire:navigate
                class="text-[11px] font-medium text-slate-700 dark:text-slate-300 hover:text-black dark:hover:text-white transition">
                {{ __('Change Bank Account') }} &rarr;
            </a>
        </div>
    @endif

    <!-- Metrics Cards Grid (4 Columns) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <x-metric-card
            :label="__('Available Balance')"
            :value="'Rp ' . number_format($this->metrics['available'], 0, ',', '.')"
            :hint="__('Cleared funds ready for bank transfer')"
            icon="fa-money-bill-transfer"
            tone="featured"
        />

        <x-metric-card
            :label="__('In Escrow')"
            :value="'Rp ' . number_format($this->metrics['pending_escrow'], 0, ',', '.')"
            :hint="__('Unlocks on trip departure date')"
            icon="fa-lock"
            tone="warning"
        />

        <x-metric-card
            :label="__('Total Gross Sales')"
            :value="'Rp ' . number_format($this->metrics['gross_sales'], 0, ',', '.')"
            :hint="__('Platform Fees: Rp :fees', ['fees' => number_format($this->metrics['total_fees'], 0, ',', '.')])"
            icon="fa-chart-line"
            tone="info"
        />

        <x-metric-card
            :label="__('Lifetime Withdrawn')"
            :value="'Rp ' . number_format($this->metrics['lifetime_withdrawn'], 0, ',', '.')"
            :hint="__('Transferred to your bank account')"
            icon="fa-circle-check"
            tone="success"
        />
    </div>

    <!-- Main Navigation Tabs Bar & Filters -->
    <x-toolbar class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-filter-tabs>
            <x-filter-tab wire:click="switchTab('ledger')" :active="$activeTab === 'ledger'" icon="fa-list-check">
                {{ __('Earnings & Ledger') }}
            </x-filter-tab>
            <x-filter-tab wire:click="switchTab('payouts')" :active="$activeTab === 'payouts'" icon="fa-clock-rotate-left">
                {{ __('Payout Requests') }}
            </x-filter-tab>
        </x-filter-tabs>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <x-search-input
                class="w-full sm:w-64"
                wire:model.live.debounce.300ms="search"
                :placeholder="$activeTab === 'ledger' ? __('Search booking / guest...') : __('Search reference #...')"
            />

            <div class="w-full sm:w-48">
                @if ($activeTab === 'ledger')
                    <x-select wire:key="wallet-filter-ledger" wire:model.live="statusFilter" class="h-9 rounded-[6px] text-xs font-medium" :options="[
                        'all' => __('All Statuses'),
                        'cleared' => __('Cleared / Available'),
                        'pending_escrow' => __('Pending Escrow'),
                    ]" />
                @else
                    <x-select wire:key="wallet-filter-payouts" wire:model.live="statusFilter" class="h-9 rounded-[6px] text-xs font-medium" :options="[
                        'all' => __('All Statuses'),
                        'pending' => __('Pending Approval'),
                        'processing' => __('Processing'),
                        'completed' => __('Completed'),
                        'rejected' => __('Rejected'),
                    ]" />
                @endif
            </div>
        </div>
    </x-toolbar>

        <!-- TAB 1: LEDGER TRANSACTIONS -->
        @if ($activeTab === 'ledger')
            <!-- Mobile Ledger Card List (md:hidden) -->
            <div class="md:hidden space-y-3 transition-opacity duration-200" wire:loading.class="opacity-60">
                @forelse ($this->transactions as $trx)
                    <div
                        class="p-3.5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                {{ $trx->created_at?->format('d M Y, H:i') }}
                            </span>
                            @if ($trx->status->value === 'cleared')
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ __('Cleared') }}
                                </span>
                            @elseif ($trx->status->value === 'pending_escrow')
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800/40">
                                    <i class="fa-solid fa-lock text-[9px]"></i>
                                    {{ __('Escrow Held') }}
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-[#F8F9FA] text-[#5A6578] dark:bg-[#141821] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433]">
                                    {{ __('Cancelled') }}
                                </span>
                            @endif
                        </div>

                        <div class="space-y-0.5">
                            <p class="font-semibold text-xs text-[#12181E] dark:text-white truncate">
                                {{ $trx->description }}
                            </p>
                            <span class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2] uppercase font-medium">
                                {{ $trx->type->label() }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433] text-xs">
                            <span class="text-[#5A6578] dark:text-[#9DA4B2] text-[11px] font-medium">{{ __('Net Amount') }}</span>
                            <span
                                class="font-mono font-semibold {{ $trx->net_amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-[#12181E] dark:text-white' }}">
                                {{ $trx->net_amount >= 0 ? '+' : '' }}Rp
                                {{ number_format((float) $trx->net_amount, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-[#5A6578] dark:text-[#9DA4B2] rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433]">
                        {{ __('No ledger transactions yet') }}
                    </div>
                @endforelse
            </div>

            <!-- Desktop Ledger Table (hidden on mobile) -->
            <div class="hidden md:block overflow-hidden rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead>
                            <tr
                                class="bg-[#F8F9FA] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">
                                <th class="py-3 px-4">{{ __('Date / Time') }}</th>
                                <th class="py-3 px-4">{{ __('Description / Reference') }}</th>
                                <th class="py-3 px-4">{{ __('Gross Sales') }}</th>
                                <th class="py-3 px-4">{{ __('Platform Fee') }}</th>
                                <th class="py-3 px-4">{{ __('Net Amount') }}</th>
                                <th class="py-3 px-4">{{ __('Escrow Release / Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                            @forelse ($this->transactions as $trx)
                                <tr class="hover:bg-[#F8F9FA] dark:hover:bg-[#141821]/60 transition">
                                    <td class="py-3 px-4 whitespace-nowrap text-[#5A6578] dark:text-[#9DA4B2]">
                                        {{ $trx->created_at?->format('d M Y, H:i') }}
                                    </td>

                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2.5 min-w-[200px]">
                                            @if ($trx->type->value === 'booking_earning')
                                                <span
                                                    class="w-7 h-7 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40 flex items-center justify-center text-xs shrink-0">
                                                    <i class="fa-solid fa-arrow-down"></i>
                                                </span>
                                            @elseif ($trx->type->value === 'payout_withdrawal')
                                                <span
                                                    class="w-7 h-7 rounded-[6px] bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 flex items-center justify-center text-xs shrink-0 font-medium">
                                                    <i class="fa-solid fa-arrow-up"></i>
                                                </span>
                                            @else
                                                <span
                                                    class="w-7 h-7 rounded-[6px] bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/40 flex items-center justify-center text-xs shrink-0">
                                                    <i class="fa-solid fa-rotate"></i>
                                                </span>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="font-semibold text-xs text-[#12181E] dark:text-white truncate">
                                                    {{ Str::limit($trx->description, 40) }}
                                                </p>
                                                <span class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2] uppercase font-medium">
                                                    {{ $trx->type->label() }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap font-medium text-[#12181E] dark:text-[#9DA4B2]">
                                        @if ($trx->gross_amount > 0)
                                            Rp {{ number_format((float) $trx->gross_amount, 0, ',', '.') }}
                                        @else
                                            <span class="text-[#5A6578] dark:text-[#9DA4B2]">&mdash;</span>
                                        @endif
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap font-medium text-rose-600 dark:text-rose-400">
                                        @if ($trx->fee_amount > 0)
                                            -Rp {{ number_format((float) $trx->fee_amount, 0, ',', '.') }}
                                        @else
                                            <span class="text-[#5A6578] dark:text-[#9DA4B2]">&mdash;</span>
                                        @endif
                                    </td>

                                    <td
                                        class="py-3 px-4 whitespace-nowrap font-mono font-semibold {{ $trx->net_amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-[#12181E] dark:text-white' }}">
                                        {{ $trx->net_amount >= 0 ? '+' : '' }}Rp
                                        {{ number_format((float) $trx->net_amount, 0, ',', '.') }}
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if ($trx->status->value === 'cleared')
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40">
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                {{ __('Cleared / Available') }}
                                            </span>
                                        @elseif ($trx->status->value === 'pending_escrow')
                                            <div class="flex flex-col">
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800/40 w-fit">
                                                    <i class="fa-solid fa-lock text-[9px]"></i>
                                                    {{ __('Escrow Held') }}
                                                </span>
                                                <span class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                                                    {{ __('Release: :date', ['date' => $trx->available_at?->format('d M Y') ?? 'Departure']) }}
                                                </span>
                                            </div>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-[#F8F9FA] text-[#5A6578] dark:bg-[#141821] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433]">
                                                {{ __('Cancelled') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-[#5A6578] dark:text-[#9DA4B2]">
                                        <i class="fa-solid fa-receipt text-3xl mb-2 block opacity-40"></i>
                                        <p class="font-semibold text-sm text-[#12181E] dark:text-white">
                                            {{ __('No ledger transactions yet') }}</p>
                                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                                            {{ __('Paid guest reservations on your storefront will automatically appear here.') }}
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($this->transactions->hasPages())
                <div class="pt-2">
                    {{ $this->transactions->links() }}
                </div>
            @endif
        @endif

        <!-- TAB 2: PAYOUT REQUESTS HISTORY -->
        @if ($activeTab === 'payouts')
            <!-- Mobile Payouts Card List (md:hidden) -->
            <div class="md:hidden space-y-3 transition-opacity duration-200" wire:loading.class="opacity-60">
                @forelse ($this->payoutRequests as $payout)
                    <div
                        class="p-3.5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono font-semibold text-xs text-[#12181E] dark:text-white">
                                {{ $payout->reference_number }}
                            </span>
                            @if ($payout->status->value === 'approved' || $payout->status->value === 'completed')
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40">Paid</span>
                            @elseif ($payout->status->value === 'pending')
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800/40">Pending</span>
                            @else
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800/40">{{ ucfirst($payout->status->value) }}</span>
                            @endif
                        </div>

                        <div
                            class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <div>
                                <span
                                    class="text-[10px] uppercase font-semibold text-[#5A6578] dark:text-[#9DA4B2] block">{{ __('Destination Bank') }}</span>
                                <span
                                    class="font-medium text-xs text-[#12181E] dark:text-white block truncate">
                                    {{ $payout->bank_provider }} &bull; {{ $payout->bank_account_number }}
                                </span>
                                <span class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2] block">
                                    a/n {{ $payout->bank_account_name }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span
                                    class="text-[10px] uppercase font-semibold text-[#5A6578] dark:text-[#9DA4B2] block">{{ __('Payout Amount') }}</span>
                                <span class="font-mono font-semibold text-xs text-[#12181E] dark:text-white block">Rp
                                    {{ number_format((float) $payout->amount, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433] text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                            <span>{{ $payout->created_at?->format('d M Y, H:i') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-[#5A6578] dark:text-[#9DA4B2] rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433]">
                        {{ __('No payout requests found') }}
                    </div>
                @endforelse
            </div>

            <!-- Desktop Payouts Table (hidden on mobile) -->
            <div class="hidden md:block overflow-hidden rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead>
                            <tr
                                class="bg-[#F8F9FA] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">
                                <th class="py-3 px-4">{{ __('Reference #') }}</th>
                                <th class="py-3 px-4">{{ __('Requested Date') }}</th>
                                <th class="py-3 px-4">{{ __('Amount') }}</th>
                                <th class="py-3 px-4">{{ __('Destination Bank') }}</th>
                                <th class="py-3 px-4">{{ __('Status') }}</th>
                                <th class="py-3 px-4">{{ __('Proof / Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                            @forelse ($this->payoutRequests as $payout)
                                <tr class="hover:bg-[#F8F9FA] dark:hover:bg-[#141821]/60 transition">
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span
                                            class="font-mono text-[11px] font-medium text-[#12181E] dark:text-white px-2 py-0.5 rounded-[4px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                                            #{{ $payout->reference_number }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap text-[#5A6578] dark:text-[#9DA4B2]">
                                        {{ $payout->created_at?->format('d M Y, H:i') }}
                                    </td>

                                    <td
                                        class="py-3 px-4 whitespace-nowrap font-mono font-semibold text-sm text-[#12181E] dark:text-white">
                                        Rp {{ number_format((float) $payout->amount, 0, ',', '.') }}
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="font-medium text-xs text-[#12181E] dark:text-white">
                                            {{ $payout->bank_provider }} &bull; {{ $payout->bank_account_number }}
                                        </div>
                                        <div class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2]">
                                            a/n {{ $payout->bank_account_name }}
                                        </div>
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if ($payout->status->value === 'completed')
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40">
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                {{ __('Completed / Paid') }}
                                            </span>
                                        @elseif ($payout->status->value === 'processing')
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800/40">
                                                <i class="fa-solid fa-spinner fa-spin text-[10px]"></i>
                                                {{ __('Processing Transfer') }}
                                            </span>
                                        @elseif ($payout->status->value === 'rejected')
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800/40"
                                                title="{{ $payout->rejection_reason }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                {{ __('Rejected') }}
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800/40">
                                                <i class="fa-solid fa-clock text-[10px]"></i>
                                                {{ __('Pending Approval') }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="py-3 px-4 text-[#5A6578] dark:text-[#9DA4B2]">
                                        @if ($payout->proof_document_path)
                                            <a href="{{ $payout->proof_document_url }}" target="_blank"
                                                class="inline-flex items-center gap-1.5 text-xs font-medium text-[#12181E] dark:text-[#FFEF4D] hover:underline">
                                                <i
                                                    class="fa-solid fa-file-invoice text-xs text-amber-600 dark:text-[#FFEF4D]"></i>
                                                <span>{{ __('Transfer Receipt') }}</span>
                                            </a>
                                        @elseif ($payout->rejection_reason)
                                            <span
                                                class="text-xs text-rose-600 font-medium truncate block max-w-[200px]"
                                                title="{{ $payout->rejection_reason }}">
                                                {{ $payout->rejection_reason }}
                                            </span>
                                        @else
                                            <span class="text-[#5A6578] dark:text-[#9DA4B2]">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-[#5A6578] dark:text-[#9DA4B2]">
                                        <i class="fa-solid fa-arrow-up-from-bracket text-3xl mb-2 block opacity-40"></i>
                                        <p class="font-semibold text-sm text-[#12181E] dark:text-white">
                                            {{ __('No payout requests submitted yet') }}</p>
                                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                                            {{ __('When you have available balance, click "Request Payout" to initiate a bank transfer.') }}
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($this->payoutRequests->hasPages())
                <div class="pt-2">
                    {{ $this->payoutRequests->links() }}
                </div>
            @endif
        @endif

    <!-- Payout Request Slide-Over / Modal -->
    @if ($showPayoutModal)
        @teleport('body')
            <div
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-xs overflow-y-auto">
                <div
                    class="w-full max-w-lg rounded-t-[16px] sm:rounded-[12px] bg-white dark:bg-[#10141d] shadow-none border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col max-h-[90vh]">
                    <!-- Drag Handle for Mobile -->
                    <div class="mx-auto my-2 h-1 w-10 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                    <!-- Modal Header -->
                    <div
                        class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="w-8 h-8 rounded-[8px] bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 flex items-center justify-center text-xs shrink-0 font-bold">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            </div>
                            <div class="min-w-0">
                                <h3
                                    class="font-semibold text-sm sm:text-base text-[#12181E] dark:text-white leading-tight truncate">
                                    {{ __('Request Bank Payout') }}
                                </h3>
                                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                    {{ __('Transfer cleared funds directly to your registered bank account.') }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="$set('showPayoutModal', false)"
                            class="p-1.5 rounded-[6px] text-[#5A6578] hover:text-[#12181E] dark:text-[#9DA4B2] dark:hover:text-white hover:bg-[#F8F9FA] dark:hover:bg-[#141821] transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <div class="p-4 sm:p-5 space-y-4 overflow-y-auto">
                        <!-- Destination Summary Card -->
                        <div
                            class="p-3.5 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-1">
                            <span
                                class="text-[10px] uppercase font-semibold text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Destination Bank Account') }}</span>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-xs sm:text-sm text-[#12181E] dark:text-white">
                                        {{ $this->currentOperator?->bank_provider }} &bull;
                                        {{ $this->currentOperator?->bank_account_number }}
                                    </p>
                                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                        a/n {{ $this->currentOperator?->bank_account_name }}
                                    </p>
                                </div>
                                <a href="{{ route('payments.edit') }}" wire:navigate
                                    class="text-xs font-medium text-[#12181E] dark:text-[#FFEF4D] hover:underline">
                                    {{ __('Edit') }}
                                </a>
                            </div>
                        </div>

                        <!-- Form -->
                        <form wire:submit="submitPayoutRequest" class="space-y-4">
                            <!-- Amount Input -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <x-label for="payoutAmount" :value="__('Withdrawal Amount (IDR)')" required />
                                    <span class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                        {{ __('Available:') }} <strong class="text-[#12181E] dark:text-white">Rp
                                            {{ number_format($this->metrics['available'], 0, ',', '.') }}</strong>
                                    </span>
                                </div>

                                <div class="relative">
                                    <span
                                        class="absolute left-3 top-1/2 -translate-y-1/2 font-semibold text-xs text-[#5A6578] dark:text-[#9DA4B2]">Rp</span>
                                    <x-input id="payoutAmount" type="number" step="1000" min="50000"
                                        wire:model="payoutAmount" placeholder="500000" class="pl-9 font-mono font-medium rounded-[6px]" />
                                </div>

                                <!-- Quick Fill Chips -->
                                <div class="flex items-center gap-1.5 pt-1">
                                    <span
                                        class="text-[10px] uppercase font-semibold text-[#5A6578] dark:text-[#9DA4B2] mr-1">{{ __('Quick Fill:') }}</span>
                                    <button type="button" wire:click="quickFillAmount(25)"
                                        class="px-2.5 py-1 rounded-[4px] text-xs font-medium bg-[#F8F9FA] dark:bg-[#141821] hover:bg-[#E4E5E9] dark:hover:bg-[#1E2433] text-[#12181E] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer">
                                        25%
                                    </button>
                                    <button type="button" wire:click="quickFillAmount(50)"
                                        class="px-2.5 py-1 rounded-[4px] text-xs font-medium bg-[#F8F9FA] dark:bg-[#141821] hover:bg-[#E4E5E9] dark:hover:bg-[#1E2433] text-[#12181E] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer">
                                        50%
                                    </button>
                                    <button type="button" wire:click="quickFillAmount(100)"
                                        class="px-2.5 py-1 rounded-[4px] text-xs font-medium bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] transition cursor-pointer shadow-none">
                                        100% (Max)
                                    </button>
                                </div>
                                <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                                    {{ __('Payouts of Rp 500.000 or more have no transfer fee. Smaller payouts include Rp 2.500.') }}
                                </p>
                                <x-input-error :messages="$errors->get('payoutAmount')" />
                                <x-input-error :messages="$errors->get('bank')" />
                                <x-input-error :messages="$errors->get('amount')" />
                            </div>

                            <!-- Notes Input -->
                            <div class="space-y-1.5">
                                <x-label for="payoutNotes" :value="__('Optional Notes / Transfer Reference')" />
                                <x-textarea id="payoutNotes" wire:model="payoutNotes" rows="2" class="rounded-[6px]"
                                    placeholder="e.g. Monthly crew operations disbursement..." />
                            </div>

                            <!-- Actions -->
                            <div
                                class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                                <x-button type="button" variant="secondary" wire:click="$set('showPayoutModal', false)"
                                    class="text-xs font-medium rounded-[6px]">
                                    {{ __('Cancel') }}
                                </x-button>
                                <x-button type="submit" variant="primary" class="text-xs font-medium rounded-[6px] !bg-[#FFEF4D] !text-[#12181E] hover:!bg-[#F3E13A]">
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
