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
            $this->dispatch('operator-status-updated', ['name' => $operator->name, 'status' => $operatorStatus->label()]);
        }
    }

    /**
     * Render the component with filtered operators and statistics.
     */
    public function with(): array
    {
        $query = Operator::query()
            ->with(['users', 'plan'])
            ->withCount(['reservations']);

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

        $operators = $query->latest()->paginate(15);

        $platformDomain = app(DomainResolverService::class)->getPlatformDomain();

        return [
            'operators' => $operators,
            'platformDomain' => $platformDomain,
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
                        {{ __('Monitor all registered tour operators, inspect dedicated insights, manage their portal, and update approval status.') }}
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

    <!-- Operators Directory Section -->
    <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden">
        <!-- Operators Mobile Responsive Card List (md:hidden) -->
        <div class="md:hidden space-y-3 p-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($operators as $operator)
                @php
                    $owner = $operator->users->first();
                    $storeUrl = request()->getScheme() . '://' . $operator->slug . '.' . $platformDomain;
                @endphp
                <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="w-9 h-9 rounded-xl bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 font-extrabold flex items-center justify-center text-xs shrink-0 overflow-hidden">
                                @if ($operator->logo_path)
                                    <img src="{{ Storage::url($operator->logo_path) }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                @else
                                    {{ strtoupper(substr($operator->name, 0, 2)) }}
                                @endif
                            </a>
                            <div class="min-w-0">
                                <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="font-extrabold text-xs text-slate-900 dark:text-white block truncate">
                                    {{ $operator->name }}
                                </a>
                                <span class="font-mono text-[10px] text-purple-600 dark:text-purple-400 block truncate">
                                    {{ $operator->slug }}.{{ $platformDomain }}
                                </span>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                            {{ $operator->subscriptionPlan?->name ?? 'Starter' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800 text-[11px] text-slate-500">
                        <span>{{ $owner?->email ?? 'No owner' }}</span>
                        <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate class="px-3 py-1 rounded-xl bg-purple-600 text-white font-bold text-xs">
                            {{ __('Manage') }}
                        </a>
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
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-zinc-800/40 border-b border-slate-200/80 dark:border-zinc-800 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Operator') }}</th>
                        <th class="py-3.5 px-4">{{ __('Plan & Status') }}</th>
                        <th class="py-3.5 px-4 text-center">{{ __('Setup') }}</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($operators as $operator)
                        @php
                            $owner = $operator->users->first();
                            $storeUrl = request()->getScheme() . '://' . $operator->slug . '.' . $platformDomain;
                            $hasBank = filled($operator->bank_provider) && filled($operator->bank_account_number);
                            $hasWhatsApp = filled($operator->contact_whatsapp);
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">

                            {{-- Column 1: Operator Identity --}}
                            <td class="py-4 px-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                        class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300 font-extrabold flex items-center justify-center text-sm shrink-0 overflow-hidden border border-purple-200 dark:border-purple-800/50 hover:scale-105 transition-transform">
                                        @if ($operator->logo_path)
                                            <img src="{{ Storage::url($operator->logo_path) }}" alt="{{ $operator->name }}" class="w-full h-full object-cover" />
                                        @else
                                            {{ strtoupper(substr($operator->name, 0, 2)) }}
                                        @endif
                                    </a>
                                    <div class="min-w-0 space-y-0.5">
                                        <a href="{{ route('admin.operators.show', $operator->id) }}" wire:navigate
                                            class="font-bold text-slate-900 dark:text-white hover:text-purple-600 dark:hover:text-purple-400 transition truncate block text-sm leading-tight">
                                            {{ $operator->name }}
                                        </a>
                                        <a href="{{ $storeUrl }}" target="_blank"
                                            class="text-[11px] text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1 w-fit">
                                            <span class="font-mono">{{ $operator->slug }}.{{ $platformDomain }}</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                        </a>
                                        <div class="flex items-center gap-2 text-[11px] text-slate-400">
                                            @if ($owner?->email)
                                                <span class="truncate max-w-[180px]">{{ $owner->email }}</span>
                                                <span>&bull;</span>
                                            @endif
                                            <span>{{ $operator->created_at?->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Column 2: Plan & Status --}}
                            <td class="py-4 px-4">
                                <div class="space-y-1.5">
                                    @php
                                        $activePlan = $operator->getPlan();
                                        $planBadgeColor = match ($activePlan->slug) {
                                            'enterprise' => 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                            'growth' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
                                            default => 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300 border-slate-200 dark:border-zinc-700',
                                        };
                                        $statusBadgeClasses = match ($operator->status) {
                                            OperatorStatus::Approved => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300',
                                            OperatorStatus::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300',
                                            OperatorStatus::Suspended => 'bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border {{ $planBadgeColor }}">
                                        {{ $activePlan->name }}
                                    </span>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider {{ $statusBadgeClasses }}">
                                        {{ $operator->status->label() }}
                                    </span>
                                </div>
                            </td>

                            {{-- Column 3: Setup Completeness --}}
                            <td class="py-4 px-4 text-center">
                                <div class="inline-flex flex-col items-start gap-1.5">
                                    <span class="flex items-center gap-1.5 text-[11px] font-semibold {{ $hasBank ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-500 dark:text-amber-400' }}">
                                        <i class="fa-solid {{ $hasBank ? 'fa-circle-check' : 'fa-circle-exclamation' }} text-xs"></i>
                                        {{ __('Bank') }}
                                    </span>
                                    <span class="flex items-center gap-1.5 text-[11px] font-semibold {{ $hasWhatsApp ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                                        <i class="fa-solid {{ $hasWhatsApp ? 'fa-circle-check' : 'fa-circle-minus' }} text-xs"></i>
                                        {{ __('WhatsApp') }}
                                    </span>
                                </div>
                            </td>

                            {{-- Column 4: Actions --}}
                            <td class="py-4 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        wire:click="manageOperator('{{ $operator->id }}')"
                                        class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/70 hover:bg-purple-100 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-300 text-xs font-bold transition cursor-pointer border border-purple-200 dark:border-purple-800/60"
                                        title="{{ __('Open & manage this operator portal') }}"
                                    >
                                        <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                                        <span>{{ __('Portal') }}</span>
                                    </button>

                                    <x-dropdown position="bottom-end" width="56">
                                        <x-slot name="trigger">
                                            <button type="button" class="h-8 w-8 rounded-xl inline-flex items-center justify-center bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-600 dark:text-slate-400 text-xs transition cursor-pointer">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <x-dropdown-item :href="route('admin.operators.show', $operator->id)" wire:navigate>
                                                <i class="fa-solid fa-eye mr-2 text-slate-400 text-xs"></i>
                                                {{ __('View Details') }}
                                            </x-dropdown-item>
                                            <div class="border-t border-slate-100 dark:border-zinc-800 my-1"></div>
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
                            <td colspan="4" class="py-12 text-center text-slate-400">
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
                                    <i class="fa-solid fa-clock mr-1.5 text-amber-500"></i>
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
