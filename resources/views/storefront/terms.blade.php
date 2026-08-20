<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
        <!-- SEO & Metadata -->
        <title>{{ __('Booking Policies & Terms') }} - {{ $agent->name }} &bull; {{ config('app.name') }}</title>
        <meta name="description" content="{{ __('Official booking terms, instant hold policies, cancellation rules, and payment protection guidelines for direct reservations with :name.', ['name' => $agent->name]) }}" />
        <link rel="canonical" href="{{ route('storefront.terms') }}" />

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
        <meta property="og:type" content="article" />
        <meta property="og:url" content="{{ route('storefront.terms') }}" />
        <meta property="og:title" content="{{ __('Booking Policies & Terms') }} - {{ $agent->name }}" />
        <meta property="og:description" content="{{ __('Official booking terms and policies for direct reservations with :name.', ['name' => $agent->name]) }}" />
        <meta property="og:site_name" content="{{ $agent->name }} • {{ config('app.name') }}" />
        @if ($agent->logo)
            <meta property="og:image" content="{{ Storage::url($agent->logo) }}" />
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
                                'name' => __('Terms & Policies'),
                                'item' => route('storefront.terms'),
                            ],
                        ],
                    ],
                    [
                        '@type' => 'WebPage',
                        '@id' => route('storefront.terms') . '#webpage',
                        'name' => __('Booking Policies & Terms - :name', ['name' => $agent->name]),
                        'url' => route('storefront.terms'),
                        'description' => __('Official terms and policies for :name.', ['name' => $agent->name]),
                    ],
                ],
            ];
        @endphp
        <script type="application/ld+json">
        {!! json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>

        <!-- Automated System Dark / Light Theme Sync -->
        <script>
            (function () {
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
        @livewireStyles
    </head>
    <body x-data="{ mobileMenuOpen: false }" class="min-h-screen flex flex-col bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-white">
        <!-- Ambient Top Glow -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
            <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-full max-w-5xl h-[500px] bg-gradient-to-b from-brand-500/15 via-sky-500/10 to-transparent rounded-full blur-3xl dark:from-brand-600/20 dark:via-sky-500/10"></div>
        </div>

        <!-- Sticky Navigation Header -->
        <header class="sticky top-0 z-40 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl border-b border-slate-200/80 dark:border-zinc-800 transition-all">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 min-w-0 group">
                    @if ($agent->logo_path ?? $agent->logo)
                        <img src="{{ Storage::url($agent->logo_path ?? $agent->logo) }}" alt="{{ $agent->name }}" class="h-9 w-9 rounded-xl object-cover border border-slate-200/80 dark:border-zinc-800 shadow-xs shrink-0 group-hover:scale-105 transition-transform bg-white dark:bg-zinc-800" />
                    @else
                        <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-600 dark:text-slate-300">
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                        </span>
                    @endif
                    <span class="truncate max-w-[180px] sm:max-w-xs font-black text-sm text-slate-900 dark:text-white group-hover:text-brand-600 transition">{{ $agent->name }}</span>
                </a>

                <!-- Desktop Nav Links -->
                <nav class="hidden md:flex items-center gap-4 text-xs font-bold text-slate-600 dark:text-slate-400">
                    <a href="{{ route('home') }}" class="hover:text-brand-600 dark:hover:text-white transition px-2 py-1">{{ __('Home') }}</a>
                    <a href="{{ route('storefront.packages') }}" class="hover:text-brand-600 dark:hover:text-white transition px-2 py-1">{{ __('Tour Packages') }}</a>
                    <a href="{{ route('storefront.products') }}" class="hover:text-brand-600 dark:hover:text-white transition px-2 py-1">{{ __('Activities & Rentals') }}</a>
                    <span class="text-brand-600 dark:text-white font-black border-b-2 border-brand-600 pb-0.5 px-2 py-1">{{ __('Terms & Policies') }}</span>
                </nav>

                <!-- Mobile Hamburger Button -->
                <button
                    type="button"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden h-9 w-9 inline-flex items-center justify-center rounded-xl border border-slate-200/80 dark:border-zinc-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-zinc-800 transition cursor-pointer"
                    aria-label="Toggle navigation menu"
                >
                    <i class="fa-solid text-sm" :class="mobileMenuOpen ? 'fa-xmark' : 'fa-bars'"></i>
                </button>
            </div>

            <!-- Mobile Navigation Dropdown Menu -->
            <div
                x-show="mobileMenuOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                @click.away="mobileMenuOpen = false"
                class="md:hidden border-b border-slate-200/80 dark:border-zinc-800 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl px-4 py-3 space-y-1 shadow-xl"
            >
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-store w-4 text-slate-400"></i>
                    <span>{{ __('Home Storefront') }}</span>
                </a>
                <a href="{{ route('storefront.packages') }}" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-cubes w-4 text-slate-400"></i>
                    <span>{{ __('All Tour Packages') }}</span>
                </a>
                <a href="{{ route('storefront.products') }}" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-box-open w-4 text-slate-400"></i>
                    <span>{{ __('Activities & Rentals') }}</span>
                </a>
                <a href="{{ route('storefront.terms') }}" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-brand-600 dark:text-white bg-brand-50/70 dark:bg-brand-950/50 transition">
                    <i class="fa-solid fa-file-contract w-4 text-brand-500"></i>
                    <span>{{ __('Terms & Policies') }}</span>
                </a>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 pb-20 sm:pb-12 space-y-8">
            <!-- Breadcrumb & Header Hero -->
            <div class="space-y-3">
                <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                    <a href="{{ route('home') }}" class="hover:text-brand-600">{{ __('Home') }}</a>
                    <span>&rsaquo;</span>
                    <span class="text-slate-900 dark:text-white font-bold">{{ __('Terms & Policies') }}</span>
                </nav>

                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 dark:text-brand-400 mb-1">
                            <i class="fa-solid fa-file-contract"></i>
                            <span>{{ __('Official Direct Booking Policies') }}</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ __('Booking Policies & Guest Protection') }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                            {{ __('Official booking terms, instant hold rules, cancellation policies, and guest safety disclaimers for :name.', ['name' => $agent->name]) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Policy Card Grid / Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 items-start">
                <!-- Main Terms Document (2 cols) -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-6">
                        @if ($agent->terms_and_conditions)
                            <div class="space-y-3">
                                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                                    <i class="fa-solid fa-shield-halved text-brand-500"></i>
                                    {{ __('Provider Policies & Guest Guidelines') }}
                                </h2>
                                <div class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-line bg-slate-50/50 dark:bg-zinc-800/30 p-5 sm:p-7 rounded-2xl border border-slate-100 dark:border-zinc-800">
                                    {{ $agent->terms_and_conditions }}
                                </div>
                            </div>
                        @else
                            <div class="p-8 text-center text-xs text-slate-400">
                                {{ __('Standard booking and cancellation terms apply.') }}
                            </div>
                        @endif

                        <!-- Standard Protection & Data Privacy Notice -->
                        <div class="pt-6 border-t border-slate-100 dark:border-zinc-800 space-y-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                <i class="fa-solid fa-lock text-emerald-500"></i>
                                {{ __('Guest Personal Data Protection (UU PDP)') }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ __('All guest information collected during the reservation and checkout process is processed strictly for fulfilling bookings, safety verification, and official transaction receipts in compliance with Indonesian Personal Data Protection regulations.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Info & WhatsApp Support (1 col) -->
                <div class="space-y-6">
                    <!-- Verified Operator Card -->
                    <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                        <div class="flex items-center gap-3">
                            @if ($agent->logo_path ?? $agent->logo)
                                <img src="{{ Storage::url($agent->logo_path ?? $agent->logo) }}" alt="{{ $agent->name }}" class="h-12 w-12 rounded-2xl object-cover border border-slate-200/80 dark:border-zinc-800 shadow-xs shrink-0 bg-white dark:bg-zinc-800" />
                            @else
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 text-white font-black text-lg shadow-sm shrink-0">
                                    {{ strtoupper(substr($agent->name, 0, 1)) }}
                                </span>
                            @endif
                            <div class="min-w-0">
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white truncate">{{ $agent->name }}</h3>
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                    <i class="fa-solid fa-circle-check text-[10px]"></i>
                                    {{ __('Verified Tour Operator') }}
                                </span>
                            </div>
                        </div>

                        @if ($agent->contact_whatsapp)
                            @php
                                $waNumber = preg_replace('/[^0-9]/', '', $agent->contact_whatsapp);
                            @endphp
                            <div class="pt-2 border-t border-slate-100 dark:border-zinc-800 space-y-2">
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ __('Have questions regarding custom group bookings, schedule adjustments, or weather policies?') }}
                                </p>
                                <a
                                    href="https://wa.me/{{ $waNumber }}?text={{ urlencode('Hi ' . $agent->name . ', I have a question regarding your booking terms.') }}"
                                    target="_blank"
                                    class="w-full h-10 px-4 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition"
                                >
                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                    <span>{{ __('Chat on WhatsApp') }}</span>
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Instant Protection Highlights -->
                    <div class="p-6 rounded-3xl bg-slate-50/80 dark:bg-zinc-900/50 border border-slate-200/80 dark:border-zinc-800 space-y-3 text-xs">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            <i class="fa-solid fa-shield-halved text-brand-600 dark:text-brand-400"></i>
                            {{ __('Direct Booking Guarantee') }}
                        </h4>
                        <ul class="space-y-2 text-slate-500 dark:text-slate-400 text-[11px] leading-relaxed">
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-check text-emerald-500 mt-0.5 shrink-0"></i>
                                <span>{{ __('Frozen price and terms snapshot at checkout time.') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-check text-emerald-500 mt-0.5 shrink-0"></i>
                                <span>{{ __('Direct communication with certified tour captains.') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-check text-emerald-500 mt-0.5 shrink-0"></i>
                                <span>{{ __('Secure Indonesian payment gateway checkout.') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>

        @include('storefront.partials.footer')

        @livewireScripts
    </body>
</html>
