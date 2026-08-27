<?php

use App\Models\Operator;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Billing & Invoices')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public ?string $selected_invoice_id = null;
    public bool $show_invoice_modal = false;

    public string $billing_email = '';
    public string $company_legal_name = '';
    public string $tax_id = '';
    public bool $show_billing_info_modal = false;

    /**
     * Mount component.
     */
    public function mount(): void
    {
        $operator = auth()->user()?->currentOperator();

        if ($operator) {
            $this->billing_email = (string) ($operator->billing_email ?: (auth()->user()->email ?? ''));
            $this->company_legal_name = (string) ($operator->settings['legal_name'] ?? $operator->name);
            $this->tax_id = (string) ($operator->settings['tax_id'] ?? '');
        }
    }

    /**
     * Save operator billing contact details.
     */
    public function updateBillingInfo(): void
    {
        $this->validate([
            'billing_email' => ['required', 'email', 'max:255'],
            'company_legal_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
        ]);

        $operator = auth()->user()?->currentOperator();

        if ($operator) {
            $settings = $operator->settings ?? [];
            $settings['legal_name'] = $this->company_legal_name;
            $settings['tax_id'] = $this->tax_id;

            $operator->update([
                'billing_email' => $this->billing_email,
                'settings' => $settings,
            ]);

            $this->show_billing_info_modal = false;
            session()->flash('success', __('Billing contact information updated successfully.'));
        }
    }

    /**
     * View full itemized invoice modal.
     */
    public function viewInvoice(string $paymentId): void
    {
        $operator = auth()->user()?->currentOperator();

        if (! $operator) {
            return;
        }

        $payment = SubscriptionPayment::where('operator_id', $operator->id)
            ->where('id', $paymentId)
            ->first();

        if ($payment) {
            $this->selected_invoice_id = $payment->id;
            $this->show_invoice_modal = true;
        }
    }

    /**
     * Close invoice modal.
     */
    public function closeInvoiceModal(): void
    {
        $this->show_invoice_modal = false;
        $this->selected_invoice_id = null;
    }

    /**
     * Get the selected invoice record.
     */
    #[Computed]
    public function selectedInvoice(): ?SubscriptionPayment
    {
        if (! $this->selected_invoice_id) {
            return null;
        }

        return SubscriptionPayment::with(['plan', 'operator'])->find($this->selected_invoice_id);
    }

    /**
     * Get paginated subscription payments for current operator.
     */
    #[Computed]
    public function invoices()
    {
        $operator = auth()->user()?->currentOperator();

        if (! $operator) {
            return SubscriptionPayment::whereNull('id')->paginate(10);
        }

        return SubscriptionPayment::with(['plan'])
            ->where('operator_id', $operator->id)
            ->latest('created_at')
            ->paginate(10);
    }
}; ?>

<div class="space-y-6 max-w-6xl mx-auto select-none">
    <!-- Header with Subscription & Billing Navigation Tabs -->
    <x-billing-nav />

    <!-- Page Title & Subtitle -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Billing & Invoices') }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Manage your subscription billing profile, payment history, and download official receipts.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('settings.plan') }}"
                wire:navigate
                class="px-4 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-extrabold text-xs shadow-sm transition flex items-center gap-2 cursor-pointer"
            >
                <i class="fa-solid fa-crown text-amber-300 text-xs"></i>
                <span>{{ __('Manage Subscription Tier') }}</span>
            </a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2 shadow-xs animate-fade-in">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @php
        $operator = auth()->user()?->currentOperator();
        $currentPlan = $operator?->getPlan();
        $autoRenew = (bool) ($operator->subscription_auto_renew ?? true);
        $totalPaidSum = $operator ? $operator->subscriptionPayments()->where('status', 'completed')->sum('net_amount_paid') : 0;
        $totalPaidCount = $operator ? $operator->subscriptionPayments()->where('status', 'completed')->count() : 0;
    @endphp

    <!-- Overview Grid Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Card 1: Active Subscription Overview -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Active Subscription') }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase {{ $currentPlan->isFree() ? 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' }}">
                    {{ $currentPlan->name }}
                </span>
            </div>

            <div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-slate-900 dark:text-white">
                        @if ($currentPlan->isFree())
                            {{ __('Free Tier') }}
                        @else
                            Rp {{ number_format(($operator->subscription_interval === 'yearly' ? (float) $currentPlan->price_yearly : (float) $currentPlan->price_monthly), 0, ',', '.') }}
                        @endif
                    </span>
                    @if (!$currentPlan->isFree())
                        <span class="text-xs text-slate-400 font-bold">/ {{ $operator->subscription_interval ?: 'monthly' }}</span>
                    @endif
                </div>

                <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $autoRenew ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
                        <i class="fa-solid {{ $autoRenew ? 'fa-repeat' : 'fa-hourglass-half' }} text-[9px]"></i>
                        <span>{{ $autoRenew ? __('Auto-Renewing') : __('Manual Term') }}</span>
                    </span>
                    @if ($operator->plan_expires_at)
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">
                            &bull; {{ __('Renews :date', ['date' => Carbon::parse($operator->plan_expires_at)->format('d M Y')]) }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800">
                <a
                    href="{{ route('settings.plan') }}"
                    wire:navigate
                    class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1.5"
                >
                    <span>{{ __('Change plan or auto-renew settings') }}</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Card 2: Billing Contact Info -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Billing Contact') }}
                </span>
                <button
                    type="button"
                    wire:click="$set('show_billing_info_modal', true)"
                    class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                >
                    {{ __('Edit') }}
                </button>
            </div>

            <div class="space-y-1.5 text-xs">
                <div>
                    <span class="text-[10px] text-slate-400 uppercase font-bold block">{{ __('Invoice Recipient') }}</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 block truncate">{{ $company_legal_name ?: ($operator->name ?? 'Default Entity') }}</span>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 uppercase font-bold block">{{ __('Billing Email') }}</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300 block truncate">{{ $billing_email ?: (auth()->user()->email ?? '-') }}</span>
                </div>
                @if ($tax_id)
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase font-bold block">{{ __('Tax / NPWP ID') }}</span>
                        <span class="font-mono text-slate-700 dark:text-slate-300 block">{{ $tax_id }}</span>
                    </div>
                @endif
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800">
                <p class="text-[11px] text-slate-400">
                    {{ __('Invoices and payment receipts are addressed to this billing contact.') }}
                </p>
            </div>
        </div>

        <!-- Card 3: Invoiced Total Summary -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('Total Invoiced YTD') }}
                </span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 text-xs">
                    <i class="fa-solid fa-receipt"></i>
                </span>
            </div>

            <div>
                <span class="text-2xl font-black font-mono text-slate-900 dark:text-white block">
                    Rp {{ number_format($totalPaidSum, 0, ',', '.') }}
                </span>
                <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">
                    {{ __(':count paid subscription invoice(s) on record', ['count' => $totalPaidCount]) }}
                </span>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center gap-2 text-emerald-600 dark:text-emerald-400 text-xs font-bold">
                <i class="fa-solid fa-shield-check text-xs"></i>
                <span>{{ __('Secure 256-bit encrypted billing') }}</span>
            </div>
        </div>
    </div>

    <!-- Invoices & Payment History Section -->
    <div class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-100 dark:border-zinc-800">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar text-indigo-600 dark:text-indigo-400"></i>
                    <span>{{ __('Invoices & Payment History') }}</span>
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Official tax invoices, subscription renewals, and proration credit receipts.') }}
                </p>
            </div>
        </div>

        @if ($this->invoices->isEmpty())
            <!-- Empty State -->
            <div class="py-14 text-center space-y-3">
                <div class="w-14 h-14 rounded-3xl bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-slate-500 flex items-center justify-center text-2xl mx-auto shadow-inner">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div class="space-y-1 max-w-sm mx-auto">
                    <h4 class="text-sm font-extrabold text-slate-800 dark:text-slate-200">
                        {{ __('No Invoices Generated Yet') }}
                    </h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ __('When you upgrade your tier or renew your plan, itemized receipts and tax invoices will appear here.') }}
                    </p>
                </div>
                @if ($currentPlan->isFree())
                    <div class="pt-2">
                        <a
                            href="{{ route('settings.plan') }}"
                            wire:navigate
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:bg-indigo-100 transition"
                        >
                            <i class="fa-solid fa-arrow-up text-xs"></i>
                            <span>{{ __('Explore Premium Growth & Pro Plans') }}</span>
                        </a>
                    </div>
                @endif
        @else
            <!-- Mobile Card List View (md:hidden) -->
            <div class="md:hidden space-y-3 transition-opacity duration-200" wire:loading.class="opacity-60">
                @foreach ($this->invoices as $payment)
                    <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono font-extrabold text-xs text-slate-900 dark:text-white">
                                {{ $payment->invoice_number }}
                            </span>
                            @if ($payment->status === 'completed')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    <i class="fa-solid fa-check text-[9px]"></i>
                                    <span>{{ __('Paid') }}</span>
                                </span>
                            @elseif ($payment->status === 'pending')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                    <i class="fa-solid fa-clock text-[9px]"></i>
                                    <span>{{ __('Pending') }}</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                    <i class="fa-solid fa-xmark text-[9px]"></i>
                                    <span>{{ ucfirst($payment->status) }}</span>
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100 dark:border-zinc-800">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Plan / Description') }}</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 block truncate">{{ $payment->plan?->name ?? 'Custom Tier' }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Net Amount') }}</span>
                                <span class="font-mono font-black text-slate-900 dark:text-white block">Rp {{ number_format((float) $payment->net_amount_paid, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800 text-[11px] text-slate-500">
                            <span class="truncate">
                                {{ $payment->paid_at ? Carbon::parse($payment->paid_at)->format('d M Y') : Carbon::parse($payment->created_at)->format('d M Y') }}
                            </span>
                            <div class="flex items-center gap-2">
                                @if ($payment->status === 'pending')
                                    <a href="{{ route('settings.plan.checkout', $payment) }}" wire:navigate class="px-3 py-1 rounded-xl bg-purple-600 text-white font-bold text-xs shadow-2xs">
                                        {{ __('Pay Now') }}
                                    </a>
                                @endif
                                <button type="button" wire:click="viewInvoice('{{ $payment->id }}')" class="px-3 py-1 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 font-bold text-xs">
                                    {{ __('View Receipt') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Desktop Invoices Table (hidden on mobile) -->
            <div class="hidden md:block overflow-x-auto transition-opacity duration-200" wire:loading.class="opacity-60">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200/80 dark:border-zinc-800 text-[11px] font-black uppercase text-slate-400 tracking-wider">
                            <th class="pb-3 px-3">{{ __('Invoice #') }}</th>
                            <th class="pb-3 px-3">{{ __('Date') }}</th>
                            <th class="pb-3 px-3">{{ __('Plan / Description') }}</th>
                            <th class="pb-3 px-3">{{ __('Payment Method') }}</th>
                            <th class="pb-3 px-3 text-right">{{ __('Net Amount') }}</th>
                            <th class="pb-3 px-3 text-center">{{ __('Status') }}</th>
                            <th class="pb-3 px-3 text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                        @foreach ($this->invoices as $payment)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition">
                                <!-- Invoice Number -->
                                <td class="py-3.5 px-3 font-mono font-bold text-slate-900 dark:text-white">
                                    {{ $payment->invoice_number }}
                                </td>

                                <!-- Date -->
                                <td class="py-3.5 px-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    {{ $payment->paid_at ? Carbon::parse($payment->paid_at)->format('d M Y, H:i') : Carbon::parse($payment->created_at)->format('d M Y, H:i') }}
                                </td>

                                <!-- Plan / Description -->
                                <td class="py-3.5 px-3">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ $payment->plan?->name ?? 'Custom Tier' }}
                                    </div>
                                    <span class="text-[10px] text-slate-400 uppercase font-medium">
                                        {{ ucfirst($payment->type ?: 'Upgrade') }} &bull; {{ ucfirst($payment->billing_interval) }}
                                    </span>
                                </td>

                                <!-- Payment Method -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-medium">
                                        @if ($payment->gateway === 'credit_card' || $payment->gateway === 'cc')
                                            <i class="fa-solid fa-credit-card text-indigo-500"></i>
                                            <span>Credit Card</span>
                                        @elseif ($payment->gateway === 'qris')
                                            <i class="fa-solid fa-qrcode text-indigo-500"></i>
                                            <span>QRIS</span>
                                        @elseif ($payment->gateway === 'va')
                                            <i class="fa-solid fa-building-columns text-indigo-500"></i>
                                            <span>Virtual Account</span>
                                        @else
                                            <i class="fa-solid fa-bolt text-amber-500"></i>
                                            <span>Sandbox Simulation</span>
                                        @endif
                                    </span>
                                </td>

                                <!-- Net Amount Paid -->
                                <td class="py-3.5 px-3 text-right font-mono font-black text-slate-900 dark:text-white whitespace-nowrap">
                                    Rp {{ number_format((float) $payment->net_amount_paid, 0, ',', '.') }}
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    @if ($payment->status === 'completed')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                            <i class="fa-solid fa-check text-[9px]"></i>
                                            <span>{{ __('Paid') }}</span>
                                        </span>
                                    @elseif ($payment->status === 'pending')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                            <i class="fa-solid fa-clock text-[9px]"></i>
                                            <span>{{ __('Pending') }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                            <i class="fa-solid fa-xmark text-[9px]"></i>
                                            <span>{{ ucfirst($payment->status) }}</span>
                                        </span>
                                    @endif
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-3.5 px-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($payment->status === 'pending')
                                            <a
                                                href="{{ route('settings.plan.checkout', $payment) }}"
                                                wire:navigate
                                                class="px-3 py-1 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-[11px] transition shadow-xs flex items-center gap-1"
                                            >
                                                <i class="fa-solid fa-credit-card text-[10px]"></i>
                                                <span>{{ __('Pay Now') }}</span>
                                            </a>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="viewInvoice('{{ $payment->id }}')"
                                            class="px-3 py-1 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-[11px] transition cursor-pointer flex items-center gap-1"
                                        >
                                            <i class="fa-solid fa-file-invoice text-[10px] text-slate-400"></i>
                                            <span>{{ __('View Receipt') }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                {{ $this->invoices->links() }}
            </div>
        @endif
    </div>

    <!-- View Itemized Invoice Modal -->
    @if ($show_invoice_modal && $this->selectedInvoice)
        @php
            $inv = $this->selectedInvoice;
            $breakdown = $inv->breakdown ?? [];
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6 select-none animate-fade-in">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs transition-opacity" wire:click="closeInvoiceModal"></div>

            <!-- Invoice Modal Content -->
            <div class="relative w-full max-w-xl rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 sm:p-8 space-y-6 z-10">
                <!-- Modal Top Header -->
                <div class="flex items-start justify-between gap-4 pb-5 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg font-black shadow-xs">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">{{ __('Official Subscription Receipt') }}</span>
                            <h3 class="text-lg font-black font-mono text-slate-900 dark:text-white">
                                {{ $inv->invoice_number }}
                            </h3>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick="window.print()"
                            class="h-8 px-3 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer"
                        >
                            <i class="fa-solid fa-print text-xs"></i>
                            <span>{{ __('Print') }}</span>
                        </button>

                        <button
                            type="button"
                            wire:click="closeInvoiceModal"
                            class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition cursor-pointer"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Invoice Meta & Customer Details -->
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Billed To') }}</span>
                        <p class="font-extrabold text-slate-900 dark:text-white mt-1">{{ $company_legal_name ?: ($operator->name ?? 'Valued Operator') }}</p>
                        <p class="text-slate-500 dark:text-slate-400 text-[11px]">{{ $billing_email ?: (auth()->user()->email ?? '') }}</p>
                        @if ($tax_id)
                            <p class="text-slate-500 dark:text-slate-400 text-[11px] font-mono">{{ __('NPWP: :id', ['id' => $tax_id]) }}</p>
                        @endif
                    </div>

                    <div class="text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Payment Details') }}</span>
                        <p class="font-bold text-slate-900 dark:text-white mt-1">
                            {{ $inv->paid_at ? Carbon::parse($inv->paid_at)->format('d M Y, H:i') : Carbon::parse($inv->created_at)->format('d M Y') }}
                        </p>
                        <p class="text-slate-500 dark:text-slate-400 text-[11px]">
                            {{ __('Gateway Ref: :ref', ['ref' => $inv->gateway_ref ?: 'SIM-INSTANT']) }}
                        </p>
                        <div class="mt-1">
                            @if ($inv->status === 'completed')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ __('Paid in Full') }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                    {{ ucfirst($inv->status) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Itemized Breakdown -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700/80 space-y-3 text-xs">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200/70 dark:border-zinc-700 text-slate-500 dark:text-slate-400 font-bold">
                        <span>{{ __('Description') }}</span>
                        <span>{{ __('Amount') }}</span>
                    </div>

                    <div class="flex items-center justify-between font-bold text-slate-800 dark:text-slate-200">
                        <div>
                            <span>{{ __(':plan Subscription Tier (:interval)', ['plan' => $inv->plan?->name ?? 'Growth Plan', 'interval' => ucfirst($inv->billing_interval)]) }}</span>
                            <span class="text-[10px] text-slate-400 font-normal block">{{ ucfirst($inv->type ?: 'Upgrade') }} subscription term</span>
                        </div>
                        <span class="font-mono font-bold">
                            Rp {{ number_format((float) $inv->gross_amount, 0, ',', '.') }}
                        </span>
                    </div>

                    @if ((float) $inv->prorated_credit > 0)
                        <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400 font-bold">
                            <div>
                                <span>{{ __('Prorated Unused Credit Applied') }}</span>
                                <span class="text-[10px] opacity-80 font-normal block">{{ __('Credit from remaining days on previous plan') }}</span>
                            </div>
                            <span class="font-mono">
                                - Rp {{ number_format((float) $inv->prorated_credit, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    <div class="pt-3 border-t border-slate-200 dark:border-zinc-700 flex items-center justify-between">
                        <div>
                            <span class="font-black text-sm text-slate-900 dark:text-white block">{{ __('Total Net Paid') }}</span>
                            <span class="text-[10px] text-slate-400">{{ __('Includes all platform services & licensing') }}</span>
                        </div>
                        <span class="text-xl font-black font-mono text-purple-600 dark:text-purple-400">
                            Rp {{ number_format((float) $inv->net_amount_paid, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        wire:click="closeInvoiceModal"
                        class="px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition cursor-pointer"
                    >
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit Billing Information Modal -->
    @if ($show_billing_info_modal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6 select-none animate-fade-in">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs transition-opacity" wire:click="$set('show_billing_info_modal', false)"></div>

            <!-- Modal Content Card -->
            <div class="relative w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden p-6 sm:p-7 space-y-5 z-10">
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">
                            {{ __('Edit Billing Contact Details') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Update the legal company name and email used on your subscription tax invoices.') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="$set('show_billing_info_modal', false)"
                        class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition cursor-pointer"
                    >
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>

                <form wire:submit="updateBillingInfo" class="space-y-4">
                    <div>
                        <x-label for="company_legal_name" :value="__('Legal Company / Entity Name')" required />
                        <x-input id="company_legal_name" type="text" wire:model="company_legal_name" placeholder="PT Bali Adventure Nusantara" :error="$errors->has('company_legal_name')" />
                        <x-input-error :messages="$errors->get('company_legal_name')" />
                    </div>

                    <div>
                        <x-label for="billing_email" :value="__('Billing & Invoicing Email')" required />
                        <x-input id="billing_email" type="email" wire:model="billing_email" placeholder="finance@company.com" :error="$errors->has('billing_email')" />
                        <x-input-error :messages="$errors->get('billing_email')" />
                    </div>

                    <div>
                        <x-label for="tax_id" :value="__('Tax / NPWP Identification (Optional)')" />
                        <x-input id="tax_id" type="text" wire:model="tax_id" placeholder="01.234.567.8-901.000" :error="$errors->has('tax_id')" />
                        <x-input-error :messages="$errors->get('tax_id')" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                        <button
                            type="button"
                            wire:click="$set('show_billing_info_modal', false)"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>

                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <i class="fa-solid fa-check text-xs"></i>
                            <span>{{ __('Save Billing Info') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
