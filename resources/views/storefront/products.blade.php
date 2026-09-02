<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />

    <!-- SEO & Metadata -->
    <title>{{ __('Single Activities') }} &bull; {{ $agent->name }} &bull; {{ config('app.name') }}</title>
    <meta name="description" content="{{ __('Book single activities, day tours, and guided experiences directly with :name. Instant holds, transparent pricing, and secure payment.', ['name' => $agent->name]) }}" />
    <link rel="canonical" href="{{ route('storefront.products') }}" />

    <!-- Search Engine & AI Agent Discovery -->
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
    <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
    <meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
    <meta name="generator" content="{{ config('app.name') }} — Direct Booking Engine" />
    <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}" />
    <link rel="alternate" type="text/plain" href="{{ url('/llms.txt') }}" title="LLMs Text Summary" />
    <!-- Favicon & Brand Icons -->
    <link rel="icon" href="{{ $agent->logo_url }}" />
    <link rel="apple-touch-icon" href="{{ $agent->logo_url }}" />

    <!-- OpenGraph -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ route('storefront.products') }}" />
    <meta property="og:title" content="{{ __('Single Activities') }} &bull; {{ $agent->name }}" />
    <meta property="og:description" content="{{ __('Browse single activities, tours, and experiences by :name.', ['name' => $agent->name]) }}" />
    <meta property="og:site_name" content="{{ $agent->name }} • {{ config('app.name') }}" />
    @if ($agent->logo)
        <meta property="og:image" content="{{ Storage::url($agent->logo) }}" />
    @endif

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ __('Single Activities') }} &bull; {{ $agent->name }}" />
    <meta name="twitter:description" content="{{ __('Browse single activities, tours, and experiences by :name.', ['name' => $agent->name]) }}" />
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
                            'name' => __('Single Activities'),
                            'item' => route('storefront.products'),
                        ],
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    'name' => $agent->name . ' - ' . __('Single Activities'),
                    'numberOfItems' => $products->count(),
                    'itemListElement' => $products->values()->map(fn ($p, $idx) => [
                        '@type' => 'ListItem',
                        'position' => $idx + 1,
                        'item' => [
                            '@type' => 'Product',
                            'name' => $p->name,
                            'description' => Str::limit($p->description ?? '', 140),
                            'url' => route('storefront.product', $p->slug),
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
                <span class="text-slate-900 dark:text-white font-bold">{{ __('Single Activities') }}</span>
            </nav>

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __('Single Activities') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                        {{ __('Book standalone activities, sessions, or guided experiences directly with :name.', ['name' => $agent->name]) }}
                    </p>
                </div>

                <span class="self-start md:self-auto px-3 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-700 dark:bg-sky-950/80 dark:text-sky-300 border border-sky-200 dark:border-sky-800 shrink-0">
                    {{ __(':count Items Available', ['count' => $products->count()]) }}
                </span>
            </div>
        </div>

        <!-- Search & Category Filters Bar -->
        <div class="p-4 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-3">
            <form method="GET" action="{{ route('storefront.products') }}" class="flex flex-col sm:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="{{ __('Search activities, gear, or transfers...') }}"
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
                        <a href="{{ route('storefront.products') }}" class="h-11 px-4 inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 text-xs font-semibold text-slate-600 dark:text-slate-300 transition">
                            {{ __('Reset') }}
                        </a>
                    @endif
                </div>
            </form>

            <!-- Category Pills -->
            @if ($categories->isNotEmpty())
                <div class="flex items-center gap-2 overflow-x-auto pt-2 border-t border-slate-100 dark:border-zinc-800/80 no-scrollbar">
                    <a href="{{ route('storefront.products', array_filter(['search' => $search])) }}"
                        class="h-8 px-3.5 inline-flex items-center rounded-xl text-xs font-bold transition shrink-0 {{ empty($selectedCategory) ? 'bg-brand-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                        {{ __('All Categories') }}
                    </a>
                    @foreach ($categories as $cat)
                        <a href="{{ route('storefront.products', array_filter(['search' => $search, 'category' => $cat])) }}"
                            class="h-8 px-3.5 inline-flex items-center rounded-xl text-xs font-bold transition shrink-0 {{ $selectedCategory === $cat ? 'bg-brand-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ $cat }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Standalone Products Grid -->
        @if ($products->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-5">
                @foreach ($products as $prod)
                    <div class="group flex flex-col justify-between rounded-2xl sm:rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs hover:shadow-xl hover:border-brand-400 dark:hover:border-brand-600 transition-all duration-200 overflow-hidden">
                        <!-- Card Media Header -->
                        <div class="relative aspect-video w-full overflow-hidden bg-slate-900 dark:bg-zinc-950 shrink-0">
                            @if ($prod->cover_photo_url)
                                <img src="{{ $prod->cover_photo_url }}" alt="{{ $prod->name }}" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                            @else
                                <div class="w-full h-full flex items-center justify-center text-sky-400/30">
                                    <i class="fa-solid fa-box-open text-3xl"></i>
                                </div>
                            @endif

                            <!-- Floating Badges on Media -->
                            <div class="absolute top-2.5 left-2.5 right-2.5 flex items-center justify-between gap-1.5 pointer-events-none">
                                <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-white/90 dark:bg-zinc-900/90 text-slate-700 dark:text-slate-300 backdrop-blur-md shadow-xs">
                                    {{ $prod->category ?? __('Service') }}
                                </span>
                                @if ($prod->capacity_per_day)
                                    <span class="text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-500/90 text-white backdrop-blur-md shadow-xs">
                                        {{ $prod->capacity_per_day }}/day
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between space-y-3">
                            <div class="space-y-1.5">
                                <h4 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white leading-snug group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                                    <a href="{{ route('storefront.product', $prod->slug) }}" class="focus:outline-none">
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
                                    <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">{{ $prod->description }}</p>
                                @endif
                            </div>

                            <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 dark:border-zinc-800">
                                <div>
                                    <p class="text-[9px] uppercase font-bold text-slate-400">{{ __('Price') }}</p>
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
        @else
            <div class="text-center py-16 px-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center mx-auto text-xl">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h3 class="font-bold text-base text-slate-900 dark:text-white">{{ __('No Items Matching Your Filter') }}</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    {{ __('Try clearing your search terms or selecting a different category to view available items.') }}
                </p>
                <div class="pt-2">
                    <a href="{{ route('storefront.products') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 dark:bg-zinc-800 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition">
                        {{ __('View All Items') }}
                    </a>
                </div>
            </div>
        @endif
    </main>



    @include('storefront.partials.footer')

    @livewireScripts
</body>
</html>
