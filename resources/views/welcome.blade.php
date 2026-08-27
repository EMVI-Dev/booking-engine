<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">

<head>
    @include('partials.head')
    @livewireStyles
</head>

<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased selection:bg-purple-500 selection:text-white font-sans">
    @php
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get();
        if ($plans->isEmpty()) {
            \App\Models\Plan::seedDefaultPlans();
            $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get();
        }
    @endphp

    <!-- Header Navigation Bar -->
    <header class="border-b border-zinc-800/80 sticky top-0 z-50 bg-zinc-950/85 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
            <!-- Platform Brand Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-3 group select-none">
                <div
                    class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-purple-600 via-indigo-600 to-sky-500 text-white flex items-center justify-center text-xl shadow-md shadow-purple-500/20 group-hover:scale-105 transition-transform duration-200">
                    <i class="fa-solid fa-compass text-lg"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-black text-lg tracking-tight text-white flex items-center gap-1.5 leading-none">
                        <span>{{ config('app.name', 'Emvi') }}</span>
                        <span
                            class="text-xs px-1.5 py-0.5 rounded-md bg-purple-500/20 text-purple-400 font-extrabold uppercase tracking-wider border border-purple-500/30">Engine</span>
                    </span>
                    <span class="text-[11px] text-zinc-400 font-semibold tracking-tight mt-0.5">
                        {{ __('Online Booking System for Tour & Activity Providers') }}
                    </span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden md:flex items-center gap-8 text-xs font-bold text-zinc-400">
                <a href="#how-it-works" class="hover:text-white transition">{{ __('How It Works') }}</a>
                <a href="#features" class="hover:text-white transition">{{ __('Features') }}</a>
                <a href="#pricing" class="hover:text-white transition">{{ __('Pricing & Plans') }}</a>
                <a href="#faq" class="hover:text-white transition">{{ __('FAQ') }}</a>
            </nav>

            <!-- Auth / Action Buttons -->
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}"
                        class="h-10 px-4 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md transition flex items-center gap-2"
                        wire:navigate>
                        <i class="fa-solid fa-gauge text-[11px]"></i>
                        <span>{{ __('Dashboard') }}</span>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="h-10 px-4 rounded-xl text-zinc-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5"
                        wire:navigate>
                        <i class="fa-solid fa-arrow-right-to-bracket text-xs text-zinc-500"></i>
                        <span>{{ __('Log In') }}</span>
                    </a>
                    <a href="{{ route('register') }}"
                        class="h-10 px-4 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-extrabold shadow-md shadow-purple-500/20 transition flex items-center gap-1.5 cursor-pointer"
                        wire:navigate>
                        <i class="fa-solid fa-bolt text-amber-300 text-xs"></i>
                        <span>{{ __('Start Free') }}</span>
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="relative pt-20 pb-20 sm:pt-28 sm:pb-28 overflow-hidden">
            <!-- Background Ambient Glow -->
            <div class="absolute inset-0 -z-10 flex items-center justify-center pointer-events-none">
                <div
                    class="w-[700px] h-[700px] rounded-full bg-gradient-to-tr from-purple-600/20 via-indigo-500/15 to-sky-500/10 blur-3xl">
                </div>
            </div>

            <div class="max-w-5xl mx-auto px-4 sm:px-6 text-center space-y-8">
                <!-- Pill Tag -->
                <div
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-zinc-800 bg-zinc-900/80 text-xs font-bold text-zinc-300 backdrop-blur-xs shadow-inner">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>{{ __('Instant Websites & Booking Engine for Tour Guides & Travel Operators') }}</span>
                </div>

                <!-- Main Catchy Title -->
                <h1
                    class="text-4xl sm:text-6xl lg:text-7xl font-black tracking-tight text-white leading-tight sm:leading-none">
                    Never worry about not having a website again.<br>
                    <span
                        class="bg-gradient-to-r from-purple-400 via-indigo-300 to-sky-300 bg-clip-text text-transparent">
                        Sell your tours direct. Keep 100%.
                    </span>
                </h1>

                <!-- Value Proposition Copy (Non-technical & Operator Focused) -->
                <p class="text-base sm:text-xl text-zinc-400 max-w-3xl mx-auto font-normal leading-relaxed">
                    {{ __('We provide simple, beautiful websites with a complete booking engine and instant payment acceptance. From freelance tour guides to professional travel agencies — start taking direct online bookings in minutes with zero technical hassle.') }}
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                    <a href="{{ route('register') }}"
                        class="w-full sm:w-auto h-12 px-8 rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-extrabold text-sm shadow-xl shadow-purple-500/25 transition-all flex items-center justify-center gap-2"
                        wire:navigate>
                        <span>{{ __('Create Your Free Tour Website') }}</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                    <a href="{{ url('/nusapenida-excursions') }}" target="_blank"
                        class="w-full sm:w-auto h-12 px-7 rounded-2xl bg-zinc-900/90 hover:bg-zinc-800 text-zinc-200 border border-zinc-800 font-bold text-sm transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-store text-indigo-400 text-xs"></i>
                        <span>{{ __('Explore Live Demo Storefront ↗') }}</span>
                    </a>
                </div>

                <!-- Trust Bar Badges -->
                <div
                    class="pt-10 flex flex-wrap items-center justify-center gap-6 sm:gap-10 text-xs font-semibold text-zinc-400">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-store text-purple-400 text-sm"></i>
                        <span>{{ __('Ready-to-Use Tour Website') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-bolt text-indigo-400 text-sm"></i>
                        <span>{{ __('Built-in Booking Engine') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-qrcode text-emerald-400 text-sm"></i>
                        <span>{{ __('Instant QRIS & Bank Payments') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp text-sky-400 text-sm"></i>
                        <span>{{ __('1-Click WhatsApp Tickets') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission & Why We Exist / How It Works Section -->
        <section id="how-it-works" class="py-20 border-t border-zinc-800/80 bg-zinc-950 scroll-mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                <div class="text-center space-y-3 max-w-3xl mx-auto">
                    <span
                        class="text-xs font-extrabold uppercase tracking-wider text-purple-400 block">{{ __('Why We Exist') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                        Built for real tour operators, not tech experts
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                        Whether you are a solo freelance tour guide taking private snorkeling trips or an established
                        travel agency managing daily island expeditions, our platform removes all the complexity of
                        selling online.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                    <!-- Problem & Solution 1 -->
                    <div class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-purple-950/80 text-purple-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-solid fa-globe"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('1. A Simple Website for Your Tours') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            No more worrying about not having a website. Get your own clean, professional booking page
                            with your logo, tour photos, pricing, and bio in under 5 minutes. Put your link directly in
                            your Instagram bio or WhatsApp status.
                        </p>
                    </div>

                    <!-- Problem & Solution 2 -->
                    <div class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-indigo-950/80 text-indigo-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('2. An End-to-End Booking Engine') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            We don't just give you a static page — we provide a complete booking system. Travelers
                            select dates, choose passenger count, see real-time pricing, and receive instant
                            confirmation vouchers automatically.
                        </p>
                    </div>

                    <!-- Problem & Solution 3 -->
                    <div class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-emerald-950/80 text-emerald-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('3. Hassle-Free Payment Acceptance') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Ready to accept QRIS, BCA, Mandiri, BRI, and Credit Cards from day one with zero technical
                            setup. Already have your own merchant account? You can easily plug in your own payment
                            gateway credentials anytime.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Showcase Grid -->
        <section id="features" class="py-20 border-t border-zinc-800/80 bg-zinc-950/60 scroll-mt-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                <div class="text-center space-y-3 max-w-2xl mx-auto">
                    <span
                        class="text-xs font-extrabold uppercase tracking-wider text-purple-400 block">{{ __('Core Features') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                        Everything you need to sell direct online
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                        Simple, friendly, and designed to help you get more direct bookings without paying hefty OTA
                        commissions.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    <!-- Feature 1 -->
                    <div
                        class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 hover:border-zinc-700 transition space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-purple-950/80 text-purple-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-solid fa-store"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('Branded Tour Storefront') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Share your official booking link on Instagram, WhatsApp, and Google. Use your free web link
                            or connect your own custom domain (yourtours.com).
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div
                        class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 hover:border-zinc-700 transition space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-sky-950/80 text-sky-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-solid fa-link"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('1-Click Direct Booking & Payment Links') }}
                        </h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Create reservations on behalf of your guests during WhatsApp or phone chats and send them a
                            direct link to double-check and pay in seconds.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div
                        class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 hover:border-zinc-700 transition space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-emerald-950/80 text-emerald-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-brands fa-whatsapp"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('WhatsApp Vouchers & Reminders') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Send pre-filled e-tickets, payment recovery links, departure preparation notes, and meeting
                            point location pins directly to guests on WhatsApp in 1 tap.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div
                        class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 hover:border-zinc-700 transition space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-amber-950/80 text-amber-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('Google & Apple Calendar Sync') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            All new bookings automatically sync to your team's Google Calendar or Apple Calendar so your
                            tour guides and team members always know the schedule.
                        </p>
                    </div>

                    <!-- Feature 5 -->
                    <div
                        class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 hover:border-zinc-700 transition space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-pink-950/80 text-pink-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-solid fa-address-book"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('Customer Directory & Guest CRM') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Automatically save all guest contact info, booking history, and preferences so you can
                            recognize VIP travelers and offer special perks to repeat guests.
                        </p>
                    </div>

                    <!-- Feature 6 -->
                    <div
                        class="p-8 rounded-3xl bg-zinc-900/60 border border-zinc-800 hover:border-zinc-700 transition space-y-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-teal-950/80 text-teal-400 flex items-center justify-center text-xl shadow-xs">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">{{ __('Direct Payouts to Your Bank') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Keep 100% of your tour ticket price. Track your earnings in real-time and request payouts
                            straight to your Indonesian bank account anytime.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing & Subscription Plans Section -->
        <section id="pricing" class="py-24 border-t border-zinc-800 relative scroll-mt-16" x-data="{ billing_interval: 'monthly' }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">

                <!-- Section Header & Billing Interval Toggle -->
                <div class="text-center space-y-4 max-w-2xl mx-auto">
                    <span
                        class="text-xs font-extrabold uppercase tracking-wider text-purple-400 block">{{ __('Simple, Honest Pricing') }}</span>
                    <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                        Start free. Keep 100% of your ticket price.
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                        Unlike other booking platforms that take 15% to 30% of your earnings, our system lets you <span
                            class="text-white font-bold">keep 100% of your tour price</span>.
                    </p>

                    <!-- Toggle -->
                    <div
                        class="inline-flex p-1 rounded-2xl bg-zinc-900 border border-zinc-800 self-center shadow-inner mt-4">
                        <button type="button" x-on:click="billing_interval = 'monthly'"
                            :class="billing_interval === 'monthly' ? 'bg-purple-600 text-white shadow-md' :
                                'text-zinc-400 hover:text-white'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                            {{ __('Monthly Billing') }}
                        </button>
                        <button type="button" x-on:click="billing_interval = 'yearly'"
                            :class="billing_interval === 'yearly' ? 'bg-purple-600 text-white shadow-md' :
                                'text-zinc-400 hover:text-white'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2">
                            <span>{{ __('Annual Billing') }}</span>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-400 text-zinc-950">{{ __('Save 17%') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Pricing Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch max-w-7xl mx-auto">
                    @foreach ($plans as $plan)
                        @php
                            $priceMonthly = (float) $plan->price_monthly;
                            $priceYearly = (float) $plan->price_yearly;
                        @endphp
                        <div
                            class="rounded-3xl bg-zinc-900/80 border {{ $plan->is_popular ? 'border-purple-500 ring-2 ring-purple-500/30 shadow-2xl shadow-purple-500/10' : 'border-zinc-800 shadow-md' }} p-6 sm:p-7 flex flex-col justify-between relative transition hover:border-zinc-700">

                            <div class="space-y-5">
                                <!-- Header & Badge -->
                                <div class="flex items-start justify-between gap-2 min-h-[32px]">
                                    <h3 class="font-black text-xl text-white tracking-tight">
                                        {{ $plan->name }}
                                    </h3>

                                    @if ($plan->is_popular)
                                        <span
                                            class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase bg-purple-600 text-white shadow-xs shrink-0">
                                            {{ __('Most Popular') }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Tagline -->
                                <p class="text-xs text-zinc-400 min-h-[36px] leading-relaxed">
                                    {{ $plan->tagline }}
                                </p>

                                <!-- Price Box -->
                                <div class="p-4 rounded-2xl bg-zinc-950/80 border border-zinc-800 space-y-2">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl sm:text-3xl font-black text-white tracking-tight"
                                            x-text="billing_interval === 'yearly' ? 'Rp {{ number_format($priceYearly, 0, ',', '.') }}' : 'Rp {{ number_format($priceMonthly, 0, ',', '.') }}'">
                                            Rp {{ number_format($priceMonthly, 0, ',', '.') }}
                                        </span>
                                        <span class="text-[11px] font-semibold text-zinc-500"
                                            x-text="billing_interval === 'yearly' ? '/ yr' : '/ mo'">
                                            / mo
                                        </span>
                                    </div>

                                    <div class="space-y-1.5 pt-2 border-t border-zinc-800 text-[11px]">
                                        <div class="flex items-center justify-between">
                                            <span class="text-zinc-400 font-medium">{{ __('Your Payout') }}</span>
                                            <span class="font-black font-mono text-xs text-emerald-400">
                                                {{ __('100% Net to You') }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between text-[10px] text-zinc-500">
                                            <span>{{ __('Guest Service Fee') }}</span>
                                            <span class="font-medium text-zinc-300">
                                                @if (in_array($plan->slug, ['enterprise', 'ai_ultimate']))
                                                    {{ __('0% (BYO Gateway)') }}
                                                @else
                                                    {{ __('5% Paid by Guest') }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Limits -->
                                <div class="grid grid-cols-2 gap-2 text-center">
                                    <div class="p-2.5 rounded-xl bg-zinc-950/50 border border-zinc-800">
                                        <span
                                            class="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block">{{ __('Tour Packages') }}</span>
                                        <span class="font-extrabold text-xs text-zinc-200 mt-0.5 block">
                                            {{ $plan->package_limit ? __(':count Packages', ['count' => $plan->package_limit]) : __('Unlimited') }}
                                        </span>
                                    </div>
                                    <div class="p-2.5 rounded-xl bg-zinc-950/50 border border-zinc-800">
                                        <span
                                            class="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block">{{ __('Team Staff Seats') }}</span>
                                        <span class="font-extrabold text-xs text-zinc-200 mt-0.5 block">
                                            {{ $plan->team_member_limit ? __(':count Staff', ['count' => $plan->team_member_limit]) : __('Unlimited Staff') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Features Checklist -->
                                <div class="space-y-2.5 pt-3 border-t border-zinc-800">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Included Features') }}</span>
                                    <ul class="space-y-2 text-[11px]">
                                        <li
                                            class="flex items-start gap-2 {{ $plan->hasFeature('quick_booking_links') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i
                                                class="fa-solid {{ $plan->hasFeature('quick_booking_links') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('1-Click Direct Booking Links') }}</span>
                                        </li>
                                        <li
                                            class="flex items-start gap-2 {{ $plan->hasFeature('tracking_pixels') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i
                                                class="fa-solid {{ $plan->hasFeature('tracking_pixels') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Meta Pixel & GA4 (ROAS)') }}</span>
                                        </li>
                                        <li
                                            class="flex items-start gap-2 {{ $plan->hasFeature('google_calendar') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i
                                                class="fa-solid {{ $plan->hasFeature('google_calendar') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Google & Apple Calendar Sync') }}</span>
                                        </li>
                                        <li
                                            class="flex items-start gap-2 {{ $plan->hasFeature('guest_crm') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i
                                                class="fa-solid {{ $plan->hasFeature('guest_crm') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Guest Directory CRM & LTV') }}</span>
                                        </li>
                                        <li
                                            class="flex items-start gap-2 {{ $plan->hasFeature('whatsapp_dispatch') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i
                                                class="fa-solid {{ $plan->hasFeature('whatsapp_dispatch') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('1-Click WhatsApp Tickets & Reminders') }}</span>
                                        </li>
                                        <li
                                            class="flex items-start gap-2 {{ $plan->hasFeature('custom_domain') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i
                                                class="fa-solid {{ $plan->hasFeature('custom_domain') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Custom Domain (`yourbrand.com`)') }}</span>
                                        </li>
                                        <li
                                            class="flex items-start gap-2 {{ $plan->hasFeature('byo_gateway') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i
                                                class="fa-solid {{ $plan->hasFeature('byo_gateway') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('BYO Merchant Payment Keys') }}</span>
                                        </li>
                                        <li
                                            class="flex items-start gap-2 {{ $plan->hasFeature('ai_discovery') ? 'text-purple-300 font-bold' : 'text-zinc-600 line-through' }}">
                                            <i
                                                class="fa-solid {{ $plan->hasFeature('ai_discovery') ? 'fa-bolt-lightning text-purple-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('ChatGPT AI Search Feed (`/llms.txt`)') }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- CTA Button -->
                            <div class="pt-6 border-t border-zinc-800 mt-5">
                                <a href="{{ route('register') }}"
                                    class="w-full h-11 rounded-2xl {{ $plan->is_popular ? 'bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white shadow-lg shadow-purple-500/25' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-100 border border-zinc-700' }} font-extrabold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer"
                                    wire:navigate>
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                    <span>{{ __('Get Started with :plan', ['plan' => $plan->name]) }}</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Feature Comparison Table Accordion (Closed by default) -->
                <div x-data="{ showComparison: false }" class="max-w-6xl mx-auto space-y-4 pt-4">
                    <!-- Accordion Trigger Button -->
                    <div class="text-center">
                        <button type="button" @click="showComparison = !showComparison"
                            class="inline-flex items-center gap-3 px-6 py-3.5 rounded-2xl bg-zinc-900/90 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 text-zinc-200 text-xs sm:text-sm font-bold transition-all shadow-sm group cursor-pointer">
                            <span
                                class="p-1.5 rounded-lg bg-purple-500/10 text-purple-400 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-table-list text-xs"></i>
                            </span>
                            <span
                                x-text="showComparison ? '{{ __('Hide Detailed Plan Comparison') }}' : '{{ __('Compare All Plan Features & Capabilities') }}'">
                                {{ __('Compare All Plan Features & Capabilities') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-300"
                                :class="showComparison ? 'rotate-180 text-purple-400' : ''"></i>
                        </button>
                    </div>

                    <!-- Accordion Content Panel -->
                    <div x-show="showComparison" x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-2" style="display: none;"
                        class="rounded-3xl bg-zinc-900/90 border border-zinc-800 shadow-2xl overflow-hidden p-6 sm:p-8">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse min-w-[800px]">
                                <thead>
                                    <tr class="border-b border-zinc-800 text-zinc-400">
                                        <th
                                            class="py-4 pr-4 font-extrabold uppercase tracking-wider text-[11px] w-2/6">
                                            {{ __('Features & Capabilities') }}</th>
                                        <th
                                            class="py-4 px-3 font-black uppercase tracking-wider text-[10px] text-center w-1/6 text-zinc-300">
                                            <span>Starter Essential</span>
                                            <span
                                                class="block text-[9px] font-normal text-zinc-500 mt-0.5">{{ __('Free Forever') }}</span>
                                        </th>
                                        <th
                                            class="py-4 px-3 font-black uppercase tracking-wider text-[10px] text-center w-1/6 text-purple-400 bg-purple-500/5 rounded-t-2xl">
                                            <span>Pro Operator</span>
                                            <span class="block text-[9px] font-normal text-purple-300/80 mt-0.5">Rp 299.000 / mo</span>
                                        </th>
                                        <th
                                            class="py-4 px-3 font-black uppercase tracking-wider text-[10px] text-center w-1/6 text-indigo-400">
                                            <span>Agency Ultimate</span>
                                            <span class="block text-[9px] font-normal text-zinc-500 mt-0.5">Rp 699.000 / mo</span>
                                        </th>
                                        <th
                                            class="py-4 px-3 font-black uppercase tracking-wider text-[10px] text-center w-1/6 text-amber-300">
                                            <span>AI Ultimate Agency</span>
                                            <span class="block text-[9px] font-normal text-amber-400/80 mt-0.5">Rp 999.000 / mo</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800/60">
                                    <!-- Category: Commercials -->
                                    <tr class="bg-zinc-950/60">
                                        <td colspan="5"
                                            class="py-3 px-3 font-extrabold text-[11px] uppercase tracking-wider text-purple-400">
                                            {{ __('1. Commercials & Payouts') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Operator Commission Cut') }}</td>
                                        <td class="py-3.5 px-3 text-center font-bold text-emerald-400">0% (100% Net)</td>
                                        <td class="py-3.5 px-3 text-center font-bold text-emerald-400 bg-purple-500/5">0% (100% Net)</td>
                                        <td class="py-3.5 px-3 text-center font-bold text-emerald-400">0% (100% Net)</td>
                                        <td class="py-3.5 px-3 text-center font-bold text-emerald-400">0% (100% Net)</td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Guest Online Booking Fee') }}</td>
                                        <td class="py-3.5 px-3 text-center text-zinc-400">5.0%</td>
                                        <td class="py-3.5 px-3 text-center text-zinc-400 bg-purple-500/5">5.0%</td>
                                        <td class="py-3.5 px-3 text-center font-bold text-indigo-400">0% (BYO Gateway)</td>
                                        <td class="py-3.5 px-3 text-center font-bold text-indigo-400">0% (BYO Gateway)</td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Direct Payouts to Indonesian Bank Account') }}</td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center bg-purple-500/5"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>

                                    <!-- Category: Storefront & Web Presence -->
                                    <tr class="bg-zinc-950/60">
                                        <td colspan="5"
                                            class="py-3 px-3 font-extrabold text-[11px] uppercase tracking-wider text-purple-400">
                                            {{ __('2. Storefront & Web Presence') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Free Subdomain (`slug.booking.emvi`)') }}</td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center bg-purple-500/5"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Custom Website Domain (`yourbrand.com`) + Auto-SSL') }}</td>
                                        <td class="py-3.5 px-3 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3.5 px-3 text-center text-zinc-600 bg-purple-500/5"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3.5 px-3 text-center font-bold text-indigo-400"><i class="fa-solid fa-check text-emerald-400"></i> Included</td>
                                        <td class="py-3.5 px-3 text-center font-bold text-indigo-400"><i class="fa-solid fa-check text-emerald-400"></i> Included</td>
                                    </tr>

                                    <!-- Category: Booking Engine & Inventory -->
                                    <tr class="bg-zinc-950/60">
                                        <td colspan="5"
                                            class="py-3 px-3 font-extrabold text-[11px] uppercase tracking-wider text-purple-400">
                                            {{ __('3. Booking Engine & Inventory') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Tour Package Listings Limit') }}</td>
                                        <td class="py-3.5 px-3 text-center font-semibold text-zinc-300">5 Packages</td>
                                        <td class="py-3.5 px-3 text-center font-bold text-purple-300 bg-purple-500/5">25 Packages</td>
                                        <td class="py-3.5 px-3 text-center font-extrabold text-emerald-400">Unlimited</td>
                                        <td class="py-3.5 px-3 text-center font-extrabold text-emerald-400">Unlimited</td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Team Staff Seats & Role Accounts') }}</td>
                                        <td class="py-3.5 px-3 text-center font-semibold text-emerald-400">Unlimited</td>
                                        <td class="py-3.5 px-3 text-center font-semibold text-emerald-400 bg-purple-500/5">Unlimited</td>
                                        <td class="py-3.5 px-3 text-center font-semibold text-emerald-400">Unlimited</td>
                                        <td class="py-3.5 px-3 text-center font-semibold text-emerald-400">Unlimited</td>
                                    </tr>

                                    <!-- Category: Automation & AI Flagship -->
                                    <tr class="bg-zinc-950/60">
                                        <td colspan="5"
                                            class="py-3 px-3 font-extrabold text-[11px] uppercase tracking-wider text-purple-400">
                                            {{ __('4. Automation & AI Discovery') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Google & Apple Calendar Live Sync (iCal Feed)') }}</td>
                                        <td class="py-3.5 px-3 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3.5 px-3 text-center bg-purple-500/5"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('Customer Directory & Guest CRM (LTV History)') }}</td>
                                        <td class="py-3.5 px-3 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3.5 px-3 text-center bg-purple-500/5"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-zinc-300 font-medium">
                                            {{ __('1-Click WhatsApp Tickets & Reminders Dispatch') }}</td>
                                        <td class="py-3.5 px-3 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3.5 px-3 text-center bg-purple-500/5"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3.5 px-3 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3.5 pr-4 text-purple-300 font-bold">
                                            {{ __('AI Search Engine Feed (/llms.txt) & Unblocked AI Crawlers') }}</td>
                                        <td class="py-3.5 px-3 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3.5 px-3 text-center text-zinc-600 bg-purple-500/5"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3.5 px-3 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3.5 px-3 text-center font-extrabold text-amber-300"><i class="fa-solid fa-bolt-lightning text-amber-400 mr-1"></i> Flagship Included</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Honest Pricing Note -->
                <div
                    class="p-6 rounded-3xl bg-zinc-900/50 border border-zinc-800 max-w-3xl mx-auto text-center space-y-2">
                    <div class="inline-flex items-center gap-2 text-xs font-bold text-purple-400">
                        <i class="fa-solid fa-heart"></i>
                        <span>{{ __('Zero Commission Guarantee') }}</span>
                    </div>
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        You work hard to provide great tour experiences. We never take a percentage cut of your ticket
                        earnings. You keep 100% of your listed prices.
                    </p>
                </div>
            </div>
        </section>

        <!-- Frequently Asked Questions (FAQ) Section -->
        <section id="faq" class="py-24 border-t border-zinc-800 bg-zinc-950/60 relative scroll-mt-16"
            x-data="{ activeAccordion: null }">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                <div class="text-center space-y-3">
                    <span
                        class="text-xs font-extrabold uppercase tracking-wider text-purple-400 block">{{ __('Frequently Asked Questions') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                        {{ __('Everything you need to know') }}
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-400 max-w-xl mx-auto leading-relaxed">
                        {{ __('Have questions about payouts, plans, or setting up your tour website? Here are quick answers.') }}
                    </p>
                </div>

                <div class="space-y-4">
                    <!-- FAQ Item 1 -->
                    <div class="rounded-2xl bg-zinc-900/80 border border-zinc-800 overflow-hidden transition-all">
                        <button type="button" @click="activeAccordion = activeAccordion === 1 ? null : 1"
                            class="w-full p-5 sm:p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-sm sm:text-base text-white">
                                {{ __('How and when do I receive payouts from my tour bookings?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 1 ? 'rotate-180 text-purple-400' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 1" x-collapse
                            class="px-5 sm:px-6 pb-6 text-xs sm:text-sm text-zinc-400 leading-relaxed border-t border-zinc-800/60 pt-4"
                            style="display: none;">
                            {{ __('When a traveler pays online for your tour via QRIS, Virtual Account, or Credit Card, funds land in your operator wallet balance. Payouts up to Rp 10.000.000 are automatically disbursed via DOKU BI-FAST directly into your Indonesian bank account (BCA, Mandiri, BRI, BNI, and more) in under 3 seconds.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 2 -->
                    <div class="rounded-2xl bg-zinc-900/80 border border-zinc-800 overflow-hidden transition-all">
                        <button type="button" @click="activeAccordion = activeAccordion === 2 ? null : 2"
                            class="w-full p-5 sm:p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-sm sm:text-base text-white">
                                {{ __('Do I need any web design or coding skills?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 2 ? 'rotate-180 text-purple-400' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 2" x-collapse
                            class="px-5 sm:px-6 pb-6 text-xs sm:text-sm text-zinc-400 leading-relaxed border-t border-zinc-800/60 pt-4"
                            style="display: none;">
                            {{ __('Not at all! Your storefront website and complete booking engine are created for you automatically. You simply upload your tour photos, choose your prices, and enter your WhatsApp contact number. Your site is ready to share on Instagram or WhatsApp in under 5 minutes.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 3 -->
                    <div class="rounded-2xl bg-zinc-900/80 border border-zinc-800 overflow-hidden transition-all">
                        <button type="button" @click="activeAccordion = activeAccordion === 3 ? null : 3"
                            class="w-full p-5 sm:p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-sm sm:text-base text-white">
                                {{ __('How does the 100% Net Payout / Zero Commission work?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 3 ? 'rotate-180 text-purple-400' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 3" x-collapse
                            class="px-5 sm:px-6 pb-6 text-xs sm:text-sm text-zinc-400 leading-relaxed border-t border-zinc-800/60 pt-4"
                            style="display: none;">
                            {{ __('Unlike OTAs that cut 15% to 30% from your ticket revenue, our platform charges 0% commission to the operator. A standard 5.0% guest service fee is added to the guest cart at checkout (identical to Loket or FareHarbor), meaning you receive 100% of your listed tour price.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 4 -->
                    <div class="rounded-2xl bg-zinc-900/80 border border-zinc-800 overflow-hidden transition-all">
                        <button type="button" @click="activeAccordion = activeAccordion === 4 ? null : 4"
                            class="w-full p-5 sm:p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-sm sm:text-base text-white">
                                {{ __('Can I use my own custom website domain (e.g. youragency.com)?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 4 ? 'rotate-180 text-purple-400' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 4" x-collapse
                            class="px-5 sm:px-6 pb-6 text-xs sm:text-sm text-zinc-400 leading-relaxed border-t border-zinc-800/60 pt-4"
                            style="display: none;">
                            {{ __('Yes! On the Agency Ultimate plan, you can connect your existing website domain or subdomain (e.g. tours.youragency.com). We provide automated SSL encryption and manage the routing seamlessly.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 5 -->
                    <div class="rounded-2xl bg-zinc-900/80 border border-zinc-800 overflow-hidden transition-all">
                        <button type="button" @click="activeAccordion = activeAccordion === 5 ? null : 5"
                            class="w-full p-5 sm:p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-sm sm:text-base text-white">
                                {{ __('Can I switch plans or cancel anytime?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 5 ? 'rotate-180 text-purple-400' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 5" x-collapse
                            class="px-5 sm:px-6 pb-6 text-xs sm:text-sm text-zinc-400 leading-relaxed border-t border-zinc-800/60 pt-4"
                            style="display: none;">
                            {{ __('Yes. There are no lock-in contracts or long-term commitments. You can start on Starter Essential for free, upgrade to Pro Operator when your tour volume expands, or switch between monthly and annual billing with 1 click.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 6 -->
                    <div class="rounded-2xl bg-zinc-900/80 border border-zinc-800 overflow-hidden transition-all">
                        <button type="button" @click="activeAccordion = activeAccordion === 6 ? null : 6"
                            class="w-full p-5 sm:p-6 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-sm sm:text-base text-white">
                                {{ __('Can my staff and tour guides have their own login accounts?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 6 ? 'rotate-180 text-purple-400' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 6" x-collapse
                            class="px-5 sm:px-6 pb-6 text-xs sm:text-sm text-zinc-400 leading-relaxed border-t border-zinc-800/60 pt-4"
                            style="display: none;">
                            {{ __('Yes! All subscription plans (including Starter Essential) include unlimited team member seats. You can invite your reservation coordinators, field guides, and team members with tailored access permissions.') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Bottom Call to Action Section -->
        <section class="py-20 border-t border-zinc-800 bg-gradient-to-b from-zinc-950 to-purple-950/20">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center space-y-6">
                <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                    Ready to start selling your tours direct?
                </h2>
                <p class="text-xs sm:text-sm text-zinc-400 max-w-xl mx-auto leading-relaxed">
                    Set up your tour packages, connect your bank account, and start accepting online payments in under
                    10 minutes.
                </p>
                <div class="pt-4">
                    <a href="{{ route('register') }}"
                        class="h-13 px-9 rounded-2xl bg-gradient-to-r from-purple-600 via-indigo-600 to-sky-500 hover:from-purple-500 hover:to-indigo-500 text-white font-extrabold text-sm sm:text-base shadow-xl shadow-purple-500/25 transition-all inline-flex items-center gap-2"
                        wire:navigate>
                        <span>{{ __('Create Your Tour Storefront Now') }}</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-zinc-900 bg-zinc-950 py-12 text-xs text-zinc-500">
        <div
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <div
                    class="w-8 h-8 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-sm shadow-xs">
                    <i class="fa-solid fa-compass"></i>
                </div>
                <span class="font-bold text-zinc-300 text-sm">{{ config('app.name', 'Emvi') }} Booking Engine</span>
            </div>

            <div class="flex items-center gap-6">
                <a href="{{ route('login') }}" class="hover:text-zinc-300 transition"
                    wire:navigate>{{ __('Operator Login') }}</a>
                <a href="{{ route('register') }}" class="hover:text-zinc-300 transition"
                    wire:navigate>{{ __('Operator Register') }}</a>
            </div>

            <p class="text-[11px] text-zinc-600">
                &copy; {{ date('Y') }} {{ config('app.name', 'Emvi') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </footer>
    @livewireScripts
</body>

</html>
