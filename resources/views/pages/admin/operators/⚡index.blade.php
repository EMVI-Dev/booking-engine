<?php

use App\Concerns\RecordsAdminActions;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\Reservation;
use App\Services\DomainResolverService;
use App\Services\OperatorAccountService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Operators')] #[Layout('layouts.admin')] class extends Component
{
    use RecordsAdminActions;

    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status_filter = 'all';

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
     * Switch context to manage the specified operator in the operator portal.
     */
    public function manageOperator(string $operatorId): void
    {
        app(OperatorAccountService::class)->startManaging(Operator::query()->findOrFail($operatorId));
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function exportCsv(): StreamedResponse
    {
        $fileName = 'operators-'.now()->format('Y-m-d').'.csv';

        $this->audit('export.operators', null, ['status' => $this->status_filter, 'search' => $this->search]);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Slug', 'Status', 'Plan', 'Email', 'WhatsApp', 'Bank Provider', 'Bank Account', 'Bank Account Name', 'Created At']);

            Operator::query()
                ->with(['plan'])
                ->when($this->status_filter !== 'all', function ($query) {
                    $query->where('status', $this->status_filter);
                })
                ->when(filled($this->search), function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', "%{$this->search}%")
                            ->orWhere('slug', 'like', "%{$this->search}%")
                            ->orWhere('booking_notification_email', 'like', "%{$this->search}%");
                    });
                })
                ->latest()
                ->chunk(100, function ($operators) use ($handle) {
                    foreach ($operators as $operator) {
                        fputcsv($handle, [
                            $operator->id,
                            $operator->name,
                            $operator->slug,
                            $operator->status->value ?? (string) $operator->status,
                            $operator->plan?->name ?? 'Free Tier',
                            $operator->booking_notification_email ?? '-',
                            $operator->contact_whatsapp ?? '-',
                            $operator->bank_provider ?? '-',
                            $operator->bank_account_number ?? '-',
                            $operator->bank_account_name ?? '-',
                            $operator->created_at?->format('Y-m-d H:i:s') ?? '-',
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
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
            $operatorStatus = OperatorAccountService::statusFromInput($status);
            app(OperatorAccountService::class)->changeStatus($operator, $operatorStatus);
            $this->dispatch('operator-status-updated', ['name' => $operator->name, 'status' => $operatorStatus->label()]);
        }
    }

    /**
     * Render the component with filtered operators and statistics.
     */
    public function with(): array
    {
        $query = Operator::query()
            ->with(['users', 'plan', 'domains'])
            ->withCount(['reservations', 'packages', 'products']);

        if (! empty($this->search)) {
            $s = '%'.trim($this->search).'%';
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

        $operators = $query->latest()->paginate(15);

        $platformDomain = app(DomainResolverService::class)->getPlatformDomain();

        return [
            'operators' => $operators,
            'platformDomain' => $platformDomain,
            'totalCount' => Operator::count(),
            'approvedCount' => Operator::where('status', OperatorStatus::Approved)->count(),
            'pendingCount' => Operator::where('status', OperatorStatus::Pending)->count(),
            'suspendedCount' => Operator::where('status', OperatorStatus::Suspended)->count(),
            'totalReservationsCount' => Reservation::count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="op-hero flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-1">
        <div>
            <h1 class="text-[20px] font-medium leading-[1.6] text-[#1C2024] dark:text-white">
                {{ __('Operators') }}
            </h1>
            <p class="text-[14px] font-normal leading-[1.43] text-[#60646C] dark:text-slate-400">
                {{ __('Who is on the platform, and whether they can sell.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="exportCsv"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-[6px] text-[12px] font-normal bg-white dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1e2433] hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] text-[#1C2024] dark:text-slate-200 transition cursor-pointer shadow-none"
            >
                <i class="fa-solid fa-download text-[11px] text-[#8B8D98]"></i>
                <span>{{ __('Export CSV') }}</span>
            </button>
        </div>
    </div>

    {{-- Top 4 KPI Metrics --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <button type="button" wire:click="$set('status_filter', 'all')"
            class="op-card op-metric text-left group block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 p-5 shadow-none transition">
            <div class="flex items-center justify-between gap-2">
                <span class="op-metric-label text-[12px] font-normal text-[#60646C] dark:text-slate-400">
                    {{ __('Total Operators') }}
                </span>
                <i class="fa-solid fa-users text-[11px] text-[#8B8D98]"></i>
            </div>
            <div class="mt-2 flex items-baseline">
                <p class="op-metric-value text-[24px] font-medium leading-none text-[#1C2024] dark:text-white">
                    {{ $totalCount }}
                </p>
            </div>
        </button>

        <button type="button" wire:click="$set('status_filter', 'approved')"
            class="op-card op-metric text-left group block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 p-5 shadow-none transition">
            <div class="flex items-center justify-between gap-2">
                <span class="op-metric-label text-[12px] font-normal text-[#60646C] dark:text-slate-400">
                    {{ __('Approved & Live') }}
                </span>
                <i class="fa-solid fa-circle-check text-[11px] text-emerald-500"></i>
            </div>
            <div class="mt-2 flex items-baseline">
                <p class="op-metric-value text-[24px] font-medium leading-none text-[#1C2024] dark:text-white">
                    {{ $approvedCount }}
                </p>
            </div>
        </button>

        <button type="button" wire:click="$set('status_filter', 'pending')"
            class="op-card op-metric text-left group block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 p-5 shadow-none transition">
            <div class="flex items-center justify-between gap-2">
                <span class="op-metric-label text-[12px] font-normal text-[#60646C] dark:text-slate-400">
                    {{ __('Pending Review') }}
                </span>
                <i class="fa-solid fa-clock text-[11px] text-[#F59E0B]"></i>
            </div>
            <div class="mt-2 flex items-baseline">
                <p class="op-metric-value text-[24px] font-medium leading-none text-[#1C2024] dark:text-white">
                    {{ $pendingCount }}
                </p>
            </div>
        </button>

        <button type="button" wire:click="$set('status_filter', 'suspended')"
            class="op-card op-metric text-left group block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] hover:border-[#D1D5DB] dark:hover:border-slate-700 p-5 shadow-none transition">
            <div class="flex items-center justify-between gap-2">
                <span class="op-metric-label text-[12px] font-normal text-[#60646C] dark:text-slate-400">
                    {{ __('Suspended') }}
                </span>
                <i class="fa-solid fa-ban text-[11px] text-rose-500"></i>
            </div>
            <div class="mt-2 flex items-baseline">
                <p class="op-metric-value text-[24px] font-medium leading-none text-[#1C2024] dark:text-white">
                    {{ $suspendedCount }}
                </p>
            </div>
        </button>
    </div>

    {{-- Filter Toolbar --}}
    <x-toolbar class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#8B8D98]"></i>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search operator name, email, WhatsApp, or bank...') }}"
                class="w-full h-8 pl-8 pr-3 rounded-[6px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] text-[13px] text-[#1C2024] dark:text-white placeholder-[#8B8D98] focus:border-[#FFEF4D] focus:ring-1 focus:ring-[#FFEF4D] outline-none shadow-none transition"
            />
        </div>

        <x-filter-tabs>
            <x-filter-tab wire:click="$set('status_filter', 'all')" :active="$status_filter === 'all'">
                {{ __('All (:count)', ['count' => $totalCount]) }}
            </x-filter-tab>
            <x-filter-tab wire:click="$set('status_filter', 'approved')" :active="$status_filter === 'approved'">
                {{ __('Approved (:count)', ['count' => $approvedCount]) }}
            </x-filter-tab>
            <x-filter-tab wire:click="$set('status_filter', 'pending')" :active="$status_filter === 'pending'">
                {{ __('Pending (:count)', ['count' => $pendingCount]) }}
            </x-filter-tab>
            <x-filter-tab wire:click="$set('status_filter', 'suspended')" :active="$status_filter === 'suspended'">
                {{ __('Suspended (:count)', ['count' => $suspendedCount]) }}
            </x-filter-tab>
        </x-filter-tabs>
    </x-toolbar>

    {{-- Operators Directory Section --}}
    <div class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1e2433] shadow-none overflow-hidden">
        {{-- Mobile Responsive Card List (md:hidden) --}}
        <div class="md:hidden space-y-3 p-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($operators as $operator)
                @php
                    $owner = $operator->users->first();
                    $storeUrl = request()->getScheme().'://'.$operator->slug.'.'.$platformDomain;
                    $hasBank = filled($operator->bank_provider) && filled($operator->bank_account_number);
                @endphp
                <div class="p-4 rounded-[8px] bg-white dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1e2433] space-y-3 shadow-none">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                class="w-9 h-9 rounded-[6px] bg-[#FFEF4D] text-[#12181E] font-bold flex items-center justify-center text-xs shrink-0 overflow-hidden shadow-none">
                                @if ($operator->logo_path)
                                    <img src="{{ $operator->logo_url }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                @else
                                    {{ strtoupper(substr($operator->name, 0, 2)) }}
                                @endif
                            </a>
                            <div class="min-w-0">
                                <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                    class="font-medium text-[13px] text-[#1C2024] dark:text-white block truncate hover:text-amber-600 dark:hover:text-[#FFEF4D] transition">
                                    {{ $operator->name }}
                                </a>
                                <a href="{{ $storeUrl }}" target="_blank"
                                    class="font-mono text-[11px] text-[#8B8D98] hover:text-[#1C2024] hover:underline flex items-center gap-1">
                                    <span>{{ $operator->slug }}.{{ $platformDomain }}</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>
                                </a>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium
                            {{ $operator->status === OperatorStatus::Approved ? 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]' : ($operator->status === OperatorStatus::Suspended ? 'bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]' : 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]') }}">
                            {{ $operator->status->label() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-[12px] pt-2 border-t border-[#E4E5E9] dark:border-[#1e2433] text-[#60646C] dark:text-slate-400">
                        <div>
                            <span class="block text-[10px] uppercase font-medium text-[#8B8D98]">{{ __('Catalog') }}</span>
                            <span class="font-normal text-[#1C2024] dark:text-slate-200">{{ $operator->packages_count }} {{ __('Pkgs') }} • {{ $operator->products_count }} {{ __('Acts') }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase font-medium text-[#8B8D98]">{{ __('Last Active') }}</span>
                            <span class="font-normal {{ $operator->last_active_at && $operator->last_active_at->diffInDays(now()) >= 30 ? 'text-[#F59E0B]' : 'text-[#1C2024] dark:text-slate-200' }}">
                                {{ $operator->last_active_at ? $operator->last_active_at->diffForHumans() : __('Never') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-[#E4E5E9] dark:border-[#1e2433] text-[12px]">
                        <span class="text-[#60646C] truncate max-w-[160px]">{{ $owner?->email ?? 'No owner' }}</span>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                wire:click="manageOperator('{{ $operator->id }}')"
                                class="px-2.5 py-1 rounded-[6px] bg-[#FFEF4D] text-[#12181E] hover:bg-[#F3E13A] font-medium text-[12px] transition cursor-pointer"
                            >
                                {{ __('Portal') }}
                            </button>
                            <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                class="px-2.5 py-1 rounded-[6px] bg-white dark:bg-[#1E2433] hover:bg-[#F4F5F6] text-[#1C2024] dark:text-slate-200 border border-[#E4E5E9] dark:border-[#1e2433] text-[12px] font-normal transition">
                                {{ __('Manage') }}
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-[12px] text-[#8B8D98]">
                    {{ __('No operators found') }}
                </div>
            @endforelse
        </div>

        {{-- Desktop Operators Table (hidden on mobile) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1e2433] text-[11px] font-medium uppercase tracking-wider text-[#8B8D98]">
                        <th class="py-3 px-4 sm:px-6">{{ __('Operator') }}</th>
                        <th class="py-3 px-4">{{ __('Plan & Economics') }}</th>
                        <th class="py-3 px-4">{{ __('Owner & Contact') }}</th>
                        <th class="py-3 px-4">{{ __('Performance & Catalog') }}</th>
                        <th class="py-3 px-4">{{ __('Disbursement Bank') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Status') }}</th>
                        <th class="py-3 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1e2433]">
                    @forelse ($operators as $operator)
                        @php
                            $owner = $operator->users->first();
                            $storeUrl = request()->getScheme().'://'.$operator->slug.'.'.$platformDomain;
                            $hasBank = filled($operator->bank_provider) && filled($operator->bank_account_number);
                            $hasWhatsApp = filled($operator->contact_whatsapp);
                            $activePlan = $operator->getPlan();
                            $statusBadgeClasses = match ($operator->status) {
                                OperatorStatus::Approved => 'bg-[#ECFDF5] text-[#065F46] border-[#A7F3D0]',
                                OperatorStatus::Pending => 'bg-[#FFFBEB] text-[#92400E] border-[#FDE68A]',
                                OperatorStatus::Suspended => 'bg-[#FEF2F2] text-[#991B1B] border-[#FECACA]',
                            };
                        @endphp
                        <tr class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/60 transition group">
                            {{-- Column 1: Operator Identity & Subdomain --}}
                            <td class="py-3 px-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                        class="w-8 h-8 rounded-[6px] bg-[#FFEF4D] text-[#12181E] font-bold flex items-center justify-center text-xs shrink-0 overflow-hidden shadow-none">
                                        @if ($operator->logo_path)
                                            <img src="{{ $operator->logo_url }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                        @else
                                            {{ strtoupper(substr($operator->name, 0, 2)) }}
                                        @endif
                                    </a>
                                    <div class="min-w-0 space-y-0.5">
                                        <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                            class="font-medium text-[13px] text-[#1C2024] dark:text-white hover:text-amber-600 dark:hover:text-[#FFEF4D] transition truncate block">
                                            {{ $operator->name }}
                                        </a>
                                        <a href="{{ $storeUrl }}" target="_blank"
                                            class="inline-flex items-center gap-1 text-[11px] font-mono text-[#8B8D98] hover:text-[#1C2024] hover:underline">
                                            <span class="truncate max-w-[200px]">{{ $operator->slug }}.{{ $platformDomain }}</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[8px] opacity-70 shrink-0"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>

                            {{-- Column 2: Plan & Subscription --}}
                            <td class="py-3 px-4">
                                <div class="space-y-1">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[4px] text-[10px] font-medium tracking-wider uppercase bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                        {{ $activePlan->name }}
                                    </span>
                                    <div class="text-[11px] text-[#60646C] dark:text-slate-400">
                                        {{ __('Listed price stays with them') }}
                                    </div>
                                </div>
                            </td>

                            {{-- Column 3: Owner & Contacts --}}
                            <td class="py-3 px-4">
                                <div class="space-y-0.5 min-w-0">
                                    <div class="font-medium text-[#1C2024] dark:text-white text-[13px] truncate max-w-[170px]">
                                        {{ $owner?->name ?? __('No Owner Assigned') }}
                                    </div>
                                    <div class="font-mono text-[11px] text-[#60646C] dark:text-slate-400 truncate max-w-[170px]">
                                        {{ $owner?->email ?? '-' }}
                                    </div>
                                    @if ($hasWhatsApp)
                                        @php
                                            $adminWaUrl = app(\App\Services\WhatsAppDispatchService::class)->buildWhatsAppUrl($operator->contact_whatsapp, __('Hello :name, reaching out from platform administration.', ['name' => $operator->name]));
                                        @endphp
                                        <a href="{{ $adminWaUrl }}" target="_blank"
                                            class="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 hover:underline font-mono">
                                            <i class="fa-brands fa-whatsapp text-[10px]"></i>
                                            <span>{{ $operator->contact_whatsapp }}</span>
                                        </a>
                                    @endif
                                </div>
                            </td>

                            {{-- Column 4: Performance & Catalog --}}
                            <td class="py-3 px-4">
                                <div class="space-y-0.5">
                                    <div class="font-mono font-medium text-[#1C2024] dark:text-white text-[13px]">
                                        Rp {{ number_format($operator->calculated_gmv ?? 0, 0, ',', '.') }}
                                    </div>
                                    <div class="text-[11px] text-[#60646C] dark:text-slate-400 flex items-center gap-1">
                                        <span class="font-medium text-[#1C2024] dark:text-slate-200">{{ $operator->reservations_count }}</span> {{ __('bookings') }}
                                        <span class="opacity-40">•</span>
                                        <span class="font-medium text-[#1C2024] dark:text-slate-200">{{ $operator->packages_count }}</span> {{ __('pkgs') }}
                                    </div>
                                    <div class="text-[10px] text-[#8B8D98] flex items-center gap-1">
                                        <span>{{ __('Active') }}:</span>
                                        @if ($operator->last_active_at)
                                            <span class="{{ $operator->last_active_at->diffInDays(now()) >= 30 ? 'text-[#F59E0B]' : 'text-[#60646C] dark:text-slate-300' }}">
                                                {{ $operator->last_active_at->diffForHumans() }}
                                            </span>
                                        @else
                                            <span>{{ __('Never') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Column 5: Disbursement Bank --}}
                            <td class="py-3 px-4">
                                @if ($hasBank)
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-[4px] text-[10px] font-medium uppercase bg-[#EFEFF0] dark:bg-[#1E2433] text-[#1C2024] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1e2433]">
                                            <i class="fa-solid fa-building-columns text-[9px] text-[#8B8D98]"></i>
                                            {{ $operator->bank_provider }}
                                        </span>
                                        <div class="font-mono text-[11px] text-[#60646C] dark:text-slate-400">
                                            {{ $operator->bank_account_number }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[11px] text-[#8B8D98] italic">{{ __('Not configured') }}</span>
                                @endif
                            </td>

                            {{-- Column 6: Approval Status --}}
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] text-[11px] font-medium border {{ $statusBadgeClasses }}">
                                    <span class="size-1.5 rounded-full {{ $operator->status === OperatorStatus::Approved ? 'bg-emerald-500' : ($operator->status === OperatorStatus::Pending ? 'bg-[#F59E0B]' : 'bg-rose-500') }}"></span>
                                    {{ $operator->status->label() }}
                                </span>
                            </td>

                            {{-- Column 7: Actions --}}
                            <td class="py-3 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                        class="h-7 px-2.5 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1e2433] text-[#1C2024] dark:text-slate-200 text-[12px] font-normal inline-flex items-center gap-1 transition shadow-none">
                                        <span>{{ __('Inspect') }}</span>
                                        <i class="fa-solid fa-arrow-right text-[8px] text-[#8B8D98]"></i>
                                    </a>

                                    <button
                                        type="button"
                                        wire:click="manageOperator('{{ $operator->id }}')"
                                        class="h-7 px-2.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-[12px] font-medium transition cursor-pointer inline-flex items-center gap-1 shadow-none"
                                        title="{{ __('Open operator dashboard portal') }}"
                                    >
                                        <i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i>
                                        <span>{{ __('Portal') }}</span>
                                    </button>

                                    <!-- 3-Dots Action Dropdown -->
                                    <x-dropdown position="bottom-end" width="56">
                                        <x-slot name="trigger">
                                            <button type="button" class="h-7 w-7 rounded-[6px] inline-flex items-center justify-center bg-white dark:bg-[#141821] hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] text-[#60646C] dark:text-slate-400 text-xs transition cursor-pointer shadow-none border border-[#E4E5E9] dark:border-[#1e2433]">
                                                <i class="fa-solid fa-ellipsis-vertical text-[10px]"></i>
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <x-dropdown-item :href="route('admin.operators.show', $operator->id)" wire:navigate>
                                                <i class="fa-solid fa-chart-line mr-2 text-[#856404] dark:text-[#FFEF4D] text-xs"></i>
                                                {{ __('Operator Analytics & Details') }}
                                            </x-dropdown-item>
                                            <x-dropdown-item :href="$storeUrl" target="_blank">
                                                <i class="fa-solid fa-arrow-up-right-from-square mr-2 text-slate-400 text-xs"></i>
                                                {{ __('Visit Live Storefront') }}
                                            </x-dropdown-item>
                                            <div class="border-t border-[#E4E5E9] dark:border-[#1e2433] my-1"></div>
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
                            <td colspan="7" class="py-12 text-center text-[#8B8D98]">
                                <i class="fa-solid fa-users-slash text-2xl mb-2 block opacity-40"></i>
                                <span class="font-medium text-[13px] text-[#1C2024] dark:text-white">{{ __('No matching operators found') }}</span>
                                <p class="text-[12px] text-[#60646C] mt-0.5">{{ __('Try clearing filters or adjusting your search term.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($operators->hasPages())
            <div class="p-3 border-t border-[#E4E5E9] dark:border-[#1e2433]">
                {{ $operators->links() }}
            </div>
        @endif
    </div>

    <!-- Confirm Operator Status Modal -->
    @if ($showConfirmOperatorStatusModal && $this->pendingStatusOperator)
        @php
            $pendingOperator = $this->pendingStatusOperator;
        @endphp
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/50 backdrop-blur-xs overflow-y-auto" wire:keydown.escape="closeConfirmOperatorStatusModal">
                <div class="w-full max-w-md rounded-[12px] bg-white dark:bg-[#10141d] shadow-none border border-[#E4E5E9] dark:border-[#1e2433] flex flex-col my-8" @click.outside="$wire.closeConfirmOperatorStatusModal()">
                    <!-- Modal Header -->
                    <div class="p-5 border-b border-[#E4E5E9] dark:border-[#1e2433] flex items-start justify-between gap-4 bg-[#FAFAFB] dark:bg-[#141821] rounded-t-[12px]">
                        <div class="flex items-start gap-3 min-w-0">
                            @if ($pendingOperatorStatus === 'approved')
                                <div class="w-8 h-8 rounded-[6px] bg-emerald-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-circle-check"></i>
                                </div>
                            @elseif ($pendingOperatorStatus === 'suspended')
                                <div class="w-8 h-8 rounded-[6px] bg-rose-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-ban"></i>
                                </div>
                            @else
                                <div class="w-8 h-8 rounded-[6px] bg-[#FFEF4D] text-[#12181E] font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                            @endif
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-medium text-[14px] text-[#1C2024] dark:text-white leading-tight truncate">
                                    @if ($pendingOperatorStatus === 'approved')
                                        {{ __('Approve Operator Account') }}
                                    @elseif ($pendingOperatorStatus === 'suspended')
                                        {{ __('Suspend Operator Account') }}
                                    @else
                                        {{ __('Mark Operator as Pending Review') }}
                                    @endif
                                </h3>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400 truncate">
                                    {{ $pendingOperator->name }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="closeConfirmOperatorStatusModal"
                            class="p-1 rounded-[4px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white hover:bg-[#EFEFF0] dark:hover:bg-[#1e2433] transition cursor-pointer"
                        >
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-5 space-y-3 text-[13px] text-[#1C2024] dark:text-slate-300">
                        @if ($pendingOperatorStatus === 'approved')
                            <p class="leading-relaxed">
                                {{ __('Are you sure you want to approve') }} <strong>{{ $pendingOperator->name }}</strong>?
                            </p>
                            <div class="p-3 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/50 space-y-1 text-emerald-800 dark:text-emerald-200">
                                <div class="font-medium text-[12px] flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span>{{ __('Live Storefront Activation') }}</span>
                                </div>
                                <p class="text-[11px] text-emerald-700 dark:text-emerald-300 leading-normal">
                                    {{ __('Their storefront and published tour packages will be immediately accessible to online travelers for direct booking and checkout.') }}
                                </p>
                            </div>
                        @elseif ($pendingOperatorStatus === 'suspended')
                            <p class="leading-relaxed">
                                {{ __('Are you sure you want to suspend') }} <strong>{{ $pendingOperator->name }}</strong>?
                            </p>
                            <div class="p-3 rounded-[6px] bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 space-y-1 text-rose-800 dark:text-rose-200">
                                <div class="font-medium text-[12px] flex items-center gap-1.5">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <span>{{ __('Storefront Offline Warning') }}</span>
                                </div>
                                <p class="text-[11px] text-rose-700 dark:text-rose-300 leading-normal">
                                    {{ __('New direct bookings and checkout will be suspended immediately. Existing bookings remain intact for fulfillment.') }}
                                </p>
                            </div>
                        @else
                            <p class="leading-relaxed">
                                {{ __('Set status to Pending Review for') }} <strong>{{ $pendingOperator->name }}</strong>?
                            </p>
                        @endif
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-4 border-t border-[#E4E5E9] dark:border-[#1e2433] flex items-center justify-end gap-2 bg-[#FAFAFB] dark:bg-[#141821] rounded-b-[12px]">
                        <button
                            type="button"
                            wire:click="closeConfirmOperatorStatusModal"
                            class="px-3.5 py-1.5 rounded-[6px] text-[12px] font-normal text-[#1C2024] dark:text-slate-300 hover:bg-[#EFEFF0] dark:hover:bg-[#1E2433] transition cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="executeOperatorStatus"
                            class="px-3.5 py-1.5 rounded-[6px] text-[12px] font-medium text-white transition cursor-pointer shadow-none
                                {{ $pendingOperatorStatus === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700' : ($pendingOperatorStatus === 'suspended' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700') }}"
                        >
                            {{ __('Confirm Status Change') }}
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
