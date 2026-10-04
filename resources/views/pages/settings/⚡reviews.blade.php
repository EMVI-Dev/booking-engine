<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Models\Operator;
use App\Services\GooglePlacesService;
use App\Services\Integrations\GooglePlacesClient;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reviews')] class extends Component {
    use ResolvesCurrentOperator;

    public string $review_url = '';

    public string $reviewSource = '';

    public string $google_place_query = '';

    /**
     * The Place ID about to be connected, after Google confirmed it exists (free id-only
     * check). Locked: only the server sets it, so the confirmed id cannot be swapped.
     */
    #[Locked]
    public ?string $googlePlacePreviewId = null;

    public bool $saved = false;

    public bool $highlightReviews = false;

    public function mount(): void
    {
        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if (! $operator) {
            return;
        }

        $this->review_url = (string) ($operator->settings['marketing']['review_url'] ?? '');

        if (! $operator->hasFeature('google_reviews')) {
            $this->reviewSource = 'link';
            $this->syncReviewHighlight();

            return;
        }

        if ($operator->googlePlaceId() !== null) {
            $this->reviewSource = 'listing';
            $this->syncReviewHighlight();

            return;
        }

        if ($this->review_url !== '') {
            $this->reviewSource = 'link';
        }

        $this->syncReviewHighlight();
    }

    protected function syncReviewHighlight(): void
    {
        $this->highlightReviews = ! ($this->currentOperator?->hasReviewUrl() ?? false);
    }

    public function chooseReviewSource(string $source): void
    {
        $this->authorizeAbility('manageSettings');

        if (! in_array($source, ['listing', 'link'], true)) {
            return;
        }

        if ($source === 'listing') {
            $this->authorizeFeature('google_reviews');
        }

        if ($this->currentOperator?->googlePlaceId() !== null) {
            return;
        }

        $this->reviewSource = $source;
        $this->googlePlacePreviewId = null;
        $this->google_place_query = '';
        $this->resetErrorBag('google_place_query');
    }

    public function updatedGooglePlaceQuery(GooglePlacesService $places): void
    {
        $this->authorizeFeature('google_reviews');

        if (trim($this->google_place_query) === '') {
            $this->googlePlacePreviewId = null;
            $this->resetErrorBag('google_place_query');

            return;
        }

        $this->lookupGooglePlace($places);
    }

    /**
     * Check the pasted Place ID with Google's free id-only request. No name search and no
     * share-link lookup: those are billed by Google.
     */
    public function lookupGooglePlace(GooglePlacesService $places): void
    {
        $this->authorizeAbility('manageSettings');
        $this->authorizeFeature('google_reviews');
        $this->googlePlacePreviewId = null;
        $this->resetErrorBag('google_place_query');

        if ($this->currentOperator?->googlePlaceId()) {
            $this->addError('google_place_query', __('Disconnect the current listing before choosing another.'));

            return;
        }

        if (! $places->isConfigured()) {
            $this->addError('google_place_query', __('Google reviews are not set up yet.'));

            return;
        }

        $this->validate([
            'google_place_query' => ['required', 'string', 'max:500'],
        ]);

        $check = $places->checkPastedPlaceId($this->google_place_query);

        match ($check['status']) {
            GooglePlacesClient::PLACE_FOUND => $this->googlePlacePreviewId = $check['place_id'],
            GooglePlacesClient::GOOGLE_UNAVAILABLE => $this->addError('google_place_query', __('Google did not answer just now. Please try again in a minute.')),
            default => $this->addError('google_place_query', __('That is not a Place ID Google knows. Copy the Place ID (it starts with ChIJ) from the Place ID Finder, following the steps above.')),
        };
    }

    public function promptConnectGooglePlace(): void
    {
        $this->authorizeAbility('manageSettings');
        $this->authorizeFeature('google_reviews');

        if ($this->googlePlacePreviewId === null) {
            return;
        }

        $this->dispatch('open-modal', 'confirm-google-listing');
    }

    public function confirmGooglePlace(GooglePlacesService $places): void
    {
        $this->authorizeAbility('manageSettings');
        $this->authorizeFeature('google_reviews');

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if (! $operator || $this->googlePlacePreviewId === null) {
            return;
        }

        $places->connect($operator, $this->googlePlacePreviewId);
        unset($this->currentOperator);

        $this->googlePlacePreviewId = null;
        $this->google_place_query = '';
        $this->reviewSource = 'listing';
        $this->syncReviewHighlight();
        $this->dispatch('close-modal', 'confirm-google-listing');
        $this->dispatch('reviews-updated');
        $this->dispatch('setup-progress-updated');
        $this->dispatch('toast', message: __('Google listing connected.'), type: 'success');
    }

    public function promptDisconnectGooglePlace(): void
    {
        $this->authorizeAbility('manageSettings');
        $this->authorizeFeature('google_reviews');

        if ($this->currentOperator?->googlePlaceId() === null) {
            return;
        }

        $this->dispatch('open-modal', 'confirm-disconnect-google-listing');
    }

    public function disconnectGooglePlace(GooglePlacesService $places): void
    {
        $this->authorizeAbility('manageSettings');
        $this->authorizeFeature('google_reviews');

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if (! $operator) {
            return;
        }

        $places->forgetPlace($operator);
        unset($this->currentOperator);
        $this->googlePlacePreviewId = null;
        $this->google_place_query = '';
        $this->reviewSource = $this->review_url !== '' ? 'link' : '';
        $this->syncReviewHighlight();
        $this->dispatch('close-modal', 'confirm-disconnect-google-listing');
        $this->dispatch('reviews-updated');
        $this->dispatch('setup-progress-updated');
        $this->dispatch('toast', message: __('Google listing disconnected.'), type: 'success');
    }

    public function cancelGooglePlacePreview(): void
    {
        $this->googlePlacePreviewId = null;
    }

    public function updateReviewSettings(): void
    {
        $this->authorizeAbility('manageSettings');

        if ($this->currentOperator?->hasFeature('google_reviews') && $this->reviewSource !== 'link') {
            return;
        }

        $validated = $this->validate([
            'review_url' => ['nullable', 'url', 'max:500'],
        ]);

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if (! $operator) {
            return;
        }

        $settings = $operator->settings ?? [];
        $marketing = is_array($settings['marketing'] ?? null) ? $settings['marketing'] : [];
        $marketing['review_url'] = $validated['review_url'] ?? null;
        $settings['marketing'] = $marketing;

        $operator->update(['settings' => $settings]);
        unset($this->currentOperator);

        $this->saved = true;
        $this->syncReviewHighlight();
        $this->dispatch('reviews-updated');
        $this->dispatch('setup-progress-updated');
        $this->dispatch('toast', message: __('Review settings saved.'), type: 'success');
    }
}; ?>

<div class="space-y-6 w-full">
    <div class="space-y-6">
        <x-settings-nav />

        <x-page-header
            :title="__('Reviews')"
            :subtitle="$this->currentOperator?->hasFeature('google_reviews') ? __('Connect a Google listing, or use a review link. Post-trip emails use whichever you choose.') : __('Post-trip review emails are only sent when this link is set.')"
            icon="fa-star"
        />

        @if ($highlightReviews)
            <div
                class="flex items-start gap-3 rounded-[12px] border border-[#FFEF4D]/50 bg-[#FFEF4D]/15 px-4 py-3 dark:border-[#FFEF4D]/25 dark:bg-[#FFEF4D]/10 shadow-none"
                role="status"
            >
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D] text-[#12181E]" aria-hidden="true">
                    <i class="fa-solid fa-list-check text-sm"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Finish the highlighted fields') }}</p>
                    <p class="mt-0.5 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('Add a review link so guests can leave feedback after the trip.') }}
                    </p>
                </div>
            </div>
        @endif

        @php
            // Connected = a stored place id. Details are fetched live and may be unavailable.
            $connectedPlaceId = $this->currentOperator?->googlePlaceId();
            // This page never calls Google's billed API: it shows the id and a Google Maps link.
            $placesService = app(\App\Services\GooglePlacesService::class);
            $connectedMapsUrl = $connectedPlaceId ? $placesService->mapsUrl($connectedPlaceId, (string) $this->currentOperator?->name) : null;
            $previewMapsUrl = $googlePlacePreviewId ? $placesService->mapsUrl($googlePlacePreviewId, (string) $this->currentOperator?->name) : null;
            // Google's free embedded card (name, address, stars); null without an embed key.
            $connectedEmbedUrl = $connectedPlaceId ? $placesService->embedUrl($connectedPlaceId) : null;
            $previewEmbedUrl = $googlePlacePreviewId ? $placesService->embedUrl($googlePlacePreviewId) : null;
            $canConnectListing = (bool) $this->currentOperator?->hasFeature('google_reviews');
        @endphp

        <div
            id="setup-reviews"
            @if ($highlightReviews) data-setup-needed="true" @endif
            @class([
                'space-y-6 scroll-mt-24',
                'rounded-[12px] border-2 border-[#FFEF4D] bg-[#FFEF4D]/10 p-2 dark:bg-[#FFEF4D]/5' => $highlightReviews,
            ])
            x-data
            x-init="
                $nextTick(() => {
                    const hash = window.location.hash;
                    const target = hash
                        ? document.querySelector(hash)
                        : document.querySelector('[data-setup-needed]');
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                })
            "
        >
            @if ($highlightReviews)
                <div class="mb-2 inline-flex items-center gap-1.5 rounded-[4px] bg-[#FFEF4D] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#12181E]">
                    <i class="fa-solid fa-circle-exclamation text-[9px]" aria-hidden="true"></i>
                    <span>{{ __('Needed for bookings — save to confirm') }}</span>
                </div>
            @endif

        @if ($canConnectListing && ! $connectedPlaceId)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <button type="button" wire:click="chooseReviewSource('listing')"
                    class="rounded-[12px] border p-4 text-left transition shadow-none cursor-pointer {{ $reviewSource === 'listing' ? 'border-[#12181E] bg-[#F4F5F7] dark:border-white dark:bg-[#141821]' : 'border-[#E4E5E9] bg-white dark:border-[#1E2433] dark:bg-[#10141d]' }}">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Connect a Google listing') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('Show your Google rating card on your booking page, with buttons to read and leave reviews. Review emails use that listing.') }}
                    </p>
                </button>
                <button type="button" wire:click="chooseReviewSource('link')"
                    class="rounded-[12px] border p-4 text-left transition shadow-none cursor-pointer {{ $reviewSource === 'link' ? 'border-[#12181E] bg-[#F4F5F7] dark:border-white dark:bg-[#141821]' : 'border-[#E4E5E9] bg-white dark:border-[#1E2433] dark:bg-[#10141d]' }}">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Use a review link') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('Paste a Google, Tripadvisor, or any other review page. No slider.') }}
                    </p>
                </button>
            </div>
        @endif

        @if ($canConnectListing && ($connectedPlaceId || $reviewSource === 'listing'))
            <div
                class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('Google listing') }}
                    </h3>
                    <p class="mt-1 text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                        @if ($connectedPlaceId)
                            {{ __('One Google listing is connected. Disconnect it before choosing another.') }}
                        @else
                            {{ __('Paste your Google Place ID. It tells us exactly which business is yours.') }}
                        @endif
                    </p>
                </div>

                @if ($connectedPlaceId)
                    <div class="rounded-[8px] border border-[#E4E5E9] bg-[#F4F5F7]/70 p-4 dark:border-[#1E2433] dark:bg-[#141821] shadow-none">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Google listing connected') }}</p>
                                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Place ID') }}: <span class="font-mono">{{ $connectedPlaceId }}</span></p>
                                <a href="{{ $connectedMapsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex text-xs font-semibold text-slate-900 underline dark:text-white">
                                    {{ __('View on Google Maps') }}
                                </a>
                            </div>
                            <button type="button" wire:click="promptDisconnectGooglePlace"
                                class="h-9 w-full shrink-0 rounded-[6px] border border-rose-200 dark:border-rose-900/60 px-3 text-xs font-semibold text-rose-700 sm:w-auto dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer">
                                {{ __('Disconnect') }}
                            </button>
                        </div>
                        @if ($connectedEmbedUrl)
                            <div class="mt-3">
                                <iframe src="{{ $connectedEmbedUrl }}" title="{{ __('Google listing preview') }}" class="block h-56 w-full rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433]" style="border:0" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                            </div>
                        @endif
                    </div>
                @elseif (! app(\App\Services\GooglePlacesService::class)->isConfigured())
                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Google reviews are not set up yet.') }}</p>
                @else
                    <div class="rounded-[8px] border border-[#E4E5E9] bg-[#F4F5F7]/70 p-4 dark:border-[#1E2433] dark:bg-[#141821] space-y-2">
                        <p class="text-xs font-semibold text-slate-900 dark:text-white">{{ __('How to find your Place ID (1 minute)') }}</p>
                        <ol class="list-decimal space-y-1 pl-4 text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                            <li>
                                {{ __('Open Google\'s') }}
                                <a href="{{ \App\Services\GooglePlacesService::PLACE_ID_FINDER_URL }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-900 underline dark:text-white">{{ __('Place ID Finder') }}</a>.
                            </li>
                            <li>{{ __('Type your business name in the search box on the map, then pick your business from the list. Check the address.') }}</li>
                            <li>{{ __('Copy the Place ID shown on the map. It starts with ChIJ, for example ChIJN1t_tDeuEmsRUsoyG83frY4.') }}</li>
                            <li>{{ __('Paste it below and press Enter.') }}</li>
                        </ol>
                        <p class="text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('Not on Google yet? Add your business at') }}
                            <a href="https://business.google.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-900 underline dark:text-white">business.google.com</a>.
                        </p>
                        <p class="text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Only connect a listing you own or manage.') }}</p>
                    </div>
                    <x-input id="google_place_query" wire:model.live.blur="google_place_query" type="text" class="h-9 w-full rounded-[6px] text-xs font-mono"
                        placeholder="{{ __('ChIJ…') }}" :error="$errors->has('google_place_query')"
                        x-on:keydown.enter.prevent="$wire.set('google_place_query', $event.target.value)" />
                    <p wire:loading wire:target="google_place_query,lookupGooglePlace"
                        class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('Looking up the listing...') }}
                    </p>
                    <x-input-error :messages="$errors->get('google_place_query')" />
                @endif

                @if (! $connectedPlaceId && $googlePlacePreviewId)
                    <div class="space-y-3 rounded-[8px] border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/60 dark:bg-amber-950/30 shadow-none">
                    @if ($previewEmbedUrl)
                        <iframe src="{{ $previewEmbedUrl }}" title="{{ __('Google listing preview') }}" class="block h-56 w-full rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433]" style="border:0" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    @endif
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Google found this Place ID') }}</p>
                            <p class="text-xs text-slate-600 dark:text-slate-300">
                                {{ __('Check it is your business before connecting:') }}
                                <a href="{{ $previewMapsUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-900 underline dark:text-white">{{ __('Open on Google Maps') }}</a>
                            </p>
                            <p class="text-[11px] font-mono text-slate-500 break-all">{{ $googlePlacePreviewId }}</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button" wire:click="promptConnectGooglePlace"
                                class="h-9 w-full rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] px-3.5 text-xs font-semibold text-[#12181E] sm:w-auto shadow-none transition cursor-pointer">
                                {{ __('Connect this listing') }}
                            </button>
                            <button type="button" wire:click="cancelGooglePlacePreview"
                                class="h-9 w-full rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] px-3.5 text-xs font-semibold text-slate-700 sm:w-auto dark:text-slate-200 hover:bg-[#F4F5F7] dark:hover:bg-[#141821] shadow-none transition cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                        </div>
                    </div>
                    </div>
                @endif
            </div>
        @endif

        @if (! $canConnectListing || (! $connectedPlaceId && $reviewSource === 'link'))
            <form wire:submit="updateReviewSettings" class="w-full space-y-6">
                <div
                    class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                        {{ __('Post-trip review emails are only sent when this link is set.') }}
                    </p>
                    <div>
                        <x-label for="review_url" :value="__('Review Platform (e.g. Google/Tripadvisor)')" required />
                        <div class="relative">
                            <i class="fa-solid fa-star absolute left-3.5 top-1/2 -translate-y-1/2 text-amber-400 text-xs"></i>
                            <x-input id="review_url" wire:model.live.debounce.300ms="review_url" type="url"
                                placeholder="https://g.page/r/your-business/review" class="pl-9 rounded-[6px] h-9 text-xs" :error="$errors->has('review_url')" />
                        </div>
                        <x-input-error :messages="$errors->get('review_url')" />
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center pt-2">
                    <x-button variant="primary" type="submit" data-test="update-reviews-button" class="w-full sm:w-auto shadow-none font-semibold" wire:loading.attr="disabled" wire:target="updateReviewSettings">
                        <i class="fa-solid fa-floppy-disk mr-1 text-xs" wire:loading.remove wire:target="updateReviewSettings"></i>
                        <i class="fa-solid fa-spinner fa-spin mr-1 text-xs" wire:loading wire:target="updateReviewSettings"></i>
                        <span wire:loading.remove wire:target="updateReviewSettings">{{ __('Save review link') }}</span>
                        <span wire:loading wire:target="updateReviewSettings">{{ __('Saving…') }}</span>
                    </x-button>

                    <div x-data="{ shown: false, timeout: null }" x-init="@this.on('reviews-updated', () => {
                        clearTimeout(timeout);
                        shown = true;
                        timeout = setTimeout(() => { shown = false }, 2500);
                    })"
                        x-show.transition.out.opacity.duration.1500ms="shown" x-transition:leave.opacity.duration.1500ms
                        style="display: none;"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        <i class="fa-solid fa-circle-check"></i>
                        {{ __('Review settings saved.') }}
                    </div>
                </div>
            </form>
        @endif
        </div>
    </div>

    @if ($canConnectListing)
        <x-modal name="confirm-google-listing" maxWidth="lg">
            <div class="space-y-4 p-5 sm:p-6">
                <!-- Mobile drag handle -->
                <div class="mx-auto -mt-1 mb-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                        <i class="fa-brands fa-google text-sm" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Connect this listing?') }}</h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('Your Google rating card and review buttons will show on your booking page.') }}
                        </p>
                    </div>
                </div>

                @if ($googlePlacePreviewId)
                    <dl class="space-y-3 rounded-[8px] border border-[#E4E5E9] bg-[#F4F5F7]/70 p-4 dark:border-[#1E2433] dark:bg-[#141821] shadow-none">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Place ID') }}</dt>
                            <dd class="text-right text-xs font-mono text-slate-900 dark:text-white break-all">{{ $googlePlacePreviewId }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Listing') }}</dt>
                            <dd class="text-right text-xs"><a href="{{ $previewMapsUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-900 underline dark:text-white">{{ __('Open on Google Maps') }}</a></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Only connect a listing you own or manage.') }}</p>
                @endif

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end border-t border-[#E4E5E9] dark:border-[#1E2433]">
                    <x-button type="button" variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'confirm-google-listing')"
                        class="w-full font-semibold text-xs sm:w-auto">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button type="button" variant="primary" size="sm" wire:click="confirmGooglePlace" wire:loading.attr="disabled"
                        class="w-full font-semibold text-xs shadow-none sm:w-auto">
                        {{ __('Connect listing') }}
                    </x-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="confirm-disconnect-google-listing" maxWidth="lg">
            <div class="space-y-4 p-5 sm:p-6">
                <!-- Mobile drag handle -->
                <div class="mx-auto -mt-1 mb-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                        <i class="fa-brands fa-google text-sm" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Disconnect this listing?') }}</h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('Google reviews will leave your booking page, and review emails will stop using this listing.') }}
                        </p>
                    </div>
                </div>

                @if ($connectedPlaceId)
                    <dl class="space-y-3 rounded-[8px] border border-[#E4E5E9] bg-[#F4F5F7]/70 p-4 dark:border-[#1E2433] dark:bg-[#141821] shadow-none">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Place ID') }}</dt>
                            <dd class="text-right text-xs font-mono text-slate-900 dark:text-white break-all">{{ $connectedPlaceId }}</dd>
                        </div>
                    </dl>
                @endif

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end border-t border-[#E4E5E9] dark:border-[#1E2433]">
                    <x-button type="button" variant="secondary" size="sm"
                        x-on:click="$dispatch('close-modal', 'confirm-disconnect-google-listing')"
                        class="w-full font-semibold text-xs sm:w-auto">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button type="button" variant="danger" size="sm" wire:click="disconnectGooglePlace" wire:loading.attr="disabled"
                        class="w-full font-semibold text-xs shadow-none sm:w-auto">
                        {{ __('Disconnect listing') }}
                    </x-button>
                </div>
            </div>
        </x-modal>
    @endif
</div>
