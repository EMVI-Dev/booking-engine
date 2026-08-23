<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />

    <!-- SEO & Metadata -->
    <title>{{ __('All Tour Packages & Expeditions') }} &bull; {{ $agent->name }} &bull; {{ config('app.name') }}</title>
    <meta name="description" content="{{ __('Browse all all-inclusive tour packages, island expeditions, and day trips offered by :name. Transparent pricing, instant hold reservations, and certified guides.', ['name' => $agent->name]) }}" />
    <link rel="canonical" href="{{ route('storefront.packages') }}" />

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

    <!-- OpenGraph -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ route('storefront.packages') }}" />
    <meta property="og:title" content="{{ __('All Tour Packages & Expeditions') }} &bull; {{ $agent->name }}" />
    <meta property="og:description" content="{{ __('Explore complete tour packages and book directly with :name.', ['name' => $agent->name]) }}" />
    <meta property="og:site_name" content="{{ $agent->name }} • {{ config('app.name') }}" />
    @if ($agent->logo)
        <meta property="og:image" content="{{ Storage::url($agent->logo) }}" />
    @endif

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ __('All Tour Packages') }} &bull; {{ $agent->name }}" />
    <meta name="twitter:description" content="{{ __('Explore complete tour packages and book directly with :name.', ['name' => $agent->name]) }}" />
    @if ($agent->logo)
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
                            'name' => __('Tour Packages'),
                            'item' => route('storefront.packages'),
                        ],
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    'name' => $agent->name . ' - ' . __('All Tour Packages'),
                    'numberOfItems' => $packages->count(),
                    'itemListElement' => $packages->values()->map(fn ($p, $idx) => [
                        '@type' => 'ListItem',
                        'position' => $idx + 1,
                        'item' => [
                            '@type' => 'TouristTrip',
                            'name' => $p->title,
                            'description' => Str::limit($p->description ?? '', 140),
                            'url' => route('storefront.package', $p->slug),
                            'offers' => [
                                '@type' => 'Offer',
                                'price' => (float) $p->price,
                                'priceCurrency' => 'IDR',
                                'availability' => 'https://schema.org/InStock',
                            ],
                        ],
                    ])->all(),
                ],
            ],
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

    @if (! empty($agent->brand_color))
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

<body x-data="{ mobileMenuOpen: false }" class="min-h-screen flex flex-col bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-white overflow-x-clip w-full max-w-full">
    <!-- Ambient Glow -->
    @include('storefront.partials.navbar')

    <!-- Main Container -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">
        <!-- Breadcrumb & Header Hero -->
        <div class="space-y-3">
            <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                <a href="{{ route('home') }}" class="hover:text-brand-600">{{ __('Home') }}</a>
                <span>&rsaquo;</span>
                <span class="text-slate-900 dark:text-white font-bold">{{ __('All Tour Packages') }}</span>
            </nav>

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __('Curated Tour Packages & Expeditions') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                        {{ __('Discover all all-inclusive experiences by :name. Guaranteed daily capacity, verified equipment, and upfront transparent pricing.', ['name' => $agent->name]) }}
                    </p>
                </div>

                <!-- Live Results Counter Badge -->
                <span class="self-start md:self-auto px-3 py-1 rounded-full text-xs font-bold bg-brand-50 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300 border border-brand-200 dark:border-brand-800 shrink-0">
                    {{ __(':count Experiences Available', ['count' => $packages->count()]) }}
                </span>
            </div>
        </div>

        <!-- Search & Category Filters Bar -->
        <div class="p-4 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-3">
            <form method="GET" action="{{ route('storefront.packages') }}" class="flex flex-col sm:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="{{ __('Search by destination, package title, or itinerary...') }}"
                        class="w-full h-11 pl-10 pr-4 rounded-2xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800/60 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
                    />
                </div>

                @if ($selectedCategory)
                    <input type="hidden" name="category" value="{{ $selectedCategory }}" />
                @endif

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="submit" class="h-11 px-6 rounded-2xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white text-xs font-bold shadow-xs transition w-full sm:w-auto cursor-pointer">
                        {{ __('Search') }}
                    </button>

                    @if ($search || $selectedCategory)
                        <a href="{{ route('storefront.packages') }}" class="h-11 px-4 inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 text-xs font-semibold text-slate-600 dark:text-slate-300 transition">
                            {{ __('Reset') }}
                        </a>
                    @endif
                </div>
            </form>

            <!-- Category Pills -->
            @if ($categories->isNotEmpty())
                <div class="flex items-center gap-2 overflow-x-auto pt-2 border-t border-slate-100 dark:border-zinc-800/80 no-scrollbar">
                    <a href="{{ route('storefront.packages', array_filter(['search' => $search])) }}"
                        class="h-8 px-3.5 inline-flex items-center rounded-xl text-xs font-bold transition shrink-0 {{ empty($selectedCategory) ? 'bg-brand-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                        {{ __('All Categories') }}
                    </a>
                    @foreach ($categories as $cat)
                        <a href="{{ route('storefront.packages', array_filter(['search' => $search, 'category' => $cat])) }}"
                            class="h-8 px-3.5 inline-flex items-center rounded-xl text-xs font-bold transition shrink-0 {{ $selectedCategory === $cat ? 'bg-brand-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ $cat }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Packages Grid -->
        @if ($packages->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
                @foreach ($packages as $pkg)
                    <div class="group flex flex-col justify-between rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs hover:shadow-xl hover:border-brand-400 dark:hover:border-brand-600 transition-all duration-200 overflow-hidden">
                        <!-- Card Media Header -->
                        <div class="relative aspect-video w-full overflow-hidden bg-gradient-to-br from-brand-950 via-slate-900 to-zinc-900 shrink-0">
                            @if ($pkg->cover_photo_url)
                                <img src="{{ $pkg->cover_photo_url }}" alt="{{ $pkg->title }}" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                            @else
                                <div class="w-full h-full flex items-center justify-center text-brand-400/30">
                                    <i class="fa-solid fa-mountain-sun text-4xl"></i>
                                </div>
                            @endif

                            <!-- Floating Badges on Media -->
                            <div class="absolute top-3 left-3 right-3 flex items-center justify-between gap-2 pointer-events-none">
                                <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-white/90 dark:bg-zinc-900/90 text-brand-700 dark:text-brand-300 backdrop-blur-md shadow-xs">
                                    {{ $pkg->category ?? __('Package') }}
                                </span>
                                @if ($pkg->free_cancellation_hours > 0)
                                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-lg bg-emerald-500/90 text-white backdrop-blur-md shadow-xs flex items-center gap-1">
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
                                    <h3 class="font-black text-base sm:text-lg text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors leading-snug">
                                        <a href="{{ route('storefront.package', $pkg->slug) }}" class="focus:outline-none">
                                            {{ $pkg->title }}
                                        </a>
                                    </h3>
                                    @if ($pkg->location)
                                        <p class="text-xs font-semibold text-slate-400 flex items-center gap-1.5 mt-1">
                                            <i class="fa-solid fa-location-dot text-brand-500 text-xs"></i>
                                            {{ $pkg->location }}
                                        </p>
                                    @endif
                                </div>

                                @if ($pkg->description)
                                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 line-clamp-2 leading-relaxed">
                                        {{ $pkg->description }}
                                    </p>
                                @endif

                                <!-- Inclusions Chips (Desktop Only) -->
                                @if (! empty($pkg->inclusions))
                                    <div class="hidden sm:block pt-2 border-t border-slate-100 dark:border-zinc-800/80 space-y-1.5">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Key Inclusions') }}</p>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach (array_slice($pkg->inclusions, 0, 3) as $inc)
                                                <span class="text-[10px] px-2 py-0.5 rounded-md bg-slate-50 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 font-medium">
                                                    &check; {{ $inc }}
                                                </span>
                                            @endforeach
                                            @if (count($pkg->inclusions) > 3)
                                                <span class="text-[10px] px-1.5 py-0.5 text-slate-400 font-semibold">
                                                    +{{ count($pkg->inclusions) - 3 }} more
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Card Bottom Row -->
                            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[9px] uppercase font-bold text-slate-400 dark:text-zinc-400 leading-none">{{ __('Price / Person') }}</p>
                                    <p class="text-base sm:text-xl font-black text-slate-900 dark:text-white mt-0.5">
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
        @else
            <div class="text-center py-16 px-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-brand-50 dark:bg-brand-950 text-brand-600 dark:text-brand-400 flex items-center justify-center mx-auto text-xl">
                    <i class="fa-solid fa-cubes"></i>
                </div>
                <h3 class="font-bold text-base text-slate-900 dark:text-white">{{ __('No Tour Packages Matching Your Filter') }}</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    {{ __('Try clearing your search terms or selecting a different category to view available tour packages.') }}
                </p>
                <div class="pt-2">
                    <a href="{{ route('storefront.packages') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 dark:bg-zinc-800 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition">
                        {{ __('View All Packages') }}
                    </a>
                </div>
            </div>
        @endif
    </main>

    <!-- Floating WhatsApp Support Button for Desktop Only -->
    @if ($agent->contact_whatsapp)
        @php
            $waNumber = preg_replace('/[^0-9]/', '', $agent->contact_whatsapp);
            if (str_starts_with($waNumber, '0')) {
                $waNumber = '62' . substr($waNumber, 1);
            }
        @endphp
        <div class="hidden lg:block fixed bottom-6 right-6 z-50">
            <a href="https://wa.me/{{ $waNumber }}?text={{ urlencode('Hello ' . $agent->name . ', I am browsing your tour packages.') }}"
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
