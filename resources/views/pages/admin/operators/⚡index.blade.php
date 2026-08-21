<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Services\DomainResolverService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Operators Management')] #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status_filter = 'all';

    // Operator Details Drawer / Modal
    public ?string $selected_operator_id = null;
    public bool $show_details_modal = false;

    /**
     * Reset pagination when searching or filtering.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Open operator details modal.
     */
    public function viewDetails(string $operatorId): void
    {
        $this->selected_operator_id = $operatorId;
        $this->show_details_modal = true;
    }

    public function closeDetails(): void
    {
        $this->show_details_modal = false;
        $this->selected_operator_id = null;
    }

    /**
     * Switch context to manage the specified operator in the operator portal.
     */
    public function manageOperator(string $operatorId): void
    {
        session(['admin_impersonated_operator_id' => $operatorId]);
        $this->redirect(route('dashboard'), navigate: true);
    }

    public bool $showConfirmOperatorStatusModal = false;
    public ?string $statusActionOperatorId = null;
    public ?string $pendingOperatorStatus = null;

    #[Computed]
    public function pendingStatusOperator(): ?Operator
    {
        if (! $this->statusActionOperatorId) {
            return null;
        }

        return Operator::find($this->statusActionOperatorId);
    }

    public function confirmOperatorStatus(string $operatorId, string $status): void
    {
        $this->statusActionOperatorId = $operatorId;
        $this->pendingOperatorStatus = $status;
        $this->showConfirmOperatorStatusModal = true;
    }

    public function closeConfirmOperatorStatusModal(): void
    {
        $this->showConfirmOperatorStatusModal = false;
        $this->statusActionOperatorId = null;
        $this->pendingOperatorStatus = null;
    }

    public function executeOperatorStatus(): void
    {
        if ($this->statusActionOperatorId && $this->pendingOperatorStatus) {
            $this->updateStatus($this->statusActionOperatorId, $this->pendingOperatorStatus);
        }

        $this->closeConfirmOperatorStatusModal();
    }

    /**
     * Update operator approval status.
     */
    public function updateStatus(string $operatorId, string $status): void
    {
        $operator = Operator::find($operatorId);
        if ($operator) {
            $operatorStatus = match ($status) {
                'approved' => OperatorStatus::Approved,
                'suspended' => OperatorStatus::Suspended,
                default => OperatorStatus::Pending,
            };

            $operator->update(['status' => $operatorStatus]);
            $this->dispatch('operator-status-updated', ['name' => $operator->name, 'status' => $operatorStatus->label()]);
        }
    }

    /**
     * Assign / update operator subscription plan tier.
     */
    public function assignPlan(string $operatorId, ?string $planId): void
    {
        $operator = Operator::find($operatorId);
        if ($operator) {
            $operator->update([
                'plan_id' => $planId ?: null,
                'subscribed_at' => $planId ? now() : null,
            ]);
            $this->dispatch('operator-status-updated', ['name' => $operator->name, 'status' => 'Plan Updated']);
        }
    }

    /**
     * Render the component with filtered operators and statistics.
     */
    public function with(): array
    {
        $query = Operator::query()
            ->with(['users', 'packages', 'products', 'plan'])
            ->withCount(['packages', 'products', 'reservations']);

        if (! empty($this->search)) {
            $s = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                    ->orWhere('slug', 'like', $s)
                    ->orWhere('contact_whatsapp', 'like', $s)
                    ->orWhere('bank_account_number', 'like', $s)
                    ->orWhere('bank_account_name', 'like', $s)
                    ->orWhereHas('users', function ($uq) use ($s) {
                        $uq->where('email', 'like', $s)->orWhere('name', 'like', $s);
                    });
            });
        }

        if ($this->status_filter !== 'all') {
            $query->where('status', $this->status_filter);
        }

        $operators = $query->latest()->paginate(10);

        $platformDomain = app(DomainResolverService::class)->getPlatformDomain();

        $selectedOperator = $this->selected_operator_id
            ? Operator::with(['users', 'packages', 'products', 'reservations', 'plan'])->find($this->selected_operator_id)
            : null;

        return [
            'operators' => $operators,
            'selectedOperator' => $selectedOperator,
            'platformDomain' => $platformDomain,
            'allPlans' => \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get(),
            'totalCount' => Operator::count(),
            'approvedCount' => Operator::where('status', OperatorStatus::Approved)->count(),
            'pendingCount' => Operator::where('status', OperatorStatus::Pending)->count(),
            'suspendedCount' => Operator::where('status', OperatorStatus::Suspended)->count(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400">
                    <i class="fa-solid fa-users-gear text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Operators Management') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Monitor all registered tour operators, manage their portal, audit settlement accounts, and update storefront status.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Overview Counters -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Total Operators') }}</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $totalCount }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Approved & Live') }}</span>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $approvedCount }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ __('Pending Review') }}</span>
            <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ $pendingCount }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">{{ __('Suspended') }}</span>
            <div class="text-2xl font-black text-rose-600 dark:text-rose-400">{{ $suspendedCount }}</div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <!-- Search -->
        <div class="relative w-full sm:w-96">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <x-input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="{{ __('Search operator name, owner email, WhatsApp, or bank...') }}"
                class="pl-9 text-xs"
            />
        </div>

        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
            @php
                $statusTabs = [
                    'all' => __('All (:count)', ['count' => $totalCount]),
                    'approved' => __('Approved (:count)', ['count' => $approvedCount]),
                    'pending' => __('Pending (:count)', ['count' => $pendingCount]),
                    'suspended' => __('Suspended (:count)', ['count' => $suspendedCount]),
                ];
            @endphp

            @foreach ($statusTabs as $val => $label)
                <button
                    type="button"
                    wire:click="$set('status_filter', '{{ $val }}')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer {{ $status_filter === $val ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-zinc-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Operator Table / Directory -->
    <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/30 text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Operator / Brand') }}</th>
                        <th class="py-3.5 px-4">{{ __('Owner & Contact') }}</th>
                        <th class="py-3.5 px-4">{{ __('Settlement Bank') }}</th>
                        <th class="py-3.5 px-4 text-center">{{ __('Plan Tier') }}</th>
                        <th class="py-3.5 px-4 text-center">{{ __('Inventory') }}</th>
                        <th class="py-3.5 px-4 text-center">{{ __('Gateway') }}</th>
                        <th class="py-3.5 px-4 text-center">{{ __('Status') }}</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60 font-medium">
                    @forelse ($operators as $operator)
                        @php
                            $owner = $operator->users->first();
                            $storeUrl = request()->getScheme() . '://' . $operator->slug . '.' . $platformDomain;
                            $hasCustomGateway = $operator->hasCustomPaymentGateway();
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Operator Info & Logo -->
                            <td class="py-4 px-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300 font-extrabold flex items-center justify-center text-sm shrink-0 overflow-hidden border border-purple-200 dark:border-purple-800/50">
                                        @if ($operator->logo_path)
                                            <img src="{{ Storage::url($operator->logo_path) }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                        @else
                                            {{ strtoupper(substr($operator->name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 dark:text-white truncate">
                                            {{ $operator->name }}
                                        </div>
                                        <a href="{{ $storeUrl }}" target="_blank" class="text-[11px] text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1">
                                            <span>{{ $operator->slug }}.{{ $platformDomain }}</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>

                            <!-- Owner & Contact -->
                            <td class="py-4 px-4">
                                <div class="space-y-0.5">
                                    <div class="text-slate-900 dark:text-white font-semibold">{{ $owner?->name ?? 'Unassigned' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $owner?->email ?? '-' }}</div>
                                    @if ($operator->contact_whatsapp)
                                        <div class="text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                            <i class="fa-brands fa-whatsapp"></i>
                                            <span>{{ $operator->contact_whatsapp }}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Bank Settlement -->
                            <td class="py-4 px-4">
                                @if ($operator->bank_provider && $operator->bank_account_number)
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-zinc-800 text-[10px]">{{ $operator->bank_provider }}</span>
                                            <span class="font-mono text-xs">{{ $operator->bank_account_number }}</span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 truncate max-w-[160px]">
                                            {{ $operator->bank_account_name ?? '-' }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[11px] text-amber-500 font-semibold flex items-center gap-1">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        {{ __('No Bank Set') }}
                                    </span>
                                @endif
                            </td>

                            <!-- Plan Tier Badge -->
                            <td class="py-4 px-4 text-center">
                                @php
                                    $activePlan = $operator->getPlan();
                                    $planBadgeColor = match ($activePlan->slug) {
                                        'enterprise' => 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                        'growth' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
                                        default => 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300 border-slate-200 dark:border-zinc-700',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase border {{ $planBadgeColor }}">
                                    {{ $activePlan->name }}
                                </span>
                            </td>

                            <!-- Inventory Counts -->
                            <td class="py-4 px-4 text-center">
                                <div class="inline-flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-bold text-[11px]" title="{{ __('Packages') }}">
                                        <i class="fa-solid fa-cube mr-1 text-[10px]"></i>{{ $operator->packages_count }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 font-bold text-[11px]" title="{{ __('Products') }}">
                                        <i class="fa-solid fa-box-open mr-1 text-[10px]"></i>{{ $operator->products_count }}
                                    </span>
                                </div>
                            </td>

                            <!-- Payment Gateway Type -->
                            <td class="py-4 px-4 text-center">
                                @if ($hasCustomGateway)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300">
                                        {{ __('BYO Custom') }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                        {{ __('Platform DOKU') }}
                                    </span>
                                @endif
                            </td>

                            <!-- Status Badge -->
                            <td class="py-4 px-4 text-center">
                                @php
                                    $statusBadgeClasses = match ($operator->status) {
                                        OperatorStatus::Approved => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300',
                                        OperatorStatus::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300',
                                        OperatorStatus::Suspended => 'bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300',
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider {{ $statusBadgeClasses }}">
                                    {{ $operator->status->label() }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Manage / Impersonate as Operator Button -->
                                    <button
                                        type="button"
                                        wire:click="manageOperator('{{ $operator->id }}')"
                                        class="h-8 px-2.5 inline-flex items-center gap-1 rounded-lg bg-purple-50 dark:bg-purple-950/70 hover:bg-purple-100 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-300 text-xs font-bold transition cursor-pointer border border-purple-200 dark:border-purple-800/60"
                                        title="{{ __('Open & manage this operator portal') }}"
                                    >
                                        <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                                        <span>{{ __('Manage') }}</span>
                                    </button>

                                    <!-- Details Button -->
                                    <button
                                        type="button"
                                        wire:click="viewDetails('{{ $operator->id }}')"
                                        class="h-8 px-2.5 inline-flex items-center gap-1 rounded-lg bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition cursor-pointer"
                                        title="{{ __('View Operator Dossier & Settings') }}"
                                    >
                                        <i class="fa-solid fa-eye text-xs"></i>
                                        <span>{{ __('Details') }}</span>
                                    </button>

                                    <!-- Quick Status Toggle Dropdown -->
                                    <x-dropdown position="bottom-end">
                                        <x-slot name="trigger">
                                            <button type="button" class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-600 dark:text-slate-400 text-xs transition cursor-pointer">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <x-dropdown-item wire:click="confirmOperatorStatus('{{ $operator->id }}', 'approved')">
                                                <i class="fa-solid fa-circle-check mr-2 text-emerald-500 text-xs"></i>
                                                {{ __('Approve & Set Active') }}
                                            </x-dropdown-item>
                                            <x-dropdown-item wire:click="confirmOperatorStatus('{{ $operator->id }}', 'pending')">
                                                <i class="fa-solid fa-clock mr-2 text-amber-500 text-xs"></i>
                                                {{ __('Mark as Pending Review') }}
                                            </x-dropdown-item>
                                            <x-dropdown-item wire:click="confirmOperatorStatus('{{ $operator->id }}', 'suspended')">
                                                <i class="fa-solid fa-ban mr-2 text-rose-500 text-xs"></i>
                                                {{ __('Suspend Storefront') }}
                                            </x-dropdown-item>
                                        </x-slot>
                                    </x-dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-users-slash text-3xl mb-2 block opacity-40"></i>
                                <span class="font-bold text-sm">{{ __('No matching operators found') }}</span>
                                <p class="text-xs text-slate-500 mt-0.5">{{ __('Try clearing filters or adjusting your search term.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($operators->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-zinc-800">
                {{ $operators->links() }}
            </div>
        @endif
    </div>

    <!-- Operator Dossier / Details Modal -->
    @if ($show_details_modal && $selectedOperator)
        @teleport('body')
            @php
                $modalOwner = $selectedOperator->users->first();
                $modalStoreUrl = request()->getScheme() . '://' . $selectedOperator->slug . '.' . $platformDomain;
                $waSchedule = $selectedOperator->settings['whatsapp_schedule'] ?? [];
                $socialLinks = $selectedOperator->settings['social_links'] ?? [];
                $storeSettings = $selectedOperator->settings['storefront'] ?? [];
            @endphp
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-2xl bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200/80 dark:border-zinc-800 shadow-2xl flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white font-extrabold flex items-center justify-center text-sm shadow-xs shrink-0 mt-0.5">
                                {{ strtoupper(substr($selectedOperator->name, 0, 1)) }}
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ $selectedOperator->name }}
                                </h3>
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ $selectedOperator->slug }}.{{ $platformDomain }}</span>
                            </div>
                        </div>

                        <button type="button" wire:click="closeDetails" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div class="p-6 overflow-y-auto space-y-6 text-xs max-h-[75vh]">
                        <!-- Status & Quick Links -->
                        <div class="flex items-center justify-between p-3.5 rounded-2xl bg-purple-50/50 dark:bg-purple-950/30 border border-purple-200/60 dark:border-purple-800/60">
                            <div class="space-y-0.5">
                                <span class="text-[10px] uppercase font-bold text-purple-700 dark:text-purple-300 block">{{ __('Account Status') }}</span>
                                <span class="font-black text-sm text-slate-900 dark:text-white">{{ $selectedOperator->status->label() }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-button
                                    size="sm"
                                    type="button"
                                    wire:click="manageOperator('{{ $selectedOperator->id }}')"
                                    class="bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs"
                                >
                                    <i class="fa-solid fa-arrow-right-to-bracket mr-1 text-xs"></i>
                                    <span>{{ __('Open Operator Portal') }}</span>
                                </x-button>
                                <a href="{{ $modalStoreUrl }}" target="_blank" class="h-9 px-3 inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 text-slate-700 dark:text-slate-200 font-bold text-xs shadow-xs transition">
                                    <span>{{ __('Visit Storefront') }}</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Subscription Plan Tier Assignment -->
                        <div class="p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-200/60 dark:border-indigo-800/60 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="space-y-0.5">
                                    <span class="text-[10px] uppercase font-bold text-indigo-700 dark:text-indigo-300 block">{{ __('Active Subscription Plan') }}</span>
                                    <span class="font-black text-sm text-slate-900 dark:text-white">{{ $selectedOperator->plan?->name ?? __('Free Tier') }}</span>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                                    {{ $selectedOperator->getEffectiveCommissionRate() * 100 }}% {{ __('Take Rate') }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 pt-2 border-t border-indigo-200/60 dark:border-indigo-800/40">
                                <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 shrink-0">{{ __('Switch Plan:') }}</span>
                                <div class="flex-1">
                                    <x-select
                                        wire:change="assignPlan('{{ $selectedOperator->id }}', $event.target.value)"
                                        class="w-full text-xs font-semibold"
                                    >
                                        @foreach ($allPlans as $p)
                                            <option value="{{ $p->id }}" {{ $selectedOperator->plan_id === $p->id ? 'selected' : '' }}>
                                                {{ $p->name }} (Rp {{ number_format((float) $p->price_monthly, 0, ',', '.') }}/mo • {{ $p->commission_rate * 100 }}%)
                                            </option>
                                        @endforeach
                                    </x-select>
                                </div>
                            </div>
                        </div>

                        <!-- Owner & Account Contacts -->
                        <div class="space-y-2">
                            <h4 class="font-bold uppercase tracking-wider text-[11px] text-slate-400 dark:text-slate-500">{{ __('Owner & Notification Routing') }}</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-800">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">{{ __('Owner Full Name') }}</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ $modalOwner?->name ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">{{ __('Owner Account Email') }}</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ $modalOwner?->email ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">{{ __('Booking Notifications Email') }}</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $selectedOperator->booking_notification_email ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">{{ __('Billing & Settlement Email') }}</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $selectedOperator->billing_email ?? '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Account Payout Details -->
                        <div class="space-y-2">
                            <h4 class="font-bold uppercase tracking-wider text-[11px] text-slate-400 dark:text-slate-500">{{ __('Direct Bank Settlement Details') }}</h4>
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-800 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">{{ __('Bank Provider') }}</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ $selectedOperator->bank_provider ?? 'Not Set' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">{{ __('Account Number') }}</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $selectedOperator->bank_account_number ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">{{ __('Beneficiary Name') }}</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ $selectedOperator->bank_account_name ?? '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- WhatsApp Operating Hours & Support -->
                        <div class="space-y-2">
                            <h4 class="font-bold uppercase tracking-wider text-[11px] text-slate-400 dark:text-slate-500">{{ __('WhatsApp & Support Hours') }}</h4>
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-800 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('WhatsApp Number:') }} <strong class="text-slate-900 dark:text-white">{{ $selectedOperator->contact_whatsapp ?? 'None' }}</strong></span>
                                    <span class="text-slate-500">{{ __('Live Hours:') }} <strong class="text-indigo-600 dark:text-indigo-400">{{ $selectedOperator->getWhatsAppScheduleSummary() }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Storefront Features -->
                        <div class="space-y-2">
                            <h4 class="font-bold uppercase tracking-wider text-[11px] text-slate-400 dark:text-slate-500">{{ __('Storefront Features') }}</h4>
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-800 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <span class="text-slate-500">{{ __('Standalone Products Selling:') }}</span>
                                    <strong class="ml-1 text-slate-900 dark:text-white">{{ ($storeSettings['allow_standalone_products'] ?? true) ? __('Enabled') : __('Disabled') }}</strong>
                                </div>
                                <div>
                                    <span class="text-slate-500">{{ __('Guest Reviews Display:') }}</span>
                                    <strong class="ml-1 text-slate-900 dark:text-white">{{ ($storeSettings['show_reviews'] ?? true) ? __('Enabled') : __('Hidden') }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-5 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-3 bg-slate-50/50 dark:bg-zinc-800/40 rounded-b-3xl">
                        <div class="flex items-center gap-2">
                            @if ($selectedOperator->status !== OperatorStatus::Approved)
                                <x-button
                                    size="sm"
                                    type="button"
                                    wire:click="confirmOperatorStatus('{{ $selectedOperator->id }}', 'approved')"
                                    class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs"
                                >
                                    <i class="fa-solid fa-circle-check mr-1 text-xs"></i>
                                    {{ __('Approve Operator') }}
                                </x-button>
                            @endif

                            @if ($selectedOperator->status !== OperatorStatus::Suspended)
                                <x-button
                                    size="sm"
                                    type="button"
                                    wire:click="confirmOperatorStatus('{{ $selectedOperator->id }}', 'suspended')"
                                    variant="danger"
                                    class="text-xs font-bold"
                                >
                                    <i class="fa-solid fa-ban mr-1 text-xs"></i>
                                    {{ __('Suspend Operator') }}
                                </x-button>
                            @endif
                        </div>

                        <x-button size="sm" variant="secondary" wire:click="closeDetails" class="text-xs font-bold">
                            {{ __('Close') }}
                        </x-button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Confirm Operator Status Modal -->
    @if ($showConfirmOperatorStatusModal && $this->pendingStatusOperator)
        @php
            $pendingOperator = $this->pendingStatusOperator;
        @endphp
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto" wire:keydown.escape="closeConfirmOperatorStatusModal">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl border border-slate-200/80 dark:border-zinc-800 flex flex-col my-8" @click.outside="$wire.closeConfirmOperatorStatusModal()">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            @if ($pendingOperatorStatus === 'approved')
                                <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-circle-check"></i>
                                </div>
                            @elseif ($pendingOperatorStatus === 'suspended')
                                <div class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-ban"></i>
                                </div>
                            @else
                                <div class="w-10 h-10 rounded-2xl bg-amber-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                            @endif
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    @if ($pendingOperatorStatus === 'approved')
                                        {{ __('Approve Operator Account') }}
                                    @elseif ($pendingOperatorStatus === 'suspended')
                                        {{ __('Suspend Operator Account') }}
                                    @else
                                        {{ __('Mark as Pending Review') }}
                                    @endif
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    @if ($pendingOperatorStatus === 'approved')
                                        {{ __('Grant full storefront and payment capabilities to this operator.') }}
                                    @elseif ($pendingOperatorStatus === 'suspended')
                                        {{ __('Immediately deactivate this operator\'s storefront and booking engine.') }}
                                    @else
                                        {{ __('Set this operator back to pending verification status.') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeConfirmOperatorStatusModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body / Summary Card -->
                    <div class="p-6 space-y-4">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-extrabold text-sm text-slate-900 dark:text-white">{{ $pendingOperator->name }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-slate-200 dark:bg-zinc-700 text-slate-700 dark:text-slate-300">
                                    {{ $pendingOperator->status->label() }} &rarr; {{ ucfirst($pendingOperatorStatus) }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 font-mono">{{ $pendingOperator->slug }}.{{ $platformDomain }}</p>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                            <x-button type="button" variant="secondary" wire:click="closeConfirmOperatorStatusModal" class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>

                            @if ($pendingOperatorStatus === 'approved')
                                <x-button type="button" variant="primary" wire:click="executeOperatorStatus" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                                    <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                                    {{ __('Yes, Approve Operator') }}
                                </x-button>
                            @elseif ($pendingOperatorStatus === 'suspended')
                                <x-button type="button" variant="danger" wire:click="executeOperatorStatus" class="text-xs font-bold">
                                    <i class="fa-solid fa-ban mr-1.5 text-xs"></i>
                                    {{ __('Yes, Suspend Operator') }}
                                </x-button>
                            @else
                                <x-button type="button" variant="secondary" wire:click="executeOperatorStatus" class="text-xs font-bold">
                                    <i class="fa-solid fa-clock mr-1.5 text-xs text-amber-500"></i>
                                    {{ __('Yes, Set to Pending') }}
                                </x-button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
