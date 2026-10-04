<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Models\Operator;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app.sidebar')] #[Title('Vendors & Suppliers - Operator Portal')] class extends Component {
    use ResolvesCurrentOperator;
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
    public function mount(): void
    {
        $this->authorizeAbility('manageCatalog');
    }

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
        $this->authorizeAbility('manageCatalog');

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
        $this->authorizeAbility('manageCatalog');

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
        $this->authorizeAbility('manageCatalog');

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
        $this->authorizeAbility('manageCatalog');

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
        $this->authorizeAbility('manageCatalog');

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
    <x-page-header
        :title="__('Vendors & Suppliers')"
        :subtitle="__('Manage 3rd-party activity providers, their reservation dispatch emails, and payout accounts.')"
        icon="fa-handshake"
    >
        <x-slot:actions>
            <x-button wire:click="createVendor" class="gap-2 shadow-none font-semibold">
                <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                <span>{{ __('Add Vendor') }}</span>
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <!-- Flash Message -->
    @if (session('success'))
        <div
            class="p-4 rounded-[8px] bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>
    @endif
    @if (session('error'))
        <div
            class="p-4 rounded-[8px] bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-sm font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-600 dark:text-rose-400"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-500 hover:text-rose-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>
    @endif

    <!-- Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        <x-metric-card :label="__('Total Vendors')" :value="$totalCount" icon="fa-handshake" tone="ebony" />
        <x-metric-card :label="__('Active Partners')" :value="$activeCount" icon="fa-circle-check" tone="success" />
        <x-metric-card :label="__('Linked Activities')" :value="$totalActivities" icon="fa-cubes" tone="brand" />
    </div>

    <!-- Search & Filters -->
    <div
        class="p-3.5 sm:p-4 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col sm:flex-row items-center gap-3">
        <div class="relative flex-1 w-full">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5A6578] dark:text-[#9DA4B2] text-xs"></i>
            <input type="text" wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search by vendor name, contact person, email, or phone...') }}"
                class="w-full pl-9 pr-3.5 h-9 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#F4F5F7] dark:bg-[#151a26] text-slate-900 dark:text-white text-xs focus:outline-none focus:border-[#FFEF4D] transition" />
        </div>
        <div class="w-full sm:w-44">
            <x-select wire:model.live="filter" :options="[
                'all' => __('All Status'),
                'active' => __('Active Only'),
                'inactive' => __('Inactive Only'),
            ]" class="rounded-[6px] h-9 text-xs" />
        </div>
    </div>

    <!-- Table / Mobile Card List -->
    @if ($vendors->count() > 0)
        <!-- Mobile Card List -->
        <div class="space-y-3 md:hidden">
            @foreach ($vendors as $vendor)
                <div wire:key="vendor-card-{{ $vendor->id }}"
                    class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] p-4 shadow-none space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-sm sm:text-base text-slate-900 dark:text-white truncate">
                                {{ $vendor->name }}
                            </div>
                            @if ($vendor->contact_person)
                                <div class="text-xs text-[#5A6578] dark:text-[#9DA4B2] flex items-center gap-1.5 mt-0.5">
                                    <i class="fa-solid fa-user text-[10px] text-[#5A6578] dark:text-[#9DA4B2]"></i>
                                    <span>{{ $vendor->contact_person }}</span>
                                </div>
                            @endif
                        </div>

                        <button
                            type="button"
                            wire:click="toggleStatus('{{ $vendor->id }}')"
                            class="h-6 px-2 rounded-[4px] inline-flex items-center gap-1.5 text-[11px] font-semibold transition cursor-pointer shrink-0 {{ $vendor->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60' : 'bg-[#F4F5F7] text-slate-600 dark:bg-[#151a26] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433]' }}"
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
                                class="font-mono text-xs text-[#12181E] dark:text-[#FFEF4D] hover:underline inline-flex items-center gap-1.5">
                                <i class="fa-regular fa-envelope text-[#5A6578] dark:text-[#9DA4B2]"></i>
                                <span class="break-all">{{ $vendor->reservation_email }}</span>
                            </a>
                        </div>
                        @if ($vendor->phone)
                            <div>
                                <a href="tel:{{ $vendor->phone }}"
                                    class="text-slate-700 dark:text-slate-300 hover:text-amber-600 inline-flex items-center gap-1.5 font-medium">
                                    <i class="fa-solid fa-phone text-[#5A6578] dark:text-[#9DA4B2] text-[11px]"></i>
                                    <span>{{ $vendor->phone }}</span>
                                </a>
                            </div>
                        @endif
                        @if (!empty($vendor->payout_details['bank_name']) || !empty($vendor->payout_details['account_number']))
                            <div class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] flex items-center gap-1.5">
                                <i class="fa-solid fa-building-columns text-[10px] text-[#5A6578] dark:text-[#9DA4B2]"></i>
                                <span>{{ $vendor->payout_details['bank_name'] ?? '' }} &bull; {{ $vendor->payout_details['account_number'] ?? '' }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Footer actions & activity badge -->
                    <div class="flex items-center justify-between gap-2 border-t border-[#E4E5E9] dark:border-[#1E2433] pt-3">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[4px] text-xs font-medium bg-[#F4F5F7] dark:bg-[#151a26] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433]">
                            <i class="fa-solid fa-cubes text-[10px] text-[#5A6578] dark:text-[#9DA4B2]"></i>
                            <span>{{ trans_choice(':count activity|:count activities', $vendor->products_count, ['count' => $vendor->products_count]) }}</span>
                        </span>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                wire:click="editVendor('{{ $vendor->id }}')"
                                class="h-8 px-2.5 rounded-[6px] bg-[#F4F5F7] dark:bg-[#151a26] hover:bg-[#E4E5E9] dark:hover:bg-[#1E2433] text-slate-700 dark:text-zinc-200 border border-[#E4E5E9] dark:border-[#1E2433] font-semibold text-xs inline-flex items-center gap-1.5 transition cursor-pointer shadow-none"
                                title="{{ __('Edit Vendor') }}"
                            >
                                <i class="fa-solid fa-pen text-[10px]"></i>
                                <span>{{ __('Edit') }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="confirmDelete('{{ $vendor->id }}')"
                                class="h-8 w-8 rounded-[6px] bg-[#F4F5F7] dark:bg-[#151a26] hover:bg-rose-50 dark:hover:bg-rose-950/50 text-[#5A6578] hover:text-rose-600 dark:text-[#9DA4B2] dark:hover:text-rose-400 border border-[#E4E5E9] dark:border-[#1E2433] inline-flex items-center justify-center transition shadow-none cursor-pointer"
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
        <div class="hidden md:block rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] overflow-hidden shadow-none">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead
                        class="bg-[#F4F5F7]/80 dark:bg-[#151a26]/80 text-[11px] uppercase font-semibold text-[#5A6578] dark:text-[#9DA4B2] border-b border-[#E4E5E9] dark:border-[#1E2433]">
                        <tr>
                            <th class="px-6 py-3.5">{{ __('Vendor / Supplier') }}</th>
                            <th class="px-6 py-3.5">{{ __('Reservation Email') }}</th>
                            <th class="px-6 py-3.5">{{ __('Phone / WhatsApp') }}</th>
                            <th class="px-6 py-3.5 text-center">{{ __('Activities') }}</th>
                            <th class="px-6 py-3.5">{{ __('Status') }}</th>
                            <th class="px-6 py-3.5 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                        @foreach ($vendors as $vendor)
                            <tr class="hover:bg-[#F4F5F7]/50 dark:hover:bg-[#151a26]/50 transition">
                                <td class="px-6 py-3.5">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $vendor->name }}</div>
                                    @if ($vendor->contact_person)
                                        <div class="text-xs text-[#5A6578] dark:text-[#9DA4B2] flex items-center gap-1 mt-0.5">
                                            <i class="fa-solid fa-user text-[10px]"></i>
                                            <span>{{ $vendor->contact_person }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5">
                                    <a href="mailto:{{ $vendor->reservation_email }}"
                                        class="font-mono text-xs text-[#12181E] dark:text-[#FFEF4D] hover:underline flex items-center gap-1.5">
                                        <i class="fa-regular fa-envelope text-xs"></i>
                                        <span>{{ $vendor->reservation_email }}</span>
                                    </a>
                                </td>
                                <td class="px-6 py-3.5">
                                    @if ($vendor->phone)
                                        <div
                                            class="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                            <i class="fa-solid fa-phone text-[11px] text-[#5A6578] dark:text-[#9DA4B2]"></i>
                                            <span>{{ $vendor->phone }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-[#5A6578] dark:text-[#9DA4B2] italic">{{ __('None') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-[4px] text-xs font-semibold bg-[#F4F5F7] dark:bg-[#151a26] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433]">
                                        {{ $vendor->products_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5">
                                    <button
                                        type="button"
                                        wire:click="toggleStatus('{{ $vendor->id }}')"
                                        class="h-6 px-2.5 rounded-[4px] inline-flex items-center gap-1.5 text-xs font-semibold transition cursor-pointer {{ $vendor->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60' : 'bg-[#F4F5F7] text-slate-600 dark:bg-[#151a26] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433]' }}"
                                        title="{{ __('Click to toggle active status') }}"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full {{ $vendor->is_active ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-slate-400' }}"></span>
                                        <span>{{ $vendor->is_active ? __('Active') : __('Inactive') }}</span>
                                    </button>
                                </td>
                                <td class="px-6 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            wire:click="editVendor('{{ $vendor->id }}')"
                                            class="h-8 px-2.5 rounded-[6px] bg-[#F4F5F7] dark:bg-[#151a26] hover:bg-[#E4E5E9] dark:hover:bg-[#1E2433] text-slate-700 dark:text-zinc-200 border border-[#E4E5E9] dark:border-[#1E2433] font-semibold text-xs transition inline-flex items-center gap-1 shadow-none cursor-pointer"
                                            title="{{ __('Edit Vendor') }}"
                                        >
                                            <i class="fa-solid fa-pen text-[10px]"></i>
                                            <span>{{ __('Edit') }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete('{{ $vendor->id }}')"
                                            class="h-8 w-8 rounded-[6px] bg-[#F4F5F7] dark:bg-[#151a26] hover:bg-rose-50 dark:hover:bg-rose-950/50 text-[#5A6578] hover:text-rose-600 dark:text-[#9DA4B2] dark:hover:text-rose-400 border border-[#E4E5E9] dark:border-[#1E2433] inline-flex items-center justify-center transition shadow-none cursor-pointer"
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
            <div class="p-3.5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none">
                {{ $vendors->links() }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] py-16 text-center px-4 shadow-none">
            <div
                class="w-12 h-12 rounded-[8px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 flex items-center justify-center mx-auto text-xl mb-4">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('No vendors found') }}</h3>
            <p class="text-xs sm:text-sm text-[#5A6578] dark:text-[#9DA4B2] max-w-sm mx-auto mt-1 mb-6">
                {{ filled($search) ? __('No vendors match your search query.') : __('Add 3rd-party activity suppliers to automatically dispatch booking notification copies to their reservation inboxes.') }}
            </p>
            <x-button wire:click="createVendor" class="gap-2 font-semibold shadow-none">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>{{ __('Add Vendor') }}</span>
            </x-button>
        </div>
    @endif

    <!-- Create / Edit Vendor Modal (Mobile-First Bottom Sheet & Desktop Dialog) -->
    @if ($show_modal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-xs overflow-y-auto"
                wire:keydown.escape="$set('show_modal', false)">
                <div class="relative flex w-full max-w-xl max-h-[92vh] sm:max-h-[88vh] flex-col overflow-hidden rounded-t-[16px] sm:rounded-[12px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] shadow-none"
                    @click.outside="$wire.set('show_modal', false)">

                    <!-- Mobile drawer drag handle -->
                    <div class="mx-auto my-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-[#E4E5E9] dark:border-[#1E2433] bg-[#F4F5F7]/70 dark:bg-[#151a26]/70 px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                <i class="fa-solid fa-handshake text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-bold leading-tight text-slate-900 dark:text-white">
                                    {{ $editing_id ? __('Edit Vendor & Supplier') : __('Add New Vendor & Supplier') }}
                                </h3>
                                <p class="truncate text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                    {{ __('Activity provider dispatch & payout details') }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="$set('show_modal', false)"
                            class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] hover:text-slate-900 dark:hover:text-white transition">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>

                    <form wire:submit.prevent="save" class="flex flex-1 flex-col overflow-hidden">
                        <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain p-5 sm:p-6">
                            <!-- Vendor Name -->
                            <div>
                                <x-label for="name" :value="__('Vendor / Business Name')" required />
                                <x-input id="name" type="text" wire:model="name"
                                    placeholder="{{ __('e.g. Bali ATV Adventures') }}" required :error="$errors->has('name')" class="rounded-[6px] h-9 text-xs" />
                                <x-input-error :messages="$errors->get('name')" />
                            </div>

                            <!-- Reservation Email -->
                            <div>
                                <x-label for="reservation_email" :value="__('Reservation Email')" required />
                                <x-input id="reservation_email" type="email" wire:model="reservation_email"
                                    placeholder="{{ __('booking@vendor.com') }}" required :error="$errors->has('reservation_email')" class="rounded-[6px] h-9 text-xs" />
                                <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1 flex items-center gap-1">
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
                                        placeholder="{{ __('e.g. Wayan') }}" :error="$errors->has('contact_person')" class="rounded-[6px] h-9 text-xs" />
                                    <x-input-error :messages="$errors->get('contact_person')" />
                                </div>

                                <div>
                                    <x-label for="phone" :value="__('Phone / WhatsApp (Optional)')" />
                                    <x-input id="phone" type="text" wire:model="phone"
                                        placeholder="{{ __('+62 812-3456-7890') }}" :error="$errors->has('phone')" class="rounded-[6px] h-9 text-xs" />
                                    <x-input-error :messages="$errors->get('phone')" />
                                </div>
                            </div>

                            <!-- Payout Details (Card group) -->
                            <div class="rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#F4F5F7]/50 dark:bg-[#151a26]/50 p-4 space-y-3">
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    <i class="fa-solid fa-building-columns text-[#5A6578] dark:text-[#9DA4B2] text-xs"></i>
                                    <span>{{ __('Vendor Payout Account (Optional)') }}</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <x-label for="bank_name" :value="__('Bank / Channel Name')" class="text-xs" />
                                        <x-input id="bank_name" type="text" wire:model="bank_name"
                                            placeholder="{{ __('e.g. BCA, Mandiri, Wise') }}" class="rounded-[6px] h-9 text-xs" />
                                    </div>
                                    <div>
                                        <x-label for="bank_account_number" :value="__('Account Number')" class="text-xs" />
                                        <x-input id="bank_account_number" type="text" wire:model="bank_account_number"
                                            placeholder="{{ __('1234567890') }}" class="rounded-[6px] h-9 text-xs font-mono" />
                                    </div>
                                </div>
                                <div>
                                    <x-label for="bank_account_holder" :value="__('Account Holder Name')" class="text-xs" />
                                    <x-input id="bank_account_holder" type="text" wire:model="bank_account_holder"
                                        placeholder="{{ __('e.g. PT Bali Petualangan') }}" class="rounded-[6px] h-9 text-xs" />
                                </div>
                            </div>

                            <!-- Active Toggle Card -->
                            <div class="rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#F4F5F7]/50 dark:bg-[#151a26]/50 p-3.5 flex items-center justify-between gap-3">
                                <div class="space-y-0.5">
                                    <label for="is_active" class="text-xs font-semibold text-slate-800 dark:text-slate-200 block cursor-pointer">
                                        {{ __('Active Status') }}
                                    </label>
                                    <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                                        {{ __('When active, this vendor receives booking dispatch notifications.') }}
                                    </p>
                                </div>
                                <input type="checkbox" id="is_active" wire:model="is_active"
                                    class="rounded-[4px] border-[#E4E5E9] dark:border-[#1E2433] text-amber-500 focus:ring-amber-400 h-4.5 w-4.5 shrink-0 cursor-pointer" />
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center justify-end gap-2 border-t border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] px-5 py-3 sm:px-6">
                            <x-button type="button" size="sm" variant="secondary" wire:click="$set('show_modal', false)">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" size="sm" variant="primary">
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
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-xs overflow-y-auto"
                wire:keydown.escape="$set('confirming_delete_id', null)">
                <div class="relative flex w-full max-w-md flex-col overflow-hidden rounded-t-[16px] sm:rounded-[12px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] p-5 sm:p-6 shadow-none space-y-4 text-center"
                    @click.outside="$wire.set('confirming_delete_id', null)">
                    
                    <!-- Mobile drawer drag handle -->
                    <div class="mx-auto my-1 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                    <div class="w-12 h-12 rounded-[8px] bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 flex items-center justify-center mx-auto text-lg">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="space-y-1.5">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ __('Delete Vendor ":name"?', ['name' => $confirming_delete_name]) }}
                        </h3>
                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] max-w-xs mx-auto leading-relaxed">
                            {{ __('Are you sure you want to delete this vendor? Activities previously linked to this vendor will become in-house.') }}
                        </p>
                    </div>
                    <div class="flex items-center justify-center gap-2 pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                        <x-button type="button" size="sm" variant="secondary" wire:click="$set('confirming_delete_id', null)">
                            {{ __('Cancel') }}
                        </x-button>
                        <x-button type="button" size="sm" variant="danger" wire:click="deleteVendor" class="font-semibold shadow-none">
                            <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                            {{ __('Confirm Delete') }}
                        </x-button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
