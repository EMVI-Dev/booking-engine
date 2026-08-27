<?php

use App\Enums\PayoutStatus;
use App\Models\PayoutRequest;
use App\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Payout Requests')] #[Layout('layouts.admin')] class extends Component {
    use WithFileUploads, WithPagination;

    public string $statusFilter = 'all';
    public string $search = '';

    // Approve / Complete Modal
    public bool $showApproveModal = false;
    public ?string $selectedPayoutId = null;
    public $proofFile = null;

    // Reject Modal
    public bool $showRejectModal = false;
    public string $rejectionReason = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Get summary metrics for platform payouts.
     *
     * @return array{pending_count: int, pending_amount: float, completed_count: int, completed_amount: float}
     */
    #[Computed]
    public function metrics(): array
    {
        return [
            'pending_count' => PayoutRequest::where('status', PayoutStatus::Pending)->count(),
            'pending_amount' => (float) PayoutRequest::where('status', PayoutStatus::Pending)->sum('amount'),
            'completed_count' => PayoutRequest::where('status', PayoutStatus::Completed)->count(),
            'completed_amount' => (float) PayoutRequest::where('status', PayoutStatus::Completed)->sum('amount'),
        ];
    }

    /**
     * Get paginated payout requests across all operators.
     */
    #[Computed]
    public function payoutRequests(): LengthAwarePaginator
    {
        return PayoutRequest::query()
            ->with('agent')
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', "%{$this->search}%")
                        ->orWhere('bank_account_name', 'like', "%{$this->search}%")
                        ->orWhere('bank_account_number', 'like', "%{$this->search}%")
                        ->orWhereHas('agent', function ($agentQuery) {
                            $agentQuery->where('name', 'like', "%{$this->search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15);
    }

    public function openApproveModal(string $payoutId): void
    {
        $this->selectedPayoutId = $payoutId;
        $this->proofFile = null;
        $this->resetErrorBag();
        $this->showApproveModal = true;
    }

    public function openRejectModal(string $payoutId): void
    {
        $this->selectedPayoutId = $payoutId;
        $this->rejectionReason = '';
        $this->resetErrorBag();
        $this->showRejectModal = true;
    }

    public function approvePayout(WalletService $walletService): void
    {
        $payout = PayoutRequest::find($this->selectedPayoutId);
        if (! $payout || $payout->status !== PayoutStatus::Pending) {
            $this->showApproveModal = false;
            return;
        }

        $this->validate([
            'proofFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $proofPath = null;
        if ($this->proofFile) {
            $proofPath = $this->proofFile->store('payouts/proofs', 'public');
        }

        $walletService->approvePayout($payout, $proofPath, auth()->id());

        $this->showApproveModal = false;
        $this->selectedPayoutId = null;
        $this->proofFile = null;
        $this->dispatch('payout-approved');
    }

    public function disburseViaDokuApi(string $payoutId, \App\Services\DokuPaymentService $dokuService): void
    {
        $payout = PayoutRequest::find($payoutId);
        if (! $payout || $payout->status !== PayoutStatus::Pending) {
            return;
        }

        $res = $dokuService->disbursePayout($payout);
        if ($res['success']) {
            $payout->update([
                'status' => PayoutStatus::Completed,
                'processed_by' => 'DOKU BI-FAST API (Admin Trigger)',
                'processed_at' => now(),
                'notes' => ($payout->notes ? $payout->notes . ' | ' : '') . $res['message'] . ' [Ref: ' . $res['reference'] . ']',
            ]);
            session()->flash('success', __('Payout #:ref disbursed via DOKU BI-FAST API successfully!', ['ref' => $payout->reference_number]));
        } else {
            session()->flash('error', __('Failed to disburse payout via DOKU API. Please check account details.'));
        }
    }

    public function rejectPayout(WalletService $walletService): void
    {
        $payout = PayoutRequest::find($this->selectedPayoutId);
        if (! $payout || $payout->status !== PayoutStatus::Pending) {
            $this->showRejectModal = false;
            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $walletService->rejectPayout($payout, $this->rejectionReason, auth()->id());

        $this->showRejectModal = false;
        $this->selectedPayoutId = null;
        $this->rejectionReason = '';
        $this->dispatch('payout-rejected');
    }
}; ?>

<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-purple-100 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400">
                    <i class="fa-solid fa-money-bill-transfer text-lg"></i>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Automated DOKU Payout Audit & Settlement') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Automated DOKU BI-FAST disbursement logs, auto-transfer audits, and exception controls.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-2xl bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 font-bold text-xs border border-emerald-200 dark:border-emerald-800 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>{{ __('DOKU BI-FAST Auto-Disbursement: ACTIVE') }}</span>
            </span>
        </div>
    </div>

    <!-- Summary Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-3xl bg-amber-500/10 border border-amber-500/20 shadow-xs flex flex-col justify-between space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">
                    {{ __('Pending Payouts') }}
                </span>
                <span class="h-6 px-2 text-xs font-black rounded-full bg-amber-500 text-white flex items-center justify-center">
                    {{ $this->metrics['pending_count'] }}
                </span>
            </div>
            <p class="text-2xl font-black text-slate-900 dark:text-white">
                Rp {{ number_format($this->metrics['pending_amount'], 0, ',', '.') }}
            </p>
            <span class="text-[11px] text-amber-700 dark:text-amber-300">{{ __('Requires operator bank disbursement') }}</span>
        </div>

        <div class="p-5 rounded-3xl bg-emerald-500/10 border border-emerald-500/20 shadow-xs flex flex-col justify-between space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                    {{ __('Completed Payouts') }}
                </span>
                <span class="h-6 px-2 text-xs font-black rounded-full bg-emerald-500 text-white flex items-center justify-center">
                    {{ $this->metrics['completed_count'] }}
                </span>
            </div>
            <p class="text-2xl font-black text-slate-900 dark:text-white">
                Rp {{ number_format($this->metrics['completed_amount'], 0, ',', '.') }}
            </p>
            <span class="text-[11px] text-emerald-700 dark:text-emerald-300">{{ __('Total fiat successfully transferred') }}</span>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
        <!-- Filters Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-zinc-800">
            <div class="relative flex-1 sm:max-w-xs">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search ref, agent, account...') }}"
                    class="h-10 w-full pl-9 pr-4 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition" />
            </div>

            <div class="w-full sm:w-52">
                <x-select
                    wire:model.live="statusFilter"
                    :options="[
                        'all' => __('All Statuses'),
                        'pending' => __('Pending Approval'),
                        'completed' => __('Completed'),
                        'rejected' => __('Rejected'),
                    ]"
                />
            </div>
        </div>

        <!-- Mobile Admin Payouts Card List (md:hidden) -->
        <div class="md:hidden space-y-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($this->payoutRequests as $payout)
                <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono font-extrabold text-xs text-slate-900 dark:text-white">
                            {{ $payout->reference_number }}
                        </span>
                        @if ($payout->status->value === 'approved' || $payout->status->value === 'completed')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Paid</span>
                        @elseif ($payout->status->value === 'pending')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">Pending</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">{{ ucfirst($payout->status->value) }}</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100 dark:border-zinc-800">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Operator') }}</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 block truncate">{{ $payout->operator?->name ?? 'System' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Amount') }}</span>
                            <span class="font-mono font-black text-slate-900 dark:text-white block">Rp {{ number_format((float) $payout->amount, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800 text-[11px] text-slate-500">
                        <span>{{ $payout->bank_name }} ({{ $payout->account_number }})</span>
                        @if ($payout->status->value === 'pending')
                            <button type="button" wire:click="openFulfillModal('{{ $payout->id }}')" class="px-3 py-1 rounded-xl bg-emerald-600 text-white font-bold text-xs">
                                {{ __('Fulfill Payout') }}
                            </button>
                        @endif
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
                    <tr class="border-b border-slate-100 dark:border-zinc-800 text-[11px] uppercase font-bold text-slate-400 tracking-wider">
                        <th class="pb-3 px-3">{{ __('Ref / Requested') }}</th>
                        <th class="pb-3 px-3">{{ __('Tour Operator') }}</th>
                        <th class="pb-3 px-3">{{ __('Amount') }}</th>
                        <th class="pb-3 px-3">{{ __('Bank Destination') }}</th>
                        <th class="pb-3 px-3">{{ __('Status') }}</th>
                        <th class="pb-3 px-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60">
                    @forelse ($this->payoutRequests as $payout)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="py-3 px-3 whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-900 dark:text-white block">{{ $payout->reference_number }}</span>
                                <span class="text-[10px] text-slate-400">{{ $payout->created_at?->format('d M Y, H:i') }}</span>
                            </td>

                            <td class="py-3 px-3 whitespace-nowrap">
                                <span class="font-bold text-slate-900 dark:text-white block">{{ $payout->operator?->name ?? 'Unknown' }}</span>
                                <span class="text-[10px] text-slate-400">{{ $payout->operator?->billing_email }}</span>
                            </td>

                            <td class="py-3 px-3 whitespace-nowrap font-extrabold text-sm text-slate-900 dark:text-white">
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
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <i class="fa-solid fa-circle-check text-[10px]"></i>
                                        {{ __('Completed') }}
                                    </span>
                                @elseif ($payout->status->value === 'rejected')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        <i class="fa-solid fa-circle-xmark text-[10px]"></i>
                                        {{ __('Rejected') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        <i class="fa-solid fa-clock text-[10px]"></i>
                                        {{ __('Pending Approval') }}
                                    </span>
                                @endif
                            </td>

                            <td class="py-3 px-3 whitespace-nowrap text-right">
                                @if ($payout->status->value === 'pending')
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" wire:click="disburseViaDokuApi('{{ $payout->id }}')"
                                            class="h-8 px-2.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-2xs transition cursor-pointer flex items-center gap-1">
                                            <i class="fa-solid fa-bolt text-[10px]"></i>
                                            <span>{{ __('DOKU BI-FAST') }}</span>
                                        </button>
                                        <button type="button" wire:click="openApproveModal('{{ $payout->id }}')"
                                            class="h-8 px-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-2xs transition cursor-pointer">
                                            {{ __('Approve') }}
                                        </button>
                                        <button type="button" wire:click="openRejectModal('{{ $payout->id }}')"
                                            class="h-8 px-2 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 hover:bg-rose-100 font-bold text-xs transition cursor-pointer">
                                            {{ __('Reject') }}
                                        </button>
                                    </div>
                                @elseif ($payout->proof_document_path)
                                    <a href="{{ Storage::url($payout->proof_document_path) }}" target="_blank"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-purple-600 dark:text-purple-400 hover:underline">
                                        <i class="fa-solid fa-file-invoice"></i>
                                        <span>{{ __('View Proof') }}</span>
                                    </a>
                                @else
                                    <span class="text-slate-400">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-receipt text-3xl mb-2 block opacity-40"></i>
                                <p class="font-bold text-sm text-slate-600 dark:text-slate-300">{{ __('No payout requests found') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-zinc-800">
            {{ $this->payoutRequests->links() }}
        </div>
    </div>

    <!-- Approve Modal -->
    @if ($showApproveModal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl border border-slate-200/80 dark:border-zinc-800 flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ __('Confirm Bank Transfer') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __('Mark this payout as completed once the fiat funds have been transferred.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showApproveModal', false)" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <form wire:submit="approvePayout" class="p-6 space-y-4">
                        <div>
                            <x-label for="proofFile" :value="__('Optional Proof of Transfer Receipt (JPG, PNG, PDF)')" />
                            <input id="proofFile" type="file" wire:model="proofFile" accept="image/*,.pdf"
                                class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-purple-50 file:text-purple-700 dark:file:bg-purple-950 dark:file:text-purple-300" />
                            <x-input-error :messages="$errors->get('proofFile')" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                            <x-button type="button" variant="secondary" wire:click="$set('showApproveModal', false)" class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" variant="primary" class="text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white">
                                <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                                {{ __('Confirm Paid') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Reject Modal -->
    @if ($showRejectModal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl border border-slate-200/80 dark:border-zinc-800 flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ __('Reject Payout Request') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __('The funds will be refunded back to the available wallet balance.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showRejectModal', false)" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <form wire:submit="rejectPayout" class="p-6 space-y-4">
                        <div>
                            <x-label for="rejectionReason" :value="__('Rejection Reason (Shown to Operator)')" required />
                            <x-textarea id="rejectionReason" wire:model="rejectionReason" rows="3" placeholder="e.g. Bank account name mismatch..." required />
                            <x-input-error :messages="$errors->get('rejectionReason')" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                            <x-button type="button" variant="secondary" wire:click="$set('showRejectModal', false)" class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" variant="danger" class="text-xs font-bold">
                                <i class="fa-solid fa-ban mr-1.5 text-xs"></i>
                                {{ __('Reject & Refund to Wallet') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
