@php
    $googlePlace = $agent->hasFeature('google_reviews') ? $agent->googlePlace() : null;
    $googleReviews = $agent->googleReviews();
@endphp

@if ($googlePlace && $googleReviews !== [])
    <section class="space-y-4 pt-6 border-t border-slate-200/80 dark:border-zinc-800" aria-label="{{ __('Reviews from Google') }}">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 space-y-1">
                <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Reviews from Google') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $googlePlace['name'] }}
                    @if (filled($googlePlace['rating'] ?? null))
                        · {{ number_format((float) $googlePlace['rating'], 1) }}
                    @endif
                    @if (filled($googlePlace['review_count'] ?? null))
                        · {{ __(':count reviews on Google', ['count' => $googlePlace['review_count']]) }}
                    @endif
                </p>
            </div>
            @if (filled($googlePlace['maps_url'] ?? null))
                <a href="{{ $googlePlace['maps_url'] }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 hover:underline dark:text-slate-200">
                    <i class="fa-brands fa-google text-sm" aria-hidden="true"></i>
                    {{ __('See on Google') }}
                </a>
            @endif
        </div>

        <div class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($googleReviews as $review)
                <article class="w-[min(100%,20rem)] shrink-0 snap-start rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 md:w-[22rem]">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $review['author'] !== '' ? $review['author'] : __('Google reviewer') }}
                        </p>
                        <span class="inline-flex shrink-0 items-center gap-0.5 text-amber-500" aria-label="{{ __(':count stars', ['count' => $review['rating'] ?? 0]) }}">
                            @for ($star = 1; $star <= 5; $star++)
                                <i class="fa-solid fa-star text-[10px] {{ $star <= (int) ($review['rating'] ?? 0) ? '' : 'opacity-25' }}"></i>
                            @endfor
                        </span>
                    </div>
                    @if (filled($review['relative_time'] ?? null))
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ $review['relative_time'] }}</p>
                    @endif
                    @if (filled($review['text'] ?? null))
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $review['text'] }}</p>
                    @endif
                    @if (filled($review['url'] ?? null))
                        <a href="{{ $review['url'] }}" target="_blank" rel="noopener noreferrer"
                            class="mt-3 inline-flex text-xs font-bold text-slate-700 hover:underline dark:text-slate-200">
                            {{ __('Read on Google') }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
