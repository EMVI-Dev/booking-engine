<?php

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Product;
use App\Concerns\ResolvesCurrentOperator;
use App\Concerns\UsesMediaStore;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Activities & Inventory')] class extends Component {
    use ResolvesCurrentOperator;
    use UsesMediaStore;
    public string $search = '';
    public string $statusFilter = 'all';


    #[Computed]
    public function isProfileComplete(): bool
    {
        return (bool) $this->currentOperator?->isProfileComplete();
    }

    #[Computed]
    public function products()
    {
        if (! $this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->products()
            ->withCount('packages')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('category', 'like', "%{$this->search}%"))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->get();
    }

    public ?string $deletingProductId = null;
    public ?string $deletingProductName = null;

    public function confirmDelete(string $id, string $name): void
    {
        $this->deletingProductId = $id;
        $this->deletingProductName = $name;
        $this->dispatch('open-modal', 'confirm-product-deletion-index');
    }

    public function deleteConfirmed(): void
    {
        if (! $this->deletingProductId) {
            return;
        }

        $this->deleteProduct($this->deletingProductId);
        $this->deletingProductId = null;
        $this->deletingProductName = null;
        $this->dispatch('close-modal', 'confirm-product-deletion-index');
    }

    public function deleteProduct(string $id): void
    {
        $product = $this->currentOperator?->products()->findOrFail($id);

        if ($product) {
            $this->media()->delete($product->cover_photo);
            $this->media()->deleteMany($product->gallery ?? []);
            $product->delete();
            $this->dispatch('toast', message: __('Activity item deleted successfully.'), type: 'success');
        }
    }
}; ?>

<div class="space-y-6">

    <!-- Profile Incomplete Locking Warning -->
    @if (! $this->isProfileComplete)
        <div class="p-5 rounded-3xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-900/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 animate-fade-in">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-300 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-amber-200">
                        {{ __('Setup Required: Complete Profile & Terms to Add Activities') }}
                    </h3>
                    <p class="text-xs text-slate-600 dark:text-amber-400 leading-relaxed">
                        {{ __('To protect guest reservations and comply with regulations, you must configure your WhatsApp contact, business bio, payout reference, and terms & conditions before creating inventory items.') }}
                    </p>
                </div>
            </div>
            <x-button :href="route('brand.edit')" size="sm" variant="primary" class="shrink-0 font-semibold shadow-xs" wire:navigate>
                <i class="fa-solid fa-paintbrush mr-1.5 text-xs"></i>
                {{ __('Complete Brand Settings') }}
            </x-button>
        </div>
    @endif



    <x-page-header
        :title="__('Single Activities')"
        :subtitle="__('Manage standalone activities, guided sessions, day tickets, and services. These can be booked directly or bundled into Tour Packages.')"
        icon="fa-compass"
    >
        <x-slot:actions>
            @if ($this->isProfileComplete && $this->currentOperator?->canAddProduct())
                <x-button :href="route('products.create')" wire:navigate>
                    <i class="fa-solid fa-plus text-xs"></i>
                    {{ __('Add Single Activity') }}
                </x-button>
            @elseif ($this->isProfileComplete)
                <x-button :href="route('products.create')" wire:navigate>
                    <i class="fa-solid fa-plus text-xs"></i>
                    {{ __('Listing limit reached') }}
                </x-button>
            @else
                <x-button disabled title="{{ __('Complete your operator profile in Settings to start adding inventory.') }}">
                    <i class="fa-solid fa-plus text-xs"></i>
                    {{ __('Add Single Activity') }}
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-toolbar class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <div class="w-full sm:w-80">
            <x-search-input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('Search by name or category...')"
            />
        </div>
        <div class="w-full sm:w-44">
            <x-select
                wire:model.live="statusFilter"
                :options="[
                    'all' => __('All Statuses'),
                    'published' => __('Published'),
                    'draft' => __('Draft'),
                ]"
            />
        </div>
    </x-toolbar>

    <!-- Products Table / List -->
    @if ($this->products->isNotEmpty())
        <!-- Mobile Card List -->
        <div class="space-y-3 md:hidden">
            @foreach ($this->products as $product)
                <div wire:key="prod-card-{{ $product->id }}"
                    class="rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] p-4 shadow-xs space-y-3">
                    <div class="flex items-start gap-3">
                        @if ($product->cover_photo_url)
                            <img src="{{ $product->cover_photo_url }}" alt=""
                                class="w-14 h-14 rounded-xl object-cover border border-slate-200/80 dark:border-[#1e2433] shrink-0" />
                        @else
                            <div class="w-14 h-14 rounded-xl bg-[#141821] text-[#FFEF4D] border border-[#1e2433] flex items-center justify-center shrink-0"
                                aria-hidden="true">
                                <i class="fa-solid fa-box"></i>
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('products.edit', $product) }}" wire:navigate
                                class="block font-bold text-sm text-slate-900 dark:text-white truncate">
                                {{ $product->name }}
                            </a>
                            <p class="text-[11px] text-slate-400 truncate">
                                {{ $product->category ?? __('Item') }} &bull; {{ $product->location ?? __('General') }}
                            </p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                {{ __(':count units/day', ['count' => $product->capacity_per_day]) }}
                                @if ($product->sellable_standalone)
                                    &bull; Rp {{ number_format((float) $product->price, 0, ',', '.') }}
                                @endif
                            </p>
                        </div>

                        <x-status-badge :status="$product->status" />
                    </div>

                    <div class="flex items-center justify-between gap-2 border-t border-slate-100 dark:border-[#1e2433] pt-3">
                        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            @if ($product->sellable_standalone)
                                {{ __('Sold direct & in packages') }}
                            @else
                                {{ __('Package bundle only') }}
                            @endif
                        </span>

                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('products.edit', $product) }}" wire:navigate
                                class="h-9 px-3 rounded-xl bg-slate-100 dark:bg-[#141821] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#1e2433] font-bold text-xs inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-pen text-[10px]" aria-hidden="true"></i>
                                {{ __('Edit') }}
                            </a>
                            <button type="button"
                                wire:click="confirmDelete('{{ $product->id }}', '{{ addslashes($product->name) }}')"
                                aria-label="{{ __('Delete :name', ['name' => $product->name]) }}"
                                class="h-9 w-9 rounded-xl bg-slate-100 dark:bg-[#141821] text-slate-400 border border-slate-200 dark:border-[#1e2433] inline-flex items-center justify-center cursor-pointer">
                                <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hidden md:block overflow-hidden rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 dark:bg-[#10141d] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 border-b border-slate-200/80 dark:border-[#1e2433]">
                        <tr>
                            <th class="px-5 py-3.5">{{ __('Activity / Inventory Item') }}</th>
                            <th class="px-5 py-3.5">{{ __('Daily Availability') }}</th>
                            <th class="px-5 py-3.5">{{ __('Storefront Listing') }}</th>
                            <th class="px-5 py-3.5">{{ __('Included in Packages') }}</th>
                            <th class="px-5 py-3.5">{{ __('Photos') }}</th>
                            <th class="px-5 py-3.5">{{ __('Status') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                        @foreach ($this->products as $product)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group" wire:key="prod-{{ $product->id }}">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($product->cover_photo_url)
                                            <img src="{{ $product->cover_photo_url }}" alt="{{ $product->name }}" class="w-10 h-10 rounded-xl object-cover border border-slate-200/80 dark:border-[#1e2433] shrink-0" />
                                        @else
                                            <div class="w-10 h-10 rounded-xl bg-[#141821] text-[#FFEF4D] border border-[#1e2433] flex items-center justify-center font-bold text-xs shrink-0">
                                                <i class="fa-solid fa-box"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <a href="{{ route('products.edit', $product) }}" wire:navigate class="font-bold text-slate-900 dark:text-white text-sm hover:text-[#FFEF4D] transition">
                                                {{ $product->name }}
                                            </a>
                                            <p class="text-[11px] text-slate-400">{{ $product->category ?? 'Item' }} &bull; {{ $product->location ?? 'General' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-mono font-bold text-slate-900 dark:text-white">
                                    {{ $product->capacity_per_day }} <span class="text-xs font-normal text-slate-400">units/day</span>
                                </td>
                                <td class="px-5 py-4">
                                    @if ($product->sellable_standalone)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60">
                                            <i class="fa-solid fa-cart-shopping text-[10px]"></i>
                                            {{ __('Direct') }} (Rp {{ number_format((float) $product->price, 0, ',', '.') }})
                                        </span>
                                    @else
                                        <span class="text-slate-400 text-xs">{{ __('Package Bundle Only') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-[#141821] text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]">
                                        <i class="fa-solid fa-cubes text-[10px]"></i>
                                        {{ $product->packages_count }} {{ __('Packages') }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    @php
                                        $photoCount = ($product->cover_photo ? 1 : 0) + (is_array($product->gallery) ? count($product->gallery) : 0);
                                    @endphp
                                    <span class="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                        <i class="fa-solid fa-image text-[10px]"></i>
                                        <span>{{ $photoCount }}</span>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <x-status-badge :status="$product->status" />
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('products.edit', $product) }}"
                                            wire:navigate
                                            class="h-8 px-3 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#1e2433] font-bold text-xs transition inline-flex items-center gap-1 shadow-2xs"
                                            title="{{ __('Edit') }}"
                                        >
                                            <i class="fa-solid fa-pen text-[10px]"></i>
                                            <span>{{ __('Edit') }}</span>
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete('{{ $product->id }}', '{{ addslashes($product->name) }}')"
                                            class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-rose-50 dark:hover:bg-rose-950/50 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 border border-slate-200 dark:border-[#1e2433] inline-flex items-center justify-center transition shadow-2xs cursor-pointer"
                                            title="{{ __('Delete') }}"
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
    @else
        <x-empty-state
            icon="fa-box"
            :title="__('No Activities or Items Found')"
            :description="__('Create activities, workshop sessions, guide services, or admission tickets with daily capacity limits.')"
        >
            <x-slot:actions>
                @if ($this->isProfileComplete && $this->currentOperator?->canAddProduct())
                    <x-button :href="route('products.create')" variant="primary" size="sm" wire:navigate>
                        <i class="fa-solid fa-plus mr-1 text-xs" aria-hidden="true"></i>
                        {{ __('Create Your First Item') }}
                    </x-button>
                @else
                    <x-button :href="route('brand.edit')" variant="primary" size="sm" wire:navigate>
                        <i class="fa-solid fa-paintbrush mr-1 text-xs" aria-hidden="true"></i>
                        {{ __('Complete Brand Settings First') }}
                    </x-button>
                @endif
            </x-slot:actions>
        </x-empty-state>
    @endif

    <!-- System UI Confirmation Modal -->
    <x-modal name="confirm-product-deletion-index" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ __('Delete ":name"?', ['name' => $deletingProductName ?? 'Item']) }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to permanently delete this inventory item? All associated media will be deleted from storage.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-3">
                <x-button
                    type="button"
                    variant="secondary"
                    x-on:click="$dispatch('close-modal', 'confirm-product-deletion-index')"
                    class="font-semibold text-xs"
                >
                    {{ __('Cancel') }}
                </x-button>
                <x-button
                    type="button"
                    variant="danger"
                    wire:click="deleteConfirmed"
                    class="font-semibold text-xs shadow-xs"
                >
                    <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                    {{ __('Confirm Delete') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
