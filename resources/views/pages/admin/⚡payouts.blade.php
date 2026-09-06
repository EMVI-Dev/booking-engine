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

new #[Title('Payouts')] #[Layout('layouts.admin')] class extends Component {
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
            session()->flash('success', __('Sent payout :ref to their bank.', ['ref' => $payout->reference_number]));
        } else {
            session()->flash('error', __('Could not send this payout. Check the bank account details.'));
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

<div class="space-y-6">
    <x-page-header
        :title="__('Payouts')"
        :subtitle="__('Send operator money to their bank. Check anything that needs a second look.')"
        icon="fa-money-bill-transfer"
    >
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                <span>{{ __('Auto send is on') }}</span>
            </span>
        </x-slot:actions>
    </x-page-header>

    <!-- Summary Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-3xl bg-amber-500/10 border border-amber-500/20 shadow-xs flex flex-col justify-between space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">
                    {{ __('Pending Payouts') }}
                </span>
                <span class="h-6 px-2 text-xs font-black rounded-full bg-[#FFEF4D] text-[#090d16] shadow-xs flex items-center justify-center">
                    {{ $this->metrics['pending_count'] }}
                </span>
            </div>
            <p class="text-2xl font-black text-slate-900 dark:text-white">
                Rp {{ number_format($this->metrics['pending_amount'], 0, ',', '.') }}
            </p>
            <span class="text-[11px] text-amber-700 dark:text-amber-300">{{ __('Waiting to send to their bank') }}</span>
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

    <!-- Filters & Search Bar -->
    <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <x-input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search ref, operator, bank account...') }}"
                class="pl-9 text-xs"
            />
        </div>

        <div class="w-full sm:w-56">
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

    <!-- Main Table Card -->
    <div class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-hidden">
        <!-- Mobile Admin Payouts Card List (md:hidden) -->
        <div class="md:hidden space-y-3 p-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($this->payoutRequests as $payout)
                <div class="p-4 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-3">
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

                    <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100 dark:border-[#1e2433]">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Operator') }}</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 block truncate">{{ $payout->operator?->name ?? 'System' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Amount') }}</span>
                            <span class="font-mono font-black text-slate-900 dark:text-white block">Rp {{ number_format((float) $payout->amount, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-[#1e2433] text-[11px] text-slate-500">
                        <span>{{ $payout->bank_name }} ({{ $payout->account_number }})</span>
                        @if ($payout->status->value === 'pending')
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="disburseViaDokuApi('{{ $payout->id }}')" class="h-8 px-2.5 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs inline-flex items-center gap-1 transition cursor-pointer shadow-2xs">
                                    <i class="fa-solid fa-bolt text-[10px]"></i>
                                    <span>{{ __('DOKU') }}</span>
                                </button>
                                <button type="button" wire:click="openApproveModal('{{ $payout->id }}')" class="h-8 px-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs inline-flex items-center transition cursor-pointer">
                                    {{ __('Approve') }}
                                </button>
                                <button type="button" wire:click="openRejectModal('{{ $payout->id }}')" class="h-8 px-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 hover:bg-rose-100 font-bold text-xs inline-flex items-center transition cursor-pointer">
                                    {{ __('Reject') }}
                                </button>
                            </div>
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
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Ref / Requested') }}</th>
                        <th class="py-3.5 px-4">{{ __('Tour Operator') }}</th>
                        <th class="py-3.5 px-4">{{ __('Amount') }}</th>
                        <th class="py-3.5 px-4">{{ __('Bank Destination') }}</th>
                        <th class="py-3.5 px-4">{{ __('Status') }}</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                    @forelse ($this->payoutRequests as $payout)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group">
                            <td class="py-3.5 px-4 sm:px-6 whitespace-nowrap">
                                <span class="font-mono font-bold text-[10px] text-[#FFEF4D] px-2 py-0.5 rounded-lg bg-[#FFEF4D]/10 border border-[#FFEF4D]/30 inline-block mb-1">#{{ $payout->reference_number }}</span>
                                <span class="text-[10px] text-slate-400 block">{{ $payout->created_at?->format('d M Y, H:i') }}</span>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-bold text-slate-900 dark:text-white block">{{ $payout->operator?->name ?? 'Unknown' }}</span>
                                <span class="text-[10px] text-slate-400">{{ $payout->operator?->billing_email }}</span>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap font-mono font-bold text-sm text-slate-900 dark:text-white">
                                Rp {{ number_format((float) $payout->amount, 0, ',', '.') }}
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <i class="fa-solid fa-building-columns text-slate-400 text-xs"></i>
                                    <span>{{ $payout->bank_name }}</span>
                                </div>
                                <div class="font-mono text-[11px] text-slate-500">
                                    {{ $payout->account_number }} ({{ $payout->account_name }})
                                </div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if ($payout->status->value === 'approved' || $payout->status->value === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-950/60 text-emerald-400 border border-emerald-800/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        <span>Paid</span>
                                    </span>
                                @elseif ($payout->status->value === 'pending')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-950/60 text-amber-400 border border-amber-800/60">
                                        <i class="fa-solid fa-spinner fa-spin text-[10px]"></i>
                                        <span>Pending</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-950/60 text-rose-400 border border-rose-800/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        <span>{{ ucfirst($payout->status->value) }}</span>
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 sm:px-6 whitespace-nowrap text-right">
                                @if ($payout->status->value === 'pending')
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" wire:click="disburseViaDokuApi('{{ $payout->id }}')"
                                            class="h-8 px-2.5 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs shadow-xs transition cursor-pointer inline-flex items-center gap-1">
                                            <i class="fa-solid fa-bolt text-[10px]"></i>
                                            <span>{{ __('Send to bank') }}</span>
                                        </button>
                                        <button type="button" wire:click="openApproveModal('{{ $payout->id }}')"
                                            class="h-8 px-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition cursor-pointer inline-flex items-center">
                                            {{ __('Approve') }}
                                        </button>
                                        <button type="button" wire:click="openRejectModal('{{ $payout->id }}')"
                                            class="h-8 px-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/40 border border-rose-200 dark:border-rose-800/60 font-bold text-xs transition cursor-pointer inline-flex items-center shadow-2xs">
                                            {{ __('Reject') }}
                                        </button>
                                    </div>
                                @elseif ($payout->proof_document_path)
                                    <a href="{{ Storage::url($payout->proof_document_path) }}" target="_blank"
                                        class="h-8 px-2.5 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#1e2433] font-bold text-xs transition inline-flex items-center gap-1 shadow-2xs">
                                        <i class="fa-solid fa-file-invoice text-[10px]"></i>
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

        @if ($this->payoutRequests->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-[#1e2433]">
                {{ $this->payoutRequests->links() }}
            </div>
        @endif
    </div>

    <!-- Approve Modal -->
    @if ($showApproveModal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#0C0E13] shadow-2xl border border-slate-200/80 dark:border-[#1e2433] flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
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
                                class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#FFEF4D]/15 file:text-[#8a7808] dark:file:text-[#FFEF4D]" />
                            <x-input-error :messages="$errors->get('proofFile')" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-[#1e2433]">
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
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#0C0E13] shadow-2xl border border-slate-200/80 dark:border-[#1e2433] flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
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

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-[#1e2433]">
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
