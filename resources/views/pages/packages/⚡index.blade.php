<?php

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use App\Concerns\ResolvesCurrentOperator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tour Packages & Combos')] class extends Component {
    use ResolvesCurrentOperator;

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
        $package = $this->currentOperator?->packages()->findOrFail($id);

        if ($package) {
            if ($package->cover_photo) {
                Storage::disk('public')->delete($package->cover_photo);
            }
            if (! empty($package->gallery)) {
                foreach ($package->gallery as $photo) {
                    Storage::disk('public')->delete($photo);
                }
            }
            $package->delete();
            session()->flash('success', __('Tour package deleted successfully.'));
        }
    }
}; ?>

<div class="space-y-6">
    <!-- Success Banner -->
    @if (session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-900 text-xs font-semibold text-emerald-800 dark:text-emerald-300 flex items-center justify-between gap-2 animate-fade-in">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    @endif

    <!-- Profile Incomplete Locking Warning -->
    @if (! $this->isProfileComplete)
        <div class="p-5 rounded-3xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-900/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 animate-fade-in">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black flex items-center justify-center shrink-0 shadow-xs">
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-amber-200">
                        {{ __('Setup Required: Complete Profile & Terms to Publish Packages') }}
                    </h3>
                    <p class="text-xs text-slate-600 dark:text-amber-400 leading-relaxed">
                        {{ __('To protect guest reservations and comply with regulations, you must configure your WhatsApp contact, business bio, payout reference, and terms & conditions before publishing public packages.') }}
                    </p>
                </div>
            </div>
            <x-button :href="route('brand.edit')" size="sm" variant="primary" class="shrink-0 font-semibold shadow-xs" wire:navigate>
                <i class="fa-solid fa-paintbrush mr-1.5 text-xs"></i>
                {{ __('Complete Brand Settings') }}
            </x-button>
        </div>
    @endif

    @php
        $agentPlan = $this->currentOperator?->getPlan();
        $packageLimit = $agentPlan?->package_limit;
        $totalPackages = $this->currentOperator?->packages()->count() ?? 0;
        $hasReachedLimit = $packageLimit !== null && $totalPackages >= $packageLimit;
    @endphp

    <!-- Package Limit Banner -->
    @if ($hasReachedLimit)
        <div class="p-5 rounded-3xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 animate-fade-in">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                    <i class="fa-solid fa-crown text-sm"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                        {{ __('Package Limit Reached (:count/:limit Listings)', ['count' => $totalPackages, 'limit' => $packageLimit]) }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('You have reached the maximum number of tour listings allowed on your :plan plan. Upgrade your plan to list more tour packages.', ['plan' => $agentPlan?->name ?? 'Starter']) }}
                    </p>
                </div>
            </div>
            <a href="{{ route('settings.plan') }}" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs transition inline-flex items-center gap-1.5 shrink-0 shadow-xs" wire:navigate>
                <i class="fa-solid fa-crown text-[10px] text-amber-300"></i>
                <span>{{ __('Upgrade Plan') }}</span>
            </a>
        </div>
    @endif



    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400">
                    <i class="fa-solid fa-cubes text-lg"></i>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Tour Packages & Expeditions') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1">
                {{ __('Create and manage multi-service experiences, private charters, and guided day trips bundled from your inventory items.') }}
            </p>
        </div>

        @if ($this->isProfileComplete)
            <x-button
                :href="route('packages.create')"
                variant="primary"
                class="shrink-0 shadow-xs transition-all"
                wire:navigate
            >
                <i class="fa-solid fa-plus mr-1 text-xs"></i>
                {{ __('Add Tour Package') }}
            </x-button>
        @else
            <x-button
                variant="primary"
                class="shrink-0 shadow-xs transition-all opacity-50 cursor-not-allowed"
                disabled
            >
                <i class="fa-solid fa-plus mr-1 text-xs"></i>
                {{ __('Add Tour Package') }}
            </x-button>
        @endif
    </div>

    <!-- Search & Filter Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs">
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search packages by title or category...') }}"
                class="w-full h-10 pl-9 pr-4 rounded-xl border border-slate-200 dark:border-[#1e2433] bg-slate-50 dark:bg-[#0c0e14] text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-[#FFEF4D]"
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
    </div>

    <!-- Packages Table / List -->
    @if ($this->packages->isNotEmpty())
        <div class="overflow-hidden rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 dark:bg-[#10141d] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 border-b border-slate-200/80 dark:border-[#1e2433]">
                        <tr>
                            <th class="px-5 py-3.5">{{ __('Tour Package') }}</th>
                            <th class="px-5 py-3.5">{{ __('Direct Price / Pax') }}</th>
                            <th class="px-5 py-3.5">{{ __('Bundled Inventory Items') }}</th>
                            <th class="px-5 py-3.5">{{ __('Photos') }}</th>
                            <th class="px-5 py-3.5">{{ __('Status') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                        @foreach ($this->packages as $package)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group" wire:key="pkg-{{ $package->id }}">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($package->cover_photo_url)
                                            <img src="{{ $package->cover_photo_url }}" alt="{{ $package->title }}" class="w-12 h-10 rounded-xl object-cover border border-slate-200/80 dark:border-[#1e2433] shrink-0" />
                                        @else
                                            <div class="w-12 h-10 rounded-xl bg-[#141821] text-[#FFEF4D] border border-[#1e2433] flex items-center justify-center font-bold text-xs shrink-0">
                                                <i class="fa-solid fa-cubes"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <a href="{{ route('packages.edit', $package) }}" wire:navigate class="font-bold text-slate-900 dark:text-white text-sm hover:text-[#FFEF4D] transition">
                                                {{ $package->title }}
                                            </a>
                                            <p class="text-[11px] text-slate-400">{{ $package->category ?? 'Tour' }} &bull; {{ $package->location ?? 'General' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-mono font-bold text-slate-900 dark:text-white">
                                    Rp {{ number_format((float) $package->price, 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-4">
                                    @if ($package->products->isNotEmpty())
                                        <div class="flex flex-wrap gap-1.5 max-w-xs">
                                            @foreach ($package->products->take(3) as $prod)
                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-[#141821] text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]">
                                                    {{ $prod->name }} (x{{ $prod->pivot->quantity_required }})
                                                </span>
                                            @endforeach
                                            @if ($package->products->count() > 3)
                                                <span class="text-[10px] text-slate-400 font-bold self-center">+{{ $package->products->count() - 3 }} more</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400 text-xs italic">{{ __('No attached items') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @php
                                        $photoCount = ($package->cover_photo ? 1 : 0) + (is_array($package->gallery) ? count($package->gallery) : 0);
                                    @endphp
                                    <span class="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                        <i class="fa-solid fa-image text-[10px]"></i>
                                        <span>{{ $photoCount }}</span>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $package->status->value === 'published' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60' : 'bg-slate-100 text-slate-700 dark:bg-[#141821] dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $package->status->value === 'published' ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-slate-400' }}"></span>
                                        {{ ucfirst($package->status->value) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('packages.edit', $package) }}"
                                            wire:navigate
                                            class="h-8 px-3 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#1e2433] font-bold text-xs transition inline-flex items-center gap-1 shadow-2xs"
                                            title="{{ __('Edit') }}"
                                        >
                                            <i class="fa-solid fa-pen text-[10px]"></i>
                                            <span>{{ __('Edit') }}</span>
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete('{{ $package->id }}', '{{ addslashes($package->title) }}')"
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
        <div class="text-center py-16 px-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] space-y-4 shadow-xs">
            <div class="w-12 h-12 rounded-2xl bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400 font-black flex items-center justify-center mx-auto text-xl shadow-xs">
                <i class="fa-solid fa-cubes"></i>
            </div>
            <div class="space-y-1 max-w-md mx-auto">
                <h3 class="font-bold text-base text-slate-900 dark:text-white">{{ __('No Tour Packages Found') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Create bundled day tours, private cruises, and snorkel expeditions by combining items from your inventory.') }}
                </p>
            </div>
            @if ($this->isProfileComplete)
                <x-button :href="route('packages.create')" variant="primary" size="sm" wire:navigate>
                    <i class="fa-solid fa-plus mr-1 text-xs"></i>
                    {{ __('Create Your First Package') }}
                </x-button>
            @else
                <x-button :href="route('brand.edit')" variant="primary" size="sm" wire:navigate>
                    <i class="fa-solid fa-paintbrush mr-1 text-xs"></i>
                    {{ __('Complete Brand Settings First') }}
                </x-button>
            @endif
        </div>
    @endif

    <!-- System UI Confirmation Modal -->
    <x-modal name="confirm-package-deletion-index" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ __('Delete ":title"?', ['title' => $deletingPackageTitle ?? 'Package']) }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to permanently delete this tour package? Inventory units attached to this package will remain safe and available.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-3">
                <x-button
                    type="button"
                    variant="secondary"
                    x-on:click="$dispatch('close-modal', 'confirm-package-deletion-index')"
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
