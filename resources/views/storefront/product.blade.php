<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
        @php
            $seo = app(\App\Services\StorefrontSeoService::class);
            $share = $seo->shareImage($agent, $product->cover_photo);
            $share['alt'] = $product->name;
        @endphp
        @include('storefront.partials.seo', [
            'agent' => $agent,
            'title' => $product->name.' · '.$agent->name,
            'description' => $seo->listingDescription(
                $product->description,
                __('Book :name with :operator. See the price and reserve a spot online.', [
                    'name' => $product->name,
                    'operator' => $agent->name,
                ]),
            ),
            'url' => route('storefront.product', $product->slug),
            'type' => 'website',
            'share' => $share,
            'schema' => $seo->productGraph($agent, $product),
        ])

        @include('storefront.partials.brand-theme')

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('storefront.partials.tracking-scripts', ['agent' => $agent])
        @livewireStyles
    </head>
    <body
        x-data="{ mobileBookingOpen: false, mobileMenuOpen: false }"
        class="min-h-screen flex flex-col sf-canvas text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-brand-foreground overflow-x-clip w-full max-w-full"
    >
        @include('storefront.partials.navbar')

        <!-- Main Product Content -->
        <main class="flex-1 w-full max-w-6xl mx-auto px-3 sm:px-6 py-4 sm:py-8 pb-[calc(6.5rem+env(safe-area-inset-bottom))] lg:pb-12 space-y-6">
            <nav class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                <a href="{{ route('home') }}" class="hover:text-brand-800 dark:hover:text-brand-400">{{ __('Home') }}</a>
                <span>&rsaquo;</span>
                <a href="{{ route('storefront.products') }}" class="hover:text-brand-800 dark:hover:text-brand-400">{{ __('Single Activities') }}</a>
                <span>&rsaquo;</span>
                <span class="text-slate-900 dark:text-white font-bold truncate max-w-[14rem] sm:max-w-md">{{ $product->name }}</span>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Left Details -->
                <div class="lg:col-span-2 space-y-6">
                    @include('storefront.partials.listing-gallery', [
                        'coverUrl' => $product->cover_photo_url,
                        'galleryUrls' => $product->gallery_urls ?? [],
                        'alt' => $product->name,
                    ])

                    <div class="p-5 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="px-2.5 py-1 rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-wider bg-brand-50 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300">
                                {{ $product->category ?? __('Service') }}
                            </span>
                            @if ($product->location)
                                <span
                                    class="px-2.5 py-1 rounded-xl text-[10px] sm:text-xs font-bold bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                    <i class="fa-solid fa-location-dot text-slate-400"></i>
                                    {{ $product->location }}
                                </span>
                            @endif
                        </div>

                        <h1 class="text-xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                            {{ $product->name }}
                        </h1>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2">
                            <div
                                class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 flex items-center gap-3">
                                <span
                                    class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs shrink-0">
                                    <i class="fa-solid fa-shield"></i>
                                </span>
                                <div class="min-w-0">
                                    <span
                                        class="text-[10px] text-slate-400 uppercase font-bold block leading-none">{{ __('Cancellation') }}</span>
                                    <span
                                        class="text-xs font-black text-slate-900 dark:text-white truncate mt-0.5 block">{{ __('Free up to :hours hrs', ['hours' => $product->free_cancellation_hours ?? 24]) }}</span>
                                </div>
                            </div>

                            @if (($product->advance_booking_hours ?? 0) > 0)
                                <div
                                    class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 flex items-center gap-3">
                                    <span
                                        class="w-8 h-8 rounded-xl bg-sky-100 dark:bg-sky-950/80 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xs shrink-0">
                                        <i class="fa-solid fa-calendar-plus"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <span
                                            class="text-[10px] text-slate-400 uppercase font-bold block leading-none">{{ __('Book ahead') }}</span>
                                        <span
                                            class="text-xs font-black text-slate-900 dark:text-white truncate mt-0.5 block">{{ __('At least :hours hrs', ['hours' => $product->advance_booking_hours]) }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @include('storefront.partials.listing-share', [
                            'agent' => $agent,
                            'listingTitle' => $product->name,
                            'pageUrl' => route('storefront.product', $product->slug),
                        ])
                    </div>

                    @if ($product->description)
                        <div
                            class="p-5 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-3">
                            <h2
                                class="font-black text-base sm:text-lg text-slate-900 dark:text-white flex items-center gap-2">
                                <i class="fa-solid fa-circle-info text-brand-700 dark:text-brand-400"></i>
                                {{ __('Activity Overview') }}
                            </h2>
                            <div
                                class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                {{ $product->description }}
                            </div>
                        </div>
                    @endif

                    <!-- Inclusions & Exclusions -->
                    @if (! empty($product->inclusions) || ! empty($product->exclusions))
                        <div
                            class="p-5 sm:p-7 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs grid grid-cols-1 sm:grid-cols-2 gap-6">
                            @if (! empty($product->inclusions))
                                <div class="space-y-3">
                                    <h4
                                        class="font-black text-xs uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ __('What is Included') }}
                                    </h4>
                                    <ul class="space-y-2 text-xs text-slate-700 dark:text-slate-300">
                                        @foreach ($product->inclusions as $inc)
                                            <li class="flex items-start gap-2.5">
                                                <i class="fa-solid fa-check text-emerald-500 text-xs mt-0.5"></i>
                                                <span class="leading-relaxed">{{ $inc }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($product->exclusions))
                                <div
                                    class="space-y-3 pt-4 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-zinc-800">
                                    <h4
                                        class="font-black text-xs uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                                        <i class="fa-solid fa-circle-xmark"></i>
                                        {{ __('Not Included') }}
                                    </h4>
                                    <ul class="space-y-2 text-xs text-slate-700 dark:text-slate-300">
                                        @foreach ($product->exclusions as $exc)
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

                    @php
                        $productPolicy = $product->cancellation_terms ?: $product->terms_and_conditions ?: $agent->terms_and_conditions;
                    @endphp
                    @if ($productPolicy)
                        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2.5">
                            <h3 class="font-bold text-xs uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                                <i class="fa-solid fa-file-contract text-brand-700 dark:text-brand-400"></i>
                                {{ __('Booking & Cancellation Policy') }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed whitespace-pre-line">
                                {{ $productPolicy }}
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
        <div class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-t border-slate-200/80 dark:border-zinc-800 px-4 pt-3.5 pb-[calc(1rem+env(safe-area-inset-bottom,0px))] sm:pb-4 shadow-xl select-none flex items-center justify-between gap-3">
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
