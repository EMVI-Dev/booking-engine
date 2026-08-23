<?php

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit Tour Package')] class extends Component {
    use WithFileUploads;

    public Package $package;

    public string $title = '';
    public string $category = 'Day Tour';
    public string $location = 'Nusa Penida & Bali';
    public float $price = 450000;
    public string $description = '';
    public string $itinerary_text = '';
    public string $inclusions = '';
    public string $exclusions = '';
    public string $terms_and_conditions = '';
    public int $free_cancellation_hours = 24;
    public int $advance_booking_hours = 12;
    public string $status = 'published';

    /**
     * Selected Products map: [product_id => quantity_required]
     *
     * @var array<string, int>
     */
    public array $selectedProducts = [];

    public ?string $existingCoverPhoto = null;
    /** @var array<string> */
    public array $existingGallery = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $coverPhoto = null;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $galleryFiles = [];

    #[Computed]
    public function currentOperator(): ?Operator
    {
        return Auth::user()?->currentOperator();
    }

    #[Computed]
    public function currentAgent(): ?Operator
    {
        return $this->currentOperator;
    }

    #[Computed]
    public function availableProducts()
    {
        if (! $this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->products()
            ->where('status', ListingStatus::Published)
            ->get();
    }

    #[Computed]
    public function suggestedCategories(): array
    {
        $defaults = ['Day Tour', 'Marine Expedition', 'VIP Charter', 'Snorkel Safari', 'Sunset Cruise', 'Scuba Diving', 'Island Escape'];
        if ($this->currentOperator) {
            $existing = $this->currentOperator->packages()
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->toArray();

            return array_values(array_unique(array_filter(array_merge($defaults, $existing))));
        }

        return $defaults;
    }

    public function mount(Package $package): void
    {
        if (! $this->currentOperator || $package->operator_id !== $this->currentOperator->id) {
            abort(403, 'Unauthorized access to this package.');
        }

        $this->package = $package->load('products');
        $this->title = $package->title;
        $this->category = $package->category ?? 'Day Tour';
        $this->location = $package->location ?? 'Nusa Penida & Bali';
        $this->price = (float) $package->price;
        $this->description = $package->description ?? '';
        $this->itinerary_text = $package->itinerary_text ?? '';
        $this->inclusions = ! empty($package->inclusions) ? implode(', ', $package->inclusions) : '';
        $this->exclusions = ! empty($package->exclusions) ? implode(', ', $package->exclusions) : '';
        $this->terms_and_conditions = $package->terms_and_conditions ?? '';
        $this->free_cancellation_hours = $package->free_cancellation_hours;
        $this->advance_booking_hours = $package->advance_booking_hours;
        $this->status = $package->status->value;
        $this->existingCoverPhoto = $package->cover_photo;
        $this->existingGallery = $package->gallery ?? [];

        foreach ($package->products as $product) {
            $this->selectedProducts[$product->id] = (int) ($product->pivot->quantity_required ?? 1);
        }
    }

    public function toggleProductSelection(string $productId): void
    {
        if (isset($this->selectedProducts[$productId])) {
            unset($this->selectedProducts[$productId]);
        } else {
            $this->selectedProducts[$productId] = 1;
        }
    }

    public function seedInclusionsFromProducts(): void
    {
        if (empty($this->selectedProducts)) {
            return;
        }

        $productNames = Product::whereIn('id', array_keys($this->selectedProducts))->pluck('name')->toArray();
        $existing = array_filter(array_map('trim', explode(',', $this->inclusions)));
        $combined = array_unique(array_merge($existing, $productNames));

        $this->inclusions = implode(', ', $combined);
    }

    public function updatedCoverPhoto(): void
    {
        $this->validate([
            'coverPhoto' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);
    }

    public function updatedGalleryFiles(): void
    {
        $this->validate([
            'galleryFiles.*' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);
    }

    public function removeExistingCoverPhoto(): void
    {
        if ($this->existingCoverPhoto) {
            Storage::disk('public')->delete($this->existingCoverPhoto);
            $this->package->update(['cover_photo' => null]);
            $this->existingCoverPhoto = null;
        }
    }

    public function removeTempCoverPhoto(): void
    {
        $this->coverPhoto = null;
    }

    public function removeExistingGalleryImage(int $index): void
    {
        if (isset($this->existingGallery[$index])) {
            $pathToDelete = $this->existingGallery[$index];
            Storage::disk('public')->delete($pathToDelete);
            unset($this->existingGallery[$index]);
            $this->existingGallery = array_values($this->existingGallery);

            $this->package->update([
                'gallery' => ! empty($this->existingGallery) ? $this->existingGallery : null,
            ]);
        }
    }

    public function removeTempGalleryFile(int $index): void
    {
        if (isset($this->galleryFiles[$index])) {
            unset($this->galleryFiles[$index]);
            $this->galleryFiles = array_values($this->galleryFiles);
        }
    }

    public function save(): void
    {
        if (! $this->currentOperator || $this->package->operator_id !== $this->currentOperator->id) {
            abort(403);
        }

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:3000'],
            'itinerary_text' => ['nullable', 'string', 'max:4000'],
            'inclusions' => ['nullable', 'string', 'max:1000'],
            'exclusions' => ['nullable', 'string', 'max:1000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:2000'],
            'free_cancellation_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'advance_booking_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'status' => ['required', 'in:draft,published'],
            'selectedProducts' => ['array'],
            'selectedProducts.*' => ['integer', 'min:1', 'max:1000'],
            'coverPhoto' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'galleryFiles.*' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        $coverPath = $this->existingCoverPhoto;
        if ($this->coverPhoto) {
            if ($this->existingCoverPhoto) {
                Storage::disk('public')->delete($this->existingCoverPhoto);
            }
            $coverPath = $this->coverPhoto->store('packages/covers', 'public');
        }

        $galleryPaths = $this->existingGallery;
        if (! empty($this->galleryFiles)) {
            foreach ($this->galleryFiles as $gFile) {
                $galleryPaths[] = $gFile->store('packages/gallery', 'public');
            }
        }

        $incArray = ! empty($this->inclusions) ? array_map('trim', explode(',', $this->inclusions)) : null;
        $excArray = ! empty($this->exclusions) ? array_map('trim', explode(',', $this->exclusions)) : null;

        $this->package->update([
            'title' => $this->title,
            'slug' => Str::slug($this->title),
            'category' => $this->category ?: null,
            'location' => $this->location ?: null,
            'price' => $this->price,
            'description' => $this->description ?: null,
            'itinerary_text' => $this->itinerary_text ?: null,
            'cover_photo' => $coverPath,
            'gallery' => ! empty($galleryPaths) ? $galleryPaths : null,
            'inclusions' => $incArray,
            'exclusions' => $excArray,
            'terms_and_conditions' => $this->terms_and_conditions ?: null,
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'advance_booking_hours' => $this->advance_booking_hours,
            'status' => $this->status,
        ]);

        // Sync attached products
        $syncData = [];
        foreach ($this->selectedProducts as $productId => $qty) {
            $syncData[$productId] = ['quantity_required' => max(1, (int) $qty)];
        }
        $this->coverPhoto = null;
        $this->galleryFiles = [];
        $this->existingCoverPhoto = $this->package->fresh()->cover_photo;
        $this->existingGallery = $this->package->fresh()->gallery ?? [];

        session()->flash('success', __('Tour package ":title" updated successfully.', ['title' => $this->title]));
    }

    public function delete(): void
    {
        if (! $this->currentOperator || $this->package->operator_id !== $this->currentOperator->id) {
            abort(403);
        }

        if ($this->package->cover_photo) {
            Storage::disk('public')->delete($this->package->cover_photo);
        }
        if (! empty($this->package->gallery)) {
            foreach ($this->package->gallery as $photo) {
                Storage::disk('public')->delete($photo);
            }
        }

        $title = $this->package->title;
        $this->package->delete();

        session()->flash('success', __('Tour package ":title" deleted successfully.', ['title' => $title]));
        $this->redirect(route('packages.index'), navigate: true);
    }
}; ?>

<div class="space-y-6 max-w-5xl">
    <!-- Desktop Notice on Mobile -->
    <x-desktop-only-notice
        :title="__('Tour Package Editing Best Managed on Desktop')"
        :description="__('Editing photo galleries, updating multi-day itineraries, and fine-tuning bundled products are best done on a computer or laptop.')"
    />

    <div class="hidden lg:block space-y-6">
        <!-- Success Banner -->
        @if (session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-900 text-xs font-semibold text-emerald-800 dark:text-emerald-300 flex items-center justify-between gap-2 animate-fade-in">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    @endif

    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                <a href="{{ route('packages.index') }}" wire:navigate class="hover:text-slate-900 dark:hover:text-white transition flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>{{ __('Tour Packages') }}</span>
                </a>
                <span>&bull;</span>
                <span class="text-slate-900 dark:text-white truncate max-w-xs">{{ $package->title }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ __('Edit Tour Package / Expedition') }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Update itineraries, bundled items, pricing, and photo gallery.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-button
                type="button"
                variant="danger"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'confirm-package-deletion')"
                class="font-semibold text-xs shadow-xs"
                title="{{ __('Delete Package') }}"
            >
                <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                {{ __('Delete') }}
            </x-button>
            <x-button :href="route('packages.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
                {{ __('Cancel') }}
            </x-button>
            <x-button wire:click="save" variant="primary" class="font-semibold text-xs shadow-xs">
                <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                {{ __('Save Changes') }}
            </x-button>
        </div>
    </div>

    <!-- Main Edit Form -->
    <form wire:submit="save" class="space-y-6">
        <!-- Card 1: Core Package Details & Pricing -->
        <div class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                    <i class="fa-solid fa-cubes"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Package Overview & Direct Pricing') }}
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2 space-y-1.5">
                    <x-label for="title" :value="__('Package Title')" required />
                    <x-input id="title" type="text" wire:model="title" required />
                    <x-input-error :messages="$errors->get('title')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="category" :value="__('Category / Experience Type')" />
                    <div class="space-y-1.5">
                        <x-input
                            id="category"
                            type="text"
                            wire:model="category"
                            list="package-categories-edit-list"
                            placeholder="{{ __('e.g., Day Tour, Snorkel Safari, Private Charter...') }}"
                        />
                        <datalist id="package-categories-edit-list">
                            @foreach ($this->suggestedCategories as $cat)
                                <option value="{{ $cat }}"></option>
                            @endforeach
                        </datalist>
                        <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                            <span class="text-[10px] font-bold uppercase text-slate-400 mr-1">{{ __('Popular:') }}</span>
                            @foreach ($this->suggestedCategories as $cat)
                                <button
                                    type="button"
                                    wire:click="$set('category', '{{ $cat }}')"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950 dark:hover:text-indigo-400 transition cursor-pointer"
                                >
                                    + {{ $cat }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('category')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="location" :value="__('Location / Highlights')" />
                    <x-input id="location" type="text" wire:model="location" />
                    <x-input-error :messages="$errors->get('location')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="price" :value="__('Selling Price per Guest / Unit (IDR)')" required />
                    <div class="relative mt-1">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">IDR</span>
                        <x-input id="price" type="number" step="1000" min="0" wire:model="price" class="pl-12" required />
                    </div>
                    <x-input-error :messages="$errors->get('price')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="status" :value="__('Listing Status')" required />
                    <x-select
                        id="status"
                        wire:model="status"
                        :options="[
                            'published' => __('Published (Visible on Storefront)'),
                            'draft' => __('Draft (Hidden from Public)'),
                        ]"
                    />
                    <x-input-error :messages="$errors->get('status')" />
                </div>
            </div>
        </div>

        <!-- Card 2: Inventory Composition (Bundled Products) -->
        <div class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 text-xs">
                        <i class="fa-solid fa-layer-group"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Bundle Inventory Items & Services') }}
                        </h3>
                    </div>
                </div>

                @if (! empty($selectedProducts))
                    <button
                        type="button"
                        wire:click="seedInclusionsFromProducts"
                        class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1 cursor-pointer"
                    >
                        <i class="fa-solid fa-wand-magic-sparkles text-[10px]"></i>
                        <span>{{ __('Auto-populate Inclusions from Items') }}</span>
                    </button>
                @endif
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Select which inventory units (e.g., boat seats, snorkel gear, guide) are reserved whenever this package is booked. Capacity will be automatically synchronized.') }}
            </p>

            @if ($this->availableProducts->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                    @foreach ($this->availableProducts as $prod)
                        @php
                            $isSelected = isset($selectedProducts[$prod->id]);
                        @endphp
                        <div
                            class="p-3.5 rounded-2xl border transition-all flex items-center justify-between gap-3 {{ $isSelected ? 'border-sky-500 bg-sky-50/50 dark:bg-sky-950/30 ring-1 ring-sky-500/20' : 'border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:border-slate-300' }}"
                        >
                            <label class="flex items-center gap-3 cursor-pointer flex-1 min-w-0">
                                <input
                                    type="checkbox"
                                    wire:click="toggleProductSelection('{{ $prod->id }}')"
                                    @checked($isSelected)
                                    class="rounded-lg text-sky-600 focus:ring-sky-500 border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-800"
                                />
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $prod->name }}</p>
                                    <p class="text-[10px] text-slate-400">
                                        {{ $prod->category ?? 'Item' }} &bull; Max {{ $prod->capacity_per_day }}/day
                                    </p>
                                </div>
                            </label>

                            @if ($isSelected)
                                <div class="flex items-center gap-1.5 shrink-0 bg-white dark:bg-zinc-900 px-2 py-1 rounded-xl border border-slate-200 dark:border-zinc-700">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">{{ __('Qty:') }}</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max="1000"
                                        wire:model="selectedProducts.{{ $prod->id }}"
                                        class="w-12 h-6 text-xs text-center font-bold rounded-md border-0 bg-slate-50 dark:bg-zinc-800 focus:ring-1 focus:ring-sky-500"
                                    />
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-6 rounded-2xl border border-dashed border-slate-200 dark:border-zinc-800 text-center text-xs text-slate-400">
                    {{ __('No published activity/inventory items found.') }}
                </div>
            @endif
        </div>

        <!-- Card 3: Cover & Gallery Image Management -->
        <div class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-6">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                    <i class="fa-solid fa-images"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Cover & Gallery Images') }}
                </h3>
            </div>

            <!-- Cover Photo Upload -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Cover Photo') }}</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Featured hero banner image displayed on storefront cards and package details. Max 5MB.') }}</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="relative w-40 h-24 rounded-2xl border-2 border-dashed border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800/60 overflow-hidden flex items-center justify-center shrink-0 shadow-xs">
                        @if ($coverPhoto)
                            <img src="{{ $coverPhoto->temporaryUrl() }}" alt="Cover preview" class="w-full h-full object-cover" />
                            <button
                                type="button"
                                wire:click="removeTempCoverPhoto"
                                class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition cursor-pointer"
                                title="{{ __('Remove temp cover') }}"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        @elseif ($existingCoverPhoto)
                            <img src="{{ Storage::url($existingCoverPhoto) }}" alt="Cover" class="w-full h-full object-cover" />
                            <button
                                type="button"
                                wire:click="removeExistingCoverPhoto"
                                class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition cursor-pointer"
                                title="{{ __('Delete cover image') }}"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        @else
                            <div class="text-center p-2 text-slate-400">
                                <i class="fa-solid fa-image text-xl mb-0.5 block"></i>
                                <span class="text-[9px] font-bold uppercase">{{ __('No Cover') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-2 flex-1">
                        <label class="h-9 px-3.5 inline-flex items-center gap-2 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer">
                            <i class="fa-solid fa-upload text-indigo-500"></i>
                            <span>{{ $existingCoverPhoto ? __('Change Cover Photo') : __('Upload Cover Photo') }}</span>
                            <input type="file" wire:model="coverPhoto" accept="image/png,image/jpeg,image/webp" class="hidden" />
                        </label>
                        <div wire:loading wire:target="coverPhoto" class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold inline-flex items-center gap-1">
                            <i class="fa-solid fa-spinner fa-spin"></i> {{ __('Uploading cover...') }}
                        </div>
                        <x-input-error :messages="$errors->get('coverPhoto')" />
                    </div>
                </div>
            </div>

            <!-- Gallery Images Multi-Upload -->
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Gallery Photos') }}</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('High-resolution photos showcasing itinerary highlights.') }}</p>
                    </div>

                    <label class="h-8 px-3 inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer">
                        <i class="fa-solid fa-plus text-indigo-500 text-[11px]"></i>
                        <span>{{ __('Add More Photos') }}</span>
                        <input type="file" wire:model="galleryFiles" accept="image/png,image/jpeg,image/webp" multiple class="hidden" />
                    </label>
                </div>

                <div wire:loading wire:target="galleryFiles" class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold inline-flex items-center gap-1">
                    <i class="fa-solid fa-spinner fa-spin"></i> {{ __('Uploading gallery photos...') }}
                </div>
                <x-input-error :messages="$errors->get('galleryFiles.*')" />

                <!-- Gallery Preview Grid -->
                @if (! empty($existingGallery) || ! empty($galleryFiles))
                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3 pt-2">
                        <!-- Existing Saved Gallery Images -->
                        @foreach ($existingGallery as $idx => $photoPath)
                            <div class="relative group aspect-video rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-100 dark:bg-zinc-800 overflow-hidden shadow-xs">
                                <img src="{{ Storage::url($photoPath) }}" alt="Gallery image {{ $idx }}" class="w-full h-full object-cover" />
                                <button
                                    type="button"
                                    wire:click="removeExistingGalleryImage({{ $idx }})"
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700 cursor-pointer"
                                    title="{{ __('Delete photo') }}"
                                >
                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                </button>
                            </div>
                        @endforeach

                        <!-- Newly Uploaded Previews -->
                        @foreach ($galleryFiles as $idx => $file)
                            <div class="relative group aspect-video rounded-xl border-2 border-indigo-400 bg-indigo-50/20 overflow-hidden shadow-xs">
                                <img src="{{ $file->temporaryUrl() }}" alt="New gallery preview {{ $idx }}" class="w-full h-full object-cover" />
                                <span class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded bg-indigo-600 text-white text-[9px] font-bold">{{ __('New') }}</span>
                                <button
                                    type="button"
                                    wire:click="removeTempGalleryFile({{ $idx }})"
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700 cursor-pointer"
                                    title="{{ __('Remove photo') }}"
                                >
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 rounded-2xl border border-dashed border-slate-200 dark:border-zinc-800 text-center text-xs text-slate-400">
                        <i class="fa-regular fa-images text-2xl mb-1 block text-slate-300 dark:text-slate-600"></i>
                        <span>{{ __('No gallery photos added yet. Click "Add More Photos" to upload.') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Card 4: Description, Itinerary & Policies -->
        <div class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                    <i class="fa-solid fa-route"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Itinerary, Inclusions & Booking Policies') }}
                </h3>
            </div>

            <div class="space-y-4">
                <div class="space-y-1.5">
                    <x-label for="description" :value="__('Package Overview / Highlights')" />
                    <x-textarea id="description" wire:model="description" rows="3" />
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="itinerary_text" :value="__('Chronological Itinerary Schedule (Line-by-line)')" />
                    <x-textarea id="itinerary_text" wire:model="itinerary_text" rows="5" />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Each line will render as an interactive step in the guest itinerary timeline.') }}</p>
                    <x-input-error :messages="$errors->get('itinerary_text')" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <x-label for="inclusions" :value="__('What is Included (Comma-separated)')" />
                        <x-input id="inclusions" type="text" wire:model="inclusions" />
                        <x-input-error :messages="$errors->get('inclusions')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="exclusions" :value="__('What is Excluded (Comma-separated)')" />
                        <x-input id="exclusions" type="text" wire:model="exclusions" />
                        <x-input-error :messages="$errors->get('exclusions')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div class="space-y-1.5">
                        <x-label for="free_cancellation_hours" :value="__('Free Cancellation Window (Hours)')" required />
                        <x-input id="free_cancellation_hours" type="number" min="0" max="720" wire:model="free_cancellation_hours" required />
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Hours prior to scheduled start for 100% refund.') }}</p>
                        <x-input-error :messages="$errors->get('free_cancellation_hours')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="advance_booking_hours" :value="__('Advance Booking Cutoff (Hours)')" required />
                        <x-input id="advance_booking_hours" type="number" min="0" max="720" wire:model="advance_booking_hours" required />
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Minimum advance booking notice required before tour start.') }}</p>
                        <x-input-error :messages="$errors->get('advance_booking_hours')" />
                    </div>
                </div>

                <div class="space-y-1.5 pt-2">
                    <x-label for="terms_and_conditions" :value="__('Specific Tour Terms & Conditions (Optional)')" />
                    <x-textarea id="terms_and_conditions" wire:model="terms_and_conditions" rows="2" />
                    <x-input-error :messages="$errors->get('terms_and_conditions')" />
                </div>
            </div>
        </div>

        <!-- Danger Zone: Delete -->
        <div class="p-6 sm:p-7 rounded-3xl bg-rose-500/5 dark:bg-rose-950/20 border border-rose-200/80 dark:border-rose-900/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <h4 class="text-sm font-bold text-rose-900 dark:text-rose-200">
                    {{ __('Danger Zone: Delete this Tour Package') }}
                </h4>
                <p class="text-xs text-rose-700/80 dark:text-rose-400">
                    {{ __('Permanently removes this package offering and its associated media from storage. This action cannot be undone.') }}
                </p>
            </div>

            <x-button
                type="button"
                variant="danger"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'confirm-package-deletion')"
                class="shrink-0 font-semibold text-xs shadow-xs"
            >
                <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                {{ __('Delete Package') }}
            </x-button>
        </div>

        <!-- Actions Bar -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <x-button :href="route('packages.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
                {{ __('Cancel') }}
            </x-button>
            <x-button type="submit" variant="primary" class="font-semibold text-xs shadow-xs">
                <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                {{ __('Save Changes') }}
            </x-button>
        </div>
    </form>

    <!-- System UI Confirmation Modal -->
    <x-modal name="confirm-package-deletion" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ __('Delete ":title"?', ['title' => $package->title]) }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to permanently delete this tour package? Inventory units attached to this package will remain safe and available.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-3">
                <x-button
                    type="button"
                    variant="secondary"
                    x-on:click="$dispatch('close-modal', 'confirm-package-deletion')"
                    class="font-semibold text-xs"
                >
                    {{ __('Cancel') }}
                </x-button>
                <x-button
                    type="button"
                    variant="danger"
                    wire:click="delete"
                    class="font-semibold text-xs shadow-xs"
                >
                    <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                    {{ __('Confirm Delete') }}
                </x-button>
            </div>
        </div>
    </x-modal>
    </div>
</div>
