<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />

    @php
        $seo = app(\App\Services\StorefrontSeoService::class);
        $share = $seo->shareImage($agent);
        $galleryService = app(\App\Services\StorefrontGalleryService::class);
    @endphp
    @include('storefront.partials.seo', [
        'agent' => $agent,
        'title' => __('Photo gallery').' · '.$agent->name,
        'description' => __('Explore real travel photos and sights from tours with :name.', ['name' => $agent->name]),
        'url' => route('storefront.gallery'),
        'type' => 'website',
        'share' => $share,
        'schema' => $seo->galleryGraph($agent, $photos),
    ])

    @include('storefront.partials.brand-theme')

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('storefront.partials.tracking-scripts', ['agent' => $agent])
    @livewireStyles
</head>

<body x-data="{ mobileMenuOpen: false }"
    class="min-h-screen flex flex-col sf-canvas text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-brand-foreground overflow-x-clip w-full max-w-full">
    @include('storefront.partials.navbar')

    <!-- Main Content Area -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">
        <!-- Breadcrumb & Header Hero -->
        <div class="space-y-3">
            <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                <a href="{{ route('home') }}" class="hover:text-brand-800 dark:hover:text-brand-400">{{ __('Home') }}</a>
                <span>&rsaquo;</span>
                <span class="text-slate-900 dark:text-white font-bold">{{ __('Photo Gallery') }}</span>
            </nav>

            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-800 dark:text-brand-400 mb-1">
                        <i class="fa-solid fa-camera"></i>
                        <span>{{ __('Moments & Destinations') }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __('Photo Gallery') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                        {{ __('Real moments and sights captured across our destinations and tours with :name.', ['name' => $agent->name]) }}
                    </p>
                </div>
                @if ($photos->isNotEmpty())
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 text-xs font-bold text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-zinc-700/60 self-start sm:self-auto shrink-0 shadow-xs">
                        <i class="fa-solid fa-images text-brand-700 dark:text-brand-400 text-xs"></i>
                        <span>{{ __(':count Photos', ['count' => $photos->count()]) }}</span>
                    </span>
                @endif
            </div>
        </div>

        @if ($photos->isNotEmpty())
            {{-- 3-Column Square Grid with Lightbox --}}
            <div id="gallery" class="space-y-6"
                x-data="{
                    open: null,
                    photos: @js($photos->map(fn ($photo) => ['src' => $galleryService->url($photo), 'caption' => (string) $photo->caption])->values()),
                    prev() { if (this.open > 0) this.open--; },
                    next() { if (this.open < this.photos.length - 1) this.open++; }
                }"
                x-on:keydown.arrow-left.window="if (open !== null) prev()"
                x-on:keydown.arrow-right.window="if (open !== null) next()"
                x-on:keydown.escape.window="open = null">

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 lg:gap-5">
                    @foreach ($photos as $index => $photo)
                        <button type="button" x-on:click="open = {{ $index }}"
                            class="group relative aspect-square overflow-hidden rounded-2xl sm:rounded-3xl bg-slate-100 dark:bg-zinc-800 border border-slate-200/70 dark:border-zinc-800 shadow-xs hover:shadow-xl hover:border-brand-400 dark:hover:border-brand-500 transition-all duration-300 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                            aria-label="{{ __('View photo :number', ['number' => $index + 1]) }}">
                            <img src="{{ $galleryService->url($photo) }}" alt="{{ $photo->caption ?: $agent->name }}" loading="lazy" decoding="async"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />

                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-between p-3 sm:p-4 pointer-events-none">
                                <div class="flex justify-end">
                                    <span class="w-8 h-8 rounded-full bg-white/90 dark:bg-zinc-900/90 text-slate-800 dark:text-white flex items-center justify-center text-xs shadow-md backdrop-blur-md">
                                        <i class="fa-solid fa-expand"></i>
                                    </span>
                                </div>
                                @if ($photo->caption)
                                    <p class="text-xs text-white/95 font-medium line-clamp-1 drop-shadow-sm text-left">
                                        {{ $photo->caption }}
                                    </p>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>

                {{-- Lightbox Modal --}}
                <div x-show="open !== null" x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    x-on:click.self="open = null"
                    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-950/90 backdrop-blur-md select-none"
                    role="dialog" aria-modal="true" aria-label="{{ __('Photo preview') }}">

                    <div class="absolute top-4 left-4 right-4 flex items-center justify-between pointer-events-none z-10">
                        <span class="px-3.5 py-1.5 rounded-full bg-white/10 text-white text-xs font-bold backdrop-blur-md border border-white/10 shadow-sm">
                            <span x-text="open + 1"></span> / <span x-text="photos.length"></span>
                        </span>
                        <button type="button" x-on:click="open = null"
                            class="pointer-events-auto w-10 h-10 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/30 text-white border border-white/15 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer shadow-lg hover:scale-105"
                            aria-label="{{ __('Close') }}">
                            <i class="fa-solid fa-xmark text-base"></i>
                        </button>
                    </div>

                    <button type="button" x-show="open > 0" x-on:click="prev()"
                        class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 z-10 w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/30 text-white border border-white/15 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer shadow-xl hover:scale-105"
                        aria-label="{{ __('Previous photo') }}">
                        <i class="fa-solid fa-chevron-left text-sm sm:text-base"></i>
                    </button>

                    <button type="button" x-show="open < photos.length - 1" x-on:click="next()"
                        class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 z-10 w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/30 text-white border border-white/15 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer shadow-xl hover:scale-105"
                        aria-label="{{ __('Next photo') }}">
                        <i class="fa-solid fa-chevron-right text-sm sm:text-base"></i>
                    </button>

                    <template x-if="open !== null">
                        <figure class="max-h-full max-w-5xl flex flex-col items-center justify-center my-auto p-2" x-on:click.outside="open = null">
                            <img :src="photos[open].src" :alt="photos[open].caption || {{ \Illuminate\Support\Js::from($agent->name) }}"
                                class="max-h-[80vh] w-auto max-w-full rounded-2xl object-contain shadow-2xl border border-white/10" />
                            <figcaption x-show="photos[open].caption" x-text="photos[open].caption"
                                class="mt-3 text-center text-xs sm:text-sm text-white/90 bg-black/50 backdrop-blur-sm px-4 py-1.5 rounded-full max-w-2xl">
                            </figcaption>
                        </figure>
                    </template>
                </div>
            </div>
        @else
            <div class="p-8 sm:p-12 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 text-center space-y-4 max-w-xl mx-auto shadow-xs">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-slate-500 mx-auto flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-camera"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('No photos yet') }}</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('We are currently updating our photo collection. Explore our trips and packages below.') }}
                    </p>
                </div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-brand-foreground text-xs font-bold transition shadow-xs">
                    <i class="fa-solid fa-compass text-xs"></i>
                    <span>{{ __('Explore Tours') }}</span>
                </a>
            </div>
        @endif
    </main>

    @include('storefront.partials.footer')
    @livewireScripts
</body>

</html>
