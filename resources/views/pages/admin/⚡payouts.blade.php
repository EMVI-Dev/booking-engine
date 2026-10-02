<?php

use App\Concerns\RecordsAdminActions;
use App\Concerns\UsesMediaStore;
use App\Enums\PayoutStatus;
use App\Models\PayoutRequest;
use App\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Payouts')] #[Layout('layouts.admin')] class extends Component
{
    use RecordsAdminActions;

    use UsesMediaStore, WithFileUploads, WithPagination;

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

    public function exportCsv(): StreamedResponse
    {
        $fileName = 'operator-payouts-'.now()->format('Y-m-d').'.csv';

        $this->audit('export.payouts', null, ['status' => $this->statusFilter, 'search' => $this->search]);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Operator', 'Bank Provider', 'Bank Account Number', 'Bank Account Name', 'Amount', 'Status', 'Notes', 'Rejection Reason', 'Requested At', 'Processed At']);

            PayoutRequest::query()
                ->with('operator')
                ->when($this->statusFilter !== 'all', function ($query) {
                    $query->where('status', $this->statusFilter);
                })
                ->when(filled($this->search), function ($query) {
                    $query->where(function ($q) {
                        $q->where('reference_number', 'like', "%{$this->search}%")
                            ->orWhere('bank_account_name', 'like', "%{$this->search}%")
                            ->orWhere('bank_account_number', 'like', "%{$this->search}%")
                            ->orWhereHas('operator', function ($operatorQuery) {
                                $operatorQuery->where('name', 'like', "%{$this->search}%");
                            });
                    });
                })
                ->latest()
                ->chunk(100, function ($payouts) use ($handle) {
                    foreach ($payouts as $payout) {
                        fputcsv($handle, [
                            $payout->reference_number,
                            $payout->operator?->name ?? 'Deleted Operator',
                            $payout->bank_provider,
                            $payout->bank_account_number,
                            $payout->bank_account_name,
                            (float) $payout->amount,
                            $payout->status->label() ?? $payout->status->value,
                            $payout->notes ?? '-',
                            $payout->rejection_reason ?? '-',
                            $payout->created_at?->format('Y-m-d H:i:s') ?? '-',
                            $payout->processed_at?->format('Y-m-d H:i:s') ?? '-',
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    /**
     * Get summary metrics for platform payouts.
     *
     * @return array{pending_count: int, pending_amount: float, completed_count: int, completed_amount: float}
     */
    #[Computed]
    public function metrics(): array
    {
        $metrics = app(\App\Services\AdminMetricsService::class);
        $pending = $metrics->payoutTotals(PayoutStatus::Pending);
        $completed = $metrics->payoutTotals(PayoutStatus::Completed);

        return [
            'pending_count' => $pending['count'],
            'pending_amount' => $pending['amount'],
            'completed_count' => $completed['count'],
            'completed_amount' => $completed['amount'],
        ];
    }

    /**
     * Get paginated payout requests across all operators.
     */
    #[Computed]
    public function payoutRequests(): LengthAwarePaginator
    {
        return PayoutRequest::query()
            ->with('operator')
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', "%{$this->search}%")
                        ->orWhere('bank_account_name', 'like', "%{$this->search}%")
                        ->orWhere('bank_account_number', 'like', "%{$this->search}%")
                        ->orWhereHas('operator', function ($operatorQuery) {
                            $operatorQuery->where('name', 'like', "%{$this->search}%");
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
            $proofPath = $this->media()->storeUpload($this->proofFile, $this->media()->directoryFor($payout->operator_id, 'payouts'));
        }

        try {
            $walletService->approvePayout($payout, $proofPath, auth()->id());
            $this->audit('payout.approved', $payout, ['amount' => (float) $payout->amount, 'reference' => $payout->reference_number]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', (string) $e->validator->errors()->first());
        }

        $this->showApproveModal = false;
        $this->selectedPayoutId = null;
        $this->proofFile = null;
        $this->dispatch('payout-approved');
    }

    public function disburseViaDokuApi(string $payoutId, WalletService $walletService): void
    {
        $payout = PayoutRequest::find($payoutId);
        if (! $payout || $payout->status !== PayoutStatus::Pending) {
            return;
        }

        $res = $walletService->disbursePayout($payout, 'DOKU BI-FAST (admin)');
        $this->audit('payout.sent_to_bank', $payout, ['amount' => (float) $payout->amount, 'success' => $res['success'], 'reference' => $res['reference']]);
        if ($res['success']) {
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

        try {
            $walletService->rejectPayout($payout, $this->rejectionReason, auth()->id());
            $this->audit('payout.rejected', $payout, ['amount' => (float) $payout->amount, 'reason' => $this->rejectionReason]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', (string) $e->validator->errors()->first());
        }

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
            <button
                type="button"
                wire:click="exportCsv"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[6px] text-[13px] font-medium bg-white dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] text-[#1C2024] dark:text-white transition cursor-pointer shadow-none"
            >
                <i class="fa-solid fa-download text-xs text-[#8B8D98]"></i>
                <span>{{ __('Export CSV') }}</span>
            </button>
            <span class="inline-flex items-center gap-1.5 rounded-[6px] bg-[#ECFDF5] px-2.5 py-1 text-[12px] font-medium text-[#065F46] border border-[#A7F3D0]">
                <span class="h-1.5 w-1.5 rounded-full bg-[#059669]"></span>
                <span>{{ __('Auto send is on') }}</span>
            </span>
        </x-slot:actions>
    </x-page-header>

    <!-- Summary Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[12px] font-medium text-amber-700 dark:text-amber-400">
                    {{ __('Pending Payouts') }}
                </span>
                <span class="h-5 px-2 text-[11px] font-medium rounded-[6px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center">
                    {{ $this->metrics['pending_count'] }}
                </span>
            </div>
            <p class="text-[22px] font-semibold text-[#1C2024] dark:text-white">
                Rp {{ number_format($this->metrics['pending_amount'], 0, ',', '.') }}
            </p>
            <span class="text-[12px] text-[#8B8D98]">{{ __('Waiting to send to their bank') }}</span>
        </div>

        <div class="p-4 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[12px] font-medium text-emerald-700 dark:text-emerald-400">
                    {{ __('Completed Payouts') }}
                </span>
                <span class="h-5 px-2 text-[11px] font-medium rounded-[6px] bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0] flex items-center justify-center">
                    {{ $this->metrics['completed_count'] }}
                </span>
            </div>
            <p class="text-[22px] font-semibold text-[#1C2024] dark:text-white">
                Rp {{ number_format($this->metrics['completed_amount'], 0, ',', '.') }}
            </p>
            <span class="text-[12px] text-[#8B8D98]">{{ __('Total fiat successfully transferred') }}</span>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="p-3.5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[#8B8D98] text-xs"></i>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search ref, operator, bank account...') }}"
                class="w-full h-8 pl-8 pr-3 rounded-[6px] text-[13px] bg-white dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white placeholder-[#8B8D98] focus:border-[#FFEF4D] focus:ring-1 focus:ring-[#FFEF4D] shadow-none"
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
    <div class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
        <!-- Mobile Admin Payouts Card List (md:hidden) -->
        <div class="md:hidden space-y-3 p-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($this->payoutRequests as $payout)
                <div class="p-3.5 rounded-[8px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono font-medium text-[12px] text-[#1C2024] dark:text-white">
                            {{ $payout->reference_number }}
                        </span>
                        @if ($payout->status->value === 'approved' || $payout->status->value === 'completed')
                            <span class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium uppercase bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]">Paid</span>
                        @elseif ($payout->status->value === 'pending')
                            <span class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium uppercase bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]">Pending</span>
                        @else
                            <span class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium uppercase bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]">{{ ucfirst($payout->status->value) }}</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-[12px] pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                        <div>
                            <span class="text-[10px] uppercase font-medium text-[#8B8D98] block">{{ __('Operator') }}</span>
                            <span class="font-medium text-[#1C2024] dark:text-slate-200 block truncate">{{ $payout->operator?->name ?? 'System' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-medium text-[#8B8D98] block">{{ __('Amount') }}</span>
                            <span class="font-mono font-medium text-[#1C2024] dark:text-white block">Rp {{ number_format((float) $payout->amount, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433] text-[11px] text-[#60646C]">
                        <span>{{ $payout->bank_name }} ({{ $payout->account_number }})</span>
                        @if ($payout->status->value === 'pending')
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="disburseViaDokuApi('{{ $payout->id }}')" class="h-7 px-2.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-[11px] inline-flex items-center gap-1 transition cursor-pointer shadow-none">
                                    <i class="fa-solid fa-bolt text-[9px]"></i>
                                    <span>{{ __('DOKU') }}</span>
                                </button>
                                <button type="button" wire:click="openApproveModal('{{ $payout->id }}')" class="h-7 px-2.5 rounded-[6px] bg-[#ECFDF5] text-[#065F46] hover:bg-[#D1FAE5] border border-[#A7F3D0] font-medium text-[11px] inline-flex items-center transition cursor-pointer shadow-none">
                                    {{ __('Approve') }}
                                </button>
                                <button type="button" wire:click="openRejectModal('{{ $payout->id }}')" class="h-7 px-2.5 rounded-[6px] bg-[#FEF2F2] text-[#991B1B] hover:bg-[#FEE2E2] border border-[#FECACA] font-medium text-[11px] inline-flex items-center transition cursor-pointer shadow-none">
                                    {{ __('Reject') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-[13px] text-[#8B8D98]">
                    {{ __('No payout requests found') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop Payouts Table (hidden on mobile) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead>
                    <tr class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium uppercase tracking-wider text-[#60646C]">
                        <th class="py-3 px-4 sm:px-5">{{ __('Ref / Requested') }}</th>
                        <th class="py-3 px-4">{{ __('Tour Operator') }}</th>
                        <th class="py-3 px-4">{{ __('Amount') }}</th>
                        <th class="py-3 px-4">{{ __('Bank Destination') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4 sm:px-5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                    @forelse ($this->payoutRequests as $payout)
                        <tr class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/60 transition group">
                            <td class="py-3.5 px-4 sm:px-5 whitespace-nowrap">
                                <span class="font-mono text-[11px] font-medium text-[#856404] dark:text-[#FFEF4D] px-2 py-0.5 rounded-[6px] bg-[#FFEF4D]/20 border border-[#FFEF4D]/40 inline-block mb-1">#{{ $payout->reference_number }}</span>
                                <span class="text-[11px] text-[#8B8D98] block">{{ $payout->created_at?->format('d M Y, H:i') }}</span>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-medium text-[13px] text-[#1C2024] dark:text-white block">{{ $payout->operator?->name ?? 'Unknown' }}</span>
                                <span class="text-[11px] text-[#8B8D98]">{{ $payout->operator?->billing_email }}</span>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap font-mono font-medium text-[13px] text-[#1C2024] dark:text-white">
                                Rp {{ number_format((float) $payout->amount, 0, ',', '.') }}
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-medium text-[13px] text-[#1C2024] dark:text-slate-200 flex items-center gap-1.5">
                                    <i class="fa-solid fa-building-columns text-[#8B8D98] text-xs"></i>
                                    <span>{{ $payout->bank_name }}</span>
                                </div>
                                <div class="font-mono text-[11px] text-[#60646C]">
                                    {{ $payout->account_number }} ({{ $payout->account_name }})
                                </div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if ($payout->status->value === 'approved' || $payout->status->value === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#059669]"></span>
                                        <span>Paid</span>
                                    </span>
                                @elseif ($payout->status->value === 'pending')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]">
                                        <i class="fa-solid fa-spinner fa-spin text-[9px]"></i>
                                        <span>Pending</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#DC2626]"></span>
                                        <span>{{ ucfirst($payout->status->value) }}</span>
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 sm:px-5 whitespace-nowrap text-right">
                                @if ($payout->status->value === 'pending')
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" wire:click="disburseViaDokuApi('{{ $payout->id }}')"
                                            class="h-7 px-2.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-[11px] shadow-none transition cursor-pointer inline-flex items-center gap-1">
                                            <i class="fa-solid fa-bolt text-[9px]"></i>
                                            <span>{{ __('Send to bank') }}</span>
                                        </button>
                                        <button type="button" wire:click="openApproveModal('{{ $payout->id }}')"
                                            class="h-7 px-2.5 rounded-[6px] bg-[#ECFDF5] hover:bg-[#D1FAE5] text-[#065F46] border border-[#A7F3D0] font-medium text-[11px] shadow-none transition cursor-pointer inline-flex items-center">
                                            {{ __('Approve') }}
                                        </button>
                                        <button type="button" wire:click="openRejectModal('{{ $payout->id }}')"
                                            class="h-7 px-2.5 rounded-[6px] bg-[#FEF2F2] text-[#991B1B] hover:bg-[#FEE2E2] border border-[#FECACA] font-medium text-[11px] transition cursor-pointer inline-flex items-center shadow-none">
                                            {{ __('Reject') }}
                                        </button>
                                    </div>
                                @elseif ($payout->proof_document_path)
                                    <a href="{{ $payout->proof_document_url }}" target="_blank"
                                        class="h-7 px-2.5 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] text-[#1C2024] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] font-medium text-[11px] transition inline-flex items-center gap-1 shadow-none">
                                        <i class="fa-solid fa-file-invoice text-[10px]"></i>
                                        <span>{{ __('View Proof') }}</span>
                                    </a>
                                @else
                                    <span class="text-[#8B8D98]">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-[#8B8D98]">
                                <i class="fa-solid fa-receipt text-2xl mb-2 block opacity-40"></i>
                                <p class="font-medium text-[13px] text-[#60646C] dark:text-slate-300">{{ __('No payout requests found') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->payoutRequests->hasPages())
            <div class="p-3.5 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                {{ $this->payoutRequests->links() }}
            </div>
        @endif
    </div>

    <!-- Approve Modal -->
    @if ($showApproveModal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-md rounded-[12px] bg-white dark:bg-[#10141d] shadow-none border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col my-8 overflow-hidden">
                    <!-- Modal Header -->
                    <div class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-start justify-between gap-3 bg-[#FAFAFB] dark:bg-[#141821]">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-[8px] bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0] flex items-center justify-center text-sm shadow-none shrink-0 mt-0.5">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-semibold text-[16px] text-[#1C2024] dark:text-white leading-tight truncate">
                                    {{ __('Confirm Bank Transfer') }}
                                </h3>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400">
                                    {{ __('Mark this payout as completed once the fiat funds have been transferred.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showApproveModal', false)" class="p-1.5 rounded-[6px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <form wire:submit="approvePayout" class="p-5 space-y-4">
                        <div>
                            <x-label for="proofFile" :value="__('Optional Proof of Transfer Receipt (JPG, PNG, PDF)')" />
                            <input id="proofFile" type="file" wire:model="proofFile" accept="image/*,.pdf"
                                class="text-[12px] text-[#60646C] file:mr-3 file:py-1.5 file:px-3 file:rounded-[6px] file:border-0 file:text-[12px] file:font-medium file:bg-[#FFEF4D] file:text-[#12181E] cursor-pointer" />
                            <x-input-error :messages="$errors->get('proofFile')" />
                        </div>

                        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <button type="button" wire:click="$set('showApproveModal', false)" class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white transition cursor-pointer shadow-none">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-[#ECFDF5] hover:bg-[#D1FAE5] text-[#065F46] border border-[#A7F3D0] inline-flex items-center gap-1.5 transition cursor-pointer shadow-none">
                                <i class="fa-solid fa-check text-xs"></i>
                                <span>{{ __('Confirm Paid') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Reject Modal -->
    @if ($showRejectModal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-md rounded-[12px] bg-white dark:bg-[#10141d] shadow-none border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col my-8 overflow-hidden">
                    <!-- Modal Header -->
                    <div class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-start justify-between gap-3 bg-[#FAFAFB] dark:bg-[#141821]">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-[8px] bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA] flex items-center justify-center text-sm shadow-none shrink-0 mt-0.5">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-semibold text-[16px] text-[#1C2024] dark:text-white leading-tight truncate">
                                    {{ __('Reject Payout Request') }}
                                </h3>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400">
                                    {{ __('The funds will be refunded back to the available wallet balance.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showRejectModal', false)" class="p-1.5 rounded-[6px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <form wire:submit="rejectPayout" class="p-5 space-y-4">
                        <div>
                            <x-label for="rejectionReason" :value="__('Rejection Reason (Shown to Operator)')" required />
                            <x-textarea id="rejectionReason" wire:model="rejectionReason" rows="3" placeholder="e.g. Bank account name mismatch..." required />
                            <x-input-error :messages="$errors->get('rejectionReason')" />
                        </div>

                        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <button type="button" wire:click="$set('showRejectModal', false)" class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white transition cursor-pointer shadow-none">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-[#FEF2F2] hover:bg-[#FEE2E2] text-[#991B1B] border border-[#FECACA] inline-flex items-center gap-1.5 transition cursor-pointer shadow-none">
                                <i class="fa-solid fa-ban text-xs"></i>
                                <span>{{ __('Reject & Refund to Wallet') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
