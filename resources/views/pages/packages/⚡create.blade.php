<?php

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
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

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $galleryFiles = [];


    #[Computed]
    public function isProfileComplete(): bool
    {
        return (bool) $this->currentOperator?->isProfileComplete();
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

    public function mount(): void
    {
        if (! $this->isProfileComplete) {
            session()->flash('warning', __('Please complete your business profile and payout settings before creating tour packages.'));
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

    public function removeTempCoverPhoto(): void
    {
        $this->coverPhoto = null;
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
        if (! $this->isProfileComplete) {
            $this->addError('profile', __('You must complete your business profile, WhatsApp contact, payout reference, and terms & conditions in settings before creating packages.'));
            return;
        }

        if (! $this->currentOperator) {
            return;
        }

        if (! $this->currentOperator->canAddPackage()) {
            $this->addError('profile', __('You have reached the maximum package limit (:limit listings) for your :plan plan. Please upgrade your subscription to create more packages.', [
                'limit' => $this->currentOperator->getPlan()->package_limit,
                'plan' => $this->currentOperator->getPlan()->name,
            ]));
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

        $coverPath = null;
        if ($this->coverPhoto) {
            $coverPath = $this->media()->storeUpload($this->coverPhoto, $this->operatorMediaDirectory('packages/covers'));
        }

        $galleryPaths = [];
        if (! empty($this->galleryFiles)) {
            foreach ($this->galleryFiles as $gFile) {
                $galleryPaths[] = $this->media()->storeUpload($gFile, $this->operatorMediaDirectory('packages/gallery'));
            }
        }

        $incArray = ! empty($this->inclusions) ? array_map('trim', explode(',', $this->inclusions)) : null;
        $excArray = ! empty($this->exclusions) ? array_map('trim', explode(',', $this->exclusions)) : null;

        $package = $this->currentOperator->packages()->create([
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
        $package->products()->sync($syncData);

        session()->flash('success', __('Tour package ":title" created successfully.', ['title' => $package->title]));
        $this->redirect(route('packages.index'), navigate: true);
    }
}; ?>

<div class="space-y-6 w-full">
    <!-- Desktop Notice on Mobile -->
    <x-desktop-only-notice
        :title="__('Tour Package Creation Best Managed on Desktop')"
        :description="__('Uploading multiple photo galleries, building itinerary timelines, and configuring custom inclusion pricing are designed for computer or laptop screens.')"
    />

    <div class="hidden lg:block space-y-6">
        @if ($this->currentOperator && ! $this->currentOperator->canAddPackage())
        <div class="py-6">
            <x-feature-gate
                :title="__('Package Limit Reached (:limit Listings)', ['limit' => $this->currentOperator->getPlan()->package_limit])"
                :description="__('Trips and activities share the same listing limit on your :plan plan. Upgrade to list more.', ['plan' => $this->currentOperator->getPlan()->name])"
                required-plan="Growth"
                plan-slug="growth"
                icon="fa-solid fa-cubes"
                :features="[
                    __('Up to 25 listings on Growth (unlimited on Agency)'),
                    __('Google & Apple Calendar live syncing for tour bookings'),
                    __('Automated 12-hour review request emails'),
                    __('Customer Directory CRM and WhatsApp ticket dispatch'),
                ]"
            />
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
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Create Tour Package / Experience') }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Bundle activities, transport, guide hire, and equipment into an all-inclusive public package.') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <x-button :href="route('packages.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
                    {{ __('Cancel') }}
                </x-button>
                <x-button wire:click="save" variant="primary" class="font-semibold text-xs shadow-xs" :disabled="! $this->isProfileComplete">
                    <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                    {{ __('Save & Publish') }}
                </x-button>
            </div>
        </div>

        <!-- Main Create Form -->
        <form wire:submit="save" class="space-y-6">
        @if ($errors->has('profile'))
            <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-xs font-semibold text-rose-700 dark:text-rose-300 flex items-center gap-2.5">
                <i class="fa-solid fa-circle-exclamation text-rose-500"></i>
                <span>{{ $errors->first('profile') }}</span>
            </div>
        @endif

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
                    <x-input id="title" type="text" wire:model="title" placeholder="{{ __('e.g., Nusa Penida Ultimate 3-Point Snorkel Safari') }}" required />
                    <x-input-error :messages="$errors->get('title')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="category" :value="__('Category / Experience Type')" />
                    <div class="space-y-1.5">
                        <x-input
                            id="category"
                            type="text"
                            wire:model="category"
                            list="package-categories-list"
                            placeholder="{{ __('e.g., Day Tour, Snorkel Safari, Private Charter...') }}"
                        />
                        <datalist id="package-categories-list">
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
                    <x-input id="location" type="text" wire:model="location" placeholder="{{ __('e.g., Manta Bay, Crystal Bay & Gamat Bay') }}" />
                    <x-input-error :messages="$errors->get('location')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="price" :value="__('Selling Price per Guest / Unit (IDR)')" required />
                    <div class="relative mt-1">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">IDR</span>
                        <x-input id="price" type="number" step="1000" min="0" wire:model="price" class="pl-12" placeholder="450000" required />
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
                {{ __('Select which inventory items (e.g., activity slots, guide hire, admission passes) are reserved whenever this package is booked. Capacity will be automatically synchronized.') }}
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
                    {{ __('No published activity/inventory items found. You can still save this package and link items later.') }}
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
                    <div class="relative w-40 h-24 rounded-2xl border-2 border-dashed border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800/60 overflow-hidden flex items-center justify-center shrink-0">
                        @if ($coverPhoto)
                            <img src="{{ $coverPhoto->temporaryUrl() }}" alt="Cover preview" class="w-full h-full object-cover" />
                            <button
                                type="button"
                                wire:click="removeTempCoverPhoto"
                                class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition"
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
                            <span>{{ __('Upload Cover Photo') }}</span>
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
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Upload multiple high-resolution photos showcasing itinerary highlights.') }}</p>
                    </div>

                    <label class="h-8 px-3 inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer">
                        <i class="fa-solid fa-plus text-indigo-500 text-[11px]"></i>
                        <span>{{ __('Add Photos') }}</span>
                        <input type="file" wire:model="galleryFiles" accept="image/png,image/jpeg,image/webp" multiple class="hidden" />
                    </label>
                </div>

                <div wire:loading wire:target="galleryFiles" class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold inline-flex items-center gap-1">
                    <i class="fa-solid fa-spinner fa-spin"></i> {{ __('Uploading gallery photos...') }}
                </div>
                <x-input-error :messages="$errors->get('galleryFiles.*')" />

                <!-- Gallery Preview Grid -->
                @if (! empty($galleryFiles))
                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3 pt-2">
                        @foreach ($galleryFiles as $idx => $file)
                            <div class="relative group aspect-video rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-100 dark:bg-zinc-800 overflow-hidden shadow-xs">
                                <img src="{{ $file->temporaryUrl() }}" alt="Gallery preview {{ $idx }}" class="w-full h-full object-cover" />
                                <button
                                    type="button"
                                    wire:click="removeTempGalleryFile({{ $idx }})"
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700"
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
                        <span>{{ __('No gallery photos added yet. Click "Add Photos" to upload.') }}</span>
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
                    <x-textarea id="description" wire:model="description" rows="3" placeholder="{{ __('Captivating overview describing what makes this experience unforgettable...') }}" />
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="itinerary_text" :value="__('Chronological Itinerary Schedule (Line-by-line)')" />
                    <x-textarea id="itinerary_text" wire:model="itinerary_text" rows="5" placeholder="08:00 — Meeting point & check-in&#10;09:00 — Guided tour & activity departure&#10;12:00 — Lunch & rest stop&#10;15:00 — Return & photo sharing" />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Each line will render as an interactive step in the guest itinerary timeline.') }}</p>
                    <x-input-error :messages="$errors->get('itinerary_text')" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <x-label for="inclusions" :value="__('What is Included (Comma-separated)')" />
                        <x-input id="inclusions" type="text" wire:model="inclusions" placeholder="{{ __('Guided Tour, Equipment Gear, Lunch, Insurance, Photos') }}" />
                        <x-input-error :messages="$errors->get('inclusions')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="exclusions" :value="__('What is Excluded (Comma-separated)')" />
                        <x-input id="exclusions" type="text" wire:model="exclusions" placeholder="{{ __('Hotel transfers in mainland, Personal alcoholic drinks') }}" />
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
                    <x-textarea id="terms_and_conditions" wire:model="terms_and_conditions" rows="2" placeholder="{{ __('Specific rules, fitness requirements, or passenger notices...') }}" />
                    <x-input-error :messages="$errors->get('terms_and_conditions')" />
                </div>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <x-button :href="route('packages.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
                {{ __('Cancel') }}
            </x-button>
            <x-button type="submit" variant="primary" class="font-semibold text-xs shadow-xs" :disabled="! $this->isProfileComplete">
                <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                {{ __('Save & Publish') }}
            </x-button>
        </div>
    </form>
    @endif
    </div>
</div>
