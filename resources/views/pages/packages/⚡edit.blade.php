<?php

use App\Enums\ListingStatus;
use App\Models\Package;
use App\Concerns\ManagesPackageProductBundle;
use App\Concerns\ResolvesCurrentOperator;
use App\Concerns\UsesMediaStore;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit Tour Package')] class extends Component {
    use WithFileUploads;
    use ResolvesCurrentOperator;
    use UsesMediaStore;
    use ManagesPackageProductBundle;

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
    public function availableProducts()
    {
        if (!$this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->products()->where('status', ListingStatus::Published)->orderBy('name')->get(['id', 'name', 'category', 'capacity_per_day', 'price']);
    }

    #[Computed]
    public function suggestedCategories(): array
    {
        $defaults = ['Day Tour', 'Marine Expedition', 'VIP Charter', 'Snorkel Safari', 'Sunset Cruise', 'Scuba Diving', 'Island Escape'];
        if ($this->currentOperator) {
            $existing = $this->currentOperator->packages()->whereNotNull('category')->distinct()->pluck('category')->toArray();

            return array_values(array_unique(array_filter(array_merge($defaults, $existing))));
        }

        return $defaults;
    }

    public function mount(Package $package): void
    {
        if (!$this->currentOperator || $package->operator_id !== $this->currentOperator->id) {
            abort(403, 'Unauthorized access to this package.');
        }

        $this->package = $package->load('products');
        $this->title = $package->title;
        $this->category = $package->category ?? 'Day Tour';
        $this->location = $package->location ?? 'Nusa Penida & Bali';
        $this->price = (float) $package->price;
        $this->description = $package->description ?? '';
        $this->itinerary_text = $package->itinerary_text ?? '';
        $this->inclusions = !empty($package->inclusions) ? implode(', ', $package->inclusions) : '';
        $this->exclusions = !empty($package->exclusions) ? implode(', ', $package->exclusions) : '';
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
            $this->media()->delete($this->existingCoverPhoto);
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
        $pathToDelete = $this->pullExistingGalleryImage($index);

        if ($pathToDelete === null) {
            return;
        }

        $this->media()->delete($pathToDelete);

        $this->package->update([
            'gallery' => ! empty($this->existingGallery) ? $this->existingGallery : null,
        ]);
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
        if (!$this->currentOperator || $this->package->operator_id !== $this->currentOperator->id) {
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

        $previousCover = $this->package->cover_photo;
        $coverPath = $this->existingCoverPhoto;
        if ($this->coverPhoto) {
            if ($this->existingCoverPhoto && $this->existingCoverPhoto !== $previousCover) {
                $this->media()->delete($this->existingCoverPhoto);
            }
            $coverPath = $this->media()->storeUpload($this->coverPhoto, $this->operatorMediaDirectory('packages/covers'));
        }

        if ($previousCover && $previousCover !== $coverPath) {
            $this->media()->delete($previousCover);
        }

        $galleryPaths = $this->existingGallery;
        if (!empty($this->galleryFiles)) {
            foreach ($this->galleryFiles as $gFile) {
                $galleryPaths[] = $this->media()->storeUpload($gFile, $this->operatorMediaDirectory('packages/gallery'));
            }
        }

        $incArray = !empty($this->inclusions) ? array_map('trim', explode(',', $this->inclusions)) : null;
        $excArray = !empty($this->exclusions) ? array_map('trim', explode(',', $this->exclusions)) : null;

        $this->package->update([
            'title' => $this->title,
            'slug' => Str::slug($this->title),
            'category' => $this->category ?: null,
            'location' => $this->location ?: null,
            'price' => $this->price,
            'description' => $this->description ?: null,
            'itinerary_text' => $this->itinerary_text ?: null,
            'cover_photo' => $coverPath,
            'gallery' => !empty($galleryPaths) ? $galleryPaths : null,
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
        $this->package->products()->sync($syncData);

        $this->coverPhoto = null;
        $this->galleryFiles = [];
        $this->existingCoverPhoto = $this->package->fresh()->cover_photo;
        $this->existingGallery = $this->package->fresh()->gallery ?? [];

        $this->dispatch('toast', message: __('Tour package ":title" updated successfully.', ['title' => $this->title]), type: 'success');
    }

    public function delete(): void
    {
        if (!$this->currentOperator || $this->package->operator_id !== $this->currentOperator->id) {
            abort(403);
        }

        $this->media()->delete($this->package->cover_photo);
        $this->media()->deleteMany($this->package->gallery ?? []);

        $title = $this->package->title;
        $this->package->delete();

        session()->flash('success', __('Tour package ":title" deleted successfully.', ['title' => $title]));
        $this->redirect(route('packages.index'), navigate: true);
    }
}; ?>

<div class="space-y-6 w-full">
    <div class="space-y-6">

        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="mb-2 flex min-w-0 flex-wrap items-center gap-2">
                    <x-back-link :href="route('packages.index')">
                        {{ __('Back to packages') }}
                    </x-back-link>
                    <span class="hidden text-op-subtle sm:inline" aria-hidden="true">&bull;</span>
                    <span class="hidden min-w-0 truncate text-sm font-semibold text-op-subtle sm:inline">{{ $package->title }}</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-op-ink">
                    {{ __('Edit Tour Package / Expedition') }}
                </h1>
                <p class="text-xs sm:text-sm text-op-subtle mt-0.5 max-w-2xl">
                    {{ __('Update itineraries, bundled items, pricing, and photo gallery.') }}
                </p>
            </div>

            <div class="hidden sm:flex flex-wrap items-center gap-2">
                <x-button type="button" variant="danger" x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'confirm-package-deletion')"
                    class="font-semibold text-xs shadow-xs" title="{{ __('Delete Package') }}">
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
        <form wire:submit="save" class="space-y-4 sm:space-y-6">
            <!-- Card 1: Basics -->
            <div
                class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-op-surface border border-op-line shadow-xs space-y-4 sm:space-y-5">
                <div class="flex items-center gap-2 pb-3 border-b border-op-line">
                    <span
                        class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs">
                        <i class="fa-solid fa-cubes"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-op-ink">
                        {{ __('Package basics') }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2 space-y-1.5">
                        <x-label for="title" :value="__('Package Title')" required />
                        <x-input id="title" type="text" wire:model="title" required />
                        <x-input-error :messages="$errors->get('title')" />
                    </div>

                    <div class="sm:col-span-2 space-y-1.5">
                        <x-label for="category" :value="__('Category / Experience Type')" />
                        <x-input id="category" type="text" wire:model="category"
                            list="package-categories-edit-list"
                            placeholder="{{ __('e.g., Day Tour, Snorkel Safari, Private Charter...') }}" />
                        <datalist id="package-categories-edit-list">
                            @foreach ($this->suggestedCategories as $cat)
                                <option value="{{ $cat }}"></option>
                            @endforeach
                        </datalist>
                        <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                            <span
                                class="text-[10px] font-bold uppercase text-op-subtle mr-1">{{ __('Popular:') }}</span>
                            @foreach ($this->suggestedCategories as $cat)
                                <button type="button" wire:click="$set('category', '{{ $cat }}')"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-op-muted text-op-subtle hover:bg-op-muted hover:text-op-ink transition cursor-pointer">
                                    + {{ $cat }}
                                </button>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('category')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="location" :value="__('Location / Highlights')" />
                        <x-input id="location" type="text" wire:model="location" />
                        <x-input-error :messages="$errors->get('location')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="status" :value="__('Listing Status')" required />
                        <x-select id="status" wire:model="status" :options="[
                            'published' => __('Published (Visible on Storefront)'),
                            'draft' => __('Draft (Hidden from Public)'),
                        ]" />
                        <x-input-error :messages="$errors->get('status')" />
                    </div>
                </div>
            </div>

            <!-- Card 2: Activities -->
            <div
                class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-op-surface border border-op-line shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-op-line">
                    <span
                        class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs">
                        <i class="fa-solid fa-layer-group"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-op-ink">
                            {{ __('Bundle activities') }}
                        </h3>
                    </div>
                </div>

                <p class="text-xs text-op-subtle">
                    {{ __('Add the activities this package uses. Search if you have a long catalog — only selected items stay on the list.') }}
                </p>

                <x-package-bundle-picker
                    :has-catalog="$this->availableProducts->isNotEmpty()"
                    :selected="$this->bundledProducts"
                    :catalog="$this->bundleCatalog"
                    :remaining-count="$this->bundleRemainingCount"
                    :requires-search="$this->bundleRequiresSearch"
                    :search="$bundleSearch"
                    :empty-copy="__('No published activity/inventory items found.')"
                />
            </div>

            <!-- Card 3: Price -->
            <div
                class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-op-surface border border-op-line shadow-xs space-y-4 sm:space-y-5">
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-op-line">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs shrink-0">
                            <i class="fa-solid fa-tags"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-op-ink">
                                {{ __('Package price') }}
                            </h3>
                            <p class="text-[11px] text-op-subtle mt-0.5">
                                {{ __('What one guest pays for this whole package.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] gap-4 lg:gap-6 lg:items-start">
                    <div class="space-y-1.5">
                        <x-label for="price" :value="__('Price per guest (IDR)')" required />
                        <div class="relative mt-1">
                            <span
                                class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-op-subtle">IDR</span>
                            <x-input id="price" type="number" step="1000" min="0" wire:model.live="price"
                                class="pl-12 text-base sm:text-sm font-semibold tabular-nums" required />
                        </div>
                        @if ((float) $this->price > 0)
                            <p class="text-[11px] text-op-subtle tabular-nums">
                                {{ __('Shown as Rp :price', ['price' => number_format((float) $this->price, 0, ',', '.')]) }}
                            </p>
                        @endif
                        <x-input-error :messages="$errors->get('price')" />
                    </div>
                    <div class="min-w-0">
                        @include('pages.packages.partials.bundle-price-hint')
                    </div>
                </div>
            </div>

            <!-- Card 4: Cover & Gallery Image Management -->
            <div
                class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-op-surface border border-op-line shadow-xs space-y-5 sm:space-y-6">
                <div class="flex items-center gap-2 pb-3 border-b border-op-line">
                    <span
                        class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs">
                        <i class="fa-solid fa-images"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-op-ink">
                        {{ __('Cover & Gallery Images') }}
                    </h3>
                </div>

                <!-- Cover Photo Upload -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-xs font-bold text-op-ink">{{ __('Cover Photo') }}</h4>
                            <p class="text-[11px] text-op-subtle">
                                {{ __('Featured hero banner image displayed on storefront cards and package details. Max 5MB.') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4">
                        <div
                            class="relative w-full aspect-video sm:w-40 sm:h-24 sm:aspect-auto rounded-2xl border-2 border-dashed border-op-line bg-op-muted overflow-hidden flex items-center justify-center shrink-0 shadow-xs">
                            @if ($coverPhoto)
                                <img src="{{ $coverPhoto->temporaryUrl() }}" alt="Cover preview"
                                    class="w-full h-full object-cover" />
                                <button type="button" wire:click="removeTempCoverPhoto"
                                    class="absolute top-1.5 right-1.5 h-9 w-9 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition cursor-pointer"
                                    title="{{ __('Remove temp cover') }}">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            @elseif ($existingCoverPhoto)
                                <img src="{{ $this->mediaUrl($existingCoverPhoto) }}" alt="Cover"
                                    class="w-full h-full object-cover" />
                                <button type="button" wire:click="removeExistingCoverPhoto"
                                    class="absolute top-1.5 right-1.5 h-9 w-9 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition cursor-pointer"
                                    title="{{ __('Delete cover image') }}">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            @else
                                <div class="text-center p-2 text-op-subtle">
                                    <i class="fa-solid fa-image text-xl mb-0.5 block"></i>
                                    <span class="text-[9px] font-bold uppercase">{{ __('No Cover') }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-2 flex-1">
                            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                                <label
                                    class="h-11 sm:h-9 px-3.5 w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-op-muted hover:bg-op-line text-op-ink text-xs font-bold transition cursor-pointer">
                                    <i class="fa-solid fa-upload text-op-subtle"></i>
                                    <span>{{ $existingCoverPhoto || $coverPhoto ? __('Change Cover Photo') : __('Upload Cover Photo') }}</span>
                                    <input type="file" wire:model="coverPhoto"
                                        accept="image/png,image/jpeg,image/webp" class="hidden" />
                                </label>
                                @if (! empty($selectedProducts))
                                    <button
                                        type="button"
                                        x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'choose-activity-cover')"
                                        class="h-11 sm:h-9 px-3.5 w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-op-muted hover:bg-op-line text-op-ink text-xs font-bold transition cursor-pointer border border-op-line"
                                    >
                                        <i class="fa-solid fa-images text-op-subtle"></i>
                                        <span>{{ __('Use activity cover') }}</span>
                                    </button>
                                @endif
                            </div>
                            <div wire:loading wire:target="coverPhoto"
                                class="text-xs text-op-subtle font-semibold inline-flex items-center gap-1">
                                <i class="fa-solid fa-spinner fa-spin"></i> {{ __('Uploading cover...') }}
                            </div>
                            <x-input-error :messages="$errors->get('coverPhoto')" />
                        </div>
                    </div>
                </div>

                <!-- Gallery Images Multi-Upload -->
                <div class="pt-4 border-t border-op-line space-y-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h4 class="text-xs font-bold text-op-ink">{{ __('Gallery Photos') }}
                            </h4>
                            <p class="text-[11px] text-op-subtle">
                                {{ __('Upload photos or pull up to 2 gallery images from each selected activity.') }}</p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                            @if (! empty($selectedProducts))
                                <button
                                    type="button"
                                    wire:click="fillGalleryFromActivities"
                                    class="h-11 sm:h-10 px-4 w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-xl bg-op-muted hover:bg-op-line text-op-ink text-xs font-bold transition cursor-pointer border border-op-line"
                                >
                                    <i class="fa-solid fa-images text-[11px]"></i>
                                    <span>{{ __('Use activity photos') }}</span>
                                </button>
                            @endif
                            <label
                                class="h-11 sm:h-10 px-4 w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-xl bg-op-muted hover:bg-op-line text-op-ink text-xs font-bold transition cursor-pointer">
                                <i class="fa-solid fa-plus text-op-subtle text-[11px]"></i>
                                <span>{{ __('Add More Photos') }}</span>
                                <input type="file" wire:model="galleryFiles" accept="image/png,image/jpeg,image/webp"
                                    multiple class="hidden" />
                            </label>
                        </div>
                    </div>

                    <div wire:loading wire:target="galleryFiles"
                        class="text-xs text-op-subtle font-semibold inline-flex items-center gap-1">
                        <i class="fa-solid fa-spinner fa-spin"></i> {{ __('Uploading gallery photos...') }}
                    </div>
                    <x-input-error :messages="$errors->get('galleryFiles.*')" />

                    <!-- Gallery Preview Grid -->
                    @if (!empty($existingGallery) || !empty($galleryFiles))
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 pt-2">
                            <!-- Existing Saved Gallery Images -->
                            @foreach ($existingGallery as $idx => $photoPath)
                                <div
                                    class="relative group aspect-video rounded-xl border border-op-line bg-op-muted overflow-hidden shadow-xs">
                                    <img src="{{ $this->mediaUrl($photoPath) }}"
                                        alt="Gallery image {{ $idx }}" class="w-full h-full object-cover" />
                                    <button type="button"
                                        wire:click="removeExistingGalleryImage({{ $idx }})"
                                        class="absolute top-1.5 right-1.5 h-9 w-9 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700 cursor-pointer"
                                        title="{{ __('Delete photo') }}">
                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                    </button>
                                </div>
                            @endforeach

                            <!-- Newly Uploaded Previews -->
                            @foreach ($galleryFiles as $idx => $file)
                                <div
                                    class="relative group aspect-video rounded-xl border-2 border-brand-400/70 bg-brand-400/10 overflow-hidden shadow-xs">
                                    <img src="{{ $file->temporaryUrl() }}"
                                        alt="New gallery preview {{ $idx }}"
                                        class="w-full h-full object-cover" />
                                    <span
                                        class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded bg-brand-400 text-brand-foreground text-[9px] font-bold">{{ __('New') }}</span>
                                    <button type="button" wire:click="removeTempGalleryFile({{ $idx }})"
                                        class="absolute top-1.5 right-1.5 h-9 w-9 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700 cursor-pointer"
                                        title="{{ __('Remove photo') }}">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div
                            class="p-6 rounded-2xl border border-dashed border-op-line text-center text-xs text-op-subtle">
                            <i class="fa-regular fa-images text-2xl mb-1 block text-op-subtle"></i>
                            <span>{{ __('No gallery photos added yet. Click "Add More Photos" to upload.') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Card 5: Description, Itinerary & Policies -->
            <div
                class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-op-surface border border-op-line shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-op-line">
                    <span
                        class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs">
                        <i class="fa-solid fa-route"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-op-ink">
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
                        <p class="text-[11px] text-op-subtle">
                            {{ __('Each line will render as an interactive step in the guest itinerary timeline.') }}
                        </p>
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
                            <x-input id="free_cancellation_hours" type="number" min="0" max="720"
                                wire:model="free_cancellation_hours" required />
                            <p class="text-[11px] text-op-subtle">
                                {{ __('Hours prior to scheduled start for 100% refund.') }}</p>
                            <x-input-error :messages="$errors->get('free_cancellation_hours')" />
                        </div>

                        <div class="space-y-1.5">
                            <x-label for="advance_booking_hours" :value="__('Advance Booking Cutoff (Hours)')" required />
                            <x-input id="advance_booking_hours" type="number" min="0" max="720"
                                wire:model="advance_booking_hours" required />
                            <p class="text-[11px] text-op-subtle">
                                {{ __('Minimum advance booking notice required before tour start.') }}</p>
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
            <div
                class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-rose-500/5 dark:bg-rose-950/20 border border-rose-200/80 dark:border-rose-900/60 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4">
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-rose-900 dark:text-rose-200">
                        {{ __('Danger Zone: Delete this Tour Package') }}
                    </h4>
                    <p class="text-xs text-rose-700/80 dark:text-rose-400">
                        {{ __('Permanently removes this package offering and its associated media from storage. This action cannot be undone.') }}
                    </p>
                </div>

                <x-button type="button" variant="danger" x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'confirm-package-deletion')"
                    class="w-full sm:w-auto !h-11 sm:!h-10 shrink-0 font-semibold text-xs shadow-xs">
                    <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                    {{ __('Delete Package') }}
                </x-button>
            </div>

            <!-- Actions Bar -->
            <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2.5 sm:gap-3 pt-1 sm:pt-2">
                <x-button :href="route('packages.index')" variant="secondary" wire:navigate class="w-full sm:w-auto !h-11 sm:!h-10 font-semibold text-xs">
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="submit" variant="primary" class="w-full sm:w-auto !h-11 sm:!h-10 font-semibold text-xs shadow-xs">
                    <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                    {{ __('Save Changes') }}
                </x-button>
            </div>
        </form>

        <!-- System UI Confirmation Modal -->
        <x-modal name="confirm-package-deletion" maxWidth="md">
            <div class="p-6 space-y-4 text-center">
                <div
                    class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <div class="space-y-1.5">
                    <h3 class="text-base font-bold text-op-ink">
                        {{ __('Delete ":title"?', ['title' => $package->title]) }}
                    </h3>
                    <p class="text-xs text-op-subtle max-w-sm mx-auto leading-relaxed">
                        {{ __('Are you sure you want to permanently delete this tour package? Inventory units attached to this package will remain safe and available.') }}
                    </p>
                </div>

                <div class="flex items-center justify-center gap-3 pt-3">
                    <x-button type="button" variant="secondary"
                        x-on:click="$dispatch('close-modal', 'confirm-package-deletion')"
                        class="font-semibold text-xs">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button type="button" variant="danger" wire:click="delete"
                        class="font-semibold text-xs shadow-xs">
                        <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                        {{ __('Confirm Delete') }}
                    </x-button>
                </div>
            </div>
        </x-modal>

        @include('pages.packages.partials.activity-cover-picker-modal')
    </div>
</div>
