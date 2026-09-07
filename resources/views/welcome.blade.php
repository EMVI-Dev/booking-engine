<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">

<head>
    @include('partials.head')
    @livewireStyles
</head>

<body class="min-h-screen bg-[#09090b] text-zinc-100 antialiased selection:bg-[#FFEF4D] selection:text-[#090d16] font-sans">
    @php
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get();
        if ($plans->isEmpty()) {
            \App\Models\Plan::seedDefaultPlans();
            $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get();
        }
    @endphp

    <!-- Top Announcement Bar (Solid & Crisp) -->
    <div class="border-b border-zinc-800 bg-[#131316] py-2 px-4 text-center text-xs text-zinc-300">
        <span class="inline-flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#FFEF4D]"></span>
            <span class="font-bold text-[#FFEF4D]">{{ __('Zero Platform Commission') }}</span>
            <span class="text-zinc-600 hidden sm:inline">•</span>
            <span class="hidden sm:inline">{{ __('Keep 100% of your listed ticket prices with direct bank payouts.') }}</span>
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
                        class="px-2.5 sm:px-3 py-1.5 text-zinc-400 hover:text-white text-xs font-medium transition"
                        wire:navigate>
                        {{ __('Log In') }}
                    </a>
                    <a href="{{ route('register') }}"
                        class="h-8 sm:h-9 px-3.5 sm:px-4 rounded-lg bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] text-xs font-black transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                        wire:navigate>
                        <span>{{ __('Start Free') }}</span>
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
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-zinc-800 bg-[#131316] text-[11px] sm:text-xs font-bold text-[#FFEF4D]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#FFEF4D]"></span>
                    <span>{{ __('The All-In-One Tour Operator Operating System') }}</span>
                </div>

                <!-- Main Catchy Title -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-tight sm:leading-tight">
                    The simple way to sell your tours online.<br>
                    <span class="text-[#FFEF4D]">
                        No coding. Zero commission.
                    </span>
                </h1>

                <!-- Value Proposition Copy -->
                <p class="text-sm sm:text-lg text-zinc-400 max-w-3xl mx-auto font-normal leading-relaxed">
                    {{ __('Create your tour website in 5 minutes. Accept instant online bookings, send payment links in your chats, and organize daily guest lists for your team — keeping 100% of your ticket sales.') }}
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 pt-2">
                    <a href="{{ route('register') }}"
                        class="w-full sm:w-auto h-11 sm:h-12 px-7 sm:px-8 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-sm transition flex items-center justify-center gap-2 cursor-pointer shadow-sm"
                        wire:navigate>
                        <span>{{ __('Create Your Free Tour Website') }}</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                    <a href="{{ url('/nusapenida-excursions') }}" target="_blank"
                        class="w-full sm:w-auto h-11 sm:h-12 px-6 sm:px-7 rounded-xl bg-[#131316] hover:bg-zinc-800 text-zinc-200 border border-zinc-800 font-bold text-sm transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-store text-[#FFEF4D] text-xs"></i>
                        <span>{{ __('See Example Tour Website ↗') }}</span>
                    </a>
                </div>

                <!-- Feature Badges Bar -->
                <div class="pt-6 sm:pt-8 grid grid-cols-2 sm:flex sm:flex-wrap items-center justify-center gap-4 sm:gap-8 text-[11px] sm:text-xs font-semibold text-zinc-400 border-t border-zinc-800 mt-6 sm:mt-10">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-globe text-[#FFEF4D] text-sm"></i>
                        <span>{{ __('Ready-to-Use Website') }}</span>
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
                        <span>{{ __('WhatsApp Vouchers') }}</span>
                    </div>
                    <div class="flex items-center gap-2 col-span-2 sm:col-span-1 justify-center sm:justify-start">
                        <i class="fa-solid fa-sack-dollar text-[#FFEF4D] text-sm"></i>
                        <span>{{ __('Zero Commission') }}</span>
                    </div>
                </div>

                <!-- Mobile: one snapshot of what you get (desktop keeps the full demo) -->
                <div class="md:hidden text-left rounded-2xl border border-zinc-800 bg-[#131316] p-4 space-y-3">
                    <p class="text-[10px] font-mono text-[#FFEF4D] truncate">yourname.travelengine.online</p>
                    <div class="space-y-1">
                        <p class="text-sm font-bold text-white">{{ __('Your own tour website') }}</p>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            {{ __('Guests pick a date, pay with QRIS or bank transfer, and get a WhatsApp ticket. You keep 100% of the listed price.') }}
                        </p>
                    </div>
                    <a href="{{ url('/nusapenida-excursions') }}" target="_blank"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#FFEF4D] cursor-pointer">
                        <span>{{ __('Open a live example') }}</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    </a>
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
                            <span>{{ __('1. Traveler Storefront') }}</span>
                        </button>
                        <button type="button" @click="activeTab = 'manifest'"
                            :class="activeTab === 'manifest' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'bg-[#131316] text-zinc-400 hover:text-white border border-zinc-800 font-medium'"
                            class="px-3.5 py-2 rounded-xl text-xs flex items-center gap-2 transition shrink-0 cursor-pointer">
                            <i class="fa-solid fa-clipboard-list text-xs"></i>
                            <span>{{ __('2. Daily Manifest & Dispatch') }}</span>
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
                            <span>{{ __('4. Instant 0% Payouts') }}</span>
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
                                    class="px-3 py-1 rounded-lg border text-xs font-bold shrink-0 cursor-pointer">
                                    🏝️ Nusa Penida Snorkeling
                                </button>
                                <button type="button" @click="selectedTour = 'komodo'"
                                    :class="selectedTour === 'komodo' ? 'bg-[#FFEF4D] text-[#090d16] font-black border-[#FFEF4D]' : 'bg-[#09090b] text-zinc-400 border-zinc-800'"
                                    class="px-3 py-1 rounded-lg border text-xs font-bold shrink-0 cursor-pointer">
                                    ⛵ Komodo Phinisi Cruise
                                </button>
                                <button type="button" @click="selectedTour = 'batur'"
                                    :class="selectedTour === 'batur' ? 'bg-[#FFEF4D] text-[#090d16] font-black border-[#FFEF4D]' : 'bg-[#09090b] text-zinc-400 border-zinc-800'"
                                    class="px-3 py-1 rounded-lg border text-xs font-bold shrink-0 cursor-pointer">
                                    🌋 Mount Batur Jeep Safari
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
                                            {{ __('Booking Confirmed! E-Ticket Dispatched') }}
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
                                    <h4 class="font-bold text-base text-white">Daily Guest Manifest — Speedboat Ocean Express #2</h4>
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
                                        <span class="font-bold text-sm text-white block">TravelEngine Dispatch Bot</span>
                                        <span class="text-[10px] text-emerald-400 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Verified WhatsApp Business
                                        </span>
                                    </div>
                                </div>

                                <div class="p-3.5 rounded-xl bg-emerald-950/30 border border-emerald-800/40 text-xs text-emerald-100 space-y-2">
                                    <p class="font-bold text-sm text-[#FFEF4D]">🎉 Booking Confirmed! E-Voucher #NUSA-8821</p>
                                    <p>Hi Sarah! Thank you for booking with Nusa Penida Excursions.</p>
                                    <div class="p-2.5 rounded-lg bg-[#09090b] border border-zinc-800 text-[11px] text-zinc-300 space-y-1">
                                        <div>📍 <strong>Pickup:</strong> Hilton Bali Resort (06:45 AM)</div>
                                        <div>⛵ <strong>Trip:</strong> Nusa Penida Snorkeling (2 Guests)</div>
                                        <div>📲 <strong>Digital QR Pass:</strong> travelengine.online/v/8821</div>
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
                                    <span class="text-[10px] text-emerald-400 font-bold mt-0.5 block">100% Retained (0% Commission)</span>
                                </div>
                                <div class="p-4 rounded-xl bg-[#0d0d10] border border-zinc-800">
                                    <span class="text-xs text-zinc-400 block">Auto-Disbursed to Bank</span>
                                    <span class="text-xl font-black text-[#FFEF4D] mt-1 block">BCA •••• 8291</span>
                                    <span class="text-[10px] text-zinc-500 font-medium mt-0.5 block">Settled via BI-FAST Instant</span>
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
            <div class="max-w-6xl mx-auto px-4 sm:px-6 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div>
                    <span class="text-2xl sm:text-3xl font-black text-white font-mono block">0%</span>
                    <span class="text-xs text-zinc-400 mt-0.5 block">{{ __('Taken from your ticket') }}</span>
                </div>
                <div>
                    <span class="text-2xl sm:text-3xl font-black text-[#FFEF4D] font-mono block">100%</span>
                    <span class="text-xs text-zinc-400 mt-0.5 block">{{ __('You keep of the listed price') }}</span>
                </div>
                <div>
                    <span class="text-2xl sm:text-3xl font-black text-white font-mono block">5%</span>
                    <span class="text-xs text-zinc-400 mt-0.5 block">{{ __('Guest fee at checkout') }}</span>
                </div>
                <div>
                    <span class="text-2xl sm:text-3xl font-black text-[#FFEF4D] font-mono block">{{ __('Free') }}</span>
                    <span class="text-xs text-zinc-400 mt-0.5 block">{{ __('Starter plan, cancel anytime') }}</span>
                </div>
            </div>
        </section>

        <!-- The 3 Core Pillars Section (How It Works) -->
        <section id="how-it-works" class="py-12 sm:py-20 scroll-mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-12">
                <div class="text-center space-y-3 max-w-2xl mx-auto">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#FFEF4D] block">{{ __('Simple 3-Step Setup') }}</span>
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                        Everything you need in 3 simple steps
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                        You don't need any technical skills or web design experience. We handle the technology so you can focus on showing your guests a wonderful time.
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
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Get your own clean tour website with your logo, tour photos, description, and prices. Share your link directly in your Instagram bio, TikTok, or WhatsApp status.
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
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Travelers can pick their tour date, select the number of guests, and book instantly. No more double-booking or manual scheduling headaches.
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
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Customers pay with QRIS, BCA, Mandiri, BRI, BNI, or Credit Card. Your earnings transfer directly into your Indonesian bank account. Keep 100% of your ticket price.
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
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                        Built for your daily tour business
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
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
                            <h3 class="text-sm font-bold text-white">{{ __('WhatsApp pay links') }}</h3>
                            <p class="text-xs text-zinc-400 leading-relaxed">{{ __('Send a checkout link in chat. Guest pays, you get confirmed.') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-ticket text-sm"></i>
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <h3 class="text-sm font-bold text-white">{{ __('E-tickets on WhatsApp') }}</h3>
                            <p class="text-xs text-zinc-400 leading-relaxed">{{ __('Auto voucher, QR pass, and meeting pin on their phone.') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-clipboard-list text-sm"></i>
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <h3 class="text-sm font-bold text-white">{{ __('Daily guest lists') }}</h3>
                            <p class="text-xs text-zinc-400 leading-relaxed">{{ __('Pickup lists for drivers, guides, and boat captains.') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-building-columns text-sm"></i>
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <h3 class="text-sm font-bold text-white">{{ __('Payouts, 0% cut') }}</h3>
                            <p class="text-xs text-zinc-400 leading-relaxed">{{ __('Keep 100% of the ticket. Money goes to your Indonesian bank.') }}</p>
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
                                Instant WhatsApp Chat-to-Checkout Links
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed max-w-xl">
                                Chatting with a guest on WhatsApp? Create a custom reservation link in 1 click. They tap, select payment (QRIS, BCA, Credit Card), and get confirmed instantly on their phone.
                            </p>
                        </div>

                        <!-- Mini Interactive Chat Mockup -->
                        <div class="rounded-xl bg-[#09090b] border border-zinc-800 p-4 space-y-3">
                            <div class="flex items-center justify-between text-xs border-b border-zinc-800 pb-2">
                                <div class="flex items-center gap-2">
                                    <i class="fa-brands fa-whatsapp text-emerald-400 text-sm"></i>
                                    <span class="font-bold text-white">WhatsApp Live Checkout</span>
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
                                    <span class="text-[10px] text-emerald-400 block">✓ Instant 2-second confirmation</span>
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
                                WhatsApp E-Tickets & Boarding Passes
                            </h3>
                            <p class="text-xs text-zinc-400 leading-relaxed">
                                Automated digital vouchers sent straight to the customer's phone with QR code and Google Map meeting pins.
                            </p>
                        </div>

                        <!-- Ticket Pass Card -->
                        <div class="rounded-xl bg-[#09090b] border border-zinc-800 p-3.5 space-y-2 text-xs">
                            <div class="flex items-center justify-between text-zinc-300 font-bold">
                                <span>🎫 E-Pass #NUSA-882</span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-[#FFEF4D] text-[#090d16] font-black">CONFIRMED</span>
                            </div>
                            <div class="text-[11px] text-zinc-400 pt-1 border-t border-zinc-800 space-y-0.5">
                                <div>📍 Sanur Harbor Gate 3 (07:30 AM)</div>
                                <div>👥 2 Guests • Snorkeling & Speedboat</div>
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
                                Daily Pick-up & Guest Manifests
                            </h3>
                            <p class="text-xs text-zinc-400 leading-relaxed">
                                Print or export daily passenger lists for boat captains, drivers, and tour guides.
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
                                Zero Commission & Direct Bank Transfers
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed max-w-xl">
                                Keep 100% of your listed ticket price. Earnings transfer directly into your Indonesian bank account (BCA, Mandiri, BRI, BNI) via BI-FAST with zero platform deductions.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-4 rounded-xl bg-[#09090b] border border-zinc-800 space-y-1">
                                <span class="text-xs text-zinc-400 block">Bank Transfer Settlement</span>
                                <span class="font-black font-mono text-lg text-emerald-400 block">Rp 7.800.000</span>
                                <span class="text-[10px] text-zinc-500 block">Direct to BCA • 0% Platform Commission</span>
                            </div>
                            <div class="p-4 rounded-xl bg-[#09090b] border border-zinc-800 space-y-1">
                                <span class="text-xs text-zinc-400 block">Phone Calendar Sync</span>
                                <span class="font-bold text-sm text-white block">Google & Apple Calendar</span>
                                <span class="text-[10px] text-zinc-500 block">Auto-syncs departures & passenger slots</span>
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
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                        Start free. Keep 100% of your tour price.
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                        No hidden cuts, no monthly surprises. Start for free and upgrade only when your business expands.
                    </p>

                    <!-- Toggle -->
                    <div class="inline-flex p-1 rounded-xl bg-[#131316] border border-zinc-800 self-center mt-3">
                        <button type="button" x-on:click="billing_interval = 'monthly'"
                            :class="billing_interval === 'monthly' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'text-zinc-400 hover:text-white'"
                            class="px-3.5 sm:px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer">
                            {{ __('Monthly') }}
                        </button>
                        <button type="button" x-on:click="billing_interval = 'yearly'"
                            :class="billing_interval === 'yearly' ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'text-zinc-400 hover:text-white'"
                            class="px-3.5 sm:px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5">
                            <span>{{ __('Yearly') }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-[#FFEF4D]/20 text-[#FFEF4D]">{{ __('Save 17%') }}</span>
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
                                'growth' => [__('WhatsApp links, tickets, guest lists'), $plan->listingLimitLabel().' · '.$plan->teamSeatLabel()],
                                'agency' => [__('Your domain + white-label site'), $plan->listingLimitLabel()],
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
                                    <p class="text-lg font-black text-white tracking-tight"
                                        x-text="billing_interval === 'yearly' ? '{{ $plan->isFree() ? __('Free') : 'Rp '.number_format($priceYearly, 0, ',', '.').' / year' }}' : '{{ $plan->isFree() ? __('Free') : 'Rp '.number_format($priceMonthly, 0, ',', '.').' / month' }}'">
                                        {{ $plan->isFree() ? __('Free') : 'Rp '.number_format($priceMonthly, 0, ',', '.').' / month' }}
                                    </p>
                                </div>
                                <a href="{{ route('register') }}"
                                    class="shrink-0 h-9 px-3 rounded-lg {{ $plan->is_popular ? 'bg-[#FFEF4D] text-[#090d16] font-black' : 'bg-zinc-800 text-zinc-100 border border-zinc-700 font-bold' }} text-xs flex items-center cursor-pointer"
                                    wire:navigate>
                                    {{ $plan->isFree() ? __('Start free') : __('Choose') }}
                                </a>
                            </div>
                            <ul class="space-y-1 text-xs text-zinc-400">
                                @foreach ($mobileHighlights as $highlight)
                                    <li class="flex items-start gap-2">
                                        <i class="fa-solid fa-check text-emerald-400 text-[10px] mt-0.5 shrink-0"></i>
                                        <span>{{ $highlight }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    <p class="text-center text-[11px] text-zinc-500">{{ __('You keep 100% of the ticket. Guest pays a 5% online fee.') }}</p>
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
                                            <span class="text-zinc-400 font-medium">{{ __('Your Payout') }}</span>
                                            <span class="font-bold font-mono text-xs text-emerald-400">
                                                {{ __('100% Net (0% Cut)') }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between text-[10px] text-zinc-500">
                                            <span>{{ __('Online Guest Fee') }}</span>
                                            <span class="font-medium text-zinc-300">
                                                {{ __('5% Paid by Customer') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Limits -->
                                <div class="grid grid-cols-2 gap-2 text-center">
                                    <div class="p-2 rounded-lg bg-[#09090b] border border-zinc-800">
                                        <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block">{{ __('Tour Packages') }}</span>
                                        <span class="font-bold text-xs text-zinc-200 mt-0.5 block">
                                            {{ $plan->listingLimitLabel() }}
                                        </span>
                                    </div>
                                    <div class="p-2 rounded-lg bg-[#09090b] border border-zinc-800">
                                        <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block">{{ __('Staff Accounts') }}</span>
                                        <span class="font-bold text-xs text-zinc-200 mt-0.5 block">
                                            {{ $plan->teamSeatLabel() }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Features Checklist -->
                                <div class="space-y-2 pt-2 border-t border-zinc-800">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('What is included:') }}</span>
                                    <ul class="space-y-1.5 text-[11px]">
                                        <li class="flex items-start gap-2 {{ $plan->hasFeature('quick_booking_links') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i class="fa-solid {{ $plan->hasFeature('quick_booking_links') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Instant WhatsApp Payment Links') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2 {{ $plan->hasFeature('google_calendar') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i class="fa-solid {{ $plan->hasFeature('google_calendar') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Sync with Google & Phone Calendar') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2 {{ $plan->hasFeature('whatsapp_dispatch') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i class="fa-solid {{ $plan->hasFeature('whatsapp_dispatch') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('WhatsApp Vouchers & Reminders') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2 {{ $plan->hasFeature('daily_manifest_export') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i class="fa-solid {{ $plan->hasFeature('daily_manifest_export') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Printable Daily Pick-up & Guest Lists') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2 {{ $plan->hasFeature('guest_crm') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i class="fa-solid {{ $plan->hasFeature('guest_crm') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Guest Contact List & Booking History') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2 {{ $plan->hasFeature('custom_domain') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i class="fa-solid {{ $plan->hasFeature('custom_domain') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Use Your Own Domain (yourcompany.com)') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2 {{ $plan->hasFeature('remove_branding') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i class="fa-solid {{ $plan->hasFeature('remove_branding') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('Remove EMVI branding on your storefront') }}</span>
                                        </li>
                                        <li class="flex items-start gap-2 {{ $plan->hasFeature('ai_discovery') ? 'text-zinc-200 font-medium' : 'text-zinc-600 line-through' }}">
                                            <i class="fa-solid {{ $plan->hasFeature('ai_discovery') ? 'fa-check text-emerald-400' : 'fa-xmark text-zinc-700' }} text-xs mt-0.5 shrink-0"></i>
                                            <span>{{ __('ChatGPT & AI Search Ready') }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- CTA Button -->
                            <div class="pt-5 border-t border-zinc-800 mt-4">
                                <a href="{{ route('register') }}"
                                    class="w-full h-10 rounded-lg {{ $plan->is_popular ? 'bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-100 border border-zinc-700 font-bold' }} text-xs transition flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                                    wire:navigate>
                                    <span>{{ __('Choose :plan', ['plan' => $plan->name]) }}</span>
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
                                    <!-- Category: Commercials -->
                                    <tr class="bg-[#09090b]">
                                        <td colspan="4" class="py-2 px-3 font-bold text-[10px] uppercase tracking-wider text-[#FFEF4D]">
                                            {{ __('1. Pricing & Payouts') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Platform Commission') }}</td>
                                        <td class="py-3 px-2 text-center font-bold text-emerald-400">0% (Keep 100%)</td>
                                        <td class="py-3 px-2 text-center font-bold text-emerald-400 bg-zinc-900/60">0% (Keep 100%)</td>
                                        <td class="py-3 px-2 text-center font-bold text-emerald-400">0% (Keep 100%)</td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Online Customer Fee') }}</td>
                                        <td class="py-3 px-2 text-center text-zinc-400">5.0%</td>
                                        <td class="py-3 px-2 text-center text-zinc-400 bg-zinc-900/60">5.0%</td>
                                        <td class="py-3 px-2 text-center text-zinc-400">5.0%</td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Direct Bank Transfers (BCA, Mandiri, BRI, BNI)') }}</td>
                                        <td class="py-3 px-2 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3 px-2 text-center bg-zinc-900/60"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3 px-2 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>

                                    <!-- Category: Storefront & Web Presence -->
                                    <tr class="bg-[#09090b]">
                                        <td colspan="4" class="py-2 px-3 font-bold text-[10px] uppercase tracking-wider text-[#FFEF4D]">
                                            {{ __('2. Tour Website & Links') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Free Website Address (`yourname.travelengine.online`)') }}</td>
                                        <td class="py-3 px-2 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3 px-2 text-center bg-zinc-900/60"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3 px-2 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Connect Your Own Website Domain (`yourcompany.com`)') }}</td>
                                        <td class="py-3 px-2 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center text-zinc-600 bg-zinc-900/60"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center font-bold text-zinc-200"><i class="fa-solid fa-check text-emerald-400"></i> Included</td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Use Your Own Payment Gateway Account') }}</td>
                                        <td class="py-3 px-2 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center text-zinc-600 bg-zinc-900/60"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                    </tr>

                                    <!-- Category: Booking Engine & Inventory -->
                                    <tr class="bg-[#09090b]">
                                        <td colspan="4" class="py-2 px-3 font-bold text-[10px] uppercase tracking-wider text-[#FFEF4D]">
                                            {{ __('3. Tour Packages & Staff') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Number of Tour Packages') }}</td>
                                        <td class="py-3 px-2 text-center font-semibold text-zinc-300">5 Tours</td>
                                        <td class="py-3 px-2 text-center font-bold text-[#FFEF4D] bg-zinc-900/60">25 Tours</td>
                                        <td class="py-3 px-2 text-center font-bold text-emerald-400">Unlimited</td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Staff & Guide Accounts') }}</td>
                                        <td class="py-3 px-2 text-center font-semibold text-zinc-300">You + 1</td>
                                        <td class="py-3 px-2 text-center font-semibold text-emerald-400 bg-zinc-900/60">Unlimited</td>
                                        <td class="py-3 px-2 text-center font-semibold text-emerald-400">Unlimited</td>
                                    </tr>

                                    <!-- Category: Daily Operations & Tools -->
                                    <tr class="bg-[#09090b]">
                                        <td colspan="4" class="py-2 px-3 font-bold text-[10px] uppercase tracking-wider text-[#FFEF4D]">
                                            {{ __('4. Daily Operations & Tools') }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Sync with Google Calendar & Phone Calendar') }}</td>
                                        <td class="py-3 px-2 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center bg-zinc-900/60"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3 px-2 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Guest Contact List & History') }}</td>
                                        <td class="py-3 px-2 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center bg-zinc-900/60"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3 px-2 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('WhatsApp Tickets & Reminders') }}</td>
                                        <td class="py-3 px-2 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center bg-zinc-900/60"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3 px-2 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('Daily Pick-up & Guest Manifests (PDF & Print)') }}</td>
                                        <td class="py-3 px-2 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center bg-zinc-900/60"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                        <td class="py-3 px-2 text-center"><i class="fa-solid fa-check text-emerald-400"></i></td>
                                    </tr>
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="py-3 pr-3 text-zinc-300 font-medium">{{ __('ChatGPT & AI Search Indexing') }}</td>
                                        <td class="py-3 px-2 text-center text-zinc-600"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center bg-zinc-900/60"><i class="fa-solid fa-minus"></i></td>
                                        <td class="py-3 px-2 text-center font-bold text-zinc-200"><i class="fa-solid fa-check text-emerald-400"></i> Included</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Honest Pricing Note -->
                <div class="hidden md:block p-5 rounded-2xl bg-[#131316] border border-zinc-800 max-w-2xl mx-auto text-center space-y-1.5">
                    <div class="inline-flex items-center gap-2 text-xs font-bold text-[#FFEF4D]">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>{{ __('Zero Commission Guarantee') }}</span>
                    </div>
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        You work hard to provide great tour experiences. We never take a percentage cut of your ticket earnings. You keep 100% of your listed prices.
                    </p>
                </div>
            </div>
        </section>

        <!-- Frequently Asked Questions (FAQ) Section -->
        <section id="faq" class="py-16 sm:py-20 border-t border-zinc-800 bg-[#0d0d10] scroll-mt-16" x-data="{ activeAccordion: null }">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
                <div class="text-center space-y-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#FFEF4D] block">{{ __('Common Questions') }}</span>
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                        Everything you need to know
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                        {{ __('Have questions about payouts, setting up your website, or plans? Here are quick answers.') }}
                    </p>
                </div>

                <div class="space-y-3">
                    <!-- FAQ Item 1 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 1 ? null : 1"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-xs sm:text-sm text-white">
                                {{ __('How do I receive payouts from my tour bookings?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 1 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 1" x-collapse
                            class="px-4 sm:px-5 pb-5 text-xs text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('When a customer pays online via QRIS, Virtual Account, or Credit Card, funds go directly to your operator balance. Payouts transfer directly into your Indonesian bank account (BCA, Mandiri, BRI, BNI, and more) quickly and securely.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 2 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 2 ? null : 2"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-xs sm:text-sm text-white">
                                {{ __('Do I need any technical or coding skills?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 2 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 2" x-collapse
                            class="px-4 sm:px-5 pb-5 text-xs text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('None at all! Your website and booking system are set up automatically. Simply add your tour photos, enter your prices, and add your WhatsApp number. Your site is ready to share on Instagram or WhatsApp in under 5 minutes.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 3 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 3 ? null : 3"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-xs sm:text-sm text-white">
                                {{ __('How does the Zero Commission work?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 3 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 3" x-collapse
                            class="px-4 sm:px-5 pb-5 text-xs text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('Unlike traditional travel agent platforms that take 15% to 30% from your ticket revenue, we charge 0% commission to the tour operator. A standard 5.0% online service fee is added to the customer at checkout, meaning you receive 100% of your listed ticket price.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 4 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 4 ? null : 4"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-xs sm:text-sm text-white">
                                {{ __('Can I use my own domain name (e.g. mycompany.com)?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 4 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 4" x-collapse
                            class="px-4 sm:px-5 pb-5 text-xs text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('Yes! On the Agency plan, you can connect your own custom domain name (e.g. www.yourcompany.com). We provide free automatic security (SSL) so your website is safe and verified.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 5 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 5 ? null : 5"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-xs sm:text-sm text-white">
                                {{ __('Can I change plans or cancel anytime?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 5 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 5" x-collapse
                            class="px-4 sm:px-5 pb-5 text-xs text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
                            style="display: none;">
                            {{ __('Yes. There are no lock-in contracts or long-term commitments. Start on the free plan, upgrade when your business grows, or cancel anytime with 1 click.') }}
                        </div>
                    </div>

                    <!-- FAQ Item 6 -->
                    <div class="rounded-xl bg-[#131316] border border-zinc-800 overflow-hidden transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 6 ? null : 6"
                            class="w-full p-4 sm:p-5 text-left flex items-center justify-between gap-4 cursor-pointer">
                            <span class="font-bold text-xs sm:text-sm text-white">
                                {{ __('Can my tour guides and staff have their own accounts?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-400 transition-transform duration-200"
                                :class="activeAccordion === 6 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 6" x-collapse
                            class="px-4 sm:px-5 pb-5 text-xs text-zinc-400 leading-relaxed border-t border-zinc-800 pt-3"
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
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight">
                    Ready to start taking direct tour bookings?
                </h2>
                <p class="text-xs sm:text-sm text-zinc-400 max-w-xl mx-auto leading-relaxed">
                    Set up your tour packages, add your bank details, and start accepting online bookings in under 10 minutes.
                </p>
                <div class="pt-2">
                    <a href="{{ route('register') }}"
                        class="h-11 sm:h-12 px-8 sm:px-9 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-sm sm:text-base transition inline-flex items-center gap-2 cursor-pointer shadow-sm"
                        wire:navigate>
                        <span>{{ __('Create Your Tour Website Now') }}</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>

                <!-- Trust Reassurance Checklist -->
                <div class="pt-2 flex flex-wrap items-center justify-center gap-4 sm:gap-6 text-xs text-zinc-400">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('No credit card required') }}</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('Free Starter plan forever') }}</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('Ready in under 5 minutes') }}</span>
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
                <a href="{{ route('login') }}" class="hover:text-zinc-300 transition" wire:navigate>{{ __('Operator Login') }}</a>
                <a href="{{ route('register') }}" class="hover:text-zinc-300 transition" wire:navigate>{{ __('Operator Register') }}</a>
            </div>

            <p class="text-[11px] text-zinc-600">
                &copy; {{ date('Y') }} {{ config('app.name', 'TravelEngine') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </footer>
    @livewireScripts
</body>

</html>
