<?php

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
            'total_revenue' => (float) SubscriptionPayment::where('status', 'completed')->sum('net_amount_paid'),
            'completed_count' => SubscriptionPayment::where('status', 'completed')->count(),
            'pending_count' => SubscriptionPayment::where('status', 'pending')->count(),
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
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-[#141821] border border-slate-200 dark:border-[#1e2433] hover:border-slate-300 dark:hover:border-slate-600 text-slate-700 dark:text-slate-300 transition cursor-pointer shadow-2xs"
            >
                <i class="fa-solid fa-download text-xs text-slate-400"></i>
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs">
        <div class="relative flex-1 max-w-sm">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search invoice # or operator...') }}"
                class="w-full pl-9 pr-4 py-2 rounded-xl text-xs bg-slate-50 dark:bg-[#141821] border border-slate-200 dark:border-[#1e2433] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:border-brand-500"
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
    <div class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Invoice #') }}</th>
                        <th class="py-3.5 px-4">{{ __('Operator') }}</th>
                        <th class="py-3.5 px-4">{{ __('Plan') }}</th>
                        <th class="py-3.5 px-4">{{ __('Amount') }}</th>
                        <th class="py-3.5 px-4">{{ __('Status') }}</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                    @forelse ($this->invoices as $invoice)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition">
                            <td class="py-3.5 px-4 sm:px-6 font-mono text-xs font-bold text-slate-900 dark:text-white">
                                {{ $invoice->invoice_number }}
                                @if ($invoice->gateway_ref)
                                    <span class="block font-mono text-[10px] text-slate-400 font-normal">{{ $invoice->gateway_ref }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($invoice->operator)
                                    <a href="{{ route('admin.operators.show', $invoice->operator->id) }}" wire:navigate class="font-bold text-slate-900 dark:text-white hover:text-brand-500 transition">
                                        {{ $invoice->operator->name }}
                                    </a>
                                @else
                                    <span class="text-slate-400">{{ __('Deleted Operator') }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-semibold text-xs text-slate-700 dark:text-slate-300">
                                    {{ $invoice->plan?->name ?? 'Plan' }}
                                </span>
                                <span class="text-[10px] text-slate-400 uppercase block">
                                    {{ $invoice->billing_interval ?? 'monthly' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                Rp {{ number_format((float) $invoice->net_amount_paid, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span @class([
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $invoice->status === 'completed',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $invoice->status === 'pending',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' => $invoice->status === 'failed',
                                ])>
                                    {{ $invoice->status }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ ($invoice->paid_at ?? $invoice->created_at)?->format('d M Y, H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-file-invoice text-3xl mb-2 block opacity-40"></i>
                                <p class="font-bold text-sm text-slate-600 dark:text-slate-300">{{ __('No subscription invoices found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->invoices->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-[#1e2433]">
                {{ $this->invoices->links() }}
            </div>
        @endif
    </div>
</div>
