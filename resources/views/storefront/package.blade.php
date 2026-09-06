<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
    <!-- SEO & Metadata -->
    <title>{{ $package->title }} &bull; {{ $agent->name }}@if ($agent->showsPlatformBranding()) &bull; {{ config('app.name') }}@endif</title>
    <meta name="description"
        content="{{ Str::limit($package->description ?: __('Book :title with :agent. Official direct reservations with instant confirmation and locked rate.', ['title' => $package->title, 'agent' => $agent->name]), 160) }}" />
    <link rel="canonical" href="{{ route('storefront.package', $package->slug) }}" />

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

    <!-- OpenGraph & WhatsApp Social Share Cards -->
    @php
        $ogImageUrl = $package->cover_photo
            ? (Str::startsWith($package->cover_photo, ['http://', 'https://'])
                ? $package->cover_photo
                : url(Storage::url($package->cover_photo)))
            : ($agent->logo
                ? (Str::startsWith($agent->logo, ['http://', 'https://'])
                    ? $agent->logo
                    : url(Storage::url($agent->logo)))
                : url('/favicon.png'));
        $ogDescription = Str::limit(
            $package->description ?:
            __('Book :title with :agent. Official online direct booking with instant confirmation.', [
                'title' => $package->title,
                'agent' => $agent->name,
            ]),
            160,
        );
    @endphp
    <meta property="og:type" content="product" />
    <meta property="og:url" content="{{ route('storefront.package', $package->slug) }}" />
    <meta property="og:title" content="{{ $package->title }} — {{ $agent->name }}" />
    <meta property="og:description" content="{{ $ogDescription }}" />
    <meta property="og:site_name" content="{{ $agent->storefrontSiteName() }}" />
    <meta property="og:image" content="{{ $ogImageUrl }}" />
    <meta property="og:image:secure_url" content="{{ $ogImageUrl }}" />
    <meta property="og:image:alt" content="{{ $package->title }}" />
    <meta property="product:price:amount" content="{{ $package->price }}" />
    <meta property="product:price:currency" content="IDR" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $package->title }} — {{ $agent->name }}" />
    <meta name="twitter:description" content="{{ $ogDescription }}" />
    <meta name="twitter:image" content="{{ $ogImageUrl }}" />

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
                            'name' => __('Tour Packages'),
                            'item' => route('storefront.packages'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $package->title,
                            'item' => route('storefront.package', $package->slug),
                        ],
                    ],
                ],
                [
                    '@type' => 'TouristTrip',
                    '@id' => route('storefront.package', $package->slug) . '#trip',
                    'name' => $package->title,
                    'description' => $package->description ?? '',
                    'touristType' => 'Adventure',
                    'touristAttraction' => $package->location
                        ? [
                            '@type' => 'TouristAttraction',
                            'name' => $package->location,
                        ]
                        : null,
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => (float) $package->price,
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

<body x-data="{ mobileBookingOpen: false, mobileMenuOpen: false }"
    class="min-h-screen flex flex-col bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-brand-foreground overflow-x-clip w-full max-w-full">
    @include('storefront.partials.navbar', ['bookAction' => true])

    <!-- Main Package Content -->
    <main class="flex-1 w-full max-w-6xl mx-auto px-3 sm:px-6 py-4 sm:py-8 pb-16 lg:pb-12 space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <!-- Left Details (Mobile-First 2 cols) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Package Cover & Gallery Photos -->
                @if ($package->cover_photo_url || !empty($package->gallery))
                    <div
                        class="overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2 p-2">
                        @if ($package->cover_photo_url)
                            <div
                                class="aspect-video sm:aspect-21/9 w-full rounded-2xl overflow-hidden bg-slate-100 dark:bg-zinc-800">
                                <img src="{{ $package->cover_photo_url }}" alt="{{ $package->title }}"
                                    fetchpriority="high" decoding="async"
                                    class="w-full h-full object-cover" />
                            </div>
                        @endif
                        @if (!empty($package->gallery_urls))
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 pt-1">
                                @foreach ($package->gallery_urls as $gUrl)
                                    <div
                                        class="aspect-video rounded-xl overflow-hidden bg-slate-100 dark:bg-zinc-800 border border-slate-200/60 dark:border-zinc-700">
                                        <img src="{{ $gUrl }}" alt="{{ $package->title }}"
                                            loading="lazy" decoding="async"
                                            class="w-full h-full object-cover hover:scale-105 transition-transform" />
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Title & Meta Highlights -->
                <div
                    class="p-5 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="px-2.5 py-1 rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-wider bg-brand-50 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300">
                            {{ $package->category ?? __('Tour Package') }}
                        </span>
                        @if ($package->destination)
                            <span
                                class="px-2.5 py-1 rounded-xl text-[10px] sm:text-xs font-bold bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                <i class="fa-solid fa-location-dot text-slate-400"></i>
                                {{ $package->destination }}
                            </span>
                        @endif
                    </div>

                    <h1
                        class="text-xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-tight">
                        {{ $package->title }}
                    </h1>

                    <!-- Key Trip Metadata Highlights -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-2">
                        @if ($package->duration_days)
                            <div
                                class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 flex items-center gap-3">
                                <span
                                    class="w-8 h-8 rounded-xl bg-brand-100 dark:bg-brand-950/80 text-brand-600 dark:text-brand-400 flex items-center justify-center text-xs shrink-0">
                                    <i class="fa-solid fa-clock"></i>
                                </span>
                                <div class="min-w-0">
                                    <span
                                        class="text-[10px] text-slate-400 uppercase font-bold block leading-none">{{ __('Duration') }}</span>
                                    <span
                                        class="text-xs font-black text-slate-900 dark:text-white truncate mt-0.5 block">{{ $package->duration_days }}
                                        {{ __('Days') }}</span>
                                </div>
                            </div>
                        @endif

                        @if ($package->min_pax || $package->max_pax)
                            <div
                                class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 flex items-center gap-3">
                                <span
                                    class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-950/80 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xs shrink-0">
                                    <i class="fa-solid fa-users"></i>
                                </span>
                                <div class="min-w-0">
                                    <span
                                        class="text-[10px] text-slate-400 uppercase font-bold block leading-none">{{ __('Group Size') }}</span>
                                    <span
                                        class="text-xs font-black text-slate-900 dark:text-white truncate mt-0.5 block">{{ $package->min_pax ?: 1 }}
                                        - {{ $package->max_pax ?: '∞' }} {{ __('Pax') }}</span>
                                </div>
                            </div>
                        @endif

                        <div
                            class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 flex items-center gap-3 col-span-2 sm:col-span-1">
                            <span
                                class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs shrink-0">
                                <i class="fa-solid fa-shield"></i>
                            </span>
                            <div class="min-w-0">
                                <span
                                    class="text-[10px] text-slate-400 uppercase font-bold block leading-none">{{ __('Cancellation') }}</span>
                                <span
                                    class="text-xs font-black text-slate-900 dark:text-white truncate mt-0.5 block">{{ __('Free up to :hours hrs', ['hours' => $package->free_cancellation_hours ?? 24]) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overview / Description -->
                @if ($package->description)
                    <div
                        class="p-5 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-3">
                        <h2
                            class="font-black text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-circle-info text-brand-500"></i>
                            {{ __('Package Overview') }}
                        </h2>
                        <div
                            class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                            {{ $package->description }}
                        </div>
                    </div>
                @endif

                <!-- Inclusions & Exclusions -->
                @if (!empty($package->inclusions) || !empty($package->exclusions))
                    <div
                        class="p-5 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs grid grid-cols-1 sm:grid-cols-2 gap-6">
                        @if (!empty($package->inclusions))
                            <div class="space-y-3">
                                <h4
                                    class="font-black text-xs uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check"></i>
                                    {{ __('What is Included') }}
                                </h4>
                                <ul class="space-y-2 text-xs text-slate-700 dark:text-slate-300">
                                    @foreach ($package->inclusions as $inc)
                                        <li class="flex items-start gap-2.5">
                                            <i class="fa-solid fa-check text-emerald-500 text-xs mt-0.5"></i>
                                            <span class="leading-relaxed">{{ $inc }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (!empty($package->exclusions))
                            <div
                                class="space-y-3 pt-4 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-zinc-800">
                                <h4
                                    class="font-black text-xs uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                    {{ __('Not Included') }}
                                </h4>
                                <ul class="space-y-2 text-xs text-slate-700 dark:text-slate-300">
                                    @foreach ($package->exclusions as $exc)
                                        <li class="flex items-start gap-2.5">
                                            <i class="fa-solid fa-xmark text-rose-400 text-xs mt-0.5"></i>
                                            <span class="leading-relaxed">{{ $exc }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Schedule / Timeline -->
                @if ($package->itinerary_text)
                    <div
                        class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                        <h3
                            class="font-black text-sm sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-map-location-dot text-brand-500"></i>
                            {{ __('Schedule & Itinerary') }}
                        </h3>
                        <div
                            class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line leading-relaxed pl-2 border-l-2 border-brand-200 dark:border-brand-900/60">
                            {{ $package->itinerary_text }}
                        </div>
                    </div>
                @endif

                <!-- Policy & Terms -->
                @if ($agent->terms_and_conditions)
                    <div
                        class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2.5">
                        <h3
                            class="font-bold text-xs uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-file-contract text-brand-500"></i>
                            {{ __('Booking & Cancellation Policy') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed whitespace-pre-line">
                            {{ $agent->terms_and_conditions }}
                        </p>
                    </div>
                @endif
            </div>

            <!-- Right Desktop Sticky Booking Box (Hidden on mobile) -->
            <div class="hidden lg:block space-y-6">
                <div class="sticky top-24">
                    <livewire:storefront.booking-box :bookable="$package" :agent="$agent" :key="'desktop-booking-box-' . $package->id" />
                </div>
            </div>
        </div>
    </main>

    @include('storefront.partials.footer')

    <!-- Sticky Mobile Bottom Booking Bar (Airbnb/Klook UX) -->
    <div
        class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-t border-slate-200/80 dark:border-zinc-800 p-3 sm:p-4 shadow-xl select-none flex items-center justify-between gap-3">
        <div class="min-w-0">
            <span
                class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block leading-none">{{ __('Price per person') }}</span>
            <div class="text-base sm:text-lg font-black text-slate-900 dark:text-white truncate mt-0.5">
                Rp {{ number_format((float) $package->price, 0, ',', '.') }}
            </div>
        </div>

        <button type="button" @click="mobileBookingOpen = true"
            class="h-11 px-5 inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-brand-foreground font-black text-xs sm:text-sm shadow-md shadow-brand-500/25 transition cursor-pointer shrink-0">
            <i class="fa-solid fa-calendar-check text-xs"></i>
            <span>{{ __('Book Now') }}</span>
        </button>
    </div>

    <!-- Mobile Slide-over Bottom Sheet Booking Modal -->
    <div x-show="mobileBookingOpen" x-cloak x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/70 backdrop-blur-xs lg:hidden">
        <div x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="translate-y-full sm:scale-95"
            x-transition:enter-end="translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="translate-y-0 sm:scale-100"
            x-transition:leave-end="translate-y-full sm:scale-95"
            class="w-full max-w-lg rounded-t-3xl sm:rounded-3xl bg-white dark:bg-zinc-900 p-4 sm:p-6 max-h-[92vh] overflow-y-auto space-y-4 shadow-2xl border border-slate-200 dark:border-zinc-800">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                <div>
                    <span
                        class="text-[10px] uppercase font-bold text-slate-400 block leading-none">{{ __('Instant Hold') }}</span>
                    <h3 class="font-black text-base text-slate-900 dark:text-white mt-0.5">
                        {{ __('Select Date & Guests') }}</h3>
                </div>
                <button type="button" @click="mobileBookingOpen = false"
                    class="w-8 h-8 rounded-full bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-500 hover:text-slate-700 dark:hover:text-white transition cursor-pointer"
                    title="{{ __('Close') }}">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <livewire:storefront.booking-box :bookable="$package" :agent="$agent" :key="'mobile-booking-box-' . $package->id" />
        </div>
    </div>

    @livewireScripts
</body>

</html>
