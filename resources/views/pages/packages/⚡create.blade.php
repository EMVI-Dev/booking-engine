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

new #[Title('Create Tour Package')] class extends Component {
    use WithFileUploads;
    use ResolvesCurrentOperator;
    use UsesMediaStore;
    use ManagesPackageProductBundle;

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

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $coverPhoto = null;

    public ?string $existingCoverPhoto = null;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $galleryFiles = [];

    /**
     * Storage paths already staged for the package gallery (imports + create flow).
     *
     * @var list<string>
     */
    public array $existingGallery = [];

    #[Computed]
    public function isProfileComplete(): bool
    {
        return (bool) $this->currentOperator?->isProfileComplete();
    }

    #[Computed]
    public function availableProducts()
    {
        if (!$this->currentOperator) {
            return collect();
        }

        return $this->currentOperator
            ->products()
            ->where('status', ListingStatus::Published)
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'capacity_per_day', 'price']);
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

    public function mount(): void
    {
        if (!$this->isProfileComplete) {
            session()->flash('warning', __('Please complete your business profile and payout settings before creating tour packages.'));
        }
    }

    public function updatedCoverPhoto(): void
    {
        $this->validate([
            'coverPhoto' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        if ($this->coverPhoto && $this->existingCoverPhoto) {
            $this->media()->delete($this->existingCoverPhoto);
            $this->existingCoverPhoto = null;
        }
    }

    public function updatedGalleryFiles(): void
    {
        $this->validate([
            'galleryFiles.*' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);
    }

    public function removeTempCoverPhoto(): void
    {
        $this->coverPhoto = null;
    }

    public function removeExistingCoverPhoto(): void
    {
        if ($this->existingCoverPhoto) {
            $this->media()->delete($this->existingCoverPhoto);
            $this->existingCoverPhoto = null;
        }
    }

    public function removeTempGalleryFile(int $index): void
    {
        if (isset($this->galleryFiles[$index])) {
            unset($this->galleryFiles[$index]);
            $this->galleryFiles = array_values($this->galleryFiles);
        }
    }

    public function removeExistingGalleryImage(int $index): void
    {
        $path = $this->pullExistingGalleryImage($index);

        if ($path === null) {
            return;
        }

        $this->media()->delete($path);
    }

    public function save(): void
    {
        if (!$this->isProfileComplete) {
            $this->addError('profile', __('You must complete your business profile, WhatsApp contact, payout reference, and terms & conditions in settings before creating packages.'));
            return;
        }

        if (!$this->currentOperator) {
            return;
        }

        if (!$this->currentOperator->canAddPackage()) {
            $this->addError(
                'profile',
                __('You have reached the maximum package limit (:limit listings) for your :plan plan. Please upgrade your subscription to create more packages.', [
                    'limit' => $this->currentOperator->getPlan()->package_limit,
                    'plan' => $this->currentOperator->getPlan()->name,
                ]),
            );
            return;
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
                $this->media()->delete($this->existingCoverPhoto);
            }
            $coverPath = $this->media()->storeUpload($this->coverPhoto, $this->operatorMediaDirectory('packages/covers'));
        }

        $galleryPaths = $this->existingGallery;
        if (!empty($this->galleryFiles)) {
            foreach ($this->galleryFiles as $gFile) {
                $galleryPaths[] = $this->media()->storeUpload($gFile, $this->operatorMediaDirectory('packages/gallery'));
            }
        }

        $incArray = !empty($this->inclusions) ? array_map('trim', explode(',', $this->inclusions)) : null;
        $excArray = !empty($this->exclusions) ? array_map('trim', explode(',', $this->exclusions)) : null;

        $package = $this->currentOperator->packages()->create([
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
        $package->products()->sync($syncData);

        session()->flash('success', __('Tour package ":title" created successfully.', ['title' => $package->title]));
        $this->redirect(route('packages.index'), navigate: true);
    }
}; ?>

<div class="space-y-6 w-full">
    <div class="space-y-6">
        @if ($this->currentOperator && !$this->currentOperator->canAddPackage())
            <div class="py-6">
                <x-feature-gate :title="__('Package Limit Reached (:limit Listings)', [
                    'limit' => $this->currentOperator->getPlan()->package_limit,
                ])" :description="__(
                    'Trips and activities share the same listing limit on your :plan plan. Upgrade to list more.',
                    ['plan' => $this->currentOperator->getPlan()->name],
                )" required-plan="Growth" plan-slug="growth"
                    icon="fa-solid fa-cubes" :features="[
                        __('Up to 25 listings on Growth (unlimited on Agency)'),
                        __('Google & Apple Calendar live syncing for tour bookings'),
                        __('Automated 12-hour review request emails'),
                        __('Customer Directory CRM and WhatsApp ticket dispatch'),
                    ]" />
            </div>
        @else
            <!-- Breadcrumb & Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="mb-2">
                        <x-back-link :href="route('packages.index')">
                            {{ __('Back to packages') }}
                        </x-back-link>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-op-ink">
                        {{ __('Create Tour Package / Experience') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-op-subtle mt-0.5 max-w-2xl">
                        {{ __('Bundle activities, transport, guide hire, and equipment into an all-inclusive public package.') }}
                    </p>
                </div>

                <div class="hidden sm:flex flex-wrap items-center gap-2">
                    <x-button :href="route('packages.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button wire:click="save" variant="primary" class="font-semibold text-xs shadow-xs"
                        :disabled="!$this->isProfileComplete">
                        <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                        {{ __('Save & Publish') }}
                    </x-button>
                </div>
            </div>

            <!-- Main Create Form -->
            <form wire:submit="save" class="space-y-4 sm:space-y-6">
                @if ($errors->has('profile'))
                    <div
                        class="p-3.5 sm:p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-xs font-semibold text-rose-700 dark:text-rose-300 flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-500"></i>
                        <span>{{ $errors->first('profile') }}</span>
                    </div>
                @endif

                <!-- Card 1: Basics -->
                <div
                    class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-op-surface border border-op-line shadow-xs space-y-4 sm:space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b border-op-line">
                        <span class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs">
                            <i class="fa-solid fa-cubes"></i>
                        </span>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-op-ink">
                            {{ __('Package basics') }}
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2 space-y-1.5">
                            <x-label for="title" :value="__('Package Title')" required />
                            <x-input id="title" type="text" wire:model="title"
                                placeholder="{{ __('e.g., Nusa Penida Ultimate 3-Point Snorkel Safari') }}" required />
                            <x-input-error :messages="$errors->get('title')" />
                        </div>

                        <div class="sm:col-span-2 space-y-1.5">
                            <x-label for="category" :value="__('Category / Experience Type')" />
                            <x-input id="category" type="text" wire:model="category" list="package-categories-list"
                                placeholder="{{ __('e.g., Day Tour, Snorkel Safari, Private Charter...') }}" />
                            <datalist id="package-categories-list">
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
                            <x-input id="location" type="text" wire:model="location"
                                placeholder="{{ __('e.g., Manta Bay, Crystal Bay & Gamat Bay') }}" />
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
                        <span class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs">
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

                    <x-package-bundle-picker :has-catalog="$this->availableProducts->isNotEmpty()" :selected="$this->bundledProducts" :catalog="$this->bundleCatalog" :remaining-count="$this->bundleRemainingCount"
                        :requires-search="$this->bundleRequiresSearch" :search="$bundleSearch" :empty-copy="__(
                            'No published activity/inventory items found. You can still save this package and link items later.',
                        )" />
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

                    <div
                        class="grid grid-cols-1 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] gap-4 lg:gap-6 lg:items-start">
                        <div class="space-y-1.5">
                            <x-label for="price" :value="__('Price per guest (IDR)')" required />
                            <div class="relative mt-1">
                                <span
                                    class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-op-subtle">IDR</span>
                                <x-input id="price" type="number" step="1000" min="0"
                                    wire:model.live="price"
                                    class="pl-12 text-base sm:text-sm font-semibold tabular-nums" placeholder="450000"
                                    required />
                            </div>
                    @if ((float) $this->price > 0)
                        <p class="text-[11px] text-op-subtle tabular-nums">
                            {{ __('Shown as Rp :price', ['price' => number_format((float) $this->price, 0, ',', '.')]) }}
                        </p>
                    @endif
                            <x-input-error :messages="$errors->get('price')" />
                        </div>
                        <div class="min-w-0 text-center h-full">
                            @include('pages.packages.partials.bundle-price-hint')
                        </div>
                    </div>
                </div>

                <!-- Card 4: Cover & Gallery Image Management -->
                <div
                    class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-op-surface border border-op-line shadow-xs space-y-5 sm:space-y-6">
                    <div class="flex items-center gap-2 pb-3 border-b border-op-line">
                        <span class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs">
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
                                class="relative w-full aspect-video sm:w-40 sm:h-24 sm:aspect-auto rounded-2xl border-2 border-dashed border-op-line bg-op-muted overflow-hidden flex items-center justify-center shrink-0">
                                @if ($coverPhoto)
                                    <img src="{{ $coverPhoto->temporaryUrl() }}" alt="Cover preview"
                                        class="w-full h-full object-cover" />
                                    <button type="button" wire:click="removeTempCoverPhoto"
                                        class="absolute top-1.5 right-1.5 h-9 w-9 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                @elseif ($existingCoverPhoto)
                                    <img src="{{ $this->mediaUrl($existingCoverPhoto) }}" alt="Cover preview"
                                        class="w-full h-full object-cover" />
                                    <button type="button" wire:click="removeExistingCoverPhoto"
                                        class="absolute top-1.5 right-1.5 h-9 w-9 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition">
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
                                    @if (!empty($selectedProducts))
                                        <button type="button" x-data=""
                                            x-on:click.prevent="$dispatch('open-modal', 'choose-activity-cover')"
                                            class="h-11 sm:h-9 px-3.5 w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-op-muted hover:bg-op-line text-op-ink text-xs font-bold transition cursor-pointer border border-op-line">
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
                                <h4 class="text-xs font-bold text-op-ink">{{ __('Gallery Photos') }}</h4>
                                <p class="text-[11px] text-op-subtle">
                                    {{ __('Upload photos or pull up to 2 gallery images from each selected activity.') }}
                                </p>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                                @if (!empty($selectedProducts))
                                    <button type="button" wire:click="fillGalleryFromActivities"
                                        class="h-11 sm:h-10 px-4 w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-xl bg-op-muted hover:bg-op-line text-op-ink text-xs font-bold transition cursor-pointer border border-op-line">
                                        <i class="fa-solid fa-images text-[11px]"></i>
                                        <span>{{ __('Use activity photos') }}</span>
                                    </button>
                                @endif
                                <label
                                    class="h-11 sm:h-10 px-4 w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-xl bg-op-muted hover:bg-op-line text-op-ink text-xs font-bold transition cursor-pointer">
                                    <i class="fa-solid fa-plus text-op-subtle text-[11px]"></i>
                                    <span>{{ __('Add Photos') }}</span>
                                    <input type="file" wire:model="galleryFiles"
                                        accept="image/png,image/jpeg,image/webp" multiple class="hidden" />
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
                                @foreach ($existingGallery as $idx => $photoPath)
                                    <div
                                        class="relative group aspect-video rounded-xl border border-op-line bg-op-muted overflow-hidden shadow-xs">
                                        <img src="{{ $this->mediaUrl($photoPath) }}"
                                            alt="Gallery image {{ $idx }}"
                                            class="w-full h-full object-cover" />
                                        <button type="button"
                                            wire:click="removeExistingGalleryImage({{ $idx }})"
                                            class="absolute top-1.5 right-1.5 h-9 w-9 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700"
                                            title="{{ __('Remove photo') }}">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                @endforeach

                                @foreach ($galleryFiles as $idx => $file)
                                    <div
                                        class="relative group aspect-video rounded-xl border border-op-line bg-op-muted overflow-hidden shadow-xs">
                                        <img src="{{ $file->temporaryUrl() }}"
                                            alt="Gallery preview {{ $idx }}"
                                            class="w-full h-full object-cover" />
                                        <button type="button"
                                            wire:click="removeTempGalleryFile({{ $idx }})"
                                            class="absolute top-1.5 right-1.5 h-9 w-9 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700"
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
                                <span>{{ __('No gallery photos yet. Use activity photos or click Add Photos.') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Card 5: Description, Itinerary & Policies -->
                <div
                    class="p-4 sm:p-6 lg:p-7 rounded-2xl sm:rounded-3xl bg-op-surface border border-op-line shadow-xs space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-op-line">
                        <span class="p-1.5 rounded-lg bg-op-muted text-op-subtle text-xs">
                            <i class="fa-solid fa-route"></i>
                        </span>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-op-ink">
                            {{ __('Itinerary, Inclusions & Booking Policies') }}
                        </h3>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <x-label for="description" :value="__('Package Overview / Highlights')" />
                            <x-textarea id="description" wire:model="description" rows="3"
                                placeholder="{{ __('Captivating overview describing what makes this experience unforgettable...') }}" />
                            <x-input-error :messages="$errors->get('description')" />
                        </div>

                        <div class="space-y-1.5">
                            <x-label for="itinerary_text" :value="__('Chronological Itinerary Schedule (Line-by-line)')" />
                            <x-textarea id="itinerary_text" wire:model="itinerary_text" rows="5"
                                placeholder="08:00 — Meeting point & check-in&#10;09:00 — Guided tour & activity departure&#10;12:00 — Lunch & rest stop&#10;15:00 — Return & photo sharing" />
                            <p class="text-[11px] text-op-subtle">
                                {{ __('Each line will render as an interactive step in the guest itinerary timeline.') }}
                            </p>
                            <x-input-error :messages="$errors->get('itinerary_text')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <x-label for="inclusions" :value="__('What is Included (Comma-separated)')" />
                                <x-input id="inclusions" type="text" wire:model="inclusions"
                                    placeholder="{{ __('Guided Tour, Equipment Gear, Lunch, Insurance, Photos') }}" />
                                <x-input-error :messages="$errors->get('inclusions')" />
                            </div>

                            <div class="space-y-1.5">
                                <x-label for="exclusions" :value="__('What is Excluded (Comma-separated)')" />
                                <x-input id="exclusions" type="text" wire:model="exclusions"
                                    placeholder="{{ __('Hotel transfers in mainland, Personal alcoholic drinks') }}" />
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
                            <x-textarea id="terms_and_conditions" wire:model="terms_and_conditions" rows="2"
                                placeholder="{{ __('Specific rules, fitness requirements, or passenger notices...') }}" />
                            <x-input-error :messages="$errors->get('terms_and_conditions')" />
                        </div>
                    </div>
                </div>

                <!-- Actions Bar -->
                <div
                    class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2.5 sm:gap-3 pt-1 sm:pt-2">
                    <x-button :href="route('packages.index')" variant="secondary" wire:navigate
                        class="w-full sm:w-auto !h-11 sm:!h-10 font-semibold text-xs">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button type="submit" variant="primary"
                        class="w-full sm:w-auto !h-11 sm:!h-10 font-semibold text-xs shadow-xs" :disabled="!$this->isProfileComplete">
                        <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                        {{ __('Save & Publish') }}
                    </x-button>
                </div>
            </form>

            @include('pages.packages.partials.activity-cover-picker-modal')
        @endif
    </div>
</div>
