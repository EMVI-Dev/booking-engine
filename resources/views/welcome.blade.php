<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">

<head>
    @include('partials.head')
    @livewireStyles
</head>

<body class="min-h-screen bg-[#09090b] text-zinc-100 antialiased selection:bg-[#FFEF4D] selection:text-[#090d16] font-sans">
    @php
        $registrationOpen = \App\Models\PlatformSetting::current()->operatorRegistrationAllowed();
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get();
        if ($plans->isEmpty()) {
            \App\Models\Plan::seedDefaultPlans();
            $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get();
        }
        $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
        $demoStorefrontUrl = request()->getScheme().'://'.config('demo.slug', 'demo').'.'.$platformDomain;
        $demoOperatorLoginUrl = $demoStorefrontUrl.'/login';
        $planFeatureRows = [
            ['key' => 'quick_booking_links', 'label' => __('Pay links you can copy and share')],
            ['key' => 'promotional_coupons', 'label' => __('Coupons for guests')],
            ['key' => 'google_calendar', 'label' => __('Trips on Google & phone calendar')],
            ['key' => 'whatsapp_dispatch', 'label' => __('WhatsApp tickets and reminders')],
            ['key' => 'daily_manifest_export', 'label' => __('Printable daily guest lists')],
            ['key' => 'guest_crm', 'label' => __('Guest contact list')],
            ['key' => 'automated_review_requests', 'label' => __('Ask for a review after the trip')],
            ['key' => 'custom_domain', 'label' => __('Your own website address (yourbrand.com)')],
            ['key' => 'remove_branding', 'label' => __('Guests see only your name, not ours')],
            ['key' => 'ai_discovery', 'label' => __('Show up when people ask ChatGPT about tours')],
        ];
    @endphp

    <!-- Top Announcement Bar (Solid & Crisp) -->
    <div class="border-b border-zinc-800 bg-[#131316] py-2 px-4 text-center text-xs text-zinc-300">
        <span class="inline-flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#FFEF4D]"></span>
            <span class="font-bold text-[#FFEF4D]">{{ __('You keep 100% of the ticket') }}</span>
            <span class="text-zinc-600 hidden sm:inline">•</span>
            <span class="hidden sm:inline">{{ __('The listed price is yours. Money goes to your Indonesian bank.') }}</span>
        </span>
    </div>

    <!-- Header Navigation Bar -->
    <header class="border-b border-zinc-800 sticky top-0 z-50 bg-[#09090b]/95 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between gap-4">
            <!-- Platform Brand Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 select-none shrink-0">
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-sm sm:text-base font-black shrink-0 shadow-xs">
                    <i class="fa-solid fa-compass"></i>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-black text-base sm:text-lg tracking-tight text-white leading-none">
                        {{ config('app.name', 'TravelEngine') }}
                    </span>
                    <span class="text-[9px] sm:text-[10px] px-1.5 py-0.5 rounded-md bg-[#FFEF4D]/15 text-[#FFEF4D] font-black uppercase tracking-wider border border-[#FFEF4D]/30 hidden xs:inline-block">
                        Booking
                    </span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden md:flex items-center gap-8 text-xs font-semibold text-zinc-400">
                <a href="#how-it-works" class="hover:text-white transition">{{ __('How It Works') }}</a>
                <a href="#features" class="hover:text-white transition">{{ __('Features') }}</a>
                <a href="#pricing" class="hover:text-white transition">{{ __('Pricing & Plans') }}</a>
                <a href="#faq" class="hover:text-white transition">{{ __('FAQ') }}</a>
            </nav>

            <!-- Auth / Action Buttons -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                @auth
                    <a href="{{ route('dashboard') }}"
                        class="h-8 sm:h-9 px-3.5 sm:px-4 rounded-lg bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                        wire:navigate>
                        <i class="fa-solid fa-gauge text-[11px]"></i>
                        <span>{{ __('Dashboard') }}</span>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="px-2.5 sm:px-3 py-1.5 text-white hover:text-[#FFEF4D] text-xs font-semibold transition"
                        wire:navigate>
                        {{ __('Operator log in') }}
                    </a>
                    <a href="{{ route('register') }}"
                        class="h-8 sm:h-9 px-3.5 sm:px-4 rounded-lg bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] text-xs font-black transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                        wire:navigate>
                        <span>{{ $registrationOpen ? __('Start Free') : __('Coming soon') }}</span>
                        <i class="fa-solid fa-arrow-right text-[10px] hidden sm:inline"></i>
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="pt-12 pb-14 sm:pt-20 sm:pb-20">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 text-center space-y-6 sm:space-y-8">
                <!-- Eyebrow Pill Tag -->
                <div class="inline-flex max-w-full items-start sm:items-center gap-2 px-3.5 py-1.5 rounded-full border border-zinc-800 bg-[#131316] text-xs font-bold text-[#FFEF4D] text-left sm:text-center">
                    <span class="w-1.5 h-1.5 mt-1.5 sm:mt-0 rounded-full bg-[#FFEF4D] shrink-0"></span>
                    <span class="text-pretty leading-snug">{{ __('For guides and tour shops — no website person needed') }}</span>
                </div>

                <!-- Main Catchy Title -->
                <h1 class="text-2xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-snug sm:leading-tight">
                    {{ __('The simple way to sell your tours online.') }}<br>
                    <span class="text-[#FFEF4D]">
                        {{ __('Guests book themselves. You keep the listed price.') }}
                    </span>
                </h1>

                <!-- Value Proposition Copy -->
                <p class="text-base sm:text-lg text-zinc-400 max-w-3xl mx-auto font-normal leading-relaxed px-1">
                    {{ __('Add your trips, share one link, and let guests pick a date and pay. Copy a pay link into chat, print tomorrow’s guest list for the driver — and keep 100% of the ticket price.') }}
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 pt-2">
                    <a href="{{ route('register') }}"
                        class="w-full sm:w-auto h-11 sm:h-12 px-6 sm:px-8 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-sm transition flex items-center justify-center gap-2 cursor-pointer shadow-sm"
                        wire:navigate>
                        @if ($registrationOpen)
                            <span>{{ __('Create Your Free Tour Website') }}</span>
                        @else
                            <span class="sm:hidden">{{ __('Coming soon') }}</span>
                            <span class="hidden sm:inline">{{ __('Coming soon — we are preparing operator sign-up') }}</span>
                        @endif
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>

                <!-- Feature Badges Bar -->
                <div class="pt-6 sm:pt-8 grid grid-cols-2 sm:flex sm:flex-wrap items-center justify-center gap-4 sm:gap-8 text-sm font-semibold text-zinc-400 border-t border-zinc-800 mt-6 sm:mt-10">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-globe text-[#FFEF4D] text-sm"></i>
                        <span>{{ __('Your own tour shop') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-calendar-check text-[#FFEF4D] text-sm"></i>
                        <span>{{ __('24/7 Online Booking') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-qrcode text-emerald-400 text-sm"></i>
                        <span>{{ __('QRIS & Bank Transfers') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp text-emerald-400 text-sm"></i>
                        <span>{{ __('Tickets on WhatsApp') }}</span>
                    </div>
                    <div class="flex items-center gap-2 col-span-2 sm:col-span-1 justify-center sm:justify-start">
                        <i class="fa-solid fa-sack-dollar text-[#FFEF4D] text-sm"></i>
                        <span>{{ __('You keep 100%') }}</span>
                    </div>
                </div>

                <!-- Mobile: one snapshot of what you get (desktop keeps the full demo) -->
                <div class="md:hidden text-left rounded-2xl border border-zinc-800 bg-[#131316] p-4 space-y-3">
                    <p class="text-xs font-mono text-[#FFEF4D] truncate">yourname.travelengine.online</p>
                    <div class="space-y-1">
                        <p class="text-base font-bold text-white">{{ __('Your own tour website') }}</p>
                        <p class="text-base text-zinc-400 leading-relaxed">
                            {{ __('Guests pick a date, pay with QRIS or bank transfer, and get a WhatsApp ticket. You keep 100% of the listed price.') }}
                        </p>
                    </div>
                </div>

                <!-- Interactive Multi-View Product Showcase -->
                <div class="pt-4 max-w-5xl mx-auto text-left hidden md:block"
                    x-data="{
                        activeTab: 'storefront',
                        selectedTour: 'nusa',
                        tours: {
                            nusa: {
                                name: 'Nusa Penida & Manta Bay Snorkeling Adventure',
                                price: 650000,
                                duration: '8 Hours (Full Day)',
                                pickup: 'Sanur Port Pickup',
                                image: '{{ asset('images/tour-preview.jpg') }}',
                                tag: 'Bestseller Excursion',
                                rating: '4.9 (128 reviews)'
                            },
                            komodo: {
                                name: 'Komodo Islands 3D2N Phinisi Luxury Cruise',
                                price: 3200000,
                                duration: '3 Days 2 Nights',
                                pickup: 'Labuan Bajo Marina',
                                image: '{{ asset('images/komodo-tour.jpg') }}',
                                tag: 'VIP Yacht Charter',
                                rating: '5.0 (84 reviews)'
                            },
                            batur: {
                                name: 'Mount Batur Sunrise 4WD Black Lava Jeep Tour',
                                price: 450000,
                                duration: '6 Hours (Sunrise)',
                                pickup: 'Ubud Hotel Pickup',
                                image: '{{ asset('images/batur-tour.jpg') }}',
                                tag: 'Trending Adventure',
                                rating: '4.9 (210 reviews)'
                            }
                        },
                        guests: 2,
                        selectedDate: 'Tomorrow, 08:00 AM',
                        isBooked: false,
                        confirmBooking() {
                            this.isBooked = true;
                            setTimeout(() => { this.isBooked = false; }, 3500);
                        }
                    }">

                    <!-- Interactive Mode Switcher Tabs -->
                    <div class="flex items-center justify-start sm:justify-center gap-2 overflow-x-auto pb-3 -mx-4 px-4 sm:mx-0 sm:px-0">
                        <button type="button" @click="activeTab = 'storefront'"
                            :class="activeTab === 'storefront' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'bg-[#131316] text-zinc-400 hover:text-white border border-zinc-800 font-medium'"
                            class="px-3.5 py-2 rounded-xl text-xs flex items-center gap-2 transition shrink-0 cursor-pointer">
                            <i class="fa-solid fa-store text-xs"></i>
                            <span>{{ __('1. Guest booking page') }}</span>
                        </button>
                        <button type="button" @click="activeTab = 'manifest'"
                            :class="activeTab === 'manifest' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'bg-[#131316] text-zinc-400 hover:text-white border border-zinc-800 font-medium'"
                            class="px-3.5 py-2 rounded-xl text-xs flex items-center gap-2 transition shrink-0 cursor-pointer">
                            <i class="fa-solid fa-clipboard-list text-xs"></i>
                            <span>{{ __('2. Tomorrow’s guest list') }}</span>
                        </button>
                        <button type="button" @click="activeTab = 'whatsapp'"
                            :class="activeTab === 'whatsapp' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'bg-[#131316] text-zinc-400 hover:text-white border border-zinc-800 font-medium'"
                            class="px-3.5 py-2 rounded-xl text-xs flex items-center gap-2 transition shrink-0 cursor-pointer">
                            <i class="fa-brands fa-whatsapp text-xs text-emerald-400"></i>
                            <span>{{ __('3. WhatsApp E-Tickets') }}</span>
                        </button>
                        <button type="button" @click="activeTab = 'payouts'"
                            :class="activeTab === 'payouts' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'bg-[#131316] text-zinc-400 hover:text-white border border-zinc-800 font-medium'"
                            class="px-3.5 py-2 rounded-xl text-xs flex items-center gap-2 transition shrink-0 cursor-pointer">
                            <i class="fa-solid fa-building-columns text-xs text-emerald-400"></i>
                            <span>{{ __('4. Money to your bank') }}</span>
                        </button>
                    </div>

                    <!-- Safari / macOS Browser Frame -->
                    <div class="rounded-2xl border border-zinc-800 bg-[#131316] overflow-hidden shadow-2xl">
                        <!-- Browser Header Bar -->
                        <div class="px-4 py-3 bg-[#0d0d10] border-b border-zinc-800 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-zinc-700 inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-zinc-700 inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-zinc-700 inline-block"></span>
                            </div>
                            <div class="px-4 py-1 rounded-lg bg-[#131316] border border-zinc-800 text-[11px] text-zinc-300 font-mono flex items-center gap-2 max-w-xs sm:max-w-md w-full justify-center">
                                <i class="fa-solid fa-lock text-[10px] text-emerald-400"></i>
                                <span class="truncate" x-text="activeTab === 'storefront' ? 'https://bali-excursions.travelengine.online' : (activeTab === 'manifest' ? 'https://travelengine.online/manifest/daily' : (activeTab === 'whatsapp' ? 'https://wa.me/628123456789' : 'https://travelengine.online/wallet/payouts'))"></span>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-zinc-500">
                                <span class="text-[10px] font-bold text-[#FFEF4D] bg-[#FFEF4D]/15 px-2 py-0.5 rounded-md border border-[#FFEF4D]/30 uppercase tracking-wider">Verified Live</span>
                            </div>
                        </div>

                        <!-- TAB 1: STOREFRONT PREVIEW -->
                        <div x-show="activeTab === 'storefront'" class="p-4 sm:p-6 lg:p-7 space-y-5">
                            <!-- Tour Selector Switcher -->
                            <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
                                <span class="text-zinc-500 font-bold text-[11px] uppercase tracking-wider shrink-0 mr-1">{{ __('Try Tour:') }}</span>
                                <button type="button" @click="selectedTour = 'nusa'"
                                    :class="selectedTour === 'nusa' ? 'bg-[#FFEF4D] text-[#090d16] font-black border-[#FFEF4D]' : 'bg-[#09090b] text-zinc-400 border-zinc-800'"
                                    class="px-3 py-1 rounded-lg border text-xs font-bold shrink-0 cursor-pointer inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-umbrella-beach text-[10px]"></i>
                                    Nusa Penida Snorkeling
                                </button>
                                <button type="button" @click="selectedTour = 'komodo'"
                                    :class="selectedTour === 'komodo' ? 'bg-[#FFEF4D] text-[#090d16] font-black border-[#FFEF4D]' : 'bg-[#09090b] text-zinc-400 border-zinc-800'"
                                    class="px-3 py-1 rounded-lg border text-xs font-bold shrink-0 cursor-pointer inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-ship text-[10px]"></i>
                                    Komodo Phinisi Cruise
                                </button>
                                <button type="button" @click="selectedTour = 'batur'"
                                    :class="selectedTour === 'batur' ? 'bg-[#FFEF4D] text-[#090d16] font-black border-[#FFEF4D]' : 'bg-[#09090b] text-zinc-400 border-zinc-800'"
                                    class="px-3 py-1 rounded-lg border text-xs font-bold shrink-0 cursor-pointer inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-mountain text-[10px]"></i>
                                    Mount Batur Jeep Safari
                                </button>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                                <!-- Left: Tour Card Showcase (7 cols) -->
                                <div class="lg:col-span-7 space-y-4">
                                    <div class="relative rounded-xl overflow-hidden aspect-video border border-zinc-800">
                                        <img :src="tours[selectedTour].image" :alt="tours[selectedTour].name" class="w-full h-full object-cover">
                                        <div class="absolute top-3 left-3 flex items-center gap-2">
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-[#FFEF4D] text-[#090d16]" x-text="tours[selectedTour].tag">
                                            </span>
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-zinc-950/90 text-zinc-200 border border-zinc-800">
                                                <i class="fa-solid fa-star text-amber-400 text-[9px] mr-1"></i><span x-text="tours[selectedTour].rating"></span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <h3 class="font-bold text-base sm:text-lg text-white tracking-tight leading-tight" x-text="tours[selectedTour].name">
                                        </h3>
                                        <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-400">
                                            <span class="flex items-center gap-1"><i class="fa-solid fa-clock text-zinc-500"></i> <span x-text="tours[selectedTour].duration"></span></span>
                                            <span>•</span>
                                            <span class="flex items-center gap-1"><i class="fa-solid fa-location-dot text-zinc-500"></i> <span x-text="tours[selectedTour].pickup"></span></span>
                                            <span>•</span>
                                            <span class="flex items-center gap-1 text-emerald-400"><i class="fa-solid fa-circle-check"></i> Instant Confirmation</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Live Interactive Booking Widget (5 cols) -->
                                <div class="lg:col-span-5 rounded-xl bg-[#0d0d10] border border-zinc-800 p-4 sm:p-5 space-y-4">
                                    <div class="flex items-baseline justify-between border-b border-zinc-800 pb-3">
                                        <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Ticket Price') }}</span>
                                        <div class="text-right">
                                            <span class="text-lg sm:text-xl font-black text-white" x-text="'Rp ' + tours[selectedTour].price.toLocaleString('id-ID')"></span>
                                            <span class="text-[10px] text-zinc-500">/ person</span>
                                        </div>
                                    </div>

                                    <!-- Date Picker Simulator -->
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-semibold text-zinc-300 block">{{ __('Select Departure Date') }}</label>
                                        <div class="grid grid-cols-3 gap-1.5">
                                            <button type="button" @click="selectedDate = 'Today, 08:00 AM'"
                                                :class="selectedDate.startsWith('Today') ? 'bg-[#FFEF4D] text-[#090d16] font-black border-[#FFEF4D]' : 'bg-[#131316] text-zinc-400 border-zinc-800 hover:text-white'"
                                                class="py-2 px-1.5 rounded-lg text-center border text-[11px] font-bold transition cursor-pointer">
                                                <span>{{ __('Today') }}</span>
                                            </button>
                                            <button type="button" @click="selectedDate = 'Tomorrow, 08:00 AM'"
                                                :class="selectedDate.startsWith('Tomorrow') ? 'bg-[#FFEF4D] text-[#090d16] font-black border-[#FFEF4D]' : 'bg-[#131316] text-zinc-400 border-zinc-800 hover:text-white'"
                                                class="py-2 px-1.5 rounded-lg text-center border text-[11px] font-bold transition cursor-pointer">
                                                <span>{{ __('Tomorrow') }}</span>
                                            </button>
                                            <button type="button" @click="selectedDate = 'Saturday, 08:00 AM'"
                                                :class="selectedDate.startsWith('Saturday') ? 'bg-[#FFEF4D] text-[#090d16] font-black border-[#FFEF4D]' : 'bg-[#131316] text-zinc-400 border-zinc-800 hover:text-white'"
                                                class="py-2 px-1.5 rounded-lg text-center border text-[11px] font-bold transition cursor-pointer">
                                                <span>{{ __('Saturday') }}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Guest Counter (Live Reactive with Alpine) -->
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-semibold text-zinc-300 flex items-center justify-between">
                                            <span>{{ __('Guests / Passengers') }}</span>
                                            <span class="text-[10px] text-zinc-500">{{ __('Max 12 slots') }}</span>
                                        </label>
                                        <div class="flex items-center justify-between p-2 rounded-lg bg-[#131316] border border-zinc-800">
                                            <span class="text-xs text-zinc-300 font-semibold pl-2">
                                                <span x-text="guests"></span> {{ __('Guests') }}
                                            </span>
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" @click="if (guests > 1) guests--"
                                                    class="w-7 h-7 rounded-md bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-bold flex items-center justify-center transition cursor-pointer">
                                                    -
                                                </button>
                                                <button type="button" @click="if (guests < 10) guests++"
                                                    class="w-7 h-7 rounded-md bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] text-xs font-black flex items-center justify-center transition cursor-pointer">
                                                    +
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Total Calculation Box -->
                                    <div class="p-3 rounded-lg bg-[#131316] border border-zinc-800 space-y-1 text-xs">
                                        <div class="flex items-center justify-between text-zinc-400 text-[11px]">
                                            <span>{{ __('Total Booking Amount') }}</span>
                                            <span class="font-black text-[#FFEF4D] font-mono text-sm" x-text="'Rp ' + (guests * tours[selectedTour].price).toLocaleString('id-ID')"></span>
                                        </div>
                                        <div class="flex items-center justify-between text-[10px] text-emerald-400">
                                            <span>{{ __('Operator Payout (0% Cut)') }}</span>
                                            <span class="font-bold font-mono" x-text="'Rp ' + (guests * tours[selectedTour].price).toLocaleString('id-ID')"></span>
                                        </div>
                                    </div>

                                    <!-- Live Interactive CTA Button -->
                                    <button type="button" @click="confirmBooking()"
                                        class="w-full h-10 rounded-lg bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                                        <span x-show="!isBooked">{{ __('Book Now & Pay Online') }}</span>
                                        <span x-show="isBooked" class="flex items-center gap-1.5 text-[#090d16] font-black" style="display: none;">
                                            <i class="fa-solid fa-circle-check text-emerald-700"></i>
                                            {{ __('Paid — ticket sent to WhatsApp') }}
                                        </span>
                                        <i x-show="!isBooked" class="fa-solid fa-arrow-right text-[11px]"></i>
                                    </button>

                                    <!-- Payment Method Badges -->
                                    <div class="pt-2 flex items-center justify-center gap-3 text-[10px] text-zinc-500 border-t border-zinc-800">
                                        <span class="flex items-center gap-1"><i class="fa-solid fa-qrcode text-emerald-400"></i> QRIS</span>
                                        <span>•</span>
                                        <span>BCA / Mandiri / BRI</span>
                                        <span>•</span>
                                        <span>Cards</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: DAILY MANIFEST & DISPATCH -->
                        <div x-show="activeTab === 'manifest'" style="display: none;" class="p-4 sm:p-6 lg:p-7 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-800 pb-4">
                                <div>
                                    <h4 class="font-bold text-base text-white">Tomorrow’s guest list — Speedboat Ocean Express #2</h4>
                                    <p class="text-xs text-zinc-400">Departure: Sanur Port Gate 3 • 08:30 AM (Captain: Wayan Sudirta)</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs px-2.5 py-1 rounded-lg bg-[#FFEF4D] text-[#090d16] font-black">
                                        12 / 12 Seats Filled (100%)
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse min-w-[600px]">
                                    <thead>
                                        <tr class="border-b border-zinc-800 text-zinc-400 text-[11px]">
                                            <th class="py-2.5 pr-3">Guest Name & Pax</th>
                                            <th class="py-2.5 px-3">Hotel Pickup Location</th>
                                            <th class="py-2.5 px-3">Payment Method</th>
                                            <th class="py-2.5 px-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-800 text-xs">
                                        <tr>
                                            <td class="py-3 pr-3 font-semibold text-white">Sarah Miller (2 Pax)</td>
                                            <td class="py-3 px-3 text-zinc-300">Hilton Bali Resort (Lobby @ 06:45 AM)</td>
                                            <td class="py-3 px-3 text-emerald-400 font-mono font-bold">QRIS (Paid)</td>
                                            <td class="py-3 px-3 text-center"><span class="px-2 py-0.5 rounded-md bg-emerald-950 text-emerald-400 border border-emerald-800 font-bold text-[10px]">Checked In</span></td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 pr-3 font-semibold text-white">David K. & Family (4 Pax)</td>
                                            <td class="py-3 px-3 text-zinc-300">Mandapa Ubud Villa (Lobby @ 06:15 AM)</td>
                                            <td class="py-3 px-3 text-emerald-400 font-mono font-bold">BCA VA (Paid)</td>
                                            <td class="py-3 px-3 text-center"><span class="px-2 py-0.5 rounded-md bg-emerald-950 text-emerald-400 border border-emerald-800 font-bold text-[10px]">Checked In</span></td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 pr-3 font-semibold text-white">Liam Wilson (2 Pax)</td>
                                            <td class="py-3 px-3 text-zinc-300">W Bali Seminyak (Lobby @ 07:00 AM)</td>
                                            <td class="py-3 px-3 text-emerald-400 font-mono font-bold">Credit Card (Paid)</td>
                                            <td class="py-3 px-3 text-center"><span class="px-2 py-0.5 rounded-md bg-amber-950 text-amber-400 border border-amber-800/60 font-bold text-[10px]">In Van</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 3: WHATSAPP 1-CLICK TICKETS -->
                        <div x-show="activeTab === 'whatsapp'" style="display: none;" class="p-4 sm:p-6 lg:p-7 max-w-2xl mx-auto space-y-4">
                            <div class="p-4 rounded-2xl bg-[#0d0d10] border border-zinc-800 space-y-3">
                                <div class="flex items-center gap-3 border-b border-zinc-800 pb-3">
                                    <div class="w-10 h-10 rounded-full bg-[#FFEF4D] text-[#090d16] flex items-center justify-center font-black text-sm">
                                        TE
                                    </div>
                                    <div>
                                        <span class="font-bold text-sm text-white block">Your booking WhatsApp</span>
                                        <span class="text-[10px] text-emerald-400 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Verified WhatsApp Business
                                        </span>
                                    </div>
                                </div>

                                <div class="p-3.5 rounded-xl bg-emerald-950/30 border border-emerald-800/40 text-xs text-emerald-100 space-y-2">
                                    <p class="font-bold text-sm text-[#FFEF4D]">{{ __('Booking confirmed') }} — E-Voucher #NUSA-8821</p>
                                    <p>Hi Sarah! Thank you for booking with Nusa Penida Excursions.</p>
                                    <div class="p-2.5 rounded-lg bg-[#09090b] border border-zinc-800 text-[11px] text-zinc-300 space-y-1.5">
                                        <div class="flex items-center gap-2"><i class="fa-solid fa-location-dot w-3 text-center text-[10px] text-zinc-500"></i> <span><strong>Pickup:</strong> Hilton Bali Resort (06:45 AM)</span></div>
                                        <div class="flex items-center gap-2"><i class="fa-solid fa-ship w-3 text-center text-[10px] text-zinc-500"></i> <span><strong>Trip:</strong> Nusa Penida Snorkeling (2 Guests)</span></div>
                                        <div class="flex items-center gap-2"><i class="fa-solid fa-qrcode w-3 text-center text-[10px] text-zinc-500"></i> <span><strong>Digital QR Pass:</strong> travelengine.online/v/8821</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: INSTANT PAYOUTS LEDGER -->
                        <div x-show="activeTab === 'payouts'" style="display: none;" class="p-4 sm:p-6 lg:p-7 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="p-4 rounded-xl bg-[#0d0d10] border border-zinc-800">
                                    <span class="text-xs text-zinc-400 block">Today's Net Revenue</span>
                                    <span class="text-xl font-black text-white mt-1 block">Rp 7.800.000</span>
                                    <span class="text-[10px] text-emerald-400 font-bold mt-0.5 block">You keep 100% of the ticket</span>
                                </div>
                                <div class="p-4 rounded-xl bg-[#0d0d10] border border-zinc-800">
                                    <span class="text-xs text-zinc-400 block">Sent to your bank</span>
                                    <span class="text-xl font-black text-[#FFEF4D] mt-1 block">BCA •••• 8291</span>
                                    <span class="text-[10px] text-zinc-500 font-medium mt-0.5 block">Sent to your BCA account</span>
                                </div>
                                <div class="p-4 rounded-xl bg-[#0d0d10] border border-zinc-800">
                                    <span class="text-xs text-zinc-400 block">Total Processed (This Month)</span>
                                    <span class="text-xl font-black text-[#FFEF4D] mt-1 block">Rp 142.600.000</span>
                                    <span class="text-[10px] text-zinc-500 font-medium mt-0.5 block">184 Bookings Fulfilled</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product facts (no invented volume numbers) -->
        <section class="border-y border-zinc-800 bg-[#0d0d10] py-8 sm:py-10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-8 text-center">
                <div class="px-2">
                    <span class="text-xl sm:text-3xl font-black text-white font-mono block">0%</span>
                    <span class="text-xs text-zinc-400 mt-1.5 block text-pretty leading-snug">{{ __('Nothing taken from your ticket') }}</span>
                </div>
                <div class="px-2">
                    <span class="text-xl sm:text-3xl font-black text-[#FFEF4D] font-mono block">100%</span>
                    <span class="text-xs text-zinc-400 mt-1.5 block text-pretty leading-snug">{{ __('You keep the listed price') }}</span>
                </div>
                <div class="px-2">
                    <span class="text-xl sm:text-3xl font-black text-white font-mono block">5%</span>
                    <span class="text-xs text-zinc-400 mt-1.5 block text-pretty leading-snug">{{ __('Platform fee at checkout') }}</span>
                </div>
                <div class="px-2">
                    <span class="text-xl sm:text-3xl font-black text-[#FFEF4D] font-mono block">{{ __('Free') }}</span>
                    <span class="text-xs text-zinc-400 mt-1.5 block text-pretty leading-snug">{{ __('Starter plan, cancel anytime') }}</span>
                </div>
            </div>
        </section>

        <!-- The 3 Core Pillars Section (How It Works) -->
        <section id="how-it-works" class="py-12 sm:py-20 scroll-mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-12">
                <div class="text-center space-y-3 max-w-2xl mx-auto">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#FFEF4D] block">{{ __('Simple 3-Step Setup') }}</span>
                    <h2 class="text-xl sm:text-4xl font-black text-white tracking-tight">
                        Everything you need in 3 simple steps
                    </h2>
                    <p class="text-base text-zinc-400 leading-relaxed">
                        You don't need a designer or a website person. Add your trips, share the link, and run the tour.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Pillar 1 -->
                    <div class="p-6 sm:p-7 rounded-2xl bg-[#131316] border border-zinc-800 hover:border-[#FFEF4D]/50 transition duration-200 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-10 h-10 rounded-xl bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-lg font-black shadow-xs">
                                <i class="fa-solid fa-store"></i>
                            </div>
                            <span class="text-xs font-bold text-zinc-500 font-mono">01</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white">{{ __('1. Your Own Tour Website') }}</h3>
                        <p class="text-base text-zinc-400 leading-relaxed">
                            Get a booking page with your logo, photos, and prices. Put the link in your Instagram bio, TikTok, or WhatsApp status.
                        </p>
                    </div>

                    <!-- Pillar 2 -->
                    <div class="p-6 sm:p-7 rounded-2xl bg-[#131316] border border-zinc-800 hover:border-[#FFEF4D]/50 transition duration-200 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-10 h-10 rounded-xl bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-lg font-black shadow-xs">
                                <i class="fa-solid fa-calendar-days"></i>
                            </div>
                            <span class="text-xs font-bold text-zinc-500 font-mono">02</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white">{{ __('2. Accept Bookings Anytime') }}</h3>
                        <p class="text-base text-zinc-400 leading-relaxed">
                            Guests pick a date and how many people, then pay. You stop chasing “is this seat still free?” in chat.
                        </p>
                    </div>

                    <!-- Pillar 3 -->
                    <div class="p-6 sm:p-7 rounded-2xl bg-[#131316] border border-zinc-800 hover:border-[#FFEF4D]/50 transition duration-200 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-10 h-10 rounded-xl bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-lg font-black shadow-xs">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                            <span class="text-xs font-bold text-zinc-500 font-mono">03</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white">{{ __('3. Fast Payouts to Your Bank') }}</h3>
                        <p class="text-base text-zinc-400 leading-relaxed">
                            Guests pay with QRIS, BCA, Mandiri, BRI, BNI, or a card. After the payment settles, the money goes to your Indonesian bank. You keep 100% of the listed price.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Asymmetric Bento Box Features Grid -->
        <section id="features" class="py-12 sm:py-20 border-t border-zinc-800 bg-[#0d0d10] scroll-mt-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-12">
                <div class="text-center space-y-3 max-w-2xl mx-auto">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#FFEF4D] block">{{ __('Practical Operator Tools') }}</span>
                    <h2 class="text-xl sm:text-4xl font-black text-white tracking-tight">
                        Built for your daily tour business
                    </h2>
                    <p class="text-base text-zinc-400 leading-relaxed">
                        Spend less time answering repetitive questions and more time growing your tours.
                    </p>
                </div>

                <!-- Mobile: short feature list (no mockups) -->
                <ul class="md:hidden divide-y divide-zinc-800 rounded-2xl border border-zinc-800 bg-[#131316] overflow-hidden">
                    <li class="flex items-start gap-3 p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center shrink-0">
                            <i class="fa-brands fa-whatsapp text-sm"></i>
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <h3 class="text-base font-bold text-white">{{ __('Pay links for chat') }}</h3>
                            <p class="text-base text-zinc-400 leading-relaxed">{{ __('Copy a checkout link into chat. Guest pays, you get confirmed.') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-ticket text-sm"></i>
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <h3 class="text-base font-bold text-white">{{ __('E-tickets on WhatsApp') }}</h3>
                            <p class="text-base text-zinc-400 leading-relaxed">{{ __('Auto voucher, QR pass, and meeting pin on their phone.') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-clipboard-list text-sm"></i>
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <h3 class="text-base font-bold text-white">{{ __('Daily guest lists') }}</h3>
                            <p class="text-base text-zinc-400 leading-relaxed">{{ __('Pickup lists for drivers, guides, and boat captains.') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-building-columns text-sm"></i>
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <h3 class="text-base font-bold text-white">{{ __('Payouts, 0% cut') }}</h3>
                            <p class="text-base text-zinc-400 leading-relaxed">{{ __('Keep 100% of the ticket. Money goes to your Indonesian bank.') }}</p>
                        </div>
                    </li>
                </ul>

                <!-- Desktop: full bento with mockups -->
                <div class="hidden md:grid grid-cols-1 md:grid-cols-12 gap-6">

                    <!-- Bento 1: WhatsApp Payment Link (Spans 8 cols) -->
                    <div class="md:col-span-8 rounded-2xl bg-[#131316] border border-zinc-800 p-6 sm:p-8 flex flex-col justify-between space-y-6">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-[#FFEF4D] text-[10px] font-mono text-[#090d16] font-black uppercase">
                                <span>Feature 01</span>
                            </div>
                            <h3 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                                {{ __('Share a pay link in chat') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed max-w-xl">
                                {{ __('Guest asking in chat? Make a pay link in one tap. Copy it into WhatsApp or anywhere else. They open it, pay with QRIS, BCA, or a card, and you see the booking as paid.') }}
                            </p>
                        </div>

                        <!-- Mini Interactive Chat Mockup -->
                        <div class="rounded-xl bg-[#09090b] border border-zinc-800 p-4 space-y-3">
                            <div class="flex items-center justify-between text-xs border-b border-zinc-800 pb-2">
                                <div class="flex items-center gap-2">
                                    <i class="fa-brands fa-whatsapp text-emerald-400 text-sm"></i>
                                    <span class="font-bold text-white">WhatsApp chat</span>
                                </div>
                                <span class="text-[10px] text-zinc-500 font-mono">09:14 AM • DELIVERED</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
                                <div class="sm:col-span-7 bg-[#131316] p-3 rounded-lg border border-zinc-800 text-xs text-zinc-300 space-y-1.5">
                                    <p class="text-zinc-400 text-[11px]">"Hi Sarah! Here is your custom booking link for 2 slots on tomorrow's Nusa Penida snorkeling trip:"</p>
                                    <div class="p-2 rounded bg-[#09090b] border border-zinc-800 flex items-center justify-between text-[11px]">
                                        <span class="font-mono text-[#FFEF4D] font-bold">pay.travelengine/b/7x9</span>
                                        <span class="font-bold text-white">Rp 1.300.000</span>
                                    </div>
                                </div>
                                <div class="sm:col-span-5 bg-[#FFEF4D]/10 border border-[#FFEF4D]/30 p-3 rounded-lg text-center space-y-1">
                                    <span class="text-[10px] font-bold text-[#FFEF4D] uppercase tracking-wider block">Customer Pays Via</span>
                                    <span class="font-bold text-white text-xs block">QRIS / BCA / Mandiri / Cards</span>
                                    <span class="text-[10px] text-emerald-400 block inline-flex items-center justify-center gap-1"><i class="fa-solid fa-check text-[9px]"></i> {{ __('Paid — you see it right away') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bento 2: 1-Click WhatsApp Tickets (Spans 4 cols) -->
                    <div class="md:col-span-4 rounded-2xl bg-[#131316] border border-zinc-800 p-6 sm:p-7 flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-[#FFEF4D] text-[10px] font-mono text-[#090d16] font-black uppercase">
                                <span>Feature 02</span>
                            </div>
                            <h3 class="text-lg font-bold text-white tracking-tight">
                                Tickets on their phone
                            </h3>
                            <p class="text-base text-zinc-400 leading-relaxed">
                                After they pay, they get the ticket, QR, and meeting point on WhatsApp. You do not type it out.
                            </p>
                        </div>

                        <!-- Ticket Pass Card -->
                        <div class="rounded-xl bg-[#09090b] border border-zinc-800 p-3.5 space-y-2 text-xs">
                            <div class="flex items-center justify-between text-zinc-300 font-bold">
                                <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-ticket text-[11px] text-[#FFEF4D]"></i> E-Pass #NUSA-882</span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-[#FFEF4D] text-[#090d16] font-black">CONFIRMED</span>
                            </div>
                            <div class="text-[11px] text-zinc-400 pt-1 border-t border-zinc-800 space-y-1">
                                <div class="flex items-center gap-2"><i class="fa-solid fa-location-dot w-3 text-center text-[10px]"></i> Sanur Harbor Gate 3 (07:30 AM)</div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-users w-3 text-center text-[10px]"></i> 2 Guests • Snorkeling & Speedboat</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bento 3: Daily Manifest & Captain Lists (Spans 4 cols) -->
                    <div class="md:col-span-4 rounded-2xl bg-[#131316] border border-zinc-800 p-6 sm:p-7 flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-[#FFEF4D] text-[10px] font-mono text-[#090d16] font-black uppercase">
                                <span>Feature 03</span>
                            </div>
                            <h3 class="text-lg font-bold text-white tracking-tight">
                                Daily guest list for drivers
                            </h3>
                            <p class="text-base text-zinc-400 leading-relaxed">
                                Print or share tomorrow’s names, hotels, and pickup times with the driver, guide, or boat captain.
                            </p>
                        </div>

                        <div class="rounded-xl bg-[#09090b] border border-zinc-800 p-3 space-y-1.5 text-[10px]">
                            <div class="flex items-center justify-between text-zinc-400 font-bold border-b border-zinc-800 pb-1">
                                <span>Passenger</span>
                                <span>Hotel</span>
                                <span>Status</span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-300">
                                <span>Sarah M. (2pax)</span>
                                <span class="text-zinc-500">Hilton Bali</span>
                                <span class="text-emerald-400 font-bold">Checked In</span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-300">
                                <span>David K. (4pax)</span>
                                <span class="text-zinc-500">Ubud Villa</span>
                                <span class="text-amber-400 font-bold">En Route</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bento 4: Automated Bank Payouts & Calendar (Spans 8 cols) -->
                    <div class="md:col-span-8 rounded-2xl bg-[#131316] border border-zinc-800 p-6 sm:p-8 flex flex-col justify-between space-y-6">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-[#FFEF4D] text-[10px] font-mono text-[#090d16] font-black uppercase">
                                <span>Feature 04</span>
                            </div>
                            <h3 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                                Zero cut of your ticket. Money to your bank.
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed max-w-xl">
                                Keep 100% of the listed price. After payment settles, we send it to your Indonesian bank (BCA, Mandiri, BRI, BNI). We never take a cut of the ticket.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-4 rounded-xl bg-[#09090b] border border-zinc-800 space-y-1">
                                <span class="text-xs text-zinc-400 block">Sent to your bank</span>
                                <span class="font-black font-mono text-lg text-emerald-400 block">Rp 7.800.000</span>
                                <span class="text-[10px] text-zinc-500 block">Goes to BCA • 0% cut of the ticket</span>
                            </div>
                            <div class="p-4 rounded-xl bg-[#09090b] border border-zinc-800 space-y-1">
                                <span class="text-xs text-zinc-400 block">On your phone calendar</span>
                                <span class="font-bold text-sm text-white block">Google & Apple Calendar</span>
                                <span class="text-[10px] text-zinc-500 block">Departures show up next to your other appointments</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing & Subscription Plans Section -->
        <section id="pricing" class="py-12 sm:py-20 scroll-mt-16" x-data="{ billing_interval: 'monthly' }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-10">

                <!-- Section Header & Billing Interval Toggle -->
                <div class="text-center space-y-3 max-w-2xl mx-auto">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#FFEF4D] block">{{ __('Simple, Honest Pricing') }}</span>
                    <h2 class="text-xl sm:text-4xl font-black text-white tracking-tight">
                        Start free. Keep 100% of your tour price.
                    </h2>
                    <p class="text-base text-zinc-400 leading-relaxed">
                        No hidden cuts, no monthly surprises. Start for free and upgrade only when your business expands.
                    </p>

                    <!-- Toggle -->
                    <div class="inline-flex p-1 rounded-xl bg-[#131316] border border-zinc-800 self-center mt-3">
                        <button type="button" x-on:click="billing_interval = 'monthly'"
                            :class="billing_interval === 'monthly' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'text-zinc-400 hover:text-white'"
                            class="px-3.5 sm:px-4 py-1.5 rounded-lg text-sm font-bold transition-all cursor-pointer">
                            {{ __('Monthly') }}
                        </button>
                        <button type="button" x-on:click="billing_interval = 'yearly'"
                            :class="billing_interval === 'yearly' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'text-zinc-400 hover:text-white'"
                            class="px-3.5 sm:px-4 py-1.5 rounded-lg text-sm font-bold transition-all cursor-pointer flex items-center gap-1.5">
                            <span>{{ __('Yearly') }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-black bg-[#FFEF4D]/20 text-[#FFEF4D]">{{ __('Save 17%') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Mobile: one-line plan summaries -->
                <div class="md:hidden space-y-3">
                    @foreach ($plans as $plan)
                        @php
                            $priceMonthly = (float) $plan->price_monthly;
                            $priceYearly = (float) $plan->price_yearly;
                            $mobileHighlights = match ($plan->slug) {
                                'starter' => [__('Tour website + 24/7 booking'), $plan->listingLimitLabel().' · '.$plan->teamSeatLabel()],
                                'growth' => [__('WhatsApp tickets, guest lists, calendar'), $plan->listingLimitLabel().' · '.$plan->teamSeatLabel()],
                                'agency' => [__('Your own website address (yourbrand.com)'), $plan->listingLimitLabel()],
                                default => [$plan->listingLimitLabel(), $plan->teamSeatLabel()],
                            };
                        @endphp
                        <div class="rounded-2xl bg-[#131316] border {{ $plan->is_popular ? 'border-[#FFEF4D]' : 'border-zinc-800' }} p-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-0.5">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-black text-base text-white tracking-tight">{{ $plan->name }}</h3>
                                        @if ($plan->is_popular)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-[#FFEF4D] text-[#090d16]">{{ __('Popular') }}</span>
                                        @endif
                                    </div>
                                    <p class="text-base font-black text-white tracking-tight"
                                        x-text="billing_interval === 'yearly' ? '{{ $plan->isFree() ? __('Free') : 'Rp '.number_format($priceYearly, 0, ',', '.').' / year' }}' : '{{ $plan->isFree() ? __('Free') : 'Rp '.number_format($priceMonthly, 0, ',', '.').' / month' }}'">
                                        {{ $plan->isFree() ? __('Free') : 'Rp '.number_format($priceMonthly, 0, ',', '.').' / month' }}
                                    </p>
                                </div>
                                <a href="{{ route('register') }}"
                                    class="shrink-0 h-9 px-3 rounded-lg {{ $plan->is_popular ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'bg-zinc-800 text-zinc-100 border border-zinc-700 font-bold' }} text-sm flex items-center cursor-pointer"
                                    wire:navigate>
                                    {{ $registrationOpen ? ($plan->isFree() ? __('Start free') : __('Choose')) : __('Coming soon') }}
                                </a>
                            </div>
                            <ul class="space-y-1 text-sm text-zinc-400">
                                @foreach ($mobileHighlights as $highlight)
                                    <li class="flex items-start gap-2">
                                        <i class="fa-solid fa-check text-emerald-400 text-[10px] mt-0.5 shrink-0"></i>
                                        <span>{{ $highlight }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    <p class="text-center text-xs text-zinc-500">{{ __('You keep 100% of the listed price. A 5% platform fee is added at checkout.') }}</p>
                </div>

                <!-- Desktop: full pricing cards -->
                <div class="hidden md:grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 items-stretch max-w-5xl mx-auto">
                    @foreach ($plans as $plan)
                        @php
                            $priceMonthly = (float) $plan->price_monthly;
                            $priceYearly = (float) $plan->price_yearly;
                        @endphp
                        <div class="rounded-2xl bg-[#131316] border {{ $plan->is_popular ? 'border-[#FFEF4D]' : 'border-zinc-800' }} p-6 sm:p-7 flex flex-col justify-between relative transition hover:border-[#FFEF4D]/60">

                            <div class="space-y-4">
                                <!-- Header & Badge -->
                                <div class="flex items-start justify-between gap-2 min-h-[28px]">
                                    <h3 class="font-black text-lg text-white tracking-tight">
                                        {{ $plan->name }}
                                    </h3>

                                    @if ($plan->is_popular)
                                        <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase bg-[#FFEF4D] text-[#090d16] shrink-0 shadow-xs">
                                            {{ __('Popular') }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Tagline -->
                                <p class="text-xs text-zinc-400 min-h-[32px] leading-relaxed">
                                    {{ $plan->tagline }}
                                </p>

                                <!-- Price Box -->
                                <div class="p-3.5 rounded-xl bg-[#09090b] border border-zinc-800 space-y-2">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl sm:text-3xl font-black text-white tracking-tight"
                                            x-text="billing_interval === 'yearly' ? 'Rp {{ number_format($priceYearly, 0, ',', '.') }}' : 'Rp {{ number_format($priceMonthly, 0, ',', '.') }}'">
                                            Rp {{ number_format($priceMonthly, 0, ',', '.') }}
                                        </span>
                                        <span class="text-[11px] font-semibold text-zinc-500"
                                            x-text="billing_interval === 'yearly' ? '/ year' : '/ month'">
                                            / month
                                        </span>
                                    </div>

                                    <div class="space-y-1 pt-2 border-t border-zinc-800 text-[11px]">
                                        <div class="flex items-center justify-between">
                                            <span class="text-zinc-400 font-medium">{{ __('Your payout') }}</span>
                                            <span class="font-bold font-mono text-xs text-emerald-400">
                                                {{ __('100% of the listed price') }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between text-[10px] text-zinc-500">
                                            <span>{{ __('Platform fee') }}</span>
                                            <span class="font-medium text-zinc-300">
                                                {{ __('5% at checkout') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Limits -->
                                <div class="grid grid-cols-2 gap-2 text-center">
                                    <div class="p-2 rounded-lg bg-[#09090b] border border-zinc-800">
                                        <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block">{{ __('Trips and activities') }}</span>
                                        <span class="font-bold text-xs text-zinc-200 mt-0.5 block">
                                            {{ $plan->listingLimitLabel() }}
                                        </span>
                                    </div>
                                    <div class="p-2 rounded-lg bg-[#09090b] border border-zinc-800">
                                        <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block">{{ __('Team logins') }}</span>
                                        <span class="font-bold text-xs text-zinc-200 mt-0.5 block">
                                            {{ $plan->teamSeatLabel() }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Features this plan actually includes -->
                                <div class="space-y-2 pt-2 border-t border-zinc-800">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('What is included:') }}</span>
                                    <ul class="space-y-1.5 text-[11px]">
                                        <li class="flex items-start gap-2 text-zinc-200 font-medium">
                                            <i class="fa-solid fa-check text-emerald-400 text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Your own tour website') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2 text-zinc-200 font-medium">
                                            <i class="fa-solid fa-check text-emerald-400 text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Guests book and pay themselves') }}</span>
                                        </li>
                                        @foreach ($planFeatureRows as $feature)
                                            @if ($plan->hasFeature($feature['key']))
                                                <li class="flex items-start gap-2 text-zinc-200 font-medium">
                                                    <i class="fa-solid fa-check text-emerald-400 text-xs mt-0.5 shrink-0"></i>
                                                    <span>{{ $feature['label'] }}</span>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            </div>

                            <!-- CTA Button -->
                            <div class="pt-5 border-t border-zinc-800 mt-4">
                                <a href="{{ route('register') }}"
                                    class="w-full h-10 rounded-lg {{ $plan->is_popular ? 'bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-100 border border-zinc-700 font-bold' }} text-xs transition flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                                    wire:navigate>
                                    <span>{{ $registrationOpen ? __('Choose :plan', ['plan' => $plan->name]) : __('Coming soon') }}</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Feature Comparison Table Accordion (desktop; closed by default) -->
                <div x-data="{ showComparison: false }" class="hidden md:block max-w-6xl mx-auto space-y-4 pt-2">
                    <!-- Accordion Trigger Button -->
                    <div class="text-center">
                        <button type="button" @click="showComparison = !showComparison"
                            class="inline-flex items-center gap-2.5 px-5 py-2.5 rounded-xl bg-[#131316] hover:bg-zinc-800 border border-zinc-800 text-zinc-200 text-xs font-bold transition group cursor-pointer">
                            <i class="fa-solid fa-table-list text-xs text-[#FFEF4D]"></i>
                            <span x-text="showComparison ? '{{ __('Hide Plan Comparison') }}' : '{{ __('Compare All Plan Features') }}'">
                                {{ __('Compare All Plan Features') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-zinc-400 transition-transform duration-200"
                                :class="showComparison ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                    </div>

                    <!-- Accordion Content Panel -->
                    <div x-show="showComparison" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1" style="display: none;"
                        class="rounded-2xl bg-[#131316] border border-zinc-800 overflow-hidden p-4 sm:p-6">
                        <div class="sm:hidden text-center text-[11px] text-zinc-400 mb-3 flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-arrows-left-right text-[#FFEF4D] text-xs"></i>
                            <span>{{ __('Scroll table sideways to compare all tiers') }}</span>
                        </div>
                        <div class="overflow-x-auto -mx-2 sm:mx-0">
                            <table class="w-full text-left text-xs border-collapse min-w-[640px]">
                                <thead>
                                    <tr class="border-b border-zinc-800 text-zinc-400">
                                        <th class="py-3 pr-3 font-bold uppercase tracking-wider text-[10px] w-1/5">
                                            {{ __('Plan Details') }}</th>
                                        <th class="py-3 px-2 font-bold uppercase tracking-wider text-[9px] sm:text-[10px] text-center text-zinc-300">
                                            <span>Starter</span>
                                            <span class="block text-[8px] font-normal text-zinc-500 mt-0.5">{{ __('Free') }}</span>
                                        </th>
                                        <th class="py-3 px-2 font-bold uppercase tracking-wider text-[9px] sm:text-[10px] text-center text-[#FFEF4D] bg-zinc-900 rounded-t-xl border-t border-x border-zinc-800">
                                            <span>Growth</span>
                                            <span class="block text-[8px] font-normal text-zinc-400 mt-0.5">Rp 299.000 / mo</span>
                                        </th>
                                        <th class="py-3 px-2 font-bold uppercase tracking-wider text-[9px] sm:text-[10px] text-center text-zinc-300">
                                            <span>Agency</span>
                                            <span class="block text-[8px] font-normal text-zinc-400 mt-0.5">Rp 799.000 / mo</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800">
                                    <tr class="bg-[#09090b]">
                                        <td colspan="4" class="py-2 px-3 font-bold text-[10px] uppercase tracking-wider text-[#FFEF4D]">
                                            {{ __('1. Tour website') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Your tour website (yourname.travelengine.online)') }}</td>
                                        @foreach ($plans as $plan)
                                            <td class="py-3 px-2 text-center {{ $plan->is_popular ? 'bg-zinc-900/60' : '' }}">
                                                <i class="fa-solid fa-check text-emerald-400"></i>
                                            </td>
                                        @endforeach
                                    </tr>
                                    @foreach ([
                                        ['key' => 'custom_domain', 'label' => __('Your own website address (yourbrand.com)')],
                                        ['key' => 'remove_branding', 'label' => __('Guests see only your name, not ours')],
                                        ['key' => 'ai_discovery', 'label' => __('Show up when people ask ChatGPT about tours')],
                                    ] as $feature)
                                        <tr class="hover:bg-zinc-800/30 transition">
                                            <td class="py-3 pr-3 text-zinc-300 font-medium">{{ $feature['label'] }}</td>
                                            @foreach ($plans as $plan)
                                                <td class="py-3 px-2 text-center {{ $plan->is_popular ? 'bg-zinc-900/60' : '' }} {{ $plan->hasFeature($feature['key']) ? '' : 'text-zinc-600' }}">
                                                    @if ($plan->hasFeature($feature['key']))
                                                        <i class="fa-solid fa-check text-emerald-400"></i>
                                                    @else
                                                        <i class="fa-solid fa-minus"></i>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach

                                    <tr class="bg-[#09090b]">
                                        <td colspan="4" class="py-2 px-3 font-bold text-[10px] uppercase tracking-wider text-[#FFEF4D]">
                                            {{ __('2. How much you can run') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Trips and activities you can list') }}</td>
                                        @foreach ($plans as $plan)
                                            <td class="py-3 px-2 text-center font-semibold {{ $plan->is_popular ? 'text-[#FFEF4D] bg-zinc-900/60' : 'text-zinc-300' }}">
                                                {{ $plan->listingLimitLabel() }}
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('People on your team') }}</td>
                                        @foreach ($plans as $plan)
                                            <td class="py-3 px-2 text-center font-semibold {{ $plan->is_popular ? 'text-emerald-400 bg-zinc-900/60' : 'text-zinc-300' }}">
                                                {{ $plan->teamSeatLabel() }}
                                            </td>
                                        @endforeach
                                    </tr>

                                    <tr class="bg-[#09090b]">
                                        <td colspan="4" class="py-2 px-3 font-bold text-[10px] uppercase tracking-wider text-[#FFEF4D]">
                                            {{ __('3. Daily tools') }}
                                        </td>
                                    </tr>
                                    @foreach (collect($planFeatureRows)->reject(fn (array $feature): bool => in_array($feature['key'], ['custom_domain', 'remove_branding', 'ai_discovery'], true)) as $feature)
                                        <tr class="hover:bg-zinc-800/30 transition">
                                            <td class="py-3 pr-3 text-zinc-300 font-medium">{{ $feature['label'] }}</td>
                                            @foreach ($plans as $plan)
                                                <td class="py-3 px-2 text-center {{ $plan->is_popular ? 'bg-zinc-900/60' : '' }} {{ $plan->hasFeature($feature['key']) ? '' : 'text-zinc-600' }}">
                                                    @if ($plan->hasFeature($feature['key']))
                                                        <i class="fa-solid fa-check text-emerald-400"></i>
                                                    @else
                                                        <i class="fa-solid fa-minus"></i>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Honest Pricing Note -->
                <div class="hidden md:block p-5 rounded-2xl bg-[#131316] border border-zinc-800 max-w-2xl mx-auto text-center space-y-1.5">
                    <div class="inline-flex items-center gap-2 text-xs font-bold text-[#FFEF4D]">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>{{ __('We never take a cut of your ticket') }}</span>
                    </div>
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        {{ __('You keep 100% of your listed prices. A 5% platform fee is added at checkout — it does not come from your ticket.') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Frequently Asked Questions (FAQ) Section -->
        <section id="faq" class="py-16 sm:py-20 border-t border-zinc-800 bg-[#0d0d10] scroll-mt-16" x-data="{ activeAccordion: null }">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
                <div class="text-center space-y-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#FFEF4D] block">{{ __('Common Questions') }}</span>
                    <h2 class="text-xl sm:text-4xl font-black text-white tracking-tight">
                        Everything you need to know
                    </h2>
                    <p class="text-base text-zinc-400 leading-relaxed">
                        {{ __('Have questions about payouts, the platform fee, or setting up your shop? Here are quick answers.') }}
                    </p>
                </div>

                <div class="space-y-3">
                    <!-- FAQ Item 1 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 1 ? null : 1"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-base text-white">
                                {{ __('How do I receive payouts from my tour bookings?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 1 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 1" x-collapse
                            class="px-4 sm:px-5 pb-5 text-base text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('When a guest pays with QRIS, a bank transfer, or a card, the money sits in your TravelEngine wallet. After the bank has settled it, we send it to your Indonesian account (BCA, Mandiri, BRI, BNI, and more).') }}
                        </div>
                    </div>

                    <!-- FAQ Item 2 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 2 ? null : 2"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-base text-white">
                                {{ __('Do I need a designer or a website person?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 2 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 2" x-collapse
                            class="px-4 sm:px-5 pb-5 text-base text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('No. Add photos, prices, and your WhatsApp number. Share the link. That is the whole setup — usually under five minutes.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 3 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 3 ? null : 3"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-base text-white">
                                {{ __('How does “you keep 100%” work?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 3 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 3" x-collapse
                            class="px-4 sm:px-5 pb-5 text-base text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('Your listed price is yours. We take 0% from the ticket — travel websites often take 15% to 30% from you instead. A 5% platform fee is added at checkout, the same idea as other booking apps, so you still receive 100% of the price you listed.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 4 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 4 ? null : 4"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-base text-white">
                                {{ __('What is the platform fee?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 4 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 4" x-collapse
                            class="px-4 sm:px-5 pb-5 text-base text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('It is a small 5% fee added at checkout so you can keep the full ticket price while the shop stays simple to run. Guests see it on the payment screen before they confirm. It does not come out of your payout, and nothing is added later.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 5 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 5 ? null : 5"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-base text-white">
                                {{ __('Can guests open mybrand.com instead of a long link?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 5 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 5" x-collapse
                            class="px-4 sm:px-5 pb-5 text-base text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('Yes, on the Agency plan. Guests can type yourbrand.com. The padlock in the browser is included — you do not set that up yourself.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 6 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 6 ? null : 6"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-base text-white">
                                {{ __('Can I change plans or cancel anytime?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 6 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 6" x-collapse
                            class="px-4 sm:px-5 pb-5 text-base text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('Yes. There are no lock-in contracts or long-term commitments. Start on the free plan, upgrade when your business grows, or cancel anytime with 1 click.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 7 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 7 ? null : 7"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-base text-white">
                                {{ __('Can my tour guides and staff have their own accounts?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 7 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 7" x-collapse
                            class="px-4 sm:px-5 pb-5 text-base text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('Starter is you and one helper. Growth and above allow as many people as you need — office staff, drivers, and guides.') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Bottom Call to Action Section -->
        <section class="py-16 sm:py-20 border-t border-zinc-800 bg-[#0d0d10]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center space-y-5">
                <h2 class="text-xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight leading-snug sm:leading-tight">
                    Ready to start taking direct tour bookings?
                </h2>
                <p class="text-base text-zinc-400 max-w-xl mx-auto leading-relaxed">
                    @if ($registrationOpen)
                        Set up your tour packages, add your bank details, and start accepting online bookings in under 10 minutes.
                    @else
                        {{ __('We are getting operator sign-up ready. Look around — accounts open soon.') }}
                    @endif
                </p>
                <div class="pt-2">
                    <a href="{{ route('register') }}"
                        class="h-11 sm:h-12 px-8 sm:px-9 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-sm sm:text-base transition inline-flex items-center gap-2 cursor-pointer shadow-sm"
                        wire:navigate>
                        <span>{{ $registrationOpen ? __('Create Your Tour Website Now') : __('Coming soon') }}</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>

                <!-- Trust Reassurance Checklist -->
                <div class="pt-2 flex flex-wrap items-center justify-center gap-4 sm:gap-6 text-sm text-zinc-400">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('No credit card required') }}</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('Free Starter plan forever') }}</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('Ready in under 5 minutes') }}</span>
                </div>
            </div>
        </section>

        <!-- Sample shop — after the product story, for people who want to try later -->
        <section class="py-12 sm:py-16 border-t border-zinc-800">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 text-center space-y-4">
                <p class="text-xs font-bold uppercase tracking-wider text-[#FFEF4D]">{{ __('When you’re ready') }}</p>
                <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                    {{ __('Want to try a sample shop?') }}
                </h2>
                <p class="text-base text-zinc-400 leading-relaxed">
                    {{ __('Open a guest shop, or sit at the operator desk. Checkout is off — nothing here charges a card.') }}
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-1">
                    <a href="{{ $demoStorefrontUrl }}" target="_blank" rel="noopener nofollow"
                        class="w-full sm:w-auto h-11 px-6 rounded-xl bg-[#131316] hover:bg-zinc-800 text-zinc-200 border border-zinc-800 font-bold text-sm transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-store text-[#FFEF4D] text-xs"></i>
                        <span>{{ __('See a sample shop') }}</span>
                    </a>
                    <a href="{{ $demoOperatorLoginUrl }}" target="_blank" rel="noopener nofollow"
                        class="w-full sm:w-auto h-11 px-6 rounded-xl bg-[#131316] hover:bg-zinc-800 text-zinc-200 border border-zinc-800 font-bold text-sm transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-gauge text-[#FFEF4D] text-xs"></i>
                        <span>{{ __('Try the operator desk') }}</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-zinc-800 bg-[#09090b] py-8 sm:py-10 text-xs text-zinc-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 sm:gap-6 text-center sm:text-left">
            <!-- Platform Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 select-none group">
                <div class="w-8 h-8 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-sm font-black shrink-0 shadow-xs">
                    <i class="fa-solid fa-compass"></i>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-black text-sm tracking-tight text-white leading-none">
                        {{ config('app.name', 'TravelEngine') }}
                    </span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-[#FFEF4D]/15 text-[#FFEF4D] font-black uppercase tracking-wider border border-[#FFEF4D]/30">
                        Booking
                    </span>
                </div>
            </a>

            <div class="flex items-center gap-6">
                <a href="{{ route('legal.terms') }}" class="hover:text-zinc-300 transition">{{ __('Terms') }}</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-zinc-300 transition">{{ __('Privacy') }}</a>
                <a href="mailto:{{ \App\Models\PlatformSetting::current()->getOperatorSupportEmail() }}" class="hover:text-zinc-300 transition">{{ __('Support') }}</a>
                <a href="{{ route('login') }}" class="hover:text-zinc-300 transition" wire:navigate>{{ __('Operator log in') }}</a>
                <a href="{{ route('register') }}" class="hover:text-zinc-300 transition" wire:navigate>{{ $registrationOpen ? __('Start selling') : __('Coming soon') }}</a>
            </div>

            <p class="text-xs text-zinc-600">
                &copy; {{ date('Y') }} {{ config('app.name', 'TravelEngine') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </footer>
    @livewireScripts
</body>

</html>
