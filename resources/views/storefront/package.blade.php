<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
        <!-- SEO & Metadata -->
        <title>{{ $package->title }} &bull; {{ $agent->name }} &bull; {{ config('app.name') }}</title>
        <meta name="description" content="{{ Str::limit($package->description ?: __('Book :title with :agent. Official direct reservations with instant confirmation and locked rate.', ['title' => $package->title, 'agent' => $agent->name]), 160) }}" />
        <link rel="canonical" href="{{ route('storefront.package', $package->slug) }}" />

        <!-- Search Engine & AI Agent Discovery -->
        <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
        <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
        <meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
        <meta name="generator" content="{{ config('app.name') }} — Direct Booking Engine for Tour Operators" />
        <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}" />
        <!-- Favicon & Brand Icons -->
        @if ($agent->logo)
            <link rel="icon" href="{{ Storage::url($agent->logo) }}" />
            <link rel="apple-touch-icon" href="{{ Storage::url($agent->logo) }}" />
        @endif

        <!-- OpenGraph -->
        <meta property="og:type" content="product" />
        <meta property="og:url" content="{{ route('storefront.package', $package->slug) }}" />
        <meta property="og:title" content="{{ $package->title }} &bull; {{ $agent->name }}" />
        <meta property="og:description" content="{{ Str::limit($package->description ?: __('Book :title with :agent.', ['title' => $package->title, 'agent' => $agent->name]), 160) }}" />
        <meta property="og:site_name" content="{{ $agent->name }} • {{ config('app.name') }}" />
        @if ($package->cover_photo)
            <meta property="og:image" content="{{ Storage::url($package->cover_photo) }}" />
        @elseif ($agent->logo)
            <meta property="og:image" content="{{ Storage::url($agent->logo) }}" />
        @endif

        <!-- Twitter -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $package->title }} &bull; {{ $agent->name }}" />
        <meta name="twitter:description" content="{{ Str::limit($package->description ?: __('Book :title with :agent.', ['title' => $package->title, 'agent' => $agent->name]), 160) }}" />
        @if ($package->cover_photo)
            <meta name="twitter:image" content="{{ Storage::url($package->cover_photo) }}" />
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
                        'touristAttraction' => $package->location ? [
                            '@type' => 'TouristAttraction',
                            'name' => $package->location,
                        ] : null,
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
    <body
        x-data="{ mobileBookingOpen: false, mobileMenuOpen: false }"
        class="min-h-screen flex flex-col bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-white"
    >
        <!-- Sticky Navigation Header -->
        <header class="sticky top-0 z-40 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl border-b border-slate-200/80 dark:border-zinc-800">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 sm:h-16 flex items-center justify-between gap-3">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 min-w-0 group">
                    @if ($agent->logo_path)
                        <img src="{{ Storage::url($agent->logo_path) }}" alt="{{ $agent->name }}" class="h-8 w-8 rounded-xl object-cover border border-slate-200/80 dark:border-zinc-800 shadow-xs shrink-0 group-hover:scale-105 transition-transform bg-white dark:bg-zinc-800" />
                    @else
                        <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-600 dark:text-slate-300">
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                        </span>
                    @endif
                    <span class="truncate max-w-[140px] sm:max-w-xs font-black text-xs sm:text-sm text-slate-900 dark:text-white group-hover:text-brand-600 transition">{{ $agent->name }}</span>
                </a>

                <div class="flex items-center gap-2">
                    <span class="hidden sm:inline-block text-[10px] sm:text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300">
                        {{ $package->category ?? __('Package') }}
                    </span>

                    <!-- Desktop Nav Links -->
                    <div class="hidden md:flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-400 mr-2">
                        <a href="{{ route('home') }}" class="hover:text-brand-600 transition px-2 py-1">{{ __('Catalog') }}</a>
                        <a href="{{ route('storefront.terms') }}" class="hover:text-brand-600 transition px-2 py-1">{{ __('Terms') }}</a>
                    </div>

                    <!-- Mobile Header Book Action -->
                    <button
                        type="button"
                        @click="mobileBookingOpen = true"
                        class="lg:hidden h-9 px-3.5 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                    >
                        <i class="fa-solid fa-calendar-check text-[11px]"></i>
                        <span>{{ __('Book Now') }}</span>
                    </button>

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
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-store w-4 text-slate-400"></i>
                    <span>{{ __('Home Storefront') }}</span>
                </a>
                <a href="{{ route('storefront.packages') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-cubes w-4 text-slate-400"></i>
                    <span>{{ __('All Tour Packages') }}</span>
                </a>
                <a href="{{ route('storefront.products') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-box-open w-4 text-slate-400"></i>
                    <span>{{ __('Activities & Rentals') }}</span>
                </a>
                <a href="{{ route('storefront.terms') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-600 transition">
                    <i class="fa-solid fa-file-contract w-4 text-slate-400"></i>
                    <span>{{ __('Terms & Policies') }}</span>
                </a>
            </div>
        </header>

        <!-- Main Package Content -->
        <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 py-5 sm:py-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Left Details (Mobile-First 2 cols) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Package Cover & Gallery Photos -->
                    @if ($package->cover_photo || !empty($package->gallery))
                        <div class="overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2 p-2">
                            @if ($package->cover_photo)
                                <div class="aspect-video sm:aspect-21/9 w-full rounded-2xl overflow-hidden bg-slate-100 dark:bg-zinc-800">
                                    <img src="{{ Storage::url($package->cover_photo) }}" alt="{{ $package->title }}" class="w-full h-full object-cover" />
                                </div>
                            @endif
                            @if (!empty($package->gallery))
                                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 pt-1">
                                    @foreach ($package->gallery as $gImg)
                                        <div class="aspect-video rounded-xl overflow-hidden bg-slate-100 dark:bg-zinc-800 border border-slate-200/60 dark:border-zinc-700">
                                            <img src="{{ Storage::url($gImg) }}" alt="{{ $package->title }}" class="w-full h-full object-cover hover:scale-105 transition-transform" />
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Title & Meta Highlights -->
                    <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                        @if ($package->location)
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 text-xs font-bold">
                                <i class="fa-solid fa-location-dot text-[11px]"></i>
                                <span>{{ $package->location }}</span>
                            </div>
                        @endif

                        <h1 class="text-2xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                            {{ $package->title }}
                        </h1>

                        @if ($package->description)
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ $package->description }}
                            </p>
                        @endif

                        <!-- Key Feature Highlights -->
                        <div class="pt-2 grid grid-cols-2 sm:grid-cols-3 gap-2.5 text-xs text-slate-600 dark:text-slate-300">
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800 flex items-center gap-2.5">
                                <i class="fa-solid fa-bolt text-amber-500 text-base"></i>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-none">{{ __('Instant') }}</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">{{ __('Auto Confirmation') }}</p>
                                </div>
                            </div>

                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800 flex items-center gap-2.5">
                                <i class="fa-solid fa-rotate-left text-emerald-500 text-base"></i>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-none">{{ $package->free_cancellation_hours }}h Free</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">{{ __('Cancellation Window') }}</p>
                                </div>
                            </div>

                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800 flex items-center gap-2.5 col-span-2 sm:col-span-1">
                                <i class="fa-solid fa-clock text-sky-500 text-base"></i>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-none">{{ $package->advance_booking_hours }}h Advance</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">{{ __('Cutoff Window') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inclusions & Exclusions Grid -->
                    @if (!empty($package->inclusions) || !empty($package->exclusions))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
                            @if (!empty($package->inclusions))
                                <div class="space-y-3">
                                    <h4 class="font-black text-xs uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
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
                                <div class="space-y-3 pt-4 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-zinc-800">
                                    <h4 class="font-black text-xs uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
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
                        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                            <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
                                <i class="fa-solid fa-map-location-dot text-brand-500"></i>
                                {{ __('Schedule & Itinerary') }}
                            </h3>
                            <div class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line leading-relaxed pl-2 border-l-2 border-brand-200 dark:border-brand-900/60">
                                {{ $package->itinerary_text }}
                            </div>
                        </div>
                    @endif

                    <!-- Policy & Terms -->
                    @if ($agent->terms_and_conditions)
                        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2.5">
                            <h3 class="font-bold text-xs uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
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
                        <livewire:storefront.booking-box :bookable="$package" :agent="$agent" />
                    </div>
                </div>
            </div>
        </main>

        @include('storefront.partials.footer')

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
                        <h3 class="font-black text-base text-slate-900 dark:text-white mt-0.5">{{ __('Select Date & Guests') }}</h3>
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

                <livewire:storefront.booking-box :bookable="$package" :agent="$agent" />
            </div>
        </div>

        @livewireScripts
    </body>
</html>
