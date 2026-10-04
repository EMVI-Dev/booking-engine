<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Models\Operator;
use App\Services\EnquiryService;
use App\Services\StorefrontFaqService;
use App\Services\StorefrontGalleryService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Website extras for the storefront: photo gallery, FAQ and the contact / group enquiry
 * form. All rules live in StorefrontGalleryService, StorefrontFaqService and EnquiryService.
 */
new #[Title('Website Settings')] class extends Component {
    use ResolvesCurrentOperator;
    use WithFileUploads;

    #[Url]
    public string $tab = 'gallery';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    /** @var array<string, string> photo id => caption being edited */
    public array $captions = [];

    /** @var list<string> generated FAQ keys the operator hides */
    public array $faqHidden = [];

    /** @var list<array{question: string, answer: string}> */
    public array $faqItems = [];

    public bool $contactEnabled = false;

    public bool $contactEmailNotifications = false;

    public string $contactNotifyEmail = '';

    public function mount(StorefrontFaqService $faq, EnquiryService $enquiries): void
    {
        if (! in_array($this->tab, ['gallery', 'faq', 'contact'], true)) {
            $this->tab = 'gallery';
        }

        $this->authorizeAbility('manageSettings');

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if (! $operator) {
            return;
        }

        $this->faqHidden = $faq->hiddenKeys($operator);
        $this->faqItems = array_map(
            fn (array $item): array => ['question' => $item['question'], 'answer' => $item['answer']],
            $faq->customItems($operator),
        );

        $contact = $enquiries->settingsFor($operator);
        $this->contactEnabled = $contact['enabled'];
        $this->contactEmailNotifications = $contact['email_notifications'];
        $this->contactNotifyEmail = (string) ($contact['notify_email'] ?? '');

        $this->syncCaptions();
    }

    public function rendering(): void
    {
        if (! in_array($this->tab, ['gallery', 'faq', 'contact'], true)) {
            $this->tab = 'gallery';
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['gallery', 'faq', 'contact'], true)) {
            $this->tab = $tab;
        }
    }

    // ── Gallery ──────────────────────────────────────────────────────────────

    #[Computed]
    public function photos()
    {
        return $this->currentOperator?->galleryPhotos()->get() ?? collect();
    }

    #[Computed]
    public function galleryLimit(): int
    {
        return $this->currentOperator ? app(StorefrontGalleryService::class)->limitFor($this->currentOperator) : 0;
    }

    public function updatedUploads(StorefrontGalleryService $gallery): void
    {
        $this->authorizeAbility('manageSettings');

        if (! $this->currentOperator) {
            return;
        }

        $this->validate([
            'uploads' => ['array', 'max:'.max(1, $gallery->remainingFor($this->currentOperator))],
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'uploads.max' => __('You can add :max more photos on your plan.', ['max' => $gallery->remainingFor($this->currentOperator)]),
        ]);

        try {
            foreach ($this->uploads as $file) {
                $gallery->add($this->currentOperator, $file);
            }
        } catch (ValidationException $e) {
            $this->addError('uploads', (string) collect($e->errors())->flatten()->first());
        } finally {
            $this->uploads = [];
            unset($this->photos);
            $this->syncCaptions();
        }
    }

    public function saveCaption(StorefrontGalleryService $gallery, string $photoId): void
    {
        $this->authorizeAbility('manageSettings');

        if (! $this->currentOperator) {
            return;
        }

        $gallery->updateCaption($this->currentOperator, $photoId, $this->captions[$photoId] ?? null);
        unset($this->photos);
        $this->dispatch('toast', message: __('Caption saved.'), type: 'success');
    }

    public function movePhoto(StorefrontGalleryService $gallery, string $photoId, int $direction): void
    {
        $this->authorizeAbility('manageSettings');

        if (! $this->currentOperator) {
            return;
        }

        $ids = $this->photos->pluck('id')->all();
        $from = array_search($photoId, $ids, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
        $gallery->reorder($this->currentOperator, $ids);
        unset($this->photos);
    }

    public function removePhoto(StorefrontGalleryService $gallery, string $photoId): void
    {
        $this->authorizeAbility('manageSettings');

        if (! $this->currentOperator) {
            return;
        }

        $gallery->remove($this->currentOperator, $photoId);
        unset($this->photos);
        $this->syncCaptions();
        $this->dispatch('toast', message: __('Photo removed.'), type: 'success');
    }

    private function syncCaptions(): void
    {
        $this->captions = $this->photos->mapWithKeys(fn ($photo) => [$photo->id => (string) $photo->caption])->all();
    }

    // ── FAQ ──────────────────────────────────────────────────────────────────

    #[Computed]
    public function generatedFaq(): array
    {
        return $this->currentOperator ? app(StorefrontFaqService::class)->generatedFor($this->currentOperator) : [];
    }

    public function addFaqItem(): void
    {
        if (count($this->faqItems) < StorefrontFaqService::MAX_CUSTOM_ITEMS) {
            $this->faqItems[] = ['question' => '', 'answer' => ''];
        }
    }

    public function removeFaqItem(int $index): void
    {
        unset($this->faqItems[$index]);
        $this->faqItems = array_values($this->faqItems);
    }

    public function saveFaq(StorefrontFaqService $faq): void
    {
        $this->authorizeAbility('manageSettings');

        if (! $this->currentOperator) {
            return;
        }

        $faq->save($this->currentOperator, array_values(array_filter($this->faqHidden, 'is_string')), $this->faqItems);
        unset($this->currentOperator);
        $this->dispatch('toast', message: __('FAQ saved.'), type: 'success');
    }

    // ── Contact form ─────────────────────────────────────────────────────────

    #[Computed]
    public function hasContactForm(): bool
    {
        return (bool) $this->currentOperator?->hasFeature('contact_form');
    }

    public function saveContact(EnquiryService $enquiries): void
    {
        $this->authorizeAbility('manageSettings');
        $this->authorizeFeature('contact_form');

        if (! $this->currentOperator) {
            return;
        }

        $this->validate([
            'contactNotifyEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $enquiries->saveSettings($this->currentOperator, $this->contactEnabled, $this->contactEmailNotifications, $this->contactNotifyEmail ?: null);
        unset($this->currentOperator);
        $this->dispatch('toast', message: __('Contact form settings saved.'), type: 'success');
    }
}; ?>

<div class="space-y-6 w-full">
    <x-settings-nav :tab="$tab" />

    @if ($tab === 'gallery')
        <x-page-header
            :title="__('Photo gallery')"
            :subtitle="__('Photos are resized to 1600px, converted to WebP, and displayed in a 3-column grid.')"
            icon="fa-images"
        />

        {{-- Gallery Section --}}
        <section class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] space-y-4">
            <div class="flex items-center justify-between gap-3 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7] flex items-center gap-2">
                        <i class="fa-solid fa-images text-[#8B8D98]"></i>
                        <span>{{ __('Photo gallery') }}</span>
                    </h3>
                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                        {{ __('Photos are resized to 1600px, converted to WebP, and displayed in a 3-column grid.') }}
                    </p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 dark:bg-zinc-800 text-xs font-bold text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-zinc-700/60 shrink-0">
                    {{ __(':count of :limit photos', ['count' => $this->photos->count(), 'limit' => $this->galleryLimit]) }}
                </span>
            </div>

            @if ($this->galleryLimit === 0)
                <div class="p-4 rounded-[10px] bg-slate-50 dark:bg-[#141821] border border-slate-200 dark:border-zinc-800 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                    {{ __('Your plan does not include a gallery. Upgrade to Starter, Growth, or Agency to enable photo galleries.') }}
                </div>
            @else
                @if ($this->photos->count() < $this->galleryLimit)
                    {{-- Drag & Drop Upload Zone --}}
                    <div x-data="{ dragging: false }"
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop="dragging = false"
                        class="relative rounded-[12px] border-2 border-dashed p-6 sm:p-8 text-center transition-all cursor-pointer group"
                        :class="dragging ? 'border-brand-500 bg-brand-50/40 dark:bg-brand-950/20' : 'border-slate-200 dark:border-zinc-800 hover:border-brand-400 dark:hover:border-brand-500 bg-slate-50/50 dark:bg-[#141821]/50'">
                        <input type="file" wire:model="uploads" multiple accept="image/jpeg,image/png,image/webp"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                        <div class="space-y-2 pointer-events-none">
                            <div class="w-12 h-12 rounded-xl bg-brand-500/10 text-brand-700 dark:text-brand-400 mx-auto flex items-center justify-center text-xl group-hover:scale-105 transition-transform">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <div>
                                <span class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ __('Click to upload or drag and drop photos') }}</span>
                                <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                                    {{ __('Add photos (JPG, PNG or WebP, up to 10 MB each). They are resized and compressed automatically.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div wire:loading wire:target="uploads" class="flex items-center gap-2 text-xs font-semibold text-brand-700 dark:text-brand-400">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <span>{{ __('Uploading...') }}</span>
                    </div>
                @else
                    <div class="p-4 rounded-[10px] bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-crown text-sm"></i>
                            </span>
                            <div class="text-xs text-amber-900 dark:text-amber-200">
                                <p class="font-bold">{{ __('Your gallery is full. Remove a photo or upgrade your plan to add more.') }}</p>
                                <p class="text-amber-700 dark:text-amber-400 mt-0.5">{{ __('Starter allows 9 photos, Growth allows 18, and Agency allows up to 36 photos.') }}</p>
                            </div>
                        </div>
                        <a href="{{ route('settings.plan') }}" wire:navigate class="h-8 px-3.5 inline-flex items-center justify-center rounded-[6px] bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white font-bold text-xs shrink-0 transition shadow-xs">
                            {{ __('Upgrade plan') }} &rarr;
                        </a>
                    </div>
                @endif

                <x-input-error :messages="$errors->get('uploads')" />
                <x-input-error :messages="$errors->get('uploads.*')" />

                {{-- Photos Grid --}}
                @if ($this->photos->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2">
                        @foreach ($this->photos as $index => $photo)
                            <div wire:key="photo-{{ $photo->id }}" class="group relative flex flex-col justify-between rounded-[10px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] p-2.5 space-y-2 shadow-xs hover:border-slate-300 dark:hover:border-zinc-700 transition">
                                <div class="relative aspect-square w-full rounded-[8px] overflow-hidden bg-slate-100 dark:bg-zinc-800">
                                    <img src="{{ app(\App\Services\StorefrontGalleryService::class)->url($photo) }}" alt="{{ $photo->caption }}" class="h-full w-full object-cover" loading="lazy" />
                                    <span class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-md bg-black/60 text-white text-[10px] font-bold backdrop-blur-sm">
                                        #{{ $index + 1 }}
                                    </span>
                                </div>
                                <input type="text" wire:model="captions.{{ $photo->id }}" wire:blur="saveCaption('{{ $photo->id }}')" maxlength="150"
                                    placeholder="{{ __('Caption (optional)') }}" class="h-8 w-full rounded-[6px] border border-[#E4E5E9] px-2 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:border-[#1E2433] dark:bg-[#10141d] focus:border-brand-500 focus:outline-none transition" />
                                <div class="flex items-center justify-between text-xs pt-1 border-t border-[#E4E5E9]/60 dark:border-[#1E2433]/60">
                                    <span class="flex items-center gap-1">
                                        <button type="button" wire:click="movePhoto('{{ $photo->id }}', -1)" @disabled($index === 0)
                                            class="h-7 w-7 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-slate-50 dark:bg-zinc-800 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-700 disabled:opacity-30 cursor-pointer transition"
                                            aria-label="{{ __('Move earlier') }}" title="{{ __('Move earlier') }}">
                                            <i class="fa-solid fa-arrow-left text-[10px]"></i>
                                        </button>
                                        <button type="button" wire:click="movePhoto('{{ $photo->id }}', 1)" @disabled($loop->last)
                                            class="h-7 w-7 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-slate-50 dark:bg-zinc-800 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-700 disabled:opacity-30 cursor-pointer transition"
                                            aria-label="{{ __('Move later') }}" title="{{ __('Move later') }}">
                                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                        </button>
                                    </span>
                                    <button type="button" wire:click="removePhoto('{{ $photo->id }}')" wire:confirm="{{ __('Remove this photo?') }}"
                                        class="h-7 px-2 inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-[6px] transition cursor-pointer">
                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                        <span>{{ __('Remove') }}</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </section>
    @elseif ($tab === 'faq')
        <x-page-header
            :title="__('FAQ')"
            :subtitle="__('These answers are written for you from your settings and stay up to date on their own. Untick any you do not want, and add your own questions if you like.')"
            icon="fa-circle-question"
        />

        {{-- FAQ Section --}}
        <section class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] space-y-5">
            <div class="pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7] flex items-center gap-2">
                    <i class="fa-solid fa-circle-question text-[#8B8D98]"></i>
                    <span>{{ __('Frequently asked questions') }}</span>
                </h3>
                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                    {{ __('These answers are written for you from your settings and stay up to date on their own. Untick any you do not want, and add your own questions if you like.') }}
                </p>
            </div>

            {{-- Generated FAQ Checklist --}}
            <div class="space-y-2">
                <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('Automated Answers (from your shop settings)') }}
                </h4>
                <div class="space-y-2">
                    @foreach ($this->generatedFaq as $item)
                        <label wire:key="faq-{{ $item['key'] }}" class="flex items-start gap-3.5 rounded-[10px] border border-[#E4E5E9] dark:border-[#1E2433] bg-slate-50/60 dark:bg-[#141821]/40 p-3.5 hover:bg-slate-50 dark:hover:bg-[#141821] cursor-pointer transition">
                            <input type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 cursor-pointer"
                                @checked(! in_array($item['key'], $faqHidden, true))
                                x-on:change="$event.target.checked ? $wire.set('faqHidden', $wire.faqHidden.filter(k => k !== '{{ $item['key'] }}')) : $wire.set('faqHidden', [...$wire.faqHidden, '{{ $item['key'] }}'])" />
                            <div class="space-y-0.5 flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $item['question'] }}</span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-200/70 dark:bg-zinc-800 text-slate-600 dark:text-slate-400">{{ __('Automated') }}</span>
                                </div>
                                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">{{ $item['answer'] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Custom FAQ Items Repeater --}}
            <div class="space-y-3 pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Your Own Questions') }}
                    </h4>
                    <span class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __(':count of :max questions', ['count' => count($faqItems), 'max' => \App\Services\StorefrontFaqService::MAX_CUSTOM_ITEMS]) }}
                    </span>
                </div>

                @foreach ($faqItems as $index => $item)
                    <div wire:key="custom-faq-{{ $index }}" class="space-y-2.5 rounded-[10px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] p-4 shadow-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Question #:number', ['number' => $index + 1]) }}
                            </span>
                            <button type="button" wire:click="removeFaqItem({{ $index }})"
                                class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700 flex items-center gap-1 cursor-pointer">
                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                                <span>{{ __('Remove question') }}</span>
                            </button>
                        </div>
                        <div class="space-y-2">
                            <input type="text" wire:model="faqItems.{{ $index }}.question" maxlength="150" placeholder="{{ __('Question (e.g. Do you offer vegetarian meal options?)') }}"
                                class="h-9 w-full rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] px-3 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-brand-500 focus:outline-none transition" />
                            <textarea wire:model="faqItems.{{ $index }}.answer" rows="2" maxlength="1000" placeholder="{{ __('Answer text shown on your storefront...') }}"
                                class="w-full rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] p-3 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-brand-500 focus:outline-none transition leading-relaxed"></textarea>
                        </div>
                        <x-input-error :messages="$errors->get('faqItems.'.$index)" />
                    </div>
                @endforeach
                <x-input-error :messages="$errors->get('faqItems')" />
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                @if (count($faqItems) < \App\Services\StorefrontFaqService::MAX_CUSTOM_ITEMS)
                    <x-button type="button" variant="outline" size="sm" wire:click="addFaqItem" class="rounded-[8px] cursor-pointer">
                        <i class="fa-solid fa-plus mr-1.5 text-xs"></i>
                        {{ __('Add a question') }}
                    </x-button>
                @endif
                <x-button type="button" variant="primary" size="sm" wire:click="saveFaq" class="rounded-[8px] cursor-pointer">
                    <i class="fa-solid fa-floppy-disk mr-1.5 text-xs"></i>
                    {{ __('Save FAQ') }}
                </x-button>
            </div>
        </section>
    @elseif ($tab === 'contact')
        <x-page-header
            :title="__('Contact & private group form')"
            :subtitle="__('Allow travelers to submit private charter inquiries or general questions directly from your website.')"
            icon="fa-envelope-open-text"
        />

        {{-- Contact Form Section --}}
        <section class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] space-y-4">
            <div class="pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7] flex items-center gap-2">
                    <i class="fa-solid fa-envelope-open-text text-[#8B8D98]"></i>
                    <span>{{ __('Contact & private group form') }}</span>
                </h3>
                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                    {{ __('Allow travelers to submit private charter inquiries or general questions directly from your website.') }}
                </p>
            </div>

            @if (! $this->hasContactForm)
                <div class="p-5 rounded-[12px] bg-slate-50 dark:bg-[#141821] border border-slate-200 dark:border-zinc-800 space-y-3">
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-700 dark:text-brand-400 flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-crown text-amber-500"></i>
                        </div>
                        <div class="space-y-1 flex-1">
                            <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ __('Growth & Agency Feature') }}</h4>
                            <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                                {{ __('Available on paid plans. Until then, guests reach you with the WhatsApp button.') }}
                            </p>
                        </div>
                    </div>
                    <div class="pt-1">
                        <a href="{{ route('settings.plan') }}" wire:navigate class="h-9 px-4 inline-flex items-center gap-1.5 rounded-[8px] bg-brand-600 hover:bg-brand-700 text-brand-foreground text-xs font-bold transition shadow-xs">
                            <span>{{ __('Upgrade plan') }}</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            @else
                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                    {{ __('Off by default: guests use your WhatsApp button. Turn this on to also let guests send a question or a private / group trip request. Every message is saved under Enquiries with a Reply on WhatsApp button.') }}
                </p>

                <div class="p-4 rounded-[10px] bg-slate-50/70 dark:bg-[#141821]/50 border border-[#E4E5E9] dark:border-[#1E2433] space-y-4">
                    <x-checkbox id="contactEnabled" wire:model.live="contactEnabled" :label="__('Show the contact form on my website')" />

                    @if ($contactEnabled)
                        <div class="pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433] space-y-4">
                            <x-checkbox id="contactEmailNotifications" wire:model.live="contactEmailNotifications" :label="__('Also email me each new message')" />

                            @if ($contactEmailNotifications)
                                <div class="space-y-1.5 max-w-md">
                                    <x-label for="contactNotifyEmail" :value="__('Send to (leave empty to use your booking email)')" />
                                    <x-input id="contactNotifyEmail" type="email" wire:model="contactNotifyEmail"
                                        placeholder="{{ $this->currentOperator?->booking_notification_email }}" />
                                    <x-input-error :messages="$errors->get('contactNotifyEmail')" />
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="pt-1">
                    <x-button type="button" variant="primary" size="sm" wire:click="saveContact" class="rounded-[8px] cursor-pointer">
                        <i class="fa-solid fa-floppy-disk mr-1.5 text-xs"></i>
                        {{ __('Save') }}
                    </x-button>
                </div>
            @endif
        </section>
    @endif
</div>

