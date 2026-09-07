<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
        @php
            $seo = app(\App\Services\StorefrontSeoService::class);
            $share = $seo->shareImage($agent);
        @endphp
        @include('storefront.partials.seo', [
            'agent' => $agent,
            'title' => __('Booking terms').' · '.$agent->name,
            'description' => __('Cancellation, payment, and booking terms for trips with :name.', ['name' => $agent->name]),
            'url' => route('storefront.terms'),
            'type' => 'article',
            'share' => $share,
            'schema' => $seo->termsGraph($agent),
        ])


        @include('storefront.partials.brand-theme')

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('storefront.partials.tracking-scripts', ['agent' => $agent])
        @livewireStyles
    </head>
    <body x-data="{ mobileMenuOpen: false }" class="min-h-screen flex flex-col bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-brand-foreground overflow-x-clip w-full max-w-full">
        @include('storefront.partials.navbar')

        <!-- Main Content Area -->
        <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">
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
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ __('If you cancel inside the free-cancel window, we send the full amount back to the same payment method, usually within a few working days.') }}
                            </p>
                            <p class="text-xs">
                                <a href="{{ route('legal.privacy') }}" class="font-semibold underline">{{ __('Platform privacy') }}</a>
                                <span class="text-slate-400"> · </span>
                                <a href="{{ route('legal.terms') }}" class="font-semibold underline">{{ __('Platform terms') }}</a>
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
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-[#101730] font-black text-lg shadow-sm shrink-0">
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
