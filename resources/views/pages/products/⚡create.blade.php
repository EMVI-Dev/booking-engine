<?php

use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Create Activity Item')] class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $category = 'Snorkeling Gear';
    public int $capacity_per_day = 20;
    public bool $sellable_standalone = true;
    public ?float $price = 75000;
    public string $location = 'Nusa Penida & Bali';
    public string $description = '';
    public string $inclusions = '';
    public string $exclusions = '';
    public string $terms_and_conditions = '';
    public int $free_cancellation_hours = 24;
    public int $advance_booking_hours = 12;
    public string $status = 'published';

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
    public function isProfileComplete(): bool
    {
        return (bool) $this->currentOperator?->isProfileComplete();
    }

    #[Computed]
    public function suggestedCategories(): array
    {
        $defaults = ['Snorkeling Gear', 'Scuba Equipment', 'Boat Seat', 'Vehicle Rental', 'Local Guide', 'Water Sport', 'Ticket / Pass'];
        if ($this->currentOperator) {
            $existing = $this->currentOperator->products()
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
            session()->flash('warning', __('Please complete your business profile and payout settings before creating inventory items.'));
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
            $this->addError('profile', __('You must complete your business profile, WhatsApp contact, payout reference, and terms & conditions in settings before creating items.'));
            return;
        }

        if (! $this->currentOperator) {
            return;
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

        $coverPath = null;
        if ($this->coverPhoto) {
            $coverPath = $this->coverPhoto->store('products/covers', 'public');
        }

        $galleryPaths = [];
        if (! empty($this->galleryFiles)) {
            foreach ($this->galleryFiles as $gFile) {
                $galleryPaths[] = $gFile->store('products/gallery', 'public');
            }
        }

        $incArray = ! empty($this->inclusions) ? array_map('trim', explode(',', $this->inclusions)) : null;
        $excArray = ! empty($this->exclusions) ? array_map('trim', explode(',', $this->exclusions)) : null;

        $product = $this->currentOperator->products()->create([
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'category' => $this->category ?: null,
            'capacity_per_day' => $this->capacity_per_day,
            'sellable_standalone' => $this->sellable_standalone,
            'price' => $this->sellable_standalone ? $this->price : null,
            'location' => $this->location ?: null,
            'description' => $this->description ?: null,
            'cover_photo' => $coverPath,
            'gallery' => ! empty($galleryPaths) ? $galleryPaths : null,
            'inclusions' => $incArray,
            'exclusions' => $excArray,
            'terms_and_conditions' => $this->terms_and_conditions ?: null,
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'advance_booking_hours' => $this->advance_booking_hours,
            'status' => $this->status,
        ]);

        session()->flash('success', __('Activity item ":name" created successfully.', ['name' => $product->name]));
        $this->redirect(route('products.index'), navigate: true);
    }
}; ?>

<div class="space-y-6 max-w-5xl">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                <a href="{{ route('products.index') }}" wire:navigate class="hover:text-slate-900 dark:hover:text-white transition flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>{{ __('Activities & Inventory') }}</span>
                </a>
                <span>&bull;</span>
                <span class="text-slate-900 dark:text-white">{{ __('Create New') }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ __('Create Activity / Inventory Item') }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Add a standalone service, rental equipment, transport seat, or tour guide unit.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-button :href="route('products.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
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

        <!-- Card 1: Core Information & Categorization -->
        <div class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
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
                    <x-input id="name" type="text" wire:model="name" placeholder="{{ __('e.g., Manta Point Snorkeling Gear Set') }}" required />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="category" :value="__('Category / Item Classification')" />
                    <div class="space-y-1.5">
                        <x-input
                            id="category"
                            type="text"
                            wire:model="category"
                            list="product-categories-list"
                            placeholder="{{ __('e.g., Snorkeling Gear, Fastboat Seat, Scooter...') }}"
                        />
                        <datalist id="product-categories-list">
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
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-950 dark:hover:text-sky-400 transition cursor-pointer"
                                >
                                    + {{ $cat }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('category')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="location" :value="__('Pickup / Operating Location')" />
                    <x-input id="location" type="text" wire:model="location" placeholder="{{ __('e.g., Toyapakeh Harbor, Nusa Penida') }}" />
                    <x-input-error :messages="$errors->get('location')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="capacity_per_day" :value="__('Daily Units / Maximum Capacity')" required />
                    <x-input id="capacity_per_day" type="number" min="1" max="10000" wire:model="capacity_per_day" required />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Maximum reservations allowed per day across all packages & standalone bookings.') }}</p>
                    <x-input-error :messages="$errors->get('capacity_per_day')" />
                </div>

                <div class="space-y-1.5">
                    <x-label for="status" :value="__('Listing Status')" required />
                    <x-select
                        id="status"
                        wire:model="status"
                        :options="[
                            'published' => __('Published (Active in Catalog)'),
                            'draft' => __('Draft (Hidden from Public)'),
                        ]"
                    />
                    <x-input-error :messages="$errors->get('status')" />
                </div>
            </div>

            <!-- Standalone Direct Sale Setting -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">
                            {{ __('Sell as Standalone Activity / Rental on Storefront') }}
                        </h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ __('Allow guests to book this item directly on your storefront without purchasing a full package.') }}
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="sellable_standalone" class="sr-only peer" />
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-zinc-600 peer-checked:bg-sky-600"></div>
                    </label>
                </div>

                @if ($sellable_standalone)
                    <div class="pt-2 border-t border-slate-200 dark:border-zinc-700">
                        <x-label for="price" :value="__('Direct Standalone Price (IDR)')" required />
                        <div class="relative mt-1">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">IDR</span>
                            <x-input id="price" type="number" step="1000" min="0" wire:model="price" class="pl-12" placeholder="75000" required />
                        </div>
                        <x-input-error :messages="$errors->get('price')" />
                    </div>
                @endif
            </div>
        </div>

        <!-- Card 2: Cover & Gallery Image Management -->
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
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Primary banner image displayed on cards and listing headers. Max 5MB.') }}</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="relative w-36 h-24 rounded-2xl border-2 border-dashed border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800/60 overflow-hidden flex items-center justify-center shrink-0">
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
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Upload multiple photos showcasing the experience or equipment.') }}</p>
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

        <!-- Card 3: Descriptions, Inclusions & Policies -->
        <div class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                    <i class="fa-solid fa-list-check"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Descriptions, Inclusions & Policies') }}
                </h3>
            </div>

            <div class="space-y-4">
                <div class="space-y-1.5">
                    <x-label for="description" :value="__('Description')" />
                    <x-textarea id="description" wire:model="description" rows="3" placeholder="{{ __('Comprehensive details about this equipment or service...') }}" />
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <x-label for="inclusions" :value="__('Inclusions (Comma-separated)')" />
                        <x-input id="inclusions" type="text" wire:model="inclusions" placeholder="{{ __('e.g., Mask, Snorkel, Fins, Mesh Bag') }}" />
                        <x-input-error :messages="$errors->get('inclusions')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="exclusions" :value="__('Exclusions (Comma-separated)')" />
                        <x-input id="exclusions" type="text" wire:model="exclusions" placeholder="{{ __('e.g., Damage Deposit, Wetsuit Upgrade') }}" />
                        <x-input-error :messages="$errors->get('exclusions')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div class="space-y-1.5">
                        <x-label for="free_cancellation_hours" :value="__('Free Cancellation Window (Hours)')" required />
                        <x-input id="free_cancellation_hours" type="number" min="0" max="720" wire:model="free_cancellation_hours" required />
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Hours prior to departure for 100% refund.') }}</p>
                        <x-input-error :messages="$errors->get('free_cancellation_hours')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-label for="advance_booking_hours" :value="__('Advance Booking Cutoff (Hours)')" required />
                        <x-input id="advance_booking_hours" type="number" min="0" max="720" wire:model="advance_booking_hours" required />
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Minimum advance notice required before departure date.') }}</p>
                        <x-input-error :messages="$errors->get('advance_booking_hours')" />
                    </div>
                </div>

                <div class="space-y-1.5 pt-2">
                    <x-label for="terms_and_conditions" :value="__('Specific Terms & Conditions (Optional)')" />
                    <x-textarea id="terms_and_conditions" wire:model="terms_and_conditions" rows="2" placeholder="{{ __('Specific rules or equipment waiver requirements...') }}" />
                    <x-input-error :messages="$errors->get('terms_and_conditions')" />
                </div>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <x-button :href="route('products.index')" variant="secondary" wire:navigate class="font-semibold text-xs">
                {{ __('Cancel') }}
            </x-button>
            <x-button type="submit" variant="primary" class="font-semibold text-xs shadow-xs" :disabled="! $this->isProfileComplete">
                <i class="fa-solid fa-check mr-1.5 text-xs"></i>
                {{ __('Save & Publish') }}
            </x-button>
        </div>
    </form>
</div>
