<?php

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Product;
use App\Concerns\ResolvesCurrentOperator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit Activity Item')] class extends Component {
    use WithFileUploads;
    use ResolvesCurrentOperator;

    public Product $product;

    public string $name = '';
    public string $category = 'Equipment';
    public int $capacity_per_day = 10;
    public bool $sellable_standalone = false;
    public ?float $price = null;
    public string $location = '';
    public string $description = '';
    public string $inclusions = '';
    public string $exclusions = '';
    public string $terms_and_conditions = '';
    public int $free_cancellation_hours = 24;
    public int $advance_booking_hours = 12;
    public string $status = 'published';

    public ?string $existingCoverPhoto = null;
    /** @var array<string> */
    public array $existingGallery = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $coverPhoto = null;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $galleryFiles = [];

    #[Computed]
    public function suggestedCategories(): array
    {
        $defaults = ['Day Tour / Trip', 'Workshop & Class', 'Activity Session', 'Guide Hire', 'Ticket & Admission', 'Day Transport', 'Add-on Service'];
        if ($this->currentOperator) {
            $existing = $this->currentOperator->products()->whereNotNull('category')->distinct()->pluck('category')->toArray();

            return array_values(array_unique(array_filter(array_merge($defaults, $existing))));
        }

        return $defaults;
    }

    public function mount(Product $product): void
    {
        if (!$this->currentOperator || $product->operator_id !== $this->currentOperator->id) {
            abort(403, 'Unauthorized access to this product.');
        }

        $this->product = $product;
        $this->name = $product->name;
        $this->category = $product->category ?? 'Equipment';
        $this->capacity_per_day = $product->capacity_per_day;
        $this->sellable_standalone = $product->sellable_standalone;
        $this->price = $product->price ? (float) $product->price : null;
        $this->location = $product->location ?? '';
        $this->description = $product->description ?? '';
        $this->inclusions = !empty($product->inclusions) ? implode(', ', $product->inclusions) : '';
        $this->exclusions = !empty($product->exclusions) ? implode(', ', $product->exclusions) : '';
        $this->terms_and_conditions = $product->terms_and_conditions ?? '';
        $this->free_cancellation_hours = $product->free_cancellation_hours;
        $this->advance_booking_hours = $product->advance_booking_hours;
        $this->status = $product->status->value;
        $this->existingCoverPhoto = $product->cover_photo;
        $this->existingGallery = $product->gallery ?? [];
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
            $this->product->update(['cover_photo' => null]);
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

            $this->product->update([
                'gallery' => !empty($this->existingGallery) ? $this->existingGallery : null,
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
        if (!$this->currentOperator || $this->product->operator_id !== $this->currentOperator->id) {
            abort(403);
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'capacity_per_day' => ['required', 'integer', 'min:1', 'max:10000'],
            'sellable_standalone' => ['boolean'],
            'price' => ['required_if:sellable_standalone,true', 'nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'inclusions' => ['nullable', 'string', 'max:1000'],
            'exclusions' => ['nullable', 'string', 'max:1000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:2000'],
            'free_cancellation_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'advance_booking_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'status' => ['required', 'in:draft,published'],
            'coverPhoto' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'galleryFiles.*' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        $coverPath = $this->existingCoverPhoto;
        if ($this->coverPhoto) {
            if ($this->existingCoverPhoto) {
                Storage::disk('public')->delete($this->existingCoverPhoto);
            }
            $coverPath = $this->coverPhoto->store('products/covers', 'public');
        }

        $galleryPaths = $this->existingGallery;
        if (!empty($this->galleryFiles)) {
            foreach ($this->galleryFiles as $gFile) {
                $galleryPaths[] = $gFile->store('products/gallery', 'public');
            }
        }

        $incArray = !empty($this->inclusions) ? array_map('trim', explode(',', $this->inclusions)) : null;
        $excArray = !empty($this->exclusions) ? array_map('trim', explode(',', $this->exclusions)) : null;

        $this->product->update([
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'category' => $this->category ?: null,
            'capacity_per_day' => $this->capacity_per_day,
            'sellable_standalone' => $this->sellable_standalone,
            'price' => $this->sellable_standalone ? $this->price : null,
            'location' => $this->location ?: null,
            'description' => $this->description ?: null,
            'cover_photo' => $coverPath,
            'gallery' => !empty($galleryPaths) ? array_values($galleryPaths) : null,
            'inclusions' => $incArray,
            'exclusions' => $excArray,
            'terms_and_conditions' => $this->terms_and_conditions ?: null,
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'advance_booking_hours' => $this->advance_booking_hours,
            'status' => $this->status,
        ]);

        $this->existingCoverPhoto = $coverPath;
        $this->existingGallery = $galleryPaths;
        $this->coverPhoto = null;
        $this->galleryFiles = [];

        session()->flash('success', __('Activity item ":name" updated successfully.', ['name' => $this->name]));
    }

    public function delete(): void
    {
        if (!$this->currentOperator || $this->product->operator_id !== $this->currentOperator->id) {
            abort(403);
        }

        if ($this->product->cover_photo) {
            Storage::disk('public')->delete($this->product->cover_photo);
        }
        if (!empty($this->product->gallery)) {
            foreach ($this->product->gallery as $photo) {
                Storage::disk('public')->delete($photo);
            }
        }

        $name = $this->product->name;
        $this->product->delete();

        session()->flash('success', __('Activity item ":name" deleted successfully.', ['name' => $name]));
        $this->redirect(route('products.index'), navigate: true);
    }
}; ?>

<div class="space-y-6 w-full">
    <!-- Desktop Notice on Mobile -->
    <x-desktop-only-notice :title="__('Activity & Inventory Editing Best Managed on Desktop')" :description="__(
        'Updating daily capacities, equipment photos, and fine-tuning standalone pricing rules are best done on a computer or laptop.',
    )" />

    <div class="hidden lg:block space-y-6">
        <!-- Success Banner -->
        @if (session('success'))
            <div
                class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-900 text-xs font-semibold text-emerald-800 dark:text-emerald-300 flex items-center justify-between gap-2 animate-fade-in">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-500"></i>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()"
                    class="text-emerald-500 hover:text-emerald-700 cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        @endif

        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    <a href="{{ route('products.index') }}" wire:navigate
                        class="hover:text-slate-900 dark:hover:text-white transition flex items-center gap-1">
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        <span>{{ __('Activities & Inventory') }}</span>
                    </a>
                    <span>&bull;</span>
                    <span class="text-slate-900 dark:text-white truncate max-w-xs">{{ $product->name }}</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Edit Activity / Inventory Item') }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Update details, pricing, capacities, and photo assets.') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <x-button type="button" variant="danger" x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'confirm-product-deletion')"
                    class="font-semibold text-xs shadow-xs" title="{{ __('Delete Item') }}">
                    <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                    {{ __('Delete') }}
                </x-button>
                <x-button :href="route('products.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
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
            <!-- Card 1: Core Information & Categorization -->
            <div
                class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 text-xs">
                        <i class="fa-solid fa-cube"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Basic Information & Inventory Rules') }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2 space-y-1.5">
                        <x-label for="name" :value="__('Activity / Item Name')" required />
                        <x-input id="name" type="text" wire:model="name" required />
                        <x-input-error :messages="$errors->get('name')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="category" :value="__('Category / Item Classification')" />
                        <div class="space-y-1.5">
                            <x-input id="category" type="text" wire:model="category"
                                list="product-categories-edit-list"
                                placeholder="{{ __('e.g., Snorkeling Gear, Mountain Bike, Day Pass...') }}" />
                            <datalist id="product-categories-edit-list">
                                @foreach ($this->suggestedCategories as $cat)
                                    <option value="{{ $cat }}"></option>
                                @endforeach
                            </datalist>
                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                <span
                                    class="text-[10px] font-bold uppercase text-slate-400 mr-1">{{ __('Popular:') }}</span>
                                @foreach ($this->suggestedCategories as $cat)
                                    <button type="button" wire:click="$set('category', '{{ $cat }}')"
                                        class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-950 dark:hover:text-sky-400 transition cursor-pointer">
                                        + {{ $cat }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('category')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="location" :value="__('Pickup / Operating Location')" />
                        <x-input id="location" type="text" wire:model="location" />
                        <x-input-error :messages="$errors->get('location')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="capacity_per_day" :value="__('Daily Units / Maximum Capacity')" required />
                        <x-input id="capacity_per_day" type="number" min="1" max="10000"
                            wire:model="capacity_per_day" required />
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ __('Maximum reservations allowed per day across all packages & standalone bookings.') }}
                        </p>
                        <x-input-error :messages="$errors->get('capacity_per_day')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="status" :value="__('Listing Status')" required />
                        <x-select id="status" wire:model="status" :options="[
                            'published' => __('Published (Active in Catalog)'),
                            'draft' => __('Draft (Hidden from Public)'),
                        ]" />
                        <x-input-error :messages="$errors->get('status')" />
                    </div>
                </div>

                <!-- Standalone Direct Sale Setting -->
                <div
                    class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">
                                {{ __('Sell as Standalone Activity / Service on Storefront') }}
                            </h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('Allow guests to book this item directly on your storefront without purchasing a full package.') }}
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.live="sellable_standalone" class="sr-only peer" />
                            <div
                                class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-zinc-600 peer-checked:bg-sky-600">
                            </div>
                        </label>
                    </div>

                    @if ($sellable_standalone)
                        <div class="pt-2 border-t border-slate-200 dark:border-zinc-700">
                            <x-label for="price" :value="__('Direct Standalone Price (IDR)')" required />
                            <div class="relative mt-1">
                                <span
                                    class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">IDR</span>
                                <x-input id="price" type="number" step="1000" min="0" wire:model="price"
                                    class="pl-12" required />
                            </div>
                            <x-input-error :messages="$errors->get('price')" />
                        </div>
                    @endif
                </div>
            </div>

            <!-- Card 2: Cover & Gallery Image Management -->
            <div
                class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-6">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <span
                        class="p-1.5 rounded-lg bg-[#FFEF4D] text-slate-950 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
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
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('Primary banner image displayed on cards and listing headers. Max 5MB.') }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <div
                            class="relative w-36 h-24 rounded-2xl border-2 border-dashed border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800/60 overflow-hidden flex items-center justify-center shrink-0 shadow-xs">
                            @if ($coverPhoto)
                                <img src="{{ $coverPhoto->temporaryUrl() }}" alt="Cover preview"
                                    class="w-full h-full object-cover" />
                                <button type="button" wire:click="removeTempCoverPhoto"
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition cursor-pointer"
                                    title="{{ __('Remove temp cover') }}">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            @elseif ($existingCoverPhoto)
                                <img src="{{ Storage::url($existingCoverPhoto) }}" alt="Cover"
                                    class="w-full h-full object-cover" />
                                <button type="button" wire:click="removeExistingCoverPhoto"
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-rose-700 transition cursor-pointer"
                                    title="{{ __('Delete cover image') }}">
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
                            <label
                                class="h-9 px-3.5 inline-flex items-center gap-2 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer">
                                <i class="fa-solid fa-upload text-indigo-500"></i>
                                <span>{{ $existingCoverPhoto ? __('Change Cover Photo') : __('Upload Cover Photo') }}</span>
                                <input type="file" wire:model="coverPhoto"
                                    accept="image/png,image/jpeg,image/webp" class="hidden" />
                            </label>
                            <div wire:loading wire:target="coverPhoto"
                                class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold inline-flex items-center gap-1">
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
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Gallery Photos') }}
                            </h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('Photos showcasing the experience or equipment.') }}</p>
                        </div>

                        <label
                            class="h-8 px-3 inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer">
                            <i class="fa-solid fa-plus text-indigo-500 text-[11px]"></i>
                            <span>{{ __('Add More Photos') }}</span>
                            <input type="file" wire:model="galleryFiles" accept="image/png,image/jpeg,image/webp"
                                multiple class="hidden" />
                        </label>
                    </div>

                    <div wire:loading wire:target="galleryFiles"
                        class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold inline-flex items-center gap-1">
                        <i class="fa-solid fa-spinner fa-spin"></i> {{ __('Uploading gallery photos...') }}
                    </div>
                    <x-input-error :messages="$errors->get('galleryFiles.*')" />

                    <!-- Gallery Preview Grid -->
                    @if (!empty($existingGallery) || !empty($galleryFiles))
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3 pt-2">
                            <!-- Existing Saved Gallery Images -->
                            @foreach ($existingGallery as $idx => $photoPath)
                                <div
                                    class="relative group aspect-video rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-100 dark:bg-zinc-800 overflow-hidden shadow-xs">
                                    <img src="{{ Storage::url($photoPath) }}"
                                        alt="Gallery image {{ $idx }}" class="w-full h-full object-cover" />
                                    <button type="button"
                                        wire:click="removeExistingGalleryImage({{ $idx }})"
                                        class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700 cursor-pointer"
                                        title="{{ __('Delete photo') }}">
                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                    </button>
                                </div>
                            @endforeach

                            <!-- Newly Uploaded Previews -->
                            @foreach ($galleryFiles as $idx => $file)
                                <div
                                    class="relative group aspect-video rounded-xl border-2 border-indigo-400 bg-indigo-50/20 overflow-hidden shadow-xs">
                                    <img src="{{ $file->temporaryUrl() }}"
                                        alt="New gallery preview {{ $idx }}"
                                        class="w-full h-full object-cover" />
                                    <span
                                        class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded bg-indigo-600 text-white text-[9px] font-bold">{{ __('New') }}</span>
                                    <button type="button" wire:click="removeTempGalleryFile({{ $idx }})"
                                        class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] opacity-90 group-hover:opacity-100 transition shadow-sm hover:bg-rose-700 cursor-pointer"
                                        title="{{ __('Remove photo') }}">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div
                            class="p-6 rounded-2xl border border-dashed border-slate-200 dark:border-zinc-800 text-center text-xs text-slate-400">
                            <i class="fa-regular fa-images text-2xl mb-1 block text-slate-300 dark:text-slate-600"></i>
                            <span>{{ __('No gallery photos added yet. Click "Add More Photos" to upload.') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Card 3: Descriptions, Inclusions & Policies -->
            <div
                class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <span
                        class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                        <i class="fa-solid fa-list-check"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Descriptions, Inclusions & Policies') }}
                    </h3>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <x-label for="description" :value="__('Description')" />
                        <x-textarea id="description" wire:model="description" rows="3" />
                        <x-input-error :messages="$errors->get('description')" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <x-label for="inclusions" :value="__('Inclusions (Comma-separated)')" />
                            <x-input id="inclusions" type="text" wire:model="inclusions" />
                            <x-input-error :messages="$errors->get('inclusions')" />
                        </div>

                        <div class="space-y-1.5">
                            <x-label for="exclusions" :value="__('Exclusions (Comma-separated)')" />
                            <x-input id="exclusions" type="text" wire:model="exclusions" />
                            <x-input-error :messages="$errors->get('exclusions')" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div class="space-y-1.5">
                            <x-label for="free_cancellation_hours" :value="__('Free Cancellation Window (Hours)')" required />
                            <x-input id="free_cancellation_hours" type="number" min="0" max="720"
                                wire:model="free_cancellation_hours" required />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('Hours prior to departure for 100% refund.') }}</p>
                            <x-input-error :messages="$errors->get('free_cancellation_hours')" />
                        </div>

                        <div class="space-y-1.5">
                            <x-label for="advance_booking_hours" :value="__('Advance Booking Cutoff (Hours)')" required />
                            <x-input id="advance_booking_hours" type="number" min="0" max="720"
                                wire:model="advance_booking_hours" required />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('Minimum advance notice required before departure date.') }}</p>
                            <x-input-error :messages="$errors->get('advance_booking_hours')" />
                        </div>
                    </div>

                    <div class="space-y-1.5 pt-2">
                        <x-label for="terms_and_conditions" :value="__('Specific Terms & Conditions (Optional)')" />
                        <x-textarea id="terms_and_conditions" wire:model="terms_and_conditions" rows="2" />
                        <x-input-error :messages="$errors->get('terms_and_conditions')" />
                    </div>
                </div>
            </div>

            <!-- Danger Zone: Delete -->
            <div
                class="p-6 sm:p-7 rounded-3xl bg-rose-500/5 dark:bg-rose-950/20 border border-rose-200/80 dark:border-rose-900/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-rose-900 dark:text-rose-200">
                        {{ __('Danger Zone: Delete this Activity Item') }}
                    </h4>
                    <p class="text-xs text-rose-700/80 dark:text-rose-400">
                        {{ __('Permanently removes this inventory item and its associated media from storage. This action cannot be undone.') }}
                    </p>
                </div>

                <x-button type="button" variant="danger" x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'confirm-product-deletion')"
                    class="shrink-0 font-semibold text-xs shadow-xs">
                    <i class="fa-solid fa-trash mr-1.5 text-xs"></i>
                    {{ __('Delete Item') }}
                </x-button>
            </div>

            <!-- Actions Bar -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button :href="route('products.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="submit" variant="primary" class="font-semibold text-xs shadow-xs">
                    <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                    {{ __('Save Changes') }}
                </x-button>
            </div>
        </form>

        <!-- System UI Confirmation Modal -->
        <x-modal name="confirm-product-deletion" maxWidth="md">
            <div class="p-6 space-y-4 text-center">
                <div
                    class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <div class="space-y-1.5">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ __('Delete ":name"?', ['name' => $product->name]) }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                        {{ __('Are you sure you want to permanently delete this inventory item? All reservations history will remain intact, but the item will be removed from your catalog.') }}
                    </p>
                </div>

                <div class="flex items-center justify-center gap-3 pt-3">
                    <x-button type="button" variant="secondary"
                        x-on:click="$dispatch('close-modal', 'confirm-product-deletion')"
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
    </div>
</div>
