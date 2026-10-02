<?php

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use App\Concerns\ResolvesCurrentOperator;
use App\Concerns\UsesMediaStore;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tour Packages & Combos')] class extends Component {
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
    public function packages()
    {
        if (! $this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->packages()
            ->with('products')
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%")->orWhere('category', 'like', "%{$this->search}%"))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->get();
    }

    public ?string $deletingPackageId = null;
    public ?string $deletingPackageTitle = null;

    public function confirmDelete(string $id, string $title): void
    {
        $this->deletingPackageId = $id;
        $this->deletingPackageTitle = $title;
        $this->dispatch('open-modal', 'confirm-package-deletion-index');
    }

    public function deleteConfirmed(): void
    {
        if (! $this->deletingPackageId) {
            return;
        }

        $this->deletePackage($this->deletingPackageId);
        $this->deletingPackageId = null;
        $this->deletingPackageTitle = null;
        $this->dispatch('close-modal', 'confirm-package-deletion-index');
    }

    public function deletePackage(string $id): void
    {
        $this->authorizeAbility('manageCatalog');

        $package = $this->currentOperator?->packages()->findOrFail($id);

        if ($package) {
            $this->media()->delete($package->cover_photo);
            $this->media()->deleteMany($package->gallery ?? []);
            $package->delete();
            $this->dispatch('toast', message: __('Tour package deleted successfully.'), type: 'success');
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
                        {{ __('Setup Required: Complete Profile & Terms to Publish Packages') }}
                    </h3>
                    <p class="text-xs text-[#5A6578] dark:text-amber-400/90 leading-relaxed">
                        {{ __('To protect guest reservations and comply with regulations, you must configure your WhatsApp contact, business bio, payout reference, and terms & conditions before publishing public packages.') }}
                    </p>
                </div>
            </div>
            <x-button :href="route('brand.edit')" size="sm" variant="primary" class="shrink-0 font-medium text-xs rounded-[6px] shadow-none !bg-[#FFEF4D] !text-[#12181E] hover:!bg-[#F3E13A]" wire:navigate>
                <i class="fa-solid fa-paintbrush mr-1.5 text-xs"></i>
                {{ __('Complete Brand Settings') }}
            </x-button>
        </div>
    @endif

    @php
        $agentPlan = $this->currentOperator?->getPlan();
        $packageLimit = $agentPlan?->package_limit;
        $totalListings = $this->currentOperator?->listingCount() ?? 0;
        $hasReachedLimit = $this->currentOperator && ! $this->currentOperator->canAddListing();
    @endphp

    <!-- Package Limit Banner -->
    @if ($hasReachedLimit)
        <div class="p-4 rounded-[12px] bg-[#F8F9FA] dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 animate-fade-in shadow-none">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-[8px] bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-crown text-xs"></i>
                </div>
                <div class="space-y-0.5">
                    <h3 class="text-xs sm:text-sm font-semibold text-[#12181E] dark:text-white">
                        {{ __('Listing limit reached (:count/:limit)', ['count' => $totalListings, 'limit' => $packageLimit]) }}
                    </h3>
                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                        {{ __('Trips and activities share the same listing limit on your :plan plan.', ['plan' => $agentPlan?->name ?? 'Starter']) }}
                    </p>
                </div>
            </div>
            <a href="{{ route('settings.plan') }}" class="px-3 py-1.5 rounded-[6px] bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs transition inline-flex items-center gap-1.5 shrink-0 shadow-none" wire:navigate>
                <i class="fa-solid fa-crown text-[10px] text-amber-300"></i>
                <span>{{ __('Upgrade Plan') }}</span>
            </a>
        </div>
    @endif

    <x-page-header
        :title="__('Tour Packages & Expeditions')"
        :subtitle="__('Create and manage multi-service experiences, private charters, and guided day trips bundled from your inventory items.')"
        icon="fa-cubes"
    >
        <x-slot:actions>
            @if ($this->isProfileComplete && $this->currentOperator?->canAddPackage())
                <x-button :href="route('packages.create')" wire:navigate class="rounded-[6px] text-xs font-medium !bg-[#FFEF4D] !text-[#12181E] hover:!bg-[#F3E13A] shadow-none">
                    <i class="fa-solid fa-plus text-xs mr-1"></i>
                    {{ __('Add Tour Package') }}
                </x-button>
            @elseif ($this->isProfileComplete)
                <x-button :href="route('packages.create')" wire:navigate class="rounded-[6px] text-xs font-medium">
                    <i class="fa-solid fa-plus text-xs mr-1"></i>
                    {{ __('Listing limit reached') }}
                </x-button>
            @else
                <x-button disabled class="rounded-[6px] text-xs font-medium">
                    <i class="fa-solid fa-plus text-xs mr-1"></i>
                    {{ __('Add Tour Package') }}
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-toolbar class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <div class="w-full sm:w-80">
            <x-search-input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('Search packages by title or category...')"
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

    <!-- Packages Table / List -->
    @if ($this->packages->isNotEmpty())
        <!-- Mobile Card List -->
        <div class="space-y-3 md:hidden">
            @foreach ($this->packages as $package)
                <div wire:key="pkg-card-{{ $package->id }}"
                    class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] p-3.5 shadow-none space-y-3">
                    <div class="flex items-start gap-3">
                        @if ($package->cover_photo_url)
                            <img src="{{ $package->cover_photo_url }}" alt=""
                                class="w-12 h-12 rounded-[8px] object-cover border border-[#E4E5E9] dark:border-[#1E2433] shrink-0" />
                        @else
                            <div class="w-12 h-12 rounded-[8px] bg-[#F8F9FA] text-[#12181E] dark:bg-[#151a26] dark:text-[#FFEF4D] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center shrink-0"
                                aria-hidden="true">
                                <i class="fa-solid fa-cubes text-xs"></i>
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('packages.edit', $package) }}" wire:navigate
                                class="block font-semibold text-xs sm:text-sm text-[#12181E] dark:text-white truncate">
                                {{ $package->title }}
                            </a>
                            <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] truncate">
                                {{ $package->category ?? __('Tour') }} &bull; {{ $package->location ?? __('General') }}
                            </p>
                            <p class="mt-0.5 font-mono text-xs font-semibold text-[#12181E] dark:text-white">
                                Rp {{ number_format((float) $package->price, 0, ',', '.') }}
                            </p>
                        </div>

                        <x-status-badge :status="$package->status" />
                    </div>

                    <div class="flex items-center justify-between gap-2 border-t border-[#E4E5E9] dark:border-[#1E2433] pt-2.5">
                        <span class="text-[11px] font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ trans_choice(':count bundled activity|:count bundled activities', $package->products->count(), ['count' => $package->products->count()]) }}
                        </span>

                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('packages.edit', $package) }}" wire:navigate
                                class="h-8 px-2.5 rounded-[6px] bg-[#F8F9FA] dark:bg-[#151a26] hover:bg-[#E4E5E9] dark:hover:bg-[#1E2433] text-[#12181E] dark:text-zinc-200 border border-[#E4E5E9] dark:border-[#1E2433] font-medium text-xs inline-flex items-center gap-1.5 transition">
                                <i class="fa-solid fa-pen text-[10px]" aria-hidden="true"></i>
                                {{ __('Edit') }}
                            </a>
                            <button type="button"
                                wire:click="confirmDelete('{{ $package->id }}', '{{ addslashes($package->title) }}')"
                                aria-label="{{ __('Delete :title', ['title' => $package->title]) }}"
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
                            <th class="px-4 py-3">{{ __('Tour Package') }}</th>
                            <th class="px-4 py-3">{{ __('Direct Price / Pax') }}</th>
                            <th class="px-4 py-3">{{ __('Bundled Inventory Items') }}</th>
                            <th class="px-4 py-3">{{ __('Photos') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                        @foreach ($this->packages as $package)
                            <tr class="hover:bg-[#F8F9FA] dark:hover:bg-[#151a26]/60 transition" wire:key="pkg-{{ $package->id }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($package->cover_photo_url)
                                            <img src="{{ $package->cover_photo_url }}" alt="{{ $package->title }}" class="w-9 h-9 rounded-[6px] object-cover border border-[#E4E5E9] dark:border-[#1E2433] shrink-0" />
                                        @else
                                            <div class="w-9 h-9 rounded-[6px] bg-[#F8F9FA] text-[#12181E] dark:bg-[#151a26] dark:text-[#FFEF4D] border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center font-medium text-xs shrink-0">
                                                <i class="fa-solid fa-cubes"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <a href="{{ route('packages.edit', $package) }}" wire:navigate class="font-semibold text-xs text-[#12181E] dark:text-white hover:text-amber-600 dark:hover:text-[#FFEF4D] transition">
                                                {{ $package->title }}
                                            </a>
                                            <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">{{ $package->category ?? 'Tour' }} &bull; {{ $package->location ?? 'General' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono font-medium text-xs text-[#12181E] dark:text-white">
                                    Rp {{ number_format((float) $package->price, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($package->products->isNotEmpty())
                                        <div class="flex flex-wrap gap-1 max-w-xs">
                                            @foreach ($package->products->take(3) as $prod)
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded-[4px] bg-[#F8F9FA] dark:bg-[#151a26] text-[#5A6578] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433]">
                                                    {{ $prod->name }} (x{{ $prod->pivot->quantity_required }})
                                                </span>
                                            @endforeach
                                            @if ($package->products->count() > 3)
                                                <span class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2] font-medium self-center">+{{ $package->products->count() - 3 }} more</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[#5A6578] dark:text-[#9DA4B2] text-xs italic">{{ __('No attached items') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $photoCount = ($package->cover_photo ? 1 : 0) + (is_array($package->gallery) ? count($package->gallery) : 0);
                                    @endphp
                                    <span class="inline-flex items-center gap-1 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                        <i class="fa-solid fa-image text-[10px]"></i>
                                        <span>{{ $photoCount }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge :status="$package->status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('packages.edit', $package) }}"
                                            wire:navigate
                                            class="h-7 px-2.5 rounded-[6px] bg-[#F8F9FA] dark:bg-[#151a26] hover:bg-[#E4E5E9] dark:hover:bg-[#1E2433] text-[#12181E] dark:text-zinc-200 border border-[#E4E5E9] dark:border-[#1E2433] font-medium text-xs transition inline-flex items-center gap-1 shadow-none"
                                            title="{{ __('Edit') }}"
                                        >
                                            <i class="fa-solid fa-pen text-[10px]"></i>
                                            <span>{{ __('Edit') }}</span>
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete('{{ $package->id }}', '{{ addslashes($package->title) }}')"
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
            icon="fa-cubes"
            :title="__('No Tour Packages Found')"
            :description="__('Create bundled day tours, private cruises, and snorkel expeditions by combining items from your inventory.')"
        >
            <x-slot:actions>
                @if ($this->isProfileComplete && $this->currentOperator?->canAddPackage())
                    <x-button :href="route('packages.create')" variant="primary" size="sm" wire:navigate class="rounded-[6px] !bg-[#FFEF4D] !text-[#12181E] hover:!bg-[#F3E13A] shadow-none font-medium">
                        <i class="fa-solid fa-plus mr-1 text-xs" aria-hidden="true"></i>
                        {{ __('Create Your First Package') }}
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
    <x-modal name="confirm-package-deletion-index" maxWidth="md">
        <div class="p-5 space-y-4 text-center">
            <div class="w-10 h-10 rounded-[8px] bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-base">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="space-y-1">
                <h3 class="text-sm font-semibold text-[#12181E] dark:text-white">
                    {{ __('Delete ":title"?', ['title' => $deletingPackageTitle ?? 'Package']) }}
                </h3>
                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to permanently delete this tour package? Inventory units attached to this package will remain safe and available.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-2.5 pt-2">
                <x-button
                    type="button"
                    variant="secondary"
                    x-on:click="$dispatch('close-modal', 'confirm-package-deletion-index')"
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
