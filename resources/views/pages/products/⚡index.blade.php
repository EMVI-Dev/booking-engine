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
        $this->authorizeAbility('manageCatalog');

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
        <div class="p-4 rounded-[12px] bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 animate-fade-in shadow-none">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-[8px] bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                </div>
                <div class="space-y-0.5">
                    <h3 class="text-xs sm:text-sm font-semibold text-[#12181E] dark:text-amber-200">
                        {{ __('Setup Required: Complete Profile & Terms to Add Activities') }}
                    </h3>
                    <p class="text-xs text-[#5A6578] dark:text-amber-400/90 leading-relaxed">
                        {{ __('To protect guest reservations and comply with regulations, you must configure your WhatsApp contact, business bio, payout reference, and terms & conditions before creating inventory items.') }}
                    </p>
                </div>
            </div>
            <x-button :href="route('brand.edit')" size="sm" variant="primary" class="shrink-0 font-medium text-xs rounded-[6px] shadow-none !bg-[#FFEF4D] !text-[#12181E] hover:!bg-[#F3E13A]" wire:navigate>
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
                <x-button :href="route('products.create')" wire:navigate class="rounded-[6px] text-xs font-medium !bg-[#FFEF4D] !text-[#12181E] hover:!bg-[#F3E13A] shadow-none">
                    <i class="fa-solid fa-plus text-xs mr-1"></i>
                    {{ __('Add Single Activity') }}
                </x-button>
            @elseif ($this->isProfileComplete)
                <x-button :href="route('products.create')" wire:navigate class="rounded-[6px] text-xs font-medium">
                    <i class="fa-solid fa-plus text-xs mr-1"></i>
                    {{ __('Listing limit reached') }}
                </x-button>
            @else
                <x-button disabled class="rounded-[6px] text-xs font-medium" title="{{ __('Complete your operator profile in Settings to start adding inventory.') }}">
                    <i class="fa-solid fa-plus text-xs mr-1"></i>
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
                class="h-9 rounded-[6px] text-xs font-medium"
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
                    class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] p-3.5 shadow-none space-y-3">
                    <div class="flex items-start gap-3">
                        @if ($product->cover_photo_url)
                            <img src="{{ $product->cover_photo_url }}" alt=""
                                class="w-12 h-12 rounded-[8px] object-cover border border-[#E4E5E9] dark:border-[#1E2433] shrink-0" />
                        @else
                            <div class="w-12 h-12 rounded-[8px] bg-[#F8F9FA] text-[#12181E] dark:bg-[#151a26] dark:text-[#FFEF4D] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center shrink-0"
                                aria-hidden="true">
                                <i class="fa-solid fa-box text-xs"></i>
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('products.edit', $product) }}" wire:navigate
                                class="block font-semibold text-xs sm:text-sm text-[#12181E] dark:text-white truncate">
                                {{ $product->name }}
                            </a>
                            <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] truncate">
                                {{ $product->category ?? __('Item') }} &bull; {{ $product->location ?? __('General') }}
                            </p>
                            <p class="mt-0.5 text-[11px] font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                                {{ __(':count units/day', ['count' => $product->capacity_per_day]) }}
                                @if ($product->sellable_standalone)
                                    &bull; Rp {{ number_format((float) $product->price, 0, ',', '.') }}
                                @endif
                            </p>
                        </div>

                        <x-status-badge :status="$product->status" />
                    </div>

                    <div class="flex items-center justify-between gap-2 border-t border-[#E4E5E9] dark:border-[#1E2433] pt-2.5">
                        <span class="text-[11px] font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                            @if ($product->sellable_standalone)
                                {{ __('Sold direct & in packages') }}
                            @else
                                {{ __('Package bundle only') }}
                            @endif
                        </span>

                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('products.edit', $product) }}" wire:navigate
                                class="h-8 px-2.5 rounded-[6px] bg-[#F8F9FA] dark:bg-[#151a26] hover:bg-[#E4E5E9] dark:hover:bg-[#1E2433] text-[#12181E] dark:text-zinc-200 border border-[#E4E5E9] dark:border-[#1E2433] font-medium text-xs inline-flex items-center gap-1.5 transition">
                                <i class="fa-solid fa-pen text-[10px]" aria-hidden="true"></i>
                                {{ __('Edit') }}
                            </a>
                            <button type="button"
                                wire:click="confirmDelete('{{ $product->id }}', '{{ addslashes($product->name) }}')"
                                aria-label="{{ __('Delete :name', ['name' => $product->name]) }}"
                                class="h-8 w-8 rounded-[6px] bg-[#F8F9FA] dark:bg-[#151a26] hover:bg-rose-50 dark:hover:bg-rose-950/40 text-[#5A6578] hover:text-rose-600 dark:text-[#9DA4B2] dark:hover:text-rose-400 border border-[#E4E5E9] dark:border-[#1E2433] inline-flex items-center justify-center cursor-pointer transition">
                                <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hidden md:block overflow-hidden rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-[#F8F9FA] dark:bg-[#10141d] text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2] border-b border-[#E4E5E9] dark:border-[#1E2433]">
                        <tr>
                            <th class="px-4 py-3">{{ __('Activity / Inventory Item') }}</th>
                            <th class="px-4 py-3">{{ __('Daily Availability') }}</th>
                            <th class="px-4 py-3">{{ __('Storefront Listing') }}</th>
                            <th class="px-4 py-3">{{ __('Included in Packages') }}</th>
                            <th class="px-4 py-3">{{ __('Photos') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                        @foreach ($this->products as $product)
                            <tr class="hover:bg-[#F8F9FA] dark:hover:bg-[#151a26]/60 transition" wire:key="prod-{{ $product->id }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($product->cover_photo_url)
                                            <img src="{{ $product->cover_photo_url }}" alt="{{ $product->name }}" class="w-9 h-9 rounded-[6px] object-cover border border-[#E4E5E9] dark:border-[#1E2433] shrink-0" />
                                        @else
                                            <div class="w-9 h-9 rounded-[6px] bg-[#F8F9FA] text-[#12181E] dark:bg-[#151a26] dark:text-[#FFEF4D] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center font-medium text-xs shrink-0">
                                                <i class="fa-solid fa-box"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <a href="{{ route('products.edit', $product) }}" wire:navigate class="font-semibold text-xs text-[#12181E] dark:text-white hover:text-amber-600 dark:hover:text-[#FFEF4D] transition">
                                                {{ $product->name }}
                                            </a>
                                            <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">{{ $product->category ?? 'Item' }} &bull; {{ $product->location ?? 'General' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono font-medium text-xs text-[#12181E] dark:text-white">
                                    {{ $product->capacity_per_day }} <span class="text-xs font-normal text-[#5A6578] dark:text-[#9DA4B2]">units/day</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($product->sellable_standalone)
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[4px] text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40">
                                            <i class="fa-solid fa-cart-shopping text-[10px]"></i>
                                            {{ __('Direct') }} (Rp {{ number_format((float) $product->price, 0, ',', '.') }})
                                        </span>
                                    @else
                                        <span class="text-[#5A6578] dark:text-[#9DA4B2] text-xs">{{ __('Package Bundle Only') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-[4px] bg-[#F8F9FA] dark:bg-[#151a26] text-[#5A6578] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433]">
                                        <i class="fa-solid fa-cubes text-[10px]"></i>
                                        {{ $product->packages_count }} {{ __('Packages') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $photoCount = ($product->cover_photo ? 1 : 0) + (is_array($product->gallery) ? count($product->gallery) : 0);
                                    @endphp
                                    <span class="inline-flex items-center gap-1 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                        <i class="fa-solid fa-image text-[10px]"></i>
                                        <span>{{ $photoCount }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge :status="$product->status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('products.edit', $product) }}"
                                            wire:navigate
                                            class="h-7 px-2.5 rounded-[6px] bg-[#F8F9FA] dark:bg-[#151a26] hover:bg-[#E4E5E9] dark:hover:bg-[#1E2433] text-[#12181E] dark:text-zinc-200 border border-[#E4E5E9] dark:border-[#1E2433] font-medium text-xs transition inline-flex items-center gap-1 shadow-none"
                                            title="{{ __('Edit') }}"
                                        >
                                            <i class="fa-solid fa-pen text-[10px]"></i>
                                            <span>{{ __('Edit') }}</span>
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete('{{ $product->id }}', '{{ addslashes($product->name) }}')"
                                            class="h-7 w-7 rounded-[6px] bg-[#F8F9FA] dark:bg-[#151a26] hover:bg-rose-50 dark:hover:bg-rose-950/50 text-[#5A6578] hover:text-rose-600 dark:text-[#9DA4B2] dark:hover:text-rose-400 border border-[#E4E5E9] dark:border-[#1E2433] inline-flex items-center justify-center transition shadow-none cursor-pointer"
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
                    <x-button :href="route('products.create')" variant="primary" size="sm" wire:navigate class="rounded-[6px] !bg-[#FFEF4D] !text-[#12181E] hover:!bg-[#F3E13A] shadow-none font-medium">
                        <i class="fa-solid fa-plus mr-1 text-xs" aria-hidden="true"></i>
                        {{ __('Create Your First Item') }}
                    </x-button>
                @else
                    <x-button :href="route('brand.edit')" variant="primary" size="sm" wire:navigate class="rounded-[6px] !bg-[#FFEF4D] !text-[#12181E] hover:!bg-[#F3E13A] shadow-none font-medium">
                        <i class="fa-solid fa-paintbrush mr-1 text-xs" aria-hidden="true"></i>
                        {{ __('Complete Brand Settings First') }}
                    </x-button>
                @endif
            </x-slot:actions>
        </x-empty-state>
    @endif

    <!-- System UI Confirmation Modal -->
    <x-modal name="confirm-product-deletion-index" maxWidth="md">
        <div class="p-5 space-y-4 text-center">
            <div class="w-10 h-10 rounded-[8px] bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-base">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="space-y-1">
                <h3 class="text-sm font-semibold text-[#12181E] dark:text-white">
                    {{ __('Delete ":name"?', ['name' => $deletingProductName ?? 'Item']) }}
                </h3>
                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to permanently delete this inventory item? All associated media will be deleted from storage.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-2.5 pt-2">
                <x-button
                    type="button"
                    variant="secondary"
                    x-on:click="$dispatch('close-modal', 'confirm-product-deletion-index')"
                    class="font-medium text-xs rounded-[6px]"
                >
                    {{ __('Cancel') }}
                </x-button>
                <x-button
                    type="button"
                    variant="danger"
                    wire:click="deleteConfirmed"
                    class="font-medium text-xs shadow-none rounded-[6px]"
                >
                    <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                    {{ __('Confirm Delete') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
