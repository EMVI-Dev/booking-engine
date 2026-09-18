<?php

use App\Models\Operator;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app.sidebar')] #[Title('Vendors & Suppliers - Operator Portal')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all'; // all, active, inactive

    public bool $show_modal = false;
    public ?string $editing_id = null;

    // Delete confirmation
    public ?string $confirming_delete_id = null;
    public ?string $confirming_delete_name = null;

    // Form fields
    public string $name = '';
    public string $contact_person = '';
    public string $reservation_email = '';
    public string $phone = '';
    public string $bank_name = '';
    public string $bank_account_number = '';
    public string $bank_account_holder = '';
    public bool $is_active = true;

    /**
     * Get current operator for authenticated user.
     */
    public function getOperatorProperty(): ?Operator
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->currentOperator();
    }

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'reservation_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Open create modal.
     */
    public function createVendor(): void
    {
        $this->resetErrorBag();
        $this->editing_id = null;
        $this->name = '';
        $this->contact_person = '';
        $this->reservation_email = '';
        $this->phone = '';
        $this->bank_name = '';
        $this->bank_account_number = '';
        $this->bank_account_holder = '';
        $this->is_active = true;
        $this->show_modal = true;
    }

    /**
     * Open edit modal.
     */
    public function editVendor(string $id): void
    {
        $this->resetErrorBag();
        $operator = $this->operator;

        if (!$operator) {
            return;
        }

        $vendor = $operator->vendors()->findOrFail($id);
        $this->editing_id = $vendor->id;
        $this->name = $vendor->name;
        $this->contact_person = $vendor->contact_person ?? '';
        $this->reservation_email = $vendor->reservation_email;
        $this->phone = $vendor->phone ?? '';
        $payout = $vendor->payout_details ?? [];
        $this->bank_name = $payout['bank_name'] ?? '';
        $this->bank_account_number = $payout['account_number'] ?? '';
        $this->bank_account_holder = $payout['account_holder'] ?? '';
        $this->is_active = $vendor->is_active;

        $this->show_modal = true;
    }

    /**
     * Save vendor.
     */
    public function save(): void
    {
        $this->validate();
        $operator = $this->operator;

        if (!$operator) {
            return;
        }

        $payoutDetails = null;
        if (filled($this->bank_name) || filled($this->bank_account_number) || filled($this->bank_account_holder)) {
            $payoutDetails = [
                'bank_name' => $this->bank_name,
                'account_number' => $this->bank_account_number,
                'account_holder' => $this->bank_account_holder,
            ];
        }

        $data = [
            'name' => $this->name,
            'contact_person' => filled($this->contact_person) ? $this->contact_person : null,
            'reservation_email' => $this->reservation_email,
            'phone' => filled($this->phone) ? $this->phone : null,
            'payout_details' => $payoutDetails,
            'is_active' => $this->is_active,
        ];

        if ($this->editing_id) {
            $vendor = $operator->vendors()->findOrFail($this->editing_id);
            $vendor->update($data);
            session()->flash('success', __('Vendor updated successfully.'));
        } else {
            $operator->vendors()->create($data);
            session()->flash('success', __('Vendor created successfully.'));
        }

        $this->show_modal = false;
        $this->resetPage();
    }

    /**
     * Toggle vendor status.
     */
    public function toggleStatus(string $id): void
    {
        $operator = $this->operator;
        if (!$operator) {
            return;
        }

        $vendor = $operator->vendors()->findOrFail($id);
        $vendor->update(['is_active' => !$vendor->is_active]);

        session()->flash('success', $vendor->is_active ? __('Vendor activated.') : __('Vendor deactivated.'));
    }

    /**
     * Confirm delete vendor.
     */
    public function confirmDelete(string $id): void
    {
        $operator = $this->operator;
        if (!$operator) {
            return;
        }

        $vendor = $operator->vendors()->findOrFail($id);
        $this->confirming_delete_id = $vendor->id;
        $this->confirming_delete_name = $vendor->name;
    }

    /**
     * Delete vendor.
     */
    public function deleteVendor(): void
    {
        if (!$this->confirming_delete_id) {
            return;
        }

        $operator = $this->operator;
        if (!$operator) {
            return;
        }

        $vendor = $operator->vendors()->findOrFail($this->confirming_delete_id);
        $vendor->delete();

        $this->confirming_delete_id = null;
        $this->confirming_delete_name = null;
        session()->flash('success', __('Vendor deleted successfully.'));
        $this->resetPage();
    }

    public function with(): array
    {
        $operator = $this->operator;

        if (!$operator) {
            return [
                'vendors' => collect(),
                'totalCount' => 0,
                'activeCount' => 0,
                'totalActivities' => 0,
            ];
        }

        $query = $operator
            ->vendors()
            ->withCount('products')
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('contact_person', 'like', "%{$this->search}%")
                        ->orWhere('reservation_email', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filter === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->filter === 'inactive', fn($q) => $q->where('is_active', false))
            ->latest();

        $totalCount = $operator->vendors()->count();
        $activeCount = $operator->vendors()->where('is_active', true)->count();
        $totalActivities = $operator->products()->whereNotNull('vendor_id')->count();

        return [
            'vendors' => $query->paginate(15),
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'totalActivities' => $totalActivities,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Vendors & Suppliers') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Manage 3rd-party activity providers, their reservation dispatch emails, and payout accounts.') }}
            </p>
        </div>
        <div>
            <x-button wire:click="createVendor" class="gap-2 shadow-sm font-bold">
                <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                <span>{{ __('Add Vendor') }}</span>
            </x-button>
        </div>
    </div>

    <!-- Flash Message -->
    @if (session('success'))
        <div
            class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>
    @endif
    @if (session('error'))
        <div
            class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-sm font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-600 dark:text-rose-400"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-500 hover:text-rose-700">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>
    @endif

    <!-- Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-metric-card :label="__('Total Vendors')" :value="$totalCount" icon="fa-handshake" tone="ebony" />
        <x-metric-card :label="__('Active Partners')" :value="$activeCount" icon="fa-circle-check" tone="success" />
        <x-metric-card :label="__('Linked Activities')" :value="$totalActivities" icon="fa-cubes" tone="brand" />
    </div>

    <!-- Search & Filters -->
    <div
        class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row items-center gap-3">
        <div class="relative flex-1 w-full">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search by vendor name, contact person, email, or phone...') }}"
                class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800/50 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
        </div>
        <div class="w-full sm:w-44">
            <x-select wire:model.live="filter" :options="[
                'all' => __('All Status'),
                'active' => __('Active Only'),
                'inactive' => __('Inactive Only'),
            ]" />
        </div>
    </div>

    <!-- Table / Mobile Card List -->
    @if ($vendors->count() > 0)
        <!-- Mobile Card List -->
        <div class="space-y-3 md:hidden">
            @foreach ($vendors as $vendor)
                <div wire:key="vendor-card-{{ $vendor->id }}"
                    class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 p-4 shadow-xs space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-base text-slate-900 dark:text-white truncate">
                                {{ $vendor->name }}
                            </div>
                            @if ($vendor->contact_person)
                                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mt-0.5">
                                    <i class="fa-solid fa-user text-[10px] text-slate-400"></i>
                                    <span>{{ $vendor->contact_person }}</span>
                                </div>
                            @endif
                        </div>

                        <button
                            type="button"
                            wire:click="toggleStatus('{{ $vendor->id }}')"
                            class="h-7 px-2.5 rounded-full inline-flex items-center gap-1.5 text-[11px] font-bold transition cursor-pointer shrink-0 {{ $vendor->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60' : 'bg-slate-100 text-slate-700 dark:bg-[#141821] dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]' }}"
                            title="{{ __('Click to toggle active status') }}"
                        >
                            <span class="w-1.5 h-1.5 rounded-full {{ $vendor->is_active ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-slate-400' }}"></span>
                            <span>{{ $vendor->is_active ? __('Active') : __('Inactive') }}</span>
                        </button>
                    </div>

                    <!-- Contact details -->
                    <div class="space-y-1.5 pt-1 text-xs">
                        <div>
                            <a href="mailto:{{ $vendor->reservation_email }}"
                                class="font-mono text-xs text-brand-600 dark:text-brand-400 hover:underline inline-flex items-center gap-1.5">
                                <i class="fa-regular fa-envelope text-slate-400"></i>
                                <span class="break-all">{{ $vendor->reservation_email }}</span>
                            </a>
                        </div>
                        @if ($vendor->phone)
                            <div>
                                <a href="tel:{{ $vendor->phone }}"
                                    class="text-slate-700 dark:text-slate-300 hover:text-brand-600 inline-flex items-center gap-1.5 font-medium">
                                    <i class="fa-solid fa-phone text-slate-400 text-[11px]"></i>
                                    <span>{{ $vendor->phone }}</span>
                                </a>
                            </div>
                        @endif
                        @if (!empty($vendor->payout_details['bank_name']) || !empty($vendor->payout_details['account_number']))
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                <i class="fa-solid fa-building-columns text-[10px] text-slate-400"></i>
                                <span>{{ $vendor->payout_details['bank_name'] ?? '' }} &bull; {{ $vendor->payout_details['account_number'] ?? '' }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Footer actions & activity badge -->
                    <div class="flex items-center justify-between gap-2 border-t border-slate-100 dark:border-zinc-800/80 pt-3">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300">
                            <i class="fa-solid fa-cubes text-[10px] text-slate-400"></i>
                            <span>{{ trans_choice(':count activity|:count activities', $vendor->products_count, ['count' => $vendor->products_count]) }}</span>
                        </span>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                wire:click="editVendor('{{ $vendor->id }}')"
                                class="h-9 px-3.5 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#1e2433] font-bold text-xs inline-flex items-center gap-1.5 transition cursor-pointer shadow-2xs"
                                title="{{ __('Edit Vendor') }}"
                            >
                                <i class="fa-solid fa-pen text-[10px]"></i>
                                <span>{{ __('Edit') }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="confirmDelete('{{ $vendor->id }}')"
                                class="h-9 w-9 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-rose-50 dark:hover:bg-rose-950/50 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 border border-slate-200 dark:border-[#1e2433] inline-flex items-center justify-center transition shadow-2xs cursor-pointer"
                                title="{{ __('Delete Vendor') }}"
                            >
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Desktop Table View -->
        <div class="hidden md:block rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead
                        class="bg-slate-50 dark:bg-zinc-800/60 text-xs uppercase font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-zinc-800">
                        <tr>
                            <th class="px-6 py-4">{{ __('Vendor / Supplier') }}</th>
                            <th class="px-6 py-4">{{ __('Reservation Email') }}</th>
                            <th class="px-6 py-4">{{ __('Phone / WhatsApp') }}</th>
                            <th class="px-6 py-4 text-center">{{ __('Activities') }}</th>
                            <th class="px-6 py-4">{{ __('Status') }}</th>
                            <th class="px-6 py-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                        @foreach ($vendors as $vendor)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/30 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $vendor->name }}</div>
                                    @if ($vendor->contact_person)
                                        <div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                            <i class="fa-solid fa-user text-[10px]"></i>
                                            <span>{{ $vendor->contact_person }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <a href="mailto:{{ $vendor->reservation_email }}"
                                        class="font-mono text-xs text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1.5">
                                        <i class="fa-regular fa-envelope text-xs"></i>
                                        <span>{{ $vendor->reservation_email }}</span>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($vendor->phone)
                                        <div
                                            class="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                            <i class="fa-solid fa-phone text-[11px] text-slate-400"></i>
                                            <span>{{ $vendor->phone }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">{{ __('None') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300">
                                        {{ $vendor->products_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button
                                        type="button"
                                        wire:click="toggleStatus('{{ $vendor->id }}')"
                                        class="h-8 px-3 rounded-full inline-flex items-center gap-1.5 text-xs font-bold transition cursor-pointer {{ $vendor->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60' : 'bg-slate-100 text-slate-700 dark:bg-[#141821] dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]' }}"
                                        title="{{ __('Click to toggle active status') }}"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full {{ $vendor->is_active ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-slate-400' }}"></span>
                                        <span>{{ $vendor->is_active ? __('Active') : __('Inactive') }}</span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            wire:click="editVendor('{{ $vendor->id }}')"
                                            class="h-8 px-3 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#1e2433] font-bold text-xs transition inline-flex items-center gap-1 shadow-2xs cursor-pointer"
                                            title="{{ __('Edit Vendor') }}"
                                        >
                                            <i class="fa-solid fa-pen text-[10px]"></i>
                                            <span>{{ __('Edit') }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete('{{ $vendor->id }}')"
                                            class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-rose-50 dark:hover:bg-rose-950/50 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 border border-slate-200 dark:border-[#1e2433] inline-flex items-center justify-center transition shadow-2xs cursor-pointer"
                                            title="{{ __('Delete Vendor') }}"
                                        >
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($vendors->hasPages())
            <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800">
                {{ $vendors->links() }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 py-16 text-center px-4 shadow-sm">
            <div
                class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center mx-auto text-2xl text-slate-400 mb-4">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('No vendors found') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto mt-1 mb-6">
                {{ filled($search) ? __('No vendors match your search query.') : __('Add 3rd-party activity suppliers to automatically dispatch booking notification copies to their reservation inboxes.') }}
            </p>
            <x-button wire:click="createVendor" class="gap-2 font-bold shadow-sm">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>{{ __('Add Vendor') }}</span>
            </x-button>
        </div>
    @endif

    <!-- Create / Edit Vendor Modal (Mobile-First Bottom Sheet & Desktop Dialog) -->
    @if ($show_modal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/75 backdrop-blur-xs overflow-y-auto"
                wire:keydown.escape="$set('show_modal', false)">
                <div class="relative flex w-full max-w-xl max-h-[92vh] sm:max-h-[88vh] flex-col overflow-hidden rounded-t-3xl border border-op-line bg-op-surface shadow-2xl sm:rounded-3xl"
                    @click.outside="$wire.set('show_modal', false)">

                    <div class="mx-auto my-2.5 h-1 w-12 shrink-0 rounded-full bg-op-line sm:hidden"></div>

                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-op-line bg-op-muted/70 px-5 py-4 sm:px-6">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-brand-400 text-brand-foreground">
                                <i class="fa-solid fa-handshake text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-bold leading-tight text-op-ink">
                                    {{ $editing_id ? __('Edit Vendor & Supplier') : __('Add New Vendor & Supplier') }}
                                </h3>
                                <p class="truncate text-xs text-op-subtle">
                                    {{ __('Activity provider dispatch & payout details') }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="$set('show_modal', false)"
                            class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl text-op-subtle hover:bg-op-muted hover:text-op-ink">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <form wire:submit.prevent="save" class="flex flex-1 flex-col overflow-hidden">
                        <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain p-5 sm:p-6">
                            <!-- Vendor Name -->
                            <div>
                                <x-label for="name" :value="__('Vendor / Business Name')" required />
                                <x-input id="name" type="text" wire:model="name"
                                    placeholder="{{ __('e.g. Bali ATV Adventures') }}" required :error="$errors->has('name')" />
                                <x-input-error :messages="$errors->get('name')" />
                            </div>

                            <!-- Reservation Email -->
                            <div>
                                <x-label for="reservation_email" :value="__('Reservation Email')" required />
                                <x-input id="reservation_email" type="email" wire:model="reservation_email"
                                    placeholder="{{ __('booking@vendor.com') }}" required :error="$errors->has('reservation_email')" />
                                <p class="text-[11px] text-op-subtle mt-1 flex items-center gap-1">
                                    <i class="fa-solid fa-circle-info text-[10px]"></i>
                                    <span>{{ __('Booking confirmation & cancellation copies are automatically sent here.') }}</span>
                                </p>
                                <x-input-error :messages="$errors->get('reservation_email')" />
                            </div>

                            <!-- Contact Person & Phone -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                <div>
                                    <x-label for="contact_person" :value="__('Contact Person (Optional)')" />
                                    <x-input id="contact_person" type="text" wire:model="contact_person"
                                        placeholder="{{ __('e.g. Wayan') }}" :error="$errors->has('contact_person')" />
                                    <x-input-error :messages="$errors->get('contact_person')" />
                                </div>

                                <div>
                                    <x-label for="phone" :value="__('Phone / WhatsApp (Optional)')" />
                                    <x-input id="phone" type="text" wire:model="phone"
                                        placeholder="{{ __('+62 812-3456-7890') }}" :error="$errors->has('phone')" />
                                    <x-input-error :messages="$errors->get('phone')" />
                                </div>
                            </div>

                            <!-- Payout Details (Card group) -->
                            <div class="rounded-2xl border border-op-line bg-op-muted/40 p-4 space-y-3">
                                <div class="flex items-center gap-2 text-xs font-bold text-op-ink">
                                    <i class="fa-solid fa-building-columns text-op-subtle text-xs"></i>
                                    <span>{{ __('Vendor Payout Account (Optional)') }}</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <x-label for="bank_name" :value="__('Bank / Channel Name')" class="text-xs" />
                                        <x-input id="bank_name" type="text" wire:model="bank_name"
                                            placeholder="{{ __('e.g. BCA, Mandiri, Wise') }}" class="text-xs" />
                                    </div>
                                    <div>
                                        <x-label for="bank_account_number" :value="__('Account Number')" class="text-xs" />
                                        <x-input id="bank_account_number" type="text" wire:model="bank_account_number"
                                            placeholder="{{ __('1234567890') }}" class="text-xs font-mono" />
                                    </div>
                                </div>
                                <div>
                                    <x-label for="bank_account_holder" :value="__('Account Holder Name')" class="text-xs" />
                                    <x-input id="bank_account_holder" type="text" wire:model="bank_account_holder"
                                        placeholder="{{ __('e.g. PT Bali Petualangan') }}" class="text-xs" />
                                </div>
                            </div>

                            <!-- Active Toggle Card -->
                            <div class="rounded-2xl border border-op-line bg-op-muted/40 p-3.5 flex items-center justify-between gap-3">
                                <div class="space-y-0.5">
                                    <label for="is_active" class="text-xs font-bold text-op-ink block cursor-pointer">
                                        {{ __('Active Status') }}
                                    </label>
                                    <p class="text-[11px] text-op-subtle">
                                        {{ __('When active, this vendor receives booking dispatch notifications.') }}
                                    </p>
                                </div>
                                <input type="checkbox" id="is_active" wire:model="is_active"
                                    class="rounded border-op-line text-brand-600 focus:ring-brand-500 h-5 w-5 shrink-0 cursor-pointer" />
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center justify-end gap-3 border-t border-op-line bg-op-surface px-5 py-3 pb-6 sm:px-6 sm:pb-4">
                            <x-button type="button" size="sm" variant="ghost" wire:click="$set('show_modal', false)">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" size="sm">
                                <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                                <span>{{ $editing_id ? __('Save Changes') : __('Create Vendor') }}</span>
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Delete Confirmation Modal (Mobile-First Bottom Sheet & Desktop Dialog) -->
    @if ($confirming_delete_id)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/75 backdrop-blur-xs overflow-y-auto"
                wire:keydown.escape="$set('confirming_delete_id', null)">
                <div class="relative flex w-full max-w-md flex-col overflow-hidden rounded-t-3xl border border-op-line bg-op-surface p-6 shadow-2xl sm:rounded-3xl space-y-4 text-center"
                    @click.outside="$wire.set('confirming_delete_id', null)">
                    
                    <div class="mx-auto -mt-2 mb-2 h-1 w-12 shrink-0 rounded-full bg-op-line sm:hidden"></div>

                    <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="space-y-1.5">
                        <h3 class="text-base font-bold text-op-ink">
                            {{ __('Delete Vendor ":name"?', ['name' => $confirming_delete_name]) }}
                        </h3>
                        <p class="text-xs text-op-subtle max-w-xs mx-auto leading-relaxed">
                            {{ __('Are you sure you want to delete this vendor? Activities previously linked to this vendor will become in-house.') }}
                        </p>
                    </div>
                    <div class="flex items-center justify-center gap-3 pt-2">
                        <x-button type="button" size="sm" variant="ghost" wire:click="$set('confirming_delete_id', null)">
                            {{ __('Cancel') }}
                        </x-button>
                        <x-button type="button" size="sm" variant="danger" wire:click="deleteVendor" class="font-bold shadow-xs">
                            <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                            {{ __('Confirm Delete') }}
                        </x-button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
