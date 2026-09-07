<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Services\DomainResolverService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Operators')] #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    public string $search = '';
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
            app(DomainResolverService::class)->clearOperatorDomainCache($operator);
            Cache::flush();
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
            'totalReservationsCount' => \App\Models\Reservation::count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-header
        :title="__('Operators')"
        :subtitle="__('Who is on the platform, and whether they can sell.')"
        icon="fa-users-gear"
    />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-metric-card
            :label="__('Total Operators')"
            :value="$totalCount"
            icon="fa-users"
            wire:click="$set('status_filter', 'all')"
        />
        <x-metric-card
            :label="__('Approved & Live')"
            :value="$approvedCount"
            icon="fa-circle-check"
            tone="success"
            wire:click="$set('status_filter', 'approved')"
        />
        <x-metric-card
            :label="__('Pending Review')"
            :value="$pendingCount"
            icon="fa-clock"
            tone="warning"
            wire:click="$set('status_filter', 'pending')"
        />
        <x-metric-card
            :label="__('Suspended')"
            :value="$suspendedCount"
            icon="fa-ban"
            wire:click="$set('status_filter', 'suspended')"
        />
    </div>

    <x-toolbar class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-search-input
            class="w-full sm:w-96"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('Search operator name, owner email, WhatsApp, or bank...')"
        />

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

    <!-- Operators Directory Section -->
    <!-- Operators Table Card -->
    <div class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-hidden">
        <!-- Operators Mobile Responsive Card List (md:hidden) -->
        <div class="md:hidden space-y-3 p-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($operators as $operator)
                @php
                    $owner = $operator->users->first();
                    $storeUrl = request()->getScheme().'://'.$operator->slug.'.'.$platformDomain;
                    $hasBank = filled($operator->bank_provider) && filled($operator->bank_account_number);
                @endphp
                <div class="p-4 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="w-10 h-10 rounded-xl bg-[#FFEF4D] text-[#090d16] font-black flex items-center justify-center text-xs shrink-0 overflow-hidden shadow-xs">
                                @if ($operator->logo_path)
                                    <img src="{{ $operator->logo_url }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                @else
                                    {{ strtoupper(substr($operator->name, 0, 2)) }}
                                @endif
                            </a>
                            <div class="min-w-0">
                                <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="font-extrabold text-xs text-slate-900 dark:text-white block truncate hover:text-[#FFEF4D] transition">
                                    {{ $operator->name }}
                                </a>
                                <a href="{{ $storeUrl }}" target="_blank" class="font-mono text-[10px] text-[#8a7808] dark:text-[#FFEF4D] hover:underline flex items-center gap-1">
                                    <span>{{ $operator->slug }}.{{ $platformDomain }}</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>
                                </a>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase
                            {{ $operator->status === OperatorStatus::Approved ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($operator->status === OperatorStatus::Suspended ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                            {{ $operator->status->label() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-[11px] pt-2 border-t border-slate-100 dark:border-[#1e2433] text-slate-500 dark:text-slate-400">
                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400">{{ __('Catalog') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $operator->packages_count }} {{ __('Pkgs') }} • {{ $operator->products_count }} {{ __('Acts') }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400">{{ __('Bookings') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $operator->reservations_count }} {{ __('Total') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-[#1e2433] text-[11px]">
                        <span class="text-slate-500 truncate max-w-[160px]">{{ $owner?->email ?? 'No owner' }}</span>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                wire:click="manageOperator('{{ $operator->id }}')"
                                class="px-2.5 py-1 rounded-xl bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] font-bold text-xs border border-[#FFEF4D]/30"
                            >
                                {{ __('Portal') }}
                            </button>
                            <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#1e2433] font-bold text-xs">
                                {{ __('Manage') }}
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs text-slate-400">
                    {{ __('No operators found') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop Operators Table (hidden on mobile) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Operator') }}</th>
                        <th class="py-3.5 px-4">{{ __('Plan & Economics') }}</th>
                        <th class="py-3.5 px-4">{{ __('Owner & Contact') }}</th>
                        <th class="py-3.5 px-4">{{ __('Performance & Catalog') }}</th>
                        <th class="py-3.5 px-4">{{ __('Disbursement Bank') }}</th>
                        <th class="py-3.5 px-4 text-center">{{ __('Status') }}</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                    @forelse ($operators as $operator)
                        @php
                            $owner = $operator->users->first();
                            $storeUrl = request()->getScheme().'://'.$operator->slug.'.'.$platformDomain;
                            $hasBank = filled($operator->bank_provider) && filled($operator->bank_account_number);
                            $hasWhatsApp = filled($operator->contact_whatsapp);
                            $activePlan = $operator->getPlan();
                            $planBadgeColor = match ($activePlan->slug) {
                                default => 'bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30',
                            };
                            $statusBadgeClasses = match ($operator->status) {
                                OperatorStatus::Approved => 'bg-emerald-950/60 text-emerald-400 border-emerald-800/60',
                                OperatorStatus::Pending => 'bg-amber-950/60 text-amber-400 border-amber-800/60',
                                OperatorStatus::Suspended => 'bg-rose-950/60 text-rose-400 border-rose-800/60',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141821]/80 transition group">

                            {{-- Column 1: Operator Identity & Subdomain --}}
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                        class="size-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black flex items-center justify-center text-xs shrink-0 overflow-hidden hover:scale-105 transition-transform shadow-xs">
                                        @if ($operator->logo_path)
                                            <img src="{{ $operator->logo_url }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                        @else
                                            {{ strtoupper(substr($operator->name, 0, 2)) }}
                                        @endif
                                    </a>
                                    <div class="min-w-0 space-y-0.5">
                                        <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                            class="font-bold text-slate-900 dark:text-white hover:text-[#FFEF4D] transition truncate block text-sm leading-snug">
                                            {{ $operator->name }}
                                        </a>
                                        <a href="{{ $storeUrl }}" target="_blank"
                                            class="inline-flex items-center gap-1 text-[11px] font-mono text-slate-400 dark:text-zinc-400 hover:text-[#FFEF4D] hover:underline">
                                            <span class="truncate max-w-[200px]">{{ $operator->slug }}.{{ $platformDomain }}</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[8px] opacity-70 shrink-0"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>

                            {{-- Column 2: Plan & Subscription --}}
                            <td class="py-3.5 px-4">
                                <div class="space-y-1">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase border {{ $planBadgeColor }}">
                                        {{ $activePlan->name }}
                                    </span>
                                    <div class="text-[11px] text-slate-500 dark:text-zinc-400 font-mono">
                                        {{ __('Listed price stays with them') }}
                                    </div>
                                </div>
                            </td>

                            {{-- Column 3: Owner & Contacts --}}
                            <td class="py-3.5 px-4">
                                <div class="space-y-1 min-w-0">
                                    <div class="font-semibold text-slate-900 dark:text-white text-xs truncate max-w-[170px]">
                                        {{ $owner?->name ?? __('No Owner Assigned') }}
                                    </div>
                                    <div class="font-mono text-[11px] text-slate-500 dark:text-zinc-400 truncate max-w-[170px]">
                                        {{ $owner?->email ?? '-' }}
                                    </div>
                                    @if ($hasWhatsApp)
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $operator->contact_whatsapp) }}" target="_blank"
                                            class="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 hover:underline font-mono">
                                            <i class="fa-brands fa-whatsapp text-[10px]"></i>
                                            <span>{{ $operator->contact_whatsapp }}</span>
                                        </a>
                                    @endif
                                </div>
                            </td>

                            {{-- Column 4: Performance & Catalog --}}
                            <td class="py-3.5 px-4">
                                <div class="space-y-1">
                                    <div class="font-mono font-black text-slate-900 dark:text-white text-xs">
                                        Rp {{ number_format($operator->calculated_gmv ?? 0, 0, ',', '.') }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-zinc-400 flex items-center gap-1">
                                        <span class="font-bold text-slate-700 dark:text-zinc-300">{{ $operator->reservations_count }}</span> {{ __('bookings') }}
                                        <span class="opacity-40">•</span>
                                        <span class="font-bold text-slate-700 dark:text-zinc-300">{{ $operator->packages_count }}</span> {{ __('pkgs') }}
                                    </div>
                                </div>
                            </td>

                            {{-- Column 5: Disbursement Bank --}}
                            <td class="py-3.5 px-4">
                                @if ($hasBank)
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-black uppercase bg-slate-100 dark:bg-[#141821] text-slate-700 dark:text-zinc-300 border border-slate-200 dark:border-[#1e2433]">
                                            <i class="fa-solid fa-building-columns text-[9px]"></i>
                                            {{ $operator->bank_provider }}
                                        </span>
                                        <div class="font-mono text-[11px] text-slate-700 dark:text-zinc-300">
                                            {{ $operator->bank_account_number }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-400 italic">{{ __('Not configured') }}</span>
                                @endif
                            </td>

                            {{-- Column 6: Approval Status --}}
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $statusBadgeClasses }}">
                                    <span class="size-1.5 rounded-full {{ $operator->status === OperatorStatus::Approved ? 'bg-emerald-400' : ($operator->status === OperatorStatus::Pending ? 'bg-amber-400' : 'bg-rose-400') }}"></span>
                                    {{ $operator->status->label() }}
                                </span>
                            </td>

                            {{-- Column 7: Actions --}}
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                        class="h-8 px-2.5 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] border border-slate-200 dark:border-[#1e2433] text-slate-700 dark:text-zinc-200 font-bold text-xs inline-flex items-center gap-1 transition shadow-2xs">
                                        <span>{{ __('Inspect') }}</span>
                                        <i class="fa-solid fa-arrow-right text-[9px]"></i>
                                    </a>

                                    <button
                                        type="button"
                                        wire:click="manageOperator('{{ $operator->id }}')"
                                        class="h-8 px-2.5 rounded-xl bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] hover:bg-[#FFEF4D]/20 text-xs font-bold transition cursor-pointer border border-[#FFEF4D]/30 inline-flex items-center gap-1"
                                        title="{{ __('Open operator dashboard portal') }}"
                                    >
                                        <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                                        <span>{{ __('Portal') }}</span>
                                    </button>

                                    <!-- 3-Dots Action Dropdown -->
                                    <x-dropdown position="bottom-end" width="56">
                                        <x-slot name="trigger">
                                            <button type="button" class="h-8 w-8 rounded-xl inline-flex items-center justify-center bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-600 dark:text-slate-400 text-xs transition cursor-pointer shadow-2xs border border-slate-200 dark:border-[#1e2433]">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <x-dropdown-item :href="route('admin.operators.show', $operator->id)" wire:navigate>
                                                <i class="fa-solid fa-chart-line mr-2 text-[#8a7808] dark:text-[#FFEF4D] text-xs"></i>
                                                {{ __('Operator Analytics & Details') }}
                                            </x-dropdown-item>
                                            <x-dropdown-item :href="$storeUrl" target="_blank">
                                                <i class="fa-solid fa-arrow-up-right-from-square mr-2 text-slate-400 text-xs"></i>
                                                {{ __('Visit Live Storefront') }}
                                            </x-dropdown-item>
                                            <div class="border-t border-slate-100 dark:border-[#1e2433] my-1"></div>
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
                            <td colspan="7" class="py-12 text-center text-slate-400">
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
            <div class="p-4 border-t border-slate-100 dark:border-[#1e2433]">
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
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto" wire:keydown.escape="closeConfirmOperatorStatusModal">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#0C0E13] shadow-2xl border border-slate-200/80 dark:border-[#1e2433] flex flex-col my-8" @click.outside="$wire.closeConfirmOperatorStatusModal()">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
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
                                <div class="w-10 h-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
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
                                        {{ __('Mark Operator as Pending Review') }}
                                    @endif
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    {{ $pendingOperator->name }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="closeConfirmOperatorStatusModal"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-[#141821] transition cursor-pointer"
                        >
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-4 text-xs text-slate-600 dark:text-slate-300">
                        @if ($pendingOperatorStatus === 'approved')
                            <p class="leading-relaxed">
                                {{ __('Are you sure you want to approve') }} <strong>{{ $pendingOperator->name }}</strong>?
                            </p>
                            <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/50 space-y-1 text-emerald-800 dark:text-emerald-200">
                                <div class="font-bold flex items-center gap-1.5">
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
                            <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 space-y-1 text-rose-800 dark:text-rose-200">
                                <div class="font-bold flex items-center gap-1.5">
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
                    <div class="p-4 border-t border-slate-100 dark:border-[#1e2433] flex items-center justify-end gap-2.5 bg-slate-50/50 dark:bg-[#10141d] rounded-b-3xl">
                        <button
                            type="button"
                            wire:click="closeConfirmOperatorStatusModal"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#141821] transition cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="executeOperatorStatus"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-white transition cursor-pointer shadow-xs
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
