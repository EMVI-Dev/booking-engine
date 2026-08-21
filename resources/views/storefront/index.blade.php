<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />

    <!-- SEO & Metadata -->
    <title>{{ $agent->name }} &bull; {{ __('Official Direct Tour Bookings') }} &bull; {{ config('app.name') }}</title>
    <meta name="description"
        content="{{ Str::limit($agent->bio ?: __('Book direct tour packages, speedboats, and equipment rentals with :name. Instant holds, transparent pricing, and secure payment.', ['name' => $agent->name]), 160) }}" />
    <link rel="canonical" href="{{ url()->current() }}" />

    <!-- Search Engine & AI Agent Discovery -->
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
    <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
    <meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
    <meta name="generator" content="{{ config('app.name') }} — Direct Booking Engine for Tour Operators" />
    <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}" />
    <link rel="alternate" type="text/plain" href="{{ url('/llms.txt') }}" title="LLMs Text Summary" />

    <!-- Favicon & Brand Icons -->
    @if ($agent->logo)
        <link rel="icon" href="{{ Storage::url($agent->logo) }}" />
        <link rel="apple-touch-icon" href="{{ Storage::url($agent->logo) }}" />
    @endif

    <!-- OpenGraph & WhatsApp Social Share Cards -->
    @php
        $ogImageUrl = $agent->logo
            ? (Str::startsWith($agent->logo, ['http://', 'https://']) ? $agent->logo : url(Storage::url($agent->logo)))
            : url('/favicon.png');
        $ogDescription = Str::limit($agent->bio ?: __('Official online booking portal for :name. Explore tour packages, fast boats, and day trips.', ['name' => $agent->name]), 160);
    @endphp
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:title" content="{{ $agent->name }} — {{ __('Direct Tour Bookings') }}" />
    <meta property="og:description" content="{{ $ogDescription }}" />
    <meta property="og:site_name" content="{{ $agent->name }} • {{ config('app.name') }}" />
    <meta property="og:image" content="{{ $ogImageUrl }}" />
    <meta property="og:image:secure_url" content="{{ $ogImageUrl }}" />
    <meta property="og:image:alt" content="{{ $agent->name }}" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $agent->name }} — {{ __('Direct Tour Bookings') }}" />
    <meta name="twitter:description" content="{{ $ogDescription }}" />
    <meta name="twitter:image" content="{{ $ogImageUrl }}" />

    <!-- Schema.org JSON-LD Structured Data (LocalBusiness / TravelAgency / ItemList) -->
    @php
        $schemaData = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(
                array_filter([
                    [
                        '@type' => 'TravelAgency',
                        '@id' => url('/') . '#agency',
                        'name' => $agent->name,
                        'description' => $agent->bio ?: 'Official direct tour and marine activities operator.',
                        'url' => url('/'),
                        'telephone' => $agent->contact_whatsapp ?: null,
                        'priceRange' => 'IDR',
                        'areaServed' => 'Indonesia',
                    ],
                    [
                        '@type' => 'ItemList',
                        '@id' => url('/') . '#catalog',
                        'name' => $agent->name . ' Featured Experiences',
                        'itemListElement' => $packages
                            ->take(5)
                            ->values()
                            ->map(
                                fn($p, $idx) => [
                                    '@type' => 'ListItem',
                                    'position' => $idx + 1,
                                    'item' => [
                                        '@type' => 'TouristTrip',
                                        'name' => $p->title,
                                        'description' => Str::limit($p->description ?? '', 120),
                                        'url' => route('storefront.package', $p->slug),
                                        'offers' => [
                                            '@type' => 'Offer',
                                            'price' => (float) $p->price,
                                            'priceCurrency' => 'IDR',
                                            'availability' => 'https://schema.org/InStock',
                                            'validFrom' => now()->toIso8601String(),
                                        ],
                                    ],
                                ],
                            )
                            ->all(),
                    ],
                    [
                        '@type' => 'WebApplication',
                        '@id' => config('app.url') . '#platform',
                        'name' => config('app.name'),
                        'description' => 'Direct booking engine platform for tour operators. Create your storefront and accept online reservations with instant confirmation.',
                        'url' => config('app.url'),
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web',
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => '0',
                            'priceCurrency' => 'USD',
                            'description' => 'Free storefront for tour operators',
                        ],
                    ],
                ]),
            ),
        ];
    @endphp
    <script type="application/ld+json">
    {!! json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    <!-- Automated System Dark / Light Theme Sync -->
    <script>
        (function() {
            function applySystemTheme() {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
            applySystemTheme();
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applySystemTheme);
            }
        })();
    </script>

    @if (!empty($agent->brand_color))
        <style>
            :root {
                --brand-color: {{ $agent->brand_color }};
            }
        </style>
    @endif

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('storefront.partials.tracking-scripts', ['agent' => $agent])
    @livewireStyles
</head>

<body x-data="{ activeTab: 'all', mobileMenuOpen: false }"
    class="min-h-screen flex flex-col bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-white">
    <!-- Ambient Top Glow -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
        <div
            class="absolute -top-32 left-1/2 -translate-x-1/2 w-full max-w-5xl h-[500px] bg-gradient-to-b from-brand-500/15 via-sky-500/10 to-transparent rounded-full blur-3xl dark:from-brand-600/20 dark:via-sky-500/10">
        </div>
    </div>

    <!-- Sticky Header Navigation -->
    <header
        class="sticky top-0 z-40 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl border-b border-slate-200/80 dark:border-zinc-800 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <!-- Brand Avatar & Title -->
            <a href="{{ route('home') }}" class="flex items-center gap-3 min-w-0 group">
                @if ($agent->logo_path)
                    <img src="{{ Storage::url($agent->logo_path) }}" alt="{{ $agent->name }}"
                        class="h-10 w-10 rounded-2xl object-cover border border-slate-200/80 dark:border-zinc-800 shadow-xs shrink-0 group-hover:scale-105 transition-transform bg-white dark:bg-zinc-800" />
                @else
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 text-white font-black text-base shadow-sm shrink-0 group-hover:scale-105 transition-transform">
                        {{ strtoupper(substr($agent->name, 0, 1)) }}
                    </span>
                @endif
                <div class="flex flex-col min-w-0">
                    <span
                        class="font-black text-base tracking-tight text-slate-900 dark:text-white truncate group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                        {{ $agent->name }}
                    </span>
                    <div
                        class="flex items-center gap-1.5 text-[10px] text-emerald-600 dark:text-emerald-400 font-bold leading-none">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ __('Verified Operator') }}
                    </div>
                </div>
            </a>

            <!-- Desktop Nav Links & Actions -->
            <nav class="flex items-center gap-2 sm:gap-4">
                <div class="hidden md:flex items-center gap-1 text-xs font-bold text-slate-600 dark:text-slate-400">
                    <button type="button" @click="activeTab = 'all'"
                        class="px-3 py-1.5 rounded-lg hover:text-brand-600 dark:hover:text-white transition cursor-pointer"
                        :class="activeTab === 'all' ? 'text-brand-600 dark:text-white font-black' : ''">
                        {{ __('All') }}
                    </button>
                    @if ($packages->isNotEmpty())
                        <a href="{{ route('storefront.packages') }}"
                            class="px-3 py-1.5 rounded-lg hover:text-brand-600 dark:hover:text-white transition">
                            {{ __('All Packages') }}
                        </a>
                    @endif
                    @if ($standaloneProducts->isNotEmpty())
                        <a href="{{ route('storefront.products') }}"
                            class="px-3 py-1.5 rounded-lg hover:text-brand-600 dark:hover:text-white transition">
                            {{ __('Activities & Rentals') }}
                        </a>
                    @endif
                    @if ($reviews->isNotEmpty())
                        <button type="button" @click="activeTab = 'reviews'"
                            class="px-3 py-1.5 rounded-lg hover:text-brand-600 dark:hover:text-white transition cursor-pointer"
                            :class="activeTab === 'reviews' ? 'text-brand-600 dark:text-white font-black' : ''">
                            {{ __('Reviews') }}
                        </button>
                    @endif
                </div>

                <a href="{{ route('storefront.terms') }}"
                    class="hidden sm:inline-flex h-9 px-3.5 items-center gap-1.5 rounded-xl border border-slate-200/80 dark:border-zinc-800 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 hover:bg-slate-50 dark:hover:bg-zinc-800/60 transition">
                    <i class="fa-solid fa-file-contract text-xs"></i>
                    <span>{{ __('Terms & Policies') }}</span>
                </a>

                <!-- Mobile-Only Header WhatsApp Icon -->
                @if ($agent->contact_whatsapp)
                    @php
                        $waNumber = preg_replace('/[^0-9]/', '', $agent->contact_whatsapp);
                        if (str_starts_with($waNumber, '0')) {
                            $waNumber = '62' . substr($waNumber, 1);
                        }
                    @endphp
                    <a href="https://wa.me/{{ $waNumber }}?text={{ urlencode('Hello ' . $agent->name . ', I am browsing your storefront and have an inquiry.') }}"
                        target="_blank"
                        class="md:hidden h-9 w-9 inline-flex items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition"
                        title="{{ __('Chat on WhatsApp') }}">
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                    </a>
                @endif

                <!-- Mobile Hamburger Button -->
                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden h-9 w-9 inline-flex items-center justify-center rounded-xl border border-slate-200/80 dark:border-zinc-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-zinc-800 transition cursor-pointer"
                    aria-label="Toggle navigation menu">
                    <i class="fa-solid text-sm" :class="mobileMenuOpen ? 'fa-xmark' : 'fa-bars'"></i>
                </button>
            </nav>
        </div>

        <!-- Mobile Navigation Dropdown Menu -->
        <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2" @click.away="mobileMenuOpen = false"
            class="md:hidden border-b border-slate-200/80 dark:border-zinc-800 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl px-4 py-3 space-y-1 shadow-xl">
            <a href="{{ route('home') }}"
                class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                <i class="fa-solid fa-store w-4 text-slate-400"></i>
                <span>{{ __('Home Storefront') }}</span>
            </a>
            @if ($packages->isNotEmpty())
                <a href="{{ route('storefront.packages') }}"
                    class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-cubes w-4 text-slate-400"></i>
                    <span>{{ __('All Tour Packages') }} ({{ $packages->count() }})</span>
                </a>
            @endif
            @if ($standaloneProducts->isNotEmpty())
                <a href="{{ route('storefront.products') }}"
                    class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-box-open w-4 text-slate-400"></i>
                    <span>{{ __('Activities & Rentals') }} ({{ $standaloneProducts->count() }})</span>
                </a>
            @endif
            <a href="{{ route('storefront.terms') }}"
                class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                <i class="fa-solid fa-file-contract w-4 text-slate-400"></i>
                <span>{{ __('Terms & Policies') }}</span>
            </a>
        </div>
    </header>

    <!-- Hero Section (Responsive 2-Column Banner on Desktop) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 pb-4">
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-brand-950 to-slate-900 p-6 sm:p-10 lg:p-12 text-white shadow-2xl border border-slate-800/60">
            <div
                class="absolute right-0 top-0 -mt-20 -mr-20 w-96 h-96 rounded-full bg-brand-500/20 blur-3xl pointer-events-none">
            </div>

            @php
                $storefrontSettings = $agent->settings['storefront'] ?? [];
                $heroHeadline = filled($storefrontSettings['hero_headline'] ?? null)
                    ? $storefrontSettings['hero_headline']
                    : $agent->name;
                $heroTagline = filled($storefrontSettings['hero_tagline'] ?? null)
                    ? $storefrontSettings['hero_tagline']
                    : ($agent->bio ?:
                    __(
                        'Explore our curated packages and standalone activities. Book online with instant confirmation and transparent pricing.',
                    ));
            @endphp
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <!-- Left: Hero Headline & Description -->
                <div class="lg:col-span-7 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-white/10 backdrop-blur-md border border-white/15 text-brand-200">
                        <i class="fa-solid fa-certificate text-sky-400 text-xs"></i>
                        {{ $agent->name }} &bull; {{ __('Official Direct Booking Portal') }}
                    </div>

                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight text-white">
                        {{ $heroHeadline }}
                    </h1>

                    <p class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-2xl">
                        {{ $heroTagline }}
                    </p>

                    <!-- Highlights Pill Row -->
                    <div class="pt-2 flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-300">
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-white/5 border border-white/10">
                            <i class="fa-solid fa-clock text-sky-400 text-xs"></i>
                            {{ __('Instant Reservation') }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-white/5 border border-white/10">
                            <i class="fa-solid fa-wallet text-emerald-400 text-xs"></i>
                            {{ __('Payment Protected') }}
                        </span>
                    </div>
                </div>

                <!-- Right: Trust Pillars Card -->
                <div class="lg:col-span-5">
                    <div
                        class="p-5 sm:p-6 rounded-2xl sm:rounded-3xl bg-white/5 backdrop-blur-md border border-white/10 space-y-3.5 shadow-xl">
                        <h3 class="font-bold text-xs uppercase tracking-wider text-slate-400 flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-400"></i>
                            {{ __('Why Book Direct With Us') }}
                        </h3>

                        <div class="space-y-2.5">
                            <div class="p-3 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-shield-halved text-base"></i>
                                </span>
                                <div>
                                    <p class="font-bold text-xs sm:text-sm text-white">
                                        {{ __('100% Secure Checkout') }}
                                    </p>
                                    <p class="text-[11px] text-slate-400">
                                        {{ __('Automated settlement via Central Payment Gateway') }}</p>
                                </div>
                            </div>

                            <div class="p-3 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-bolt text-base"></i>
                                </span>
                                <div>
                                    <p class="font-bold text-xs sm:text-sm text-white">
                                        {{ __('Instant 30-Min Locked Rate') }}</p>
                                    <p class="text-[11px] text-slate-400">
                                        {{ __('Hold your departure slots immediately before completing payment') }}</p>
                                </div>
                            </div>

                            <div class="p-3 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-rotate-left text-base"></i>
                                </span>
                                <div>
                                    <p class="font-bold text-xs sm:text-sm text-white">
                                        {{ __('Free Cancellation Window') }}</p>
                                    <p class="text-[11px] text-slate-400">
                                        {{ __('Full refund if cancelled up to 24h before departure') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Filter Tabs Bar -->
    <div
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 sticky top-16 z-30 bg-slate-50/95 dark:bg-zinc-950/95 backdrop-blur-md pb-2">
        <div class="flex items-center justify-between gap-3 overflow-x-auto pb-1 no-scrollbar">
            <div class="flex items-center gap-2">
                <button type="button" @click="activeTab = 'all'"
                    class="h-10 px-5 rounded-xl text-xs font-bold transition shrink-0 cursor-pointer"
                    :class="activeTab === 'all' ? 'bg-brand-600 text-white shadow-sm' :
                        'bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-zinc-700'">
                    <i class="fa-solid fa-layer-group mr-1.5 text-[11px]"></i>
                    {{ __('Top Featured (:count)', ['count' => min(5, $packages->count()) + min(5, $standaloneProducts->count())]) }}
                </button>

                @if ($packages->isNotEmpty())
                    <button type="button" @click="activeTab = 'packages'"
                        class="h-10 px-5 rounded-xl text-xs font-bold transition shrink-0 cursor-pointer"
                        :class="activeTab === 'packages' ? 'bg-brand-600 text-white shadow-sm' :
                            'bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-zinc-700'">
                        <i class="fa-solid fa-cubes mr-1.5 text-[11px]"></i>
                        {{ __('Packages (:count)', ['count' => $packages->count()]) }}
                    </button>
                @endif

                @if ($standaloneProducts->isNotEmpty())
                    <button type="button" @click="activeTab = 'products'"
                        class="h-10 px-5 rounded-xl text-xs font-bold transition shrink-0 cursor-pointer"
                        :class="activeTab === 'products' ? 'bg-brand-600 text-white shadow-sm' :
                            'bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-zinc-700'">
                        <i class="fa-solid fa-box-open mr-1.5 text-[11px]"></i>
                        {{ __('Services & Rentals (:count)', ['count' => $standaloneProducts->count()]) }}
                    </button>
                @endif

                @if ($reviews->isNotEmpty())
                    <button type="button" @click="activeTab = 'reviews'"
                        class="h-10 px-5 rounded-xl text-xs font-bold transition shrink-0 cursor-pointer"
                        :class="activeTab === 'reviews' ? 'bg-brand-600 text-white shadow-sm' :
                            'bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-zinc-700'">
                        <i class="fa-solid fa-star mr-1.5 text-[11px] text-amber-400"></i>
                        {{ __('Guest Reviews (:count)', ['count' => $reviews->count()]) }}
                    </button>
                @endif
            </div>

            <!-- Fast Link to Full Catalog -->
            @if ($packages->count() > 3 || $standaloneProducts->count() > 3)
                <div class="hidden sm:flex items-center gap-2 shrink-0">
                    <a href="{{ route('storefront.packages') }}"
                        class="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1">
                        <span>{{ __('Browse Full Catalog') }}</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Main Listings Section -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-24 space-y-12">
        <!-- Packages Section (3 on mobile, 5 on desktop) -->
        <section x-show="activeTab === 'all' || activeTab === 'packages'" class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h2
                        class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                        {{ __('Curated Packages & Expeditions') }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Top-rated tour experiences ordered by most booked departures.') }}
                    </p>
                </div>

                @if ($packages->count() > 3)
                    <a href="{{ route('storefront.packages') }}"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-600 dark:text-brand-400 hover:text-brand-500 transition shrink-0">
                        <span>{{ __('See All :count Packages', ['count' => $packages->count()]) }}</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                @endif
            </div>

            @if ($packages->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
                    @foreach ($packages->take(6) as $index => $pkg)
                        <!-- Card: 3 items on mobile, 5 on desktop -->
                        <div
                            class="group flex flex-col justify-between rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs hover:shadow-xl hover:border-brand-400 dark:hover:border-brand-600 transition-all duration-200 overflow-hidden {{ $index >= 3 ? 'hidden sm:flex' : 'flex' }}">
                            <!-- Card Media Header -->
                            <div
                                class="relative aspect-video w-full overflow-hidden bg-gradient-to-br from-brand-950 via-slate-900 to-zinc-900 shrink-0">
                                @if ($pkg->cover_photo)
                                    <img src="{{ Storage::url($pkg->cover_photo) }}" alt="{{ $pkg->title }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-brand-400/30">
                                        <i class="fa-solid fa-mountain-sun text-4xl"></i>
                                    </div>
                                @endif

                                <!-- Floating Badges on Media -->
                                <div
                                    class="absolute top-3 left-3 right-3 flex items-center justify-between gap-2 pointer-events-none">
                                    <span
                                        class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-white/90 dark:bg-zinc-900/90 text-brand-700 dark:text-brand-300 backdrop-blur-md shadow-xs">
                                        {{ $pkg->category ?? __('Package') }}
                                    </span>
                                    @if ($pkg->free_cancellation_hours > 0)
                                        <span
                                            class="text-[10px] font-bold px-2.5 py-1 rounded-lg bg-emerald-500/90 text-white backdrop-blur-md shadow-xs flex items-center gap-1">
                                            <i class="fa-solid fa-rotate-left text-[9px]"></i>
                                            {{ $pkg->free_cancellation_hours }}h Free Cancel
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Card Content -->
                            <div class="p-4 sm:p-6 space-y-3 flex-1 flex flex-col justify-between">
                                <div class="space-y-2">
                                    <div>
                                        <h3
                                            class="font-black text-base sm:text-lg text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors leading-snug">
                                            <a href="{{ route('storefront.package', $pkg->slug) }}"
                                                class="focus:outline-none">
                                                {{ $pkg->title }}
                                            </a>
                                        </h3>
                                        @if ($pkg->location)
                                            <p
                                                class="text-xs font-semibold text-slate-400 flex items-center gap-1.5 mt-1">
                                                <i class="fa-solid fa-location-dot text-brand-500 text-xs"></i>
                                                {{ $pkg->location }}
                                            </p>
                                        @endif
                                    </div>

                                    @if ($pkg->description)
                                        <p
                                            class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 line-clamp-2 leading-relaxed">
                                            {{ $pkg->description }}
                                        </p>
                                    @endif

                                    <!-- Inclusions Chips (Desktop Only) -->
                                    @if (!empty($pkg->inclusions))
                                        <div
                                            class="hidden sm:block pt-2 border-t border-slate-100 dark:border-zinc-800/80 space-y-1.5">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                {{ __('Key Inclusions') }}</p>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach (array_slice($pkg->inclusions, 0, 3) as $inc)
                                                    <span
                                                        class="text-[10px] px-2 py-0.5 rounded-md bg-slate-50 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 font-medium">
                                                        &check; {{ $inc }}
                                                    </span>
                                                @endforeach
                                                @if (count($pkg->inclusions) > 3)
                                                    <span
                                                        class="text-[10px] px-1.5 py-0.5 text-slate-400 font-semibold">
                                                        +{{ count($pkg->inclusions) - 3 }} more
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Card Bottom Row -->
                                <div
                                    class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-3">
                                    <div>
                                        <p
                                            class="text-[9px] uppercase font-bold text-slate-400 dark:text-zinc-400 leading-none">
                                            {{ __('Price / Person') }}</p>
                                        <p
                                            class="text-base sm:text-xl font-black text-slate-900 dark:text-white mt-0.5">
                                            Rp {{ number_format((float) $pkg->price, 0, ',', '.') }}
                                        </p>
                                    </div>

                                    <a href="{{ route('storefront.package', $pkg->slug) }}"
                                        class="h-9 sm:h-10 px-4 sm:px-5 inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white text-xs font-bold shadow-xs hover:shadow-md transition text-center shrink-0 cursor-pointer">
                                        <span>{{ __('Book Now') }}</span>
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- See More Button on Mobile & Desktop -->
                @if ($packages->count() > 3)
                    <div class="text-center pt-2">
                        <a href="{{ route('storefront.packages') }}"
                            class="h-11 px-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:border-brand-400 dark:hover:border-brand-600 text-xs font-bold text-slate-800 dark:text-slate-200 shadow-xs hover:shadow-md transition">
                            <i class="fa-solid fa-cubes text-brand-500"></i>
                            <span>{{ __('Explore All :count Packages', ['count' => $packages->count()]) }}</span>
                            <i class="fa-solid fa-arrow-right text-[10px] ml-1"></i>
                        </a>
                    </div>
                @endif
            @else
                <div
                    class="text-center py-12 px-4 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 space-y-2">
                    <i class="fa-solid fa-cubes text-2xl text-slate-300 dark:text-slate-600"></i>
                    <p class="text-xs text-slate-500">{{ __('No tour packages published yet.') }}</p>
                </div>
            @endif
        </section>

        <!-- Activities & Standalone Products Section (3 on mobile, 5 on desktop) -->
        @if ($standaloneProducts->isNotEmpty())
            <section x-show="activeTab === 'all' || activeTab === 'services'" class="space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                    <div>
                        <h2
                            class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ __('Individual Services & Equipment Rentals') }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            {{ __('Rent standalone gear, reserve fastboat seats, or hire local marine guides directly.') }}
                        </p>
                    </div>

                    @if ($standaloneProducts->count() > 3)
                        <a href="{{ route('storefront.products') }}"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-600 dark:text-brand-400 hover:text-brand-500 transition shrink-0">
                            <span>{{ __('See All :count Items', ['count' => $standaloneProducts->count()]) }}</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    @foreach ($standaloneProducts->take(5) as $index => $prod)
                        <!-- Card: 3 items on mobile, 5 on desktop -->
                        <div
                            class="group flex flex-col justify-between rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs hover:shadow-xl hover:border-brand-400 dark:hover:border-brand-600 transition-all duration-200 overflow-hidden {{ $index >= 3 ? 'hidden sm:flex' : 'flex' }}">
                            <!-- Card Media Header -->
                            <div
                                class="relative aspect-video w-full overflow-hidden bg-gradient-to-br from-slate-900 via-sky-950 to-slate-900 shrink-0">
                                @if ($prod->cover_photo)
                                    <img src="{{ Storage::url($prod->cover_photo) }}" alt="{{ $prod->name }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-sky-400/30">
                                        <i class="fa-solid fa-box-open text-3xl"></i>
                                    </div>
                                @endif

                                <!-- Floating Badges on Media -->
                                <div
                                    class="absolute top-2.5 left-2.5 right-2.5 flex items-center justify-between gap-1.5 pointer-events-none">
                                    <span
                                        class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-white/90 dark:bg-zinc-900/90 text-slate-700 dark:text-slate-300 backdrop-blur-md shadow-xs">
                                        {{ $prod->category ?? __('Service') }}
                                    </span>
                                    @if ($prod->capacity_per_day)
                                        <span
                                            class="text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-500/90 text-white backdrop-blur-md shadow-xs">
                                            {{ $prod->capacity_per_day }}/day
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Card Content -->
                            <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between space-y-3">
                                <div class="space-y-1.5">
                                    <h4
                                        class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white leading-snug group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                                        <a href="{{ route('storefront.product', $prod->slug) }}"
                                            class="focus:outline-none">
                                            {{ $prod->name }}
                                        </a>
                                    </h4>
                                    @if ($prod->location)
                                        <p class="text-[11px] font-semibold text-slate-400 flex items-center gap-1">
                                            <i class="fa-solid fa-location-dot text-brand-500 text-[10px]"></i>
                                            {{ $prod->location }}
                                        </p>
                                    @endif
                                    @if ($prod->description)
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                            {{ $prod->description }}</p>
                                    @endif
                                </div>

                                <div
                                    class="flex items-center justify-between pt-2.5 border-t border-slate-100 dark:border-zinc-800">
                                    <div>
                                        <p class="text-[9px] uppercase font-bold text-slate-400">{{ __('Price') }}
                                        </p>
                                        <p class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">
                                            Rp {{ number_format((float) $prod->price, 0, ',', '.') }}
                                        </p>
                                    </div>
                                    <a href="{{ route('storefront.product', $prod->slug) }}"
                                        class="h-8 px-3.5 inline-flex items-center gap-1 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-xs shadow-xs transition">
                                        <span>{{ __('Book') }}</span>
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- See More Standalone Items Button -->
                @if ($standaloneProducts->count() > 3)
                    <div class="text-center pt-2">
                        <a href="{{ route('storefront.products') }}"
                            class="h-11 px-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:border-brand-400 dark:hover:border-brand-600 text-xs font-bold text-slate-800 dark:text-slate-200 shadow-xs hover:shadow-md transition">
                            <i class="fa-solid fa-box-open text-sky-500"></i>
                            <span>{{ __('Explore All :count Activities & Rentals', ['count' => $standaloneProducts->count()]) }}</span>
                            <i class="fa-solid fa-arrow-right text-[10px] ml-1"></i>
                        </a>
                    </div>
                @endif
            </section>
        @endif

        <!-- Verified Guest Reviews Section -->
        @if ($reviews->isNotEmpty())
            <section x-show="activeTab === 'all' || activeTab === 'reviews'"
                class="space-y-5 pt-6 border-t border-slate-200/80 dark:border-zinc-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2
                            class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-star text-amber-400 text-xl"></i>
                            {{ __('Verified Guest Reviews') }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            {{ __('Authentic feedback from guests who completed reservations with :agent.', ['agent' => $agent->name]) }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    @foreach ($reviews as $rev)
                        <div
                            class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-3.5 flex flex-col justify-between">
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1 text-amber-400 text-xs">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i
                                                class="fa-solid fa-star {{ $i <= $rev->rating ? 'text-amber-400' : 'text-slate-200 dark:text-zinc-700' }}"></i>
                                        @endfor
                                    </div>
                                    <span
                                        class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                        <i class="fa-solid fa-circle-check text-[10px]"></i>
                                        {{ __('Verified Guest') }}
                                    </span>
                                </div>
                                <p
                                    class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed italic">
                                    &ldquo;{{ $rev->comment }}&rdquo;
                                </p>
                            </div>

                            <div
                                class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between text-xs text-slate-400">
                                <span class="font-bold text-slate-700 dark:text-slate-300 truncate max-w-[200px]">
                                    {{ $rev->bookable->name ?? ($rev->bookable->title ?? 'Tour Experience') }}
                                </span>
                                <span>{{ $rev->created_at?->format('M Y') ?? 'Recent' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- About the Operator (bio) Section -->
        @if ($agent->bio)
            <section class="space-y-5 pt-6 border-t border-slate-200/80 dark:border-zinc-800">
                <!-- Operator Card -->
                <div
                    class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-start gap-5">
                    <!-- Logo / Avatar -->
                    <div class="shrink-0">
                        @if ($agent->logo_url)
                            <img src="{{ $agent->logo_url }}" alt="{{ $agent->name }}"
                                class="w-14 h-14 rounded-2xl object-contain border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1 shadow-xs" />
                        @else
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center font-black text-xl text-white shadow-sm"
                                style="background-color: {{ $agent->brand_color }};">
                                {{ strtoupper(substr($agent->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>

                    <!-- Bio Text -->
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-extrabold text-base text-slate-900 dark:text-white">
                                {{ $agent->name }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                                {{ __('Verified Operator') }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            {{ $agent->bio }}
                        </p>

                        @php
                            $socialLinks = $agent->settings['social_links'] ?? [];
                        @endphp
                        @if (collect($socialLinks)->filter()->isNotEmpty() || $agent->contact_whatsapp)
                            <div class="flex flex-wrap items-center gap-3 pt-2">
                                @if ($agent->contact_whatsapp)
                                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $agent->contact_whatsapp) }}"
                                        target="_blank" rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-400 hover:underline">
                                        <i class="fa-brands fa-whatsapp text-sm"></i>
                                        <span>{{ __('WhatsApp') }}</span>
                                    </a>
                                @endif
                                @if (!empty($socialLinks['instagram']))
                                    <a href="{{ $socialLinks['instagram'] }}" target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-pink-600 dark:text-pink-400 hover:underline">
                                        <i class="fa-brands fa-instagram text-sm"></i>
                                        <span>{{ __('Instagram') }}</span>
                                    </a>
                                @endif
                                @if (!empty($socialLinks['facebook']))
                                    <a href="{{ $socialLinks['facebook'] }}" target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
                                        <i class="fa-brands fa-facebook text-sm"></i>
                                        <span>{{ __('Facebook') }}</span>
                                    </a>
                                @endif
                                @if (!empty($socialLinks['website']))
                                    <a href="{{ $socialLinks['website'] }}" target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-400 hover:underline">
                                        <i class="fa-solid fa-globe text-sm"></i>
                                        <span>{{ __('Website') }}</span>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif
    </main>

    <!-- Floating WhatsApp Support Button for Desktop Only -->
    @if ($agent->contact_whatsapp)
        <div class="hidden lg:block fixed bottom-6 right-6 z-50">
            <a href="https://wa.me/{{ $waNumber }}?text={{ urlencode('Hello ' . $agent->name . ', I have a question about your tours.') }}"
                target="_blank"
                class="h-13 px-5 inline-flex items-center gap-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 text-white font-black text-sm shadow-2xl shadow-emerald-500/30 transition-transform hover:scale-105"
                title="{{ __('Direct WhatsApp Chat') }}">
                <i class="fa-brands fa-whatsapp text-xl"></i>
                <span>{{ __('Chat with Us') }}</span>
            </a>
        </div>
    @endif

    @include('storefront.partials.footer')

    @livewireScripts
</body>

</html>
