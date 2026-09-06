<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
        <!-- SEO & Metadata -->
        <title>{{ $product->name }} &bull; {{ $agent->name }}@if ($agent->showsPlatformBranding()) &bull; {{ config('app.name') }}@endif</title>
        <meta name="description" content="{{ Str::limit($product->description ?: __('Book :name with :agent. Official direct reservations with instant confirmation and locked rate.', ['name' => $product->name, 'agent' => $agent->name]), 160) }}" />
        <link rel="canonical" href="{{ route('storefront.product', $product->slug) }}" />

        <!-- Search Engine & AI Agent Discovery -->
        <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
        <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
        <meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
        @if ($agent->showsPlatformBranding())
            <meta name="generator" content="{{ config('app.name') }} — Direct Booking Engine for Tour Operators" />
        @endif
        <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}" />
        <link rel="alternate" type="text/plain" href="{{ url('/llms.txt') }}" title="LLMs Text Summary" />
        <!-- Favicon & Brand Icons -->
        <link rel="icon" href="{{ $agent->logo_url }}" />
        <link rel="apple-touch-icon" href="{{ $agent->logo_url }}" />

        <!-- OpenGraph -->
        <meta property="og:type" content="product" />
        <meta property="og:url" content="{{ route('storefront.product', $product->slug) }}" />
        <meta property="og:title" content="{{ $product->name }} &bull; {{ $agent->name }}" />
        <meta property="og:description" content="{{ Str::limit($product->description ?: __('Book :name with :agent.', ['name' => $product->name, 'agent' => $agent->name]), 160) }}" />
        <meta property="og:site_name" content="{{ $agent->storefrontSiteName() }}" />
        @if ($product->cover_photo)
            <meta property="og:image" content="{{ Storage::url($product->cover_photo) }}" />
        @elseif ($agent->logo)
            <meta property="og:image" content="{{ Storage::url($agent->logo) }}" />
        @endif

        <!-- Twitter -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $product->name }} &bull; {{ $agent->name }}" />
        <meta name="twitter:description" content="{{ Str::limit($product->description ?: __('Book :name with :agent.', ['name' => $product->name, 'agent' => $agent->name]), 160) }}" />
        @if ($product->cover_photo)
            <meta name="twitter:image" content="{{ Storage::url($product->cover_photo) }}" />
        @elseif ($agent->logo)
            <meta name="twitter:image" content="{{ Storage::url($agent->logo) }}" />
        @endif

        <!-- Schema.org JSON-LD Structured Data -->
        @php
            $schemaData = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            [
                                '@type' => 'ListItem',
                                'position' => 1,
                                'name' => __('Home'),
                                'item' => route('home'),
                            ],
                            [
                                '@type' => 'ListItem',
                                'position' => 2,
                                'name' => __('Single Activities'),
                                'item' => route('storefront.products'),
                            ],
                            [
                                '@type' => 'ListItem',
                                'position' => 3,
                                'name' => $product->name,
                                'item' => route('storefront.product', $product->slug),
                            ],
                        ],
                    ],
                    [
                        '@type' => 'Product',
                        '@id' => route('storefront.product', $product->slug) . '#product',
                        'name' => $product->name,
                        'description' => $product->description ?? '',
                        'category' => $product->category ?? 'Service',
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => (float) $product->price,
                            'priceCurrency' => 'IDR',
                            'availability' => 'https://schema.org/InStock',
                            'validFrom' => now()->toIso8601String(),
                            'seller' => [
                                '@type' => 'TravelAgency',
                                'name' => $agent->name,
                            ],
                        ],
                    ],
                ],
            ];
        @endphp
        <script type="application/ld+json">
        {!! json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>

        @include('storefront.partials.brand-theme')

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('storefront.partials.tracking-scripts', ['agent' => $agent])
        @livewireStyles
    </head>
    <body
        x-data="{ mobileBookingOpen: false, mobileMenuOpen: false }"
        class="min-h-screen flex flex-col bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-brand-foreground overflow-x-clip w-full max-w-full"
    >
        @include('storefront.partials.navbar', ['bookAction' => true])

        <!-- Main Product Content -->
        <main class="flex-1 w-full max-w-6xl mx-auto px-3 sm:px-6 py-4 sm:py-8 pb-16 lg:pb-12 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Left Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Product Cover & Gallery Photos -->
                    @if ($product->cover_photo_url || !empty($product->gallery))
                        <div class="overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2 p-2">
                            @if ($product->cover_photo_url)
                                <div class="aspect-video sm:aspect-21/9 w-full rounded-2xl overflow-hidden bg-slate-100 dark:bg-zinc-800">
                                    <img src="{{ $product->cover_photo_url }}" alt="{{ $product->name }}" fetchpriority="high" decoding="async" class="w-full h-full object-cover" />
                                </div>
                            @endif
                            @if (!empty($product->gallery_urls))
                                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 pt-1">
                                    @foreach ($product->gallery_urls as $gUrl)
                                        <div class="aspect-video rounded-xl overflow-hidden bg-slate-100 dark:bg-zinc-800 border border-slate-200/60 dark:border-zinc-700">
                                            <img src="{{ $gUrl }}" alt="{{ $product->name }}" loading="lazy" decoding="async" class="w-full h-full object-cover hover:scale-105 transition-transform" />
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="p-5 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                        @if ($product->location)
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 text-xs font-bold">
                                <i class="fa-solid fa-location-dot text-[11px]"></i>
                                <span>{{ $product->location }}</span>
                            </div>
                        @endif

                        <h1 class="text-xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                            {{ $product->name }}
                        </h1>

                        @if ($product->description)
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ $product->description }}
                            </p>
                        @endif

                        @if ($product->capacity_per_day)
                            <div class="pt-2 flex items-center gap-2 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                <i class="fa-solid fa-circle-check text-xs"></i>
                                <span>{{ __(':count units daily capacity verified', ['count' => $product->capacity_per_day]) }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Inclusions -->
                    @if (!empty($product->inclusions))
                        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-3">
                            <h4 class="font-black text-xs uppercase tracking-wider text-slate-900 dark:text-white">{{ __('Included in this service') }}</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($product->inclusions as $inc)
                                    <span class="text-xs px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 font-semibold border border-slate-100 dark:border-zinc-700/60">
                                        &check; {{ $inc }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($agent->terms_and_conditions)
                        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
                            <h3 class="font-bold text-xs uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                                <i class="fa-solid fa-file-contract text-brand-500"></i>
                                {{ __('Booking Policy & Terms') }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed whitespace-pre-line">
                                {{ $agent->terms_and_conditions }}
                            </p>
                        </div>
                    @endif
                </div>

                <!-- Right Desktop Sticky Booking Box -->
                <div class="hidden lg:block space-y-6">
                    <div class="sticky top-24">
                        <livewire:storefront.booking-box :bookable="$product" :agent="$agent" :key="'desktop-booking-box-' . $product->id" />
                    </div>
                </div>
            </div>
        </main>

        @include('storefront.partials.footer')

        <!-- Sticky Mobile Bottom Booking Bar -->
        <div class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-t border-slate-200/80 dark:border-zinc-800 p-3 sm:p-4 shadow-xl select-none flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block leading-none">{{ __('Price per unit') }}</span>
                <div class="text-base sm:text-lg font-black text-slate-900 dark:text-white truncate mt-0.5">
                    Rp {{ number_format((float) $product->price, 0, ',', '.') }}
                </div>
            </div>

            <button
                type="button"
                @click="mobileBookingOpen = true"
                class="h-11 px-5 inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-brand-foreground font-black text-xs sm:text-sm shadow-md shadow-brand-500/25 transition cursor-pointer shrink-0"
            >
                <i class="fa-solid fa-calendar-check text-xs"></i>
                <span>{{ __('Book Now') }}</span>
            </button>
        </div>

        <!-- Mobile Slide-over Bottom Sheet Booking Modal -->
        <div
            x-show="mobileBookingOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/70 backdrop-blur-xs lg:hidden"
        >
            <div
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="translate-y-full sm:scale-95"
                x-transition:enter-end="translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="translate-y-0 sm:scale-100"
                x-transition:leave-end="translate-y-full sm:scale-95"
                class="w-full max-w-lg rounded-t-3xl sm:rounded-3xl bg-white dark:bg-zinc-900 p-4 sm:p-6 max-h-[92vh] overflow-y-auto space-y-4 shadow-2xl border border-slate-200 dark:border-zinc-800"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block leading-none">{{ __('Instant Hold') }}</span>
                        <h3 class="font-black text-base text-slate-900 dark:text-white mt-0.5">{{ __('Select Date & Units') }}</h3>
                    </div>
                    <button
                        type="button"
                        @click="mobileBookingOpen = false"
                        class="w-8 h-8 rounded-full bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-500 hover:text-slate-700 dark:hover:text-white transition cursor-pointer"
                        title="{{ __('Close') }}"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <livewire:storefront.booking-box :bookable="$product" :agent="$agent" :key="'mobile-booking-box-' . $product->id" />
            </div>
        </div>

        @livewireScripts
    </body>
</html>
