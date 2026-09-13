<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Models\Operator;
use App\Services\GooglePlacesService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reviews')] class extends Component {
    use ResolvesCurrentOperator;

    public string $review_url = '';

    public string $reviewSource = '';

    public string $google_place_query = '';

    /** @var array<string, mixed>|null */
    public ?array $googlePlacePreview = null;

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
        $this->googlePlacePreview = null;
        $this->google_place_query = '';
        $this->resetErrorBag('google_place_query');
    }

    public function updatedGooglePlaceQuery(GooglePlacesService $places): void
    {
        $this->authorizeFeature('google_reviews');

        $query = trim($this->google_place_query);
        if ($query === '' || (mb_strlen($query) < 3 && ! str_starts_with($query, 'http'))) {
            $this->googlePlacePreview = null;
            $this->resetErrorBag('google_place_query');

            return;
        }

        $this->lookupGooglePlace($places);
    }

    public function lookupGooglePlace(GooglePlacesService $places): void
    {
        $this->authorizeAbility('manageSettings');
        $this->authorizeFeature('google_reviews');
        $this->googlePlacePreview = null;
        $this->resetErrorBag('google_place_query');

        $query = trim($this->google_place_query);
        if ($query === '' || (mb_strlen($query) < 3 && ! str_starts_with($query, 'http'))) {
            return;
        }

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

        $snapshot = $places->lookup($query);
        if ($snapshot === null) {
            $this->addError('google_place_query', __('No Google listing matched that link or name.'));

            return;
        }

        $this->googlePlacePreview = $snapshot;
    }

    public function promptConnectGooglePlace(): void
    {
        $this->authorizeAbility('manageSettings');
        $this->authorizeFeature('google_reviews');

        if (! is_array($this->googlePlacePreview) || blank($this->googlePlacePreview['place_id'] ?? null)) {
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
        if (! $operator || ! is_array($this->googlePlacePreview) || blank($this->googlePlacePreview['place_id'] ?? null)) {
            return;
        }

        $places->storeSnapshot($operator, $this->googlePlacePreview);
        unset($this->currentOperator);

        $this->googlePlacePreview = null;
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
        $this->googlePlacePreview = null;
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
        $this->googlePlacePreview = null;
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

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-300">
                        <i class="fa-solid fa-star text-lg"></i>
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Reviews') }}
                    </h1>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    @if ($this->currentOperator?->hasFeature('google_reviews'))
                        {{ __('Connect a Google listing, or use a review link. Post-trip emails use whichever you choose.') }}
                    @else
                        {{ __('Post-trip review emails are only sent when this link is set.') }}
                    @endif
                </p>
            </div>
        </div>

        @if ($highlightReviews)
            <div
                class="flex items-start gap-3 rounded-2xl border border-[#FFEF4D]/50 bg-[#FFEF4D]/15 px-4 py-3 dark:border-[#FFEF4D]/25 dark:bg-[#FFEF4D]/10"
                role="status"
            >
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#FFEF4D] text-[#12181E]" aria-hidden="true">
                    <i class="fa-solid fa-list-check text-sm"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-op-ink">{{ __('Finish the highlighted fields') }}</p>
                    <p class="mt-0.5 text-xs text-op-subtle">
                        {{ __('Add a review link so guests can leave feedback after the trip.') }}
                    </p>
                </div>
            </div>
        @endif

        @php
            $connectedPlace = $this->currentOperator?->googlePlace();
            $canConnectListing = (bool) $this->currentOperator?->hasFeature('google_reviews');
        @endphp

        <div
            id="setup-reviews"
            @if ($highlightReviews) data-setup-needed="true" @endif
            @class([
                'space-y-6 scroll-mt-24',
                'rounded-[1.35rem] border-2 border-[#FFEF4D] bg-[#FFEF4D]/20 p-1.5 shadow-[0_0_0_4px_rgba(255,239,77,0.35)] dark:bg-[#FFEF4D]/10 dark:shadow-[0_0_0_4px_rgba(255,239,77,0.2)]' => $highlightReviews,
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
                <div class="mb-2 inline-flex items-center gap-1.5 rounded-lg bg-[#FFEF4D] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-[#12181E]">
                    <i class="fa-solid fa-circle-exclamation text-[9px]" aria-hidden="true"></i>
                    <span>{{ __('Needed for bookings — save to confirm') }}</span>
                </div>
            @endif

        @if ($canConnectListing && ! $connectedPlace)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <button type="button" wire:click="chooseReviewSource('listing')"
                    class="rounded-2xl border p-4 text-left {{ $reviewSource === 'listing' ? 'border-slate-900 bg-slate-50 dark:border-white dark:bg-zinc-900' : 'border-slate-200 bg-white dark:border-[#1e2433] dark:bg-[#0C0E13]' }}">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Connect a Google listing') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ __('Show up to 5 Google reviews on your booking page. Review emails use that listing.') }}
                    </p>
                </button>
                <button type="button" wire:click="chooseReviewSource('link')"
                    class="rounded-2xl border p-4 text-left {{ $reviewSource === 'link' ? 'border-slate-900 bg-slate-50 dark:border-white dark:bg-zinc-900' : 'border-slate-200 bg-white dark:border-[#1e2433] dark:bg-[#0C0E13]' }}">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Use a review link') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ __('Paste a Google, Tripadvisor, or any other review page. No slider.') }}
                    </p>
                </button>
            </div>
        @endif

        @if ($canConnectListing && ($connectedPlace || $reviewSource === 'listing'))
            <div
                class="p-5 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Google listing') }}
                    </h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        @if ($connectedPlace)
                            {{ __('One Google listing is connected. Disconnect it before choosing another.') }}
                        @else
                            {{ __('One Google listing. Paste a Maps link or search the business name.') }}
                        @endif
                    </p>
                </div>

                @if ($connectedPlace)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $connectedPlace['name'] }}</p>
                                @if (filled($connectedPlace['address'] ?? null))
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $connectedPlace['address'] }}</p>
                                @endif
                                <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    @if (filled($connectedPlace['rating'] ?? null))
                                        {{ number_format((float) $connectedPlace['rating'], 1) }}
                                    @endif
                                    @if (filled($connectedPlace['review_count'] ?? null))
                                        · {{ __(':count Google reviews', ['count' => $connectedPlace['review_count']]) }}
                                    @endif
                                    · {{ __('Showing :count on the booking page', ['count' => count($connectedPlace['reviews'] ?? [])]) }}
                                </p>
                            </div>
                            <button type="button" wire:click="promptDisconnectGooglePlace"
                                class="h-11 w-full shrink-0 rounded-xl border border-rose-200 px-4 text-sm font-semibold text-rose-700 sm:w-auto dark:border-rose-900 dark:text-rose-300">
                                {{ __('Disconnect') }}
                            </button>
                        </div>
                    </div>
                @elseif (! app(\App\Services\GooglePlacesService::class)->isConfigured())
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Google reviews are not set up yet.') }}</p>
                @else
                    <x-input id="google_place_query" wire:model.live.blur="google_place_query" type="text" class="h-11 w-full"
                        placeholder="{{ __('Google Maps link or business name') }}" :error="$errors->has('google_place_query')"
                        x-on:keydown.enter.prevent="$wire.set('google_place_query', $event.target.value)" />
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Press Enter to show the listing.') }}</p>
                    <p wire:loading wire:target="google_place_query,lookupGooglePlace"
                        class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Looking up the listing...') }}
                    </p>
                    <x-input-error :messages="$errors->get('google_place_query')" />
                @endif

                @if (! $connectedPlace && $googlePlacePreview)
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900 dark:bg-amber-950/40">
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $googlePlacePreview['name'] }}</p>
                            @if (filled($googlePlacePreview['address'] ?? null))
                                <p class="text-xs text-slate-600 dark:text-slate-300">{{ $googlePlacePreview['address'] }}</p>
                            @endif
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button" wire:click="promptConnectGooglePlace"
                                class="h-11 w-full rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white sm:w-auto dark:bg-white dark:text-slate-900">
                                {{ __('Connect this listing') }}
                            </button>
                            <button type="button" wire:click="cancelGooglePlacePreview"
                                class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 sm:w-auto dark:border-zinc-700 dark:text-slate-200">
                                {{ __('Cancel') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if (! $canConnectListing || (! $connectedPlace && $reviewSource === 'link'))
            <form wire:submit="updateReviewSettings" class="w-full space-y-6">
                <div
                    class="p-5 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Post-trip review emails are only sent when this link is set.') }}
                    </p>
                    <div>
                        <x-label for="review_url" :value="__('Review Platform (e.g. Google/Tripadvisor)')" required />
                        <div class="relative">
                            <i class="fa-solid fa-star absolute left-3.5 top-1/2 -translate-y-1/2 text-amber-400 text-xs"></i>
                            <x-input id="review_url" wire:model.live.debounce.300ms="review_url" type="url"
                                placeholder="https://g.page/r/your-business/review" class="pl-9" :error="$errors->has('review_url')" />
                        </div>
                        <x-input-error :messages="$errors->get('review_url')" />
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center pt-2">
                    <x-button variant="primary" type="submit" data-test="update-reviews-button" class="w-full sm:w-auto shadow-sm" wire:loading.attr="disabled" wire:target="updateReviewSettings">
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
            <div class="space-y-4 p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-400 text-brand-foreground">
                        <i class="fa-brands fa-google text-lg" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Connect this listing?') }}</h3>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ __('Up to 5 Google reviews will show on your booking page.') }}
                        </p>
                    </div>
                </div>

                @if ($googlePlacePreview)
                    <dl class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-[#1e2433] dark:bg-[#141821]">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Listing') }}</dt>
                            <dd class="text-right text-sm font-semibold text-slate-900 dark:text-white">{{ $googlePlacePreview['name'] }}</dd>
                        </div>
                        @if (filled($googlePlacePreview['address'] ?? null))
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Address') }}</dt>
                                <dd class="text-right text-sm font-semibold text-slate-900 dark:text-white">{{ $googlePlacePreview['address'] }}</dd>
                            </div>
                        @endif
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Google rating') }}</dt>
                            <dd class="text-right text-sm font-semibold text-slate-900 dark:text-white">
                                @if (filled($googlePlacePreview['rating'] ?? null))
                                    {{ number_format((float) $googlePlacePreview['rating'], 1) }}
                                @else
                                    —
                                @endif
                                @if (filled($googlePlacePreview['review_count'] ?? null))
                                    · {{ __(':count Google reviews', ['count' => $googlePlacePreview['review_count']]) }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                @endif

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'confirm-google-listing')"
                        class="w-full font-semibold text-xs sm:w-auto">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button type="button" variant="primary" wire:click="confirmGooglePlace" wire:loading.attr="disabled"
                        class="w-full font-semibold text-xs shadow-xs sm:w-auto">
                        {{ __('Connect listing') }}
                    </x-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="confirm-disconnect-google-listing" maxWidth="lg">
            <div class="space-y-4 p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                        <i class="fa-brands fa-google text-lg" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Disconnect this listing?') }}</h3>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ __('Google reviews will leave your booking page, and review emails will stop using this listing.') }}
                        </p>
                    </div>
                </div>

                @php
                    $listingToDisconnect = $this->currentOperator?->googlePlace();
                @endphp

                @if ($listingToDisconnect)
                    <dl class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-[#1e2433] dark:bg-[#141821]">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Listing') }}</dt>
                            <dd class="text-right text-sm font-semibold text-slate-900 dark:text-white">{{ $listingToDisconnect['name'] }}</dd>
                        </div>
                        @if (filled($listingToDisconnect['address'] ?? null))
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Address') }}</dt>
                                <dd class="text-right text-sm font-semibold text-slate-900 dark:text-white">{{ $listingToDisconnect['address'] }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <x-button type="button" variant="secondary"
                        x-on:click="$dispatch('close-modal', 'confirm-disconnect-google-listing')"
                        class="w-full font-semibold text-xs sm:w-auto">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button type="button" variant="danger" wire:click="disconnectGooglePlace" wire:loading.attr="disabled"
                        class="w-full font-semibold text-xs shadow-xs sm:w-auto">
                        {{ __('Disconnect listing') }}
                    </x-button>
                </div>
            </div>
        </x-modal>
    @endif
</div>
