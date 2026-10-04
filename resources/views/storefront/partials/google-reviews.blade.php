{{--
    Google listing on the storefront, using only Google's free services: the Maps Embed card
    (name, address, stars, review count) and plain links to read or write a review.
    Agency plans (google_reviews) with a connected place ID only.
--}}
@if ($agent->hasFeature('google_reviews') && $agent->googlePlaceId() !== null)
    @php
        $googleEmbedUrl = $agent->googleListingEmbedUrl();
    @endphp
    <section class="space-y-4 pt-6 border-t border-slate-200/80 dark:border-zinc-800" aria-label="{{ __('Reviews on Google') }}">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 space-y-1">
                <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Reviews on Google') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('See what guests say about :name on Google.', ['name' => $agent->name]) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $agent->googleListingMapsUrl() }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-slate-100 dark:hover:bg-zinc-800">
                    <i class="fa-brands fa-google text-xs" aria-hidden="true"></i>
                    {{ __('Read our reviews on Google') }}
                </a>
                <a href="{{ $agent->googleListingReviewUrl() }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-900 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                    <i class="fa-solid fa-pen text-[10px]" aria-hidden="true"></i>
                    {{ __('Leave a review') }}
                </a>
            </div>
        </div>

        @if ($googleEmbedUrl)
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 dark:border-zinc-800">
                <iframe
                    src="{{ $googleEmbedUrl }}"
                    title="{{ __(':name on Google Maps', ['name' => $agent->name]) }}"
                    class="block h-64 w-full sm:h-72"
                    style="border:0"
                    loading="lazy"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                ></iframe>
            </div>
        @endif
    </section>
@endif
