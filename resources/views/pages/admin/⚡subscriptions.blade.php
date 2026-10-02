<?php

use App\Concerns\RecordsAdminActions;
use App\Models\SubscriptionPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Subscription Invoices')] #[Layout('layouts.admin')] class extends Component
{
    use RecordsAdminActions;

    use WithPagination;

    public string $statusFilter = 'all';

    public string $search = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{total_revenue: float, completed_count: int, pending_count: int, total_count: int}
     */
    #[Computed]
    public function metrics(): array
    {
        return [
            'total_revenue' => app(\App\Services\AdminMetricsService::class)->subscriptionRevenue(),
            'completed_count' => SubscriptionPayment::where('status', SubscriptionPayment::STATUS_COMPLETED)->count(),
            'pending_count' => SubscriptionPayment::where('status', SubscriptionPayment::STATUS_PENDING)->count(),
            'total_count' => SubscriptionPayment::count(),
        ];
    }

    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        return SubscriptionPayment::query()
            ->with(['operator', 'plan'])
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('invoice_number', 'like', "%{$this->search}%")
                        ->orWhere('gateway_ref', 'like', "%{$this->search}%")
                        ->orWhereHas('operator', function ($operatorQuery) {
                            $operatorQuery->where('name', 'like', "%{$this->search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15);
    }

    public function exportCsv(): StreamedResponse
    {
        $fileName = 'subscription-invoices-'.now()->format('Y-m-d').'.csv';

        $this->audit('export.subscription_invoices', null, ['status' => $this->statusFilter, 'search' => $this->search]);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice Number', 'Operator', 'Plan', 'Interval', 'Amount Paid', 'Status', 'Gateway', 'Paid At', 'Created At']);

            SubscriptionPayment::query()
                ->with(['operator', 'plan'])
                ->latest()
                ->chunk(100, function ($payments) use ($handle) {
                    foreach ($payments as $payment) {
                        fputcsv($handle, [
                            $payment->invoice_number,
                            $payment->operator?->name ?? 'Deleted Operator',
                            $payment->plan?->name ?? 'Custom Plan',
                            ucfirst($payment->billing_interval ?? 'monthly'),
                            (float) $payment->net_amount_paid,
                            ucfirst($payment->status),
                            strtoupper($payment->gateway ?? 'N/A'),
                            $payment->paid_at?->format('Y-m-d H:i:s') ?? '-',
                            $payment->created_at?->format('Y-m-d H:i:s') ?? '-',
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}; ?>

<div class="space-y-6 w-full">
    <x-page-header
        :title="__('Subscription Invoices')"
        :subtitle="__('Operator plan upgrade transactions and billing history.')"
        icon="fa-receipt"
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
        </x-slot:actions>
    </x-page-header>

    {{-- Metrics Summary Row --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-metric-card
            :label="__('Total Collected')"
            :value="'Rp '.number_format($this->metrics['total_revenue'], 0, ',', '.')"
            :hint="__('Lifetime subscription fees')"
            icon="fa-vault"
            tone="featured"
        />

        <x-metric-card
            :label="__('Paid Invoices')"
            :value="$this->metrics['completed_count']"
            :hint="__('Successful transactions')"
            icon="fa-circle-check"
            tone="success"
        />

        <x-metric-card
            :label="__('Pending Invoices')"
            :value="$this->metrics['pending_count']"
            :hint="$this->metrics['pending_count'] > 0 ? __('Awaiting settlement') : __('No pending payments')"
            icon="fa-clock"
            :tone="$this->metrics['pending_count'] > 0 ? 'warning' : 'neutral'"
        />

        <x-metric-card
            :label="__('Total Invoices')"
            :value="$this->metrics['total_count']"
            :hint="__('All billing records')"
            icon="fa-file-invoice-dollar"
        />
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none">
        <div class="relative flex-1 max-w-sm">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#8B8D98]"></i>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search invoice # or operator...') }}"
                class="w-full h-8 pl-8 pr-3 rounded-[6px] text-[13px] bg-white dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white placeholder-[#8B8D98] focus:border-[#FFEF4D] focus:ring-1 focus:ring-[#FFEF4D] shadow-none"
            />
        </div>

        <div class="flex items-center gap-2">
            <x-filter-tabs padded>
                <x-filter-tab wire:click="$set('statusFilter', 'all')" :active="$statusFilter === 'all'">
                    {{ __('All') }}
                </x-filter-tab>
                <x-filter-tab wire:click="$set('statusFilter', 'completed')" :active="$statusFilter === 'completed'">
                    {{ __('Paid') }}
                </x-filter-tab>
                <x-filter-tab wire:click="$set('statusFilter', 'pending')" :active="$statusFilter === 'pending'">
                    {{ __('Pending') }}
                </x-filter-tab>
                <x-filter-tab wire:click="$set('statusFilter', 'failed')" :active="$statusFilter === 'failed'">
                    {{ __('Failed') }}
                </x-filter-tab>
            </x-filter-tabs>
        </div>
    </div>

    {{-- Invoices Table --}}
    <div class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead>
                    <tr class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium uppercase tracking-wider text-[#60646C]">
                        <th class="py-3 px-4 sm:px-5">{{ __('Invoice #') }}</th>
                        <th class="py-3 px-4">{{ __('Operator') }}</th>
                        <th class="py-3 px-4">{{ __('Plan') }}</th>
                        <th class="py-3 px-4">{{ __('Amount') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4 sm:px-5 text-right">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                    @forelse ($this->invoices as $invoice)
                        <tr class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/60 transition">
                            <td class="py-3 px-4 sm:px-5 font-mono text-[12px] font-medium text-[#1C2024] dark:text-white">
                                {{ $invoice->invoice_number }}
                                @if ($invoice->gateway_ref)
                                    <span class="block font-mono text-[10px] text-[#8B8D98]">{{ $invoice->gateway_ref }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($invoice->operator)
                                    <a href="{{ route('admin.operators.show', $invoice->operator->id) }}" wire:navigate class="font-medium text-[13px] text-[#1C2024] dark:text-white hover:text-[#856404] dark:hover:text-[#FFEF4D] transition">
                                        {{ $invoice->operator->name }}
                                    </a>
                                @else
                                    <span class="text-[#8B8D98]">{{ __('Deleted Operator') }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-medium text-[13px] text-[#1C2024] dark:text-slate-200">
                                    {{ $invoice->plan?->name ?? 'Plan' }}
                                </span>
                                <span class="text-[10px] text-[#8B8D98] uppercase block">
                                    {{ $invoice->billing_interval ?? 'monthly' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono font-medium text-[13px] text-[#1C2024] dark:text-white">
                                Rp {{ number_format((float) $invoice->net_amount_paid, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4">
                                <span @class([
                                    'inline-flex items-center px-2 py-0.5 rounded-[6px] text-[11px] font-medium uppercase',
                                    'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]' => $invoice->status === 'completed',
                                    'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]' => $invoice->status === 'pending',
                                    'bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]' => $invoice->status === 'failed',
                                ])>
                                    {{ $invoice->status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 sm:px-5 text-right font-mono text-[12px] text-[#60646C] dark:text-slate-400">
                                {{ ($invoice->paid_at ?? $invoice->created_at)?->format('d M Y, H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-[#8B8D98]">
                                <i class="fa-solid fa-file-invoice text-2xl mb-2 block opacity-40"></i>
                                <p class="font-medium text-[13px] text-[#60646C] dark:text-slate-300">{{ __('No subscription invoices found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->invoices->hasPages())
            <div class="p-3.5 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                {{ $this->invoices->links() }}
            </div>
        @endif
    </div>
</div>
