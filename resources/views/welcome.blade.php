<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">

<head>
    @include('partials.head', [
        'title' => __('TravelEngine — The Modern Booking Engine for Tour Operators'),
    ])
    @livewireStyles
    <style>
        /* Architectural Blueprint Grid Background */
        .blueprint-grid {
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        .blueprint-dots {
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        /* Subtle glow accents */
        .glow-radial-yellow {
            background: radial-gradient(circle at 50% 0%, rgba(255, 239, 77, 0.12) 0%, rgba(255, 239, 77, 0.02) 40%, transparent 70%);
        }

        /* Monospace numerical alignment */
        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>

<body class="min-h-screen bg-[#08090d] text-zinc-100 antialiased selection:bg-[#FFEF4D] selection:text-[#090d16] font-sans relative overflow-x-clip">

    <!-- Top Architectural System Status Bar -->
    <div class="border-b border-white/[0.08] bg-[#0c0e14] py-1.5 sm:py-2 px-3 sm:px-4 text-xs font-mono text-zinc-400">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] sm:text-[11px] font-medium whitespace-nowrap shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="sm:hidden">{{ __('PAYMENTS: ACTIVE') }}</span>
                    <span class="hidden sm:inline">{{ __('ONLINE PAYMENTS & BANK PAYOUTS: ACTIVE') }}</span>
                </span>
                <span class="hidden md:inline text-zinc-600">|</span>
                <span class="hidden md:inline text-zinc-400 text-[11px]">
                    <span class="text-[#FFEF4D] font-bold">{{ __('Website + Bookings + Payments') }}</span> — {{ __('Direct settlements to Indonesian Bank Accounts') }}
                </span>
            </div>
            <div class="flex items-center gap-2 sm:gap-4 text-[10px] sm:text-[11px] shrink-0">
                <a href="#pricing" class="text-zinc-400 hover:text-white transition flex items-center gap-1">
                    <span class="text-[#FFEF4D] font-bold sm:text-zinc-400 sm:font-normal">{{ __('0%') }}</span>
                    <span class="hidden xs:inline">{{ __('Commission') }}</span>
                </a>
                <span class="text-zinc-700 hidden sm:inline">•</span>
                <span class="hidden sm:inline text-zinc-500">v2.4.0-id</span>
            </div>
        </div>
    </div>

    <!-- Header Navigation Bar (Architectural Blueprint) -->
    <header class="sticky top-0 z-50 border-b border-white/[0.08] bg-[#08090d]/85 backdrop-blur-xl" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3 sm:gap-4">
            
            <!-- Platform Brand Logo & Blueprint Tag -->
            <div class="flex items-center gap-2.5 sm:gap-3 select-none shrink-0 min-w-0">
                <a href="{{ route('home') }}" class="flex items-center gap-2 sm:gap-2.5 group">
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-sm sm:text-base font-black shrink-0 transition-transform group-hover:scale-105">
                        <i class="fa-solid fa-compass"></i>
                    </div>
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <span class="font-black text-base sm:text-lg tracking-tight text-white leading-none">
                            {{ config('app.name', 'TravelEngine') }}
                        </span>
                        <span class="hidden xs:inline-block px-1.5 sm:px-2 py-0.5 rounded text-[9px] sm:text-[10px] font-mono font-bold uppercase tracking-wider bg-white/[0.06] border border-white/[0.12] text-[#FFEF4D]">
                            Platform
                        </span>
                    </div>
                </a>
            </div>

            <!-- Architectural Nav Links -->
            <nav class="hidden lg:flex items-center gap-8 text-xs font-mono font-medium text-zinc-400">
                <a href="#sandbox" class="hover:text-white transition flex items-center gap-1.5">
                    <span class="text-zinc-600">01</span>
                    <span>{{ __('How It Works') }}</span>
                </a>
                <a href="#capabilities" class="hover:text-white transition flex items-center gap-1.5">
                    <span class="text-zinc-600">02</span>
                    <span>{{ __('What’s Included') }}</span>
                </a>
                <a href="#specs" class="hover:text-white transition flex items-center gap-1.5">
                    <span class="text-zinc-600">03</span>
                    <span>{{ __('Features') }}</span>
                </a>
                <a href="#pricing" class="hover:text-white transition flex items-center gap-1.5">
                    <span class="text-zinc-600">04</span>
                    <span>{{ __('Pricing & Plans') }}</span>
                </a>
                <a href="#faq" class="hover:text-white transition flex items-center gap-1.5">
                    <span class="text-zinc-600">05</span>
                    <span>{{ __('FAQ') }}</span>
                </a>
            </nav>

            <!-- Nav Action Buttons -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                @auth
                    <a href="{{ route('dashboard') }}"
                        class="h-9 px-3 sm:px-4 rounded-lg bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-bold text-xs transition flex items-center gap-1.5 sm:gap-2 shadow-xs"
                        wire:navigate>
                        <i class="fa-solid fa-gauge text-[11px]"></i>
                        <span class="hidden sm:inline">{{ __('Operator Console') }}</span>
                        <span class="sm:hidden">{{ __('Console') }}</span>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="hidden sm:inline-flex px-3.5 py-2 text-xs font-medium text-zinc-300 hover:text-white transition"
                        wire:navigate>
                        {{ __('Operator log in') }}
                    </a>
                    <a href="{{ route('register') }}"
                        class="hidden sm:inline-flex h-9 px-4 cursor-pointer items-center justify-center rounded-lg bg-[#FFEF4D] text-[#090d16] hover:bg-[#fae639] text-xs font-black transition gap-2 shadow-sm"
                        wire:navigate>
                        <span>{{ $registrationOpen ? __('Start Free') : __('Coming soon') }}</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                @endauth

                <!-- Mobile Hamburger Toggle Button -->
                <button type="button"
                    class="lg:hidden h-9 w-9 inline-flex items-center justify-center rounded-lg border border-white/[0.1] bg-white/[0.04] text-zinc-300 hover:text-white focus:outline-none cursor-pointer"
                    x-on:click="mobileMenuOpen = !mobileMenuOpen"
                    :aria-expanded="mobileMenuOpen"
                    aria-label="{{ __('Toggle Menu') }}">
                    <i class="fa-solid text-sm" :class="mobileMenuOpen ? 'fa-xmark' : 'fa-bars'"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Drawer -->
        <div x-show="mobileMenuOpen" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="lg:hidden border-t border-white/[0.08] bg-[#090b10]/95 backdrop-blur-2xl px-5 py-5 space-y-4 font-mono text-xs">
            <div class="space-y-1">
                <a href="#sandbox" class="flex items-center justify-between py-2 text-zinc-300 hover:text-white border-b border-white/[0.04]" x-on:click="mobileMenuOpen = false">
                    <span><span class="text-zinc-600 mr-2">01</span>{{ __('See How It Works') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px] text-zinc-600"></i>
                </a>
                <a href="#capabilities" class="flex items-center justify-between py-2 text-zinc-300 hover:text-white border-b border-white/[0.04]" x-on:click="mobileMenuOpen = false">
                    <span><span class="text-zinc-600 mr-2">02</span>{{ __('What’s Included') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px] text-zinc-600"></i>
                </a>
                <a href="#specs" class="flex items-center justify-between py-2 text-zinc-300 hover:text-white border-b border-white/[0.04]" x-on:click="mobileMenuOpen = false">
                    <span><span class="text-zinc-600 mr-2">03</span>{{ __('Platform Features') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px] text-zinc-600"></i>
                </a>
                <a href="#pricing" class="flex items-center justify-between py-2 text-zinc-300 hover:text-white border-b border-white/[0.04]" x-on:click="mobileMenuOpen = false">
                    <span><span class="text-zinc-600 mr-2">04</span>{{ __('Pricing & Plans') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px] text-zinc-600"></i>
                </a>
                <a href="#faq" class="flex items-center justify-between py-2 text-zinc-300 hover:text-white" x-on:click="mobileMenuOpen = false">
                    <span><span class="text-zinc-600 mr-2">05</span>{{ __('FAQ') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px] text-zinc-600"></i>
                </a>
            </div>

            <div class="pt-4 border-t border-white/[0.08] space-y-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="w-full h-11 rounded-lg bg-[#FFEF4D] text-[#090d16] font-bold text-xs flex items-center justify-center gap-2" wire:navigate>
                        <i class="fa-solid fa-gauge text-[11px]"></i>
                        <span>{{ __('Open Operator Console') }}</span>
                    </a>
                @else
                    <a href="{{ route('register') }}" class="w-full h-11 rounded-lg bg-[#FFEF4D] text-[#090d16] font-black text-xs flex items-center justify-center gap-2 shadow-sm" wire:navigate>
                        <span>{{ $registrationOpen ? __('Start free') : __('Coming soon') }}</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                    <a href="{{ route('login') }}" class="w-full h-10 rounded-lg bg-white/[0.04] border border-white/[0.08] text-zinc-300 font-medium text-xs flex items-center justify-center" wire:navigate>
                        {{ __('Operator Log in') }}
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Structural Blueprint Frame Container -->
    <div class="max-w-7xl mx-auto border-x border-white/[0.08] relative">

        <!-- Blueprint Corner Crosshairs -->
        <div class="absolute -top-1.5 -left-1.5 text-zinc-600 font-mono text-[10px] select-none z-10">+</div>
        <div class="absolute -top-1.5 -right-1.5 text-zinc-600 font-mono text-[10px] select-none z-10">+</div>

        <!-- HERO SECTION: Architectural Header & Identity -->
        <section data-mobile-hero class="relative pt-10 pb-14 md:pt-20 md:pb-24 px-4 sm:px-6 lg:px-8 border-b border-white/[0.08] blueprint-grid glow-radial-yellow overflow-hidden">
            
            <div class="max-w-4xl mx-auto text-center space-y-5 sm:space-y-6">
                
                <!-- Eyebrow Pill -->
                <div class="inline-flex items-center gap-2 px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-full bg-white/[0.04] border border-white/[0.12] text-[11px] sm:text-xs font-mono text-zinc-300 backdrop-blur-md max-w-full">
                    <span class="flex h-2 w-2 relative shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#FFEF4D] opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-[#FFEF4D]"></span>
                    </span>
                    <span class="text-[#FFEF4D] font-bold whitespace-nowrap">{{ __('WEBSITE + BOOKING ENGINE + PAYMENTS') }}</span>
                    <span class="text-zinc-600 hidden xs:inline">/</span>
                    <span class="text-zinc-300 hidden xs:inline whitespace-nowrap">{{ __('ALL IN 1 PLATFORM') }}</span>
                </div>

                <!-- Main Hero Headline -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-[1.15] sm:leading-[1.1] text-balance">
                    {{ __('Guests book themselves.') }}<br>
                    <span class="text-[#FFEF4D]">
                        {{ __('You keep the listed price.') }}
                    </span>
                </h1>

                <!-- Subheadline -->
                <p class="text-sm sm:text-lg text-zinc-400 max-w-2xl mx-auto font-normal leading-relaxed text-balance px-2 sm:px-0">
                    {{ __('Your complete tour website, online booking engine, and payment gateway in one simple platform. Built for boat charters, volcano treks, and diving operators across Indonesia. Guests book and pay themselves, with direct payouts to your Indonesian bank.') }}
                </p>

                <!-- Dual Action CTAs -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2 sm:pt-3">
                    <a href="{{ route('register') }}"
                        class="w-full sm:w-auto h-12 px-8 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-sm transition flex items-center justify-center gap-2 cursor-pointer shadow-sm group"
                        wire:navigate>
                        <span>{{ $registrationOpen ? __('Start free') : __('Coming soon') }}</span>
                        <i class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-0.5"></i>
                    </a>
                    
                    <a href="#sandbox"
                        class="w-full sm:w-auto h-12 px-6 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] text-white border border-white/[0.12] font-semibold text-sm transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-play text-xs text-[#FFEF4D]"></i>
                        <span>{{ __('See How It Works') }}</span>
                    </a>
                </div>

                <!-- Trust Micro-Badges -->
                <div class="pt-3 sm:pt-4 flex flex-wrap items-center justify-center gap-x-4 sm:gap-x-6 gap-y-2 text-[11px] sm:text-xs font-mono text-zinc-400">
                    <span class="inline-flex items-center gap-1.5 sm:gap-2">
                        <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                        <span>{{ __('Tour website included') }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 sm:gap-2">
                        <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                        <span>{{ __('0% Ticket Cut (Keep 100%)') }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 sm:gap-2">
                        <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                        <span>{{ __('Virtual Accounts & QRIS') }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 sm:gap-2">
                        <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                        <span>{{ __('No credit card required') }}</span>
                    </span>
                </div>
            </div>

            <!-- Blueprint Metric Ribbon (4-Tile Architectural Row) -->
            <div class="mt-10 sm:mt-12 max-w-5xl mx-auto grid grid-cols-2 md:grid-cols-4 border border-white/[0.08] bg-[#0c0e14]/60 backdrop-blur-sm rounded-xl overflow-hidden">
                <div class="p-3.5 sm:p-5 text-center space-y-1 border-r border-b md:border-b-0 border-white/[0.08]">
                    <div class="text-[9px] sm:text-[11px] font-mono uppercase tracking-wider text-zinc-500">{{ __('TICKET COMMISSION') }}</div>
                    <div class="text-2xl sm:text-3xl font-black text-[#FFEF4D] tabular-nums">0%</div>
                    <div class="text-[10px] sm:text-[11px] text-zinc-400 truncate">{{ __('Keep 100% listed price') }}</div>
                </div>
                <div class="p-3.5 sm:p-5 text-center space-y-1 border-b md:border-b-0 md:border-r border-white/[0.08]">
                    <div class="text-[9px] sm:text-[11px] font-mono uppercase tracking-wider text-zinc-500">{{ __('PAYMENT GATEWAY') }}</div>
                    <div class="text-xl sm:text-3xl font-black text-white tabular-nums tracking-tight">INSTANT</div>
                    <div class="text-[10px] sm:text-[11px] text-zinc-400 truncate">{{ __('Direct domestic bank') }}</div>
                </div>
                <div class="p-3.5 sm:p-5 text-center space-y-1 border-r border-white/[0.08]">
                    <div class="text-[9px] sm:text-[11px] font-mono uppercase tracking-wider text-zinc-500">{{ __('CHECKOUT SPEED') }}</div>
                    <div class="text-2xl sm:text-3xl font-black text-emerald-400 tabular-nums">&lt; 1.2s</div>
                    <div class="text-[10px] sm:text-[11px] text-zinc-400 truncate">{{ __('Instant QRIS & Cards') }}</div>
                </div>
                <div class="p-3.5 sm:p-5 text-center space-y-1">
                    <div class="text-[9px] sm:text-[11px] font-mono uppercase tracking-wider text-zinc-500">{{ __('SETUP TIMELINE') }}</div>
                    <div class="text-2xl sm:text-3xl font-black text-white tabular-nums">5 Mins</div>
                    <div class="text-[10px] sm:text-[11px] text-zinc-400 truncate">{{ __('Tour website + 24/7 booking') }}</div>
                </div>
            </div>
        </section>

        <!-- SECTION 01: THE INTERACTIVE SANDBOX (Laravel Cloud Inspired) -->
        <section id="sandbox" class="scroll-mt-16 py-16 md:py-24 px-4 sm:px-6 lg:px-8 border-b border-white/[0.08] relative"
            x-data="{
                activeTab: 'storefront',
                selectedTour: 'nusa',
                guests: 2,
                selectedDate: 'Tomorrow, 08:00 AM',
                tours: {
                    nusa: {
                        name: 'Nusa Penida & Manta Bay Snorkeling Adventure',
                        price: 650000,
                        duration: '8 Hours (Full Day)',
                        pickup: 'Sanur Port Harbor Pier 3',
                        tag: 'Bestseller Fastboat Excursion',
                        vessel: 'Speedboat Sea Falcon III',
                        maxCapacity: 24,
                        slotsLeft: 6
                    },
                    komodo: {
                        name: 'Komodo Islands 3D2N Phinisi Luxury Cruise',
                        price: 3200000,
                        duration: '3 Days 2 Nights',
                        pickup: 'Labuan Bajo Marina Gate 2',
                        tag: 'VIP Phinisi Yacht Charter',
                        vessel: 'KLM Cordelia Liveaboard',
                        maxCapacity: 14,
                        slotsLeft: 3
                    },
                    batur: {
                        name: 'Mount Batur Sunrise 4WD Black Lava Jeep Tour',
                        price: 450000,
                        duration: '6 Hours (Sunrise Summit)',
                        pickup: 'Ubud Hotel Lobby Direct',
                        tag: 'Trending Adventure Excursion',
                        vessel: 'Custom 4x4 Jeep Convoy',
                        maxCapacity: 4,
                        slotsLeft: 2
                    }
                },
                bookingSimulated: false,
                magicLinkCopied: false,
                manifestStatus: {
                    'pax1': 'Checked In',
                    'pax2': 'Boarded',
                    'pax3': 'En Route'
                },
                formatIdr(val) {
                    return 'Rp ' + val.toLocaleString('id-ID');
                },
                simulateBooking() {
                    this.bookingSimulated = true;
                    setTimeout(() => { this.bookingSimulated = false; }, 4000);
                },
                copyMagicLink() {
                    this.magicLinkCopied = true;
                    setTimeout(() => { this.magicLinkCopied = false; }, 2500);
                },
                togglePaxStatus(key) {
                    if (this.manifestStatus[key] === 'En Route') {
                        this.manifestStatus[key] = 'Checked In';
                    } else if (this.manifestStatus[key] === 'Checked In') {
                        this.manifestStatus[key] = 'Boarded';
                    } else {
                        this.manifestStatus[key] = 'Checked In';
                    }
                }
            }">

            <!-- Section Eyebrow & Headline -->
            <div class="max-w-3xl mx-auto text-center space-y-3 mb-12">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white/[0.04] border border-white/[0.1] text-xs font-mono text-zinc-400">
                    <span class="text-[#FFEF4D] font-bold">01</span>
                    <span>{{ __('SEE HOW IT WORKS') }}</span>
                </div>
                <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                    {{ __('Your website, bookings, and payments working as one.') }}
                </h2>
                <p class="text-sm sm:text-base text-zinc-400 max-w-xl mx-auto">
                    {{ __('Experience the complete flow: your live tour website, instant WhatsApp guest tickets, daily crew passenger list, and direct bank payouts.') }}
                </p>
            </div>

            <!-- Architectural Console Window -->
            <div class="max-w-5xl mx-auto rounded-2xl border border-white/[0.12] bg-[#0c0e14] shadow-2xl overflow-hidden">
                
                <!-- Window Header Bar (Blueprint Terminal Header) -->
                <div class="border-b border-white/[0.08] bg-[#090b10] px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1.5 mr-2">
                            <span class="w-3 h-3 rounded-full bg-red-500/80 border border-red-600/40"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500/80 border border-amber-600/40"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500/80 border border-emerald-600/40"></span>
                        </div>
                        <span class="font-mono text-xs text-zinc-400 hidden sm:inline">travelengine // live-platform-walkthrough</span>
                    </div>

                    <!-- Sandbox Tab Switcher (Horizontally scrollable on mobile) -->
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1 w-full sm:w-auto -mx-1 px-1">
                        <button type="button" @click="activeTab = 'storefront'"
                            :class="activeTab === 'storefront' ? 'bg-[#FFEF4D] text-[#090d16] font-bold' : 'text-zinc-400 hover:text-white bg-white/[0.04] border border-white/[0.06]'"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-mono transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <i class="fa-solid fa-store text-[10px]"></i>
                            <span><span class="hidden sm:inline">01 · </span>TOUR WEBSITE</span>
                        </button>
                        <button type="button" @click="activeTab = 'whatsapp'"
                            :class="activeTab === 'whatsapp' ? 'bg-[#FFEF4D] text-[#090d16] font-bold' : 'text-zinc-400 hover:text-white bg-white/[0.04] border border-white/[0.06]'"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-mono transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <i class="fa-brands fa-whatsapp text-[11px]"></i>
                            <span><span class="hidden sm:inline">02 · </span>WHATSAPP TICKET</span>
                        </button>
                        <button type="button" @click="activeTab = 'manifest'"
                            :class="activeTab === 'manifest' ? 'bg-[#FFEF4D] text-[#090d16] font-bold' : 'text-zinc-400 hover:text-white bg-white/[0.04] border border-white/[0.06]'"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-mono transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <i class="fa-solid fa-ship text-[10px]"></i>
                            <span><span class="hidden sm:inline">03 · </span>PASSENGER LIST</span>
                        </button>
                        <button type="button" @click="activeTab = 'payouts'"
                            :class="activeTab === 'payouts' ? 'bg-[#FFEF4D] text-[#090d16] font-bold' : 'text-zinc-400 hover:text-white bg-white/[0.04] border border-white/[0.06]'"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-mono transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <i class="fa-solid fa-building-columns text-[10px]"></i>
                            <span><span class="hidden sm:inline">04 · </span>BANK PAYOUTS</span>
                        </button>
                    </div>
                </div>

                <!-- Window Content Area -->
                <div class="p-3.5 sm:p-8">
                    
                    <!-- TAB 01: STOREFRONT INTERACTIVE CALCULATOR -->
                    <div x-show="activeTab === 'storefront'" x-cloak class="space-y-4 sm:space-y-6">
                        
                        <!-- Tour Selection Pills (Compact 3-Column on Mobile) -->
                        <div class="space-y-1.5 sm:space-y-2">
                            <label class="block text-[11px] sm:text-xs font-mono uppercase tracking-wider text-zinc-400">
                                {{ __('Select Tour Listing to Simulate:') }}
                            </label>
                            <div class="grid grid-cols-3 gap-1.5 sm:gap-3">
                                <button type="button" @click="selectedTour = 'nusa'"
                                    :class="selectedTour === 'nusa' ? 'border-[#FFEF4D] bg-[#FFEF4D]/10 text-white' : 'border-white/[0.08] bg-white/[0.02] text-zinc-400 hover:border-white/[0.2]'"
                                    class="p-2 sm:p-3.5 rounded-xl border text-left transition space-y-0.5 sm:space-y-1 cursor-pointer">
                                    <div class="text-[11px] sm:text-xs font-bold truncate">Nusa Penida</div>
                                    <div class="text-[#FFEF4D] font-mono text-[11px] sm:text-xs font-bold">Rp 650k</div>
                                    <div class="hidden sm:block text-[11px] text-zinc-500">Sanur Speedboat • 8h</div>
                                </button>
                                <button type="button" @click="selectedTour = 'komodo'"
                                    :class="selectedTour === 'komodo' ? 'border-[#FFEF4D] bg-[#FFEF4D]/10 text-white' : 'border-white/[0.08] bg-white/[0.02] text-zinc-400 hover:border-white/[0.2]'"
                                    class="p-2 sm:p-3.5 rounded-xl border text-left transition space-y-0.5 sm:space-y-1 cursor-pointer">
                                    <div class="text-[11px] sm:text-xs font-bold truncate">Komodo Cruise</div>
                                    <div class="text-[#FFEF4D] font-mono text-[11px] sm:text-xs font-bold">Rp 3.2M</div>
                                    <div class="hidden sm:block text-[11px] text-zinc-500">Labuan Bajo • Phinisi</div>
                                </button>
                                <button type="button" @click="selectedTour = 'batur'"
                                    :class="selectedTour === 'batur' ? 'border-[#FFEF4D] bg-[#FFEF4D]/10 text-white' : 'border-white/[0.08] bg-white/[0.02] text-zinc-400 hover:border-white/[0.2]'"
                                    class="p-2 sm:p-3.5 rounded-xl border text-left transition space-y-0.5 sm:space-y-1 cursor-pointer">
                                    <div class="text-[11px] sm:text-xs font-bold truncate">Batur 4WD</div>
                                    <div class="text-[#FFEF4D] font-mono text-[11px] sm:text-xs font-bold">Rp 450k</div>
                                    <div class="hidden sm:block text-[11px] text-zinc-500">Ubud Jeep • Sunrise</div>
                                </button>
                            </div>
                        </div>

                        <!-- Active Tour Details & Live Calculator -->
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start">
                            
                            <!-- Left: Tour Card Preview -->
                            <div class="lg:col-span-7 rounded-xl border border-white/[0.08] bg-[#090b10] p-3.5 sm:p-5 space-y-3 sm:space-y-4">
                                <div class="flex items-start justify-between gap-2 sm:gap-3">
                                    <div class="space-y-0.5 sm:space-y-1 min-w-0">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[9px] sm:text-[10px] font-mono font-bold bg-[#FFEF4D]/20 text-[#FFEF4D]" x-text="tours[selectedTour].tag"></span>
                                        <h3 class="text-sm sm:text-lg font-bold text-white tracking-tight" x-text="tours[selectedTour].name"></h3>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-base sm:text-lg font-black text-[#FFEF4D] font-mono" x-text="formatIdr(tours[selectedTour].price)"></div>
                                        <div class="text-[9px] sm:text-[10px] text-zinc-500">per person</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3 text-xs text-zinc-400 border-y border-white/[0.06] py-2.5 sm:py-3">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-clock text-zinc-500 text-[11px] shrink-0"></i>
                                        <span x-text="tours[selectedTour].duration"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-location-dot text-zinc-500 text-[11px] shrink-0"></i>
                                        <span class="truncate" x-text="tours[selectedTour].pickup"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-ship text-zinc-500 text-[11px] shrink-0"></i>
                                        <span class="truncate" x-text="tours[selectedTour].vessel"></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-emerald-400 font-mono">
                                        <i class="fa-solid fa-check-circle text-[11px] shrink-0"></i>
                                        <span x-text="tours[selectedTour].slotsLeft + ' live seats remaining'"></span>
                                    </div>
                                </div>

                                <!-- Guest Selector & Date -->
                                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                                    <div class="flex items-center gap-2 sm:gap-3">
                                        <span class="text-xs text-zinc-400">{{ __('Guests:') }}</span>
                                        <div class="inline-flex items-center border border-white/[0.12] rounded-lg bg-white/[0.04]">
                                            <button type="button" @click="guests = Math.max(1, guests - 1)" class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center text-zinc-300 hover:text-white hover:bg-white/[0.06] rounded-l-lg cursor-pointer font-bold text-xs">-</button>
                                            <span class="w-7 sm:w-8 text-center text-xs font-mono font-bold text-white" x-text="guests"></span>
                                            <button type="button" @click="guests = Math.min(8, guests + 1)" class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center text-zinc-300 hover:text-white hover:bg-white/[0.06] rounded-r-lg cursor-pointer font-bold text-xs">+</button>
                                        </div>
                                    </div>

                                    <div class="text-[11px] sm:text-xs text-zinc-400 font-mono">
                                        <i class="fa-regular fa-calendar mr-1 text-zinc-500"></i>
                                        <span x-text="selectedDate"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Live Financial Breakdown & Simulation -->
                            <div class="lg:col-span-5 rounded-xl border border-white/[0.08] bg-[#090b10] p-3.5 sm:p-5 space-y-3 sm:space-y-4">
                                <div class="text-xs font-mono uppercase tracking-wider text-zinc-400 border-b border-white/[0.08] pb-2">
                                    {{ __('Zero Commission Breakdown') }}
                                </div>

                                <div class="space-y-2 text-xs">
                                    <div class="flex justify-between text-zinc-400">
                                        <span x-text="'Ticket Price (' + guests + ' pax)'"></span>
                                        <span class="font-mono text-zinc-200" x-text="formatIdr(tours[selectedTour].price * guests)"></span>
                                    </div>
                                    <div class="flex justify-between text-zinc-400">
                                        <span>{{ __('Platform fee at checkout (Paid by guest)') }}</span>
                                        <span class="font-mono text-zinc-400" x-text="formatIdr(Math.min((tours[selectedTour].price * guests) * 0.05, 250000))"></span>
                                    </div>
                                    <div class="flex justify-between text-zinc-400">
                                        <span class="text-emerald-400 font-medium">{{ __('Ticket Commission Deducted') }}</span>
                                        <span class="font-mono text-emerald-400 font-bold">Rp 0 (0%)</span>
                                    </div>
                                </div>

                                <!-- Net Operator Payout Highlight Box -->
                                <div class="p-3.5 rounded-lg bg-[#FFEF4D]/10 border border-[#FFEF4D]/30 space-y-1">
                                    <div class="flex justify-between items-center text-xs font-bold text-white">
                                        <span>{{ __('Net Operator Payout:') }}</span>
                                        <span class="text-base font-black text-[#FFEF4D] font-mono" x-text="formatIdr(tours[selectedTour].price * guests)"></span>
                                    </div>
                                    <div class="text-[10px] text-zinc-400">
                                        {{ __('100% goes to your Indonesian bank account via automated bank settlement.') }}
                                    </div>
                                </div>

                                <!-- Interactive Simulation Button -->
                                <button type="button" @click="simulateBooking()"
                                    :disabled="bookingSimulated"
                                    class="w-full h-10 rounded-lg bg-[#FFEF4D] hover:bg-[#fae639] disabled:bg-emerald-500 text-[#090d16] font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                                    <template x-if="!bookingSimulated">
                                        <span class="flex items-center gap-2">
                                            <i class="fa-solid fa-bolt text-[11px]"></i>
                                            <span>{{ __('Simulate Instant Guest Booking') }}</span>
                                        </span>
                                    </template>
                                    <template x-if="bookingSimulated">
                                        <span class="flex items-center gap-2 text-white">
                                            <i class="fa-solid fa-check text-[11px]"></i>
                                            <span>{{ __('Booking Confirmed! Check & Paid') }}</span>
                                        </span>
                                    </template>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 02: WHATSAPP 1-TAP QUOTE & DIGITAL PASS -->
                    <div x-show="activeTab === 'whatsapp'" x-cloak class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                            
                            <!-- Left: Simulated WhatsApp Chat Message -->
                            <div class="md:col-span-6 rounded-xl border border-white/[0.08] bg-[#070a0e] p-4 space-y-3 font-sans">
                                <div class="flex items-center justify-between text-xs border-b border-white/[0.08] pb-2 text-zinc-400 font-mono">
                                    <div class="flex items-center gap-2 text-emerald-400">
                                        <i class="fa-brands fa-whatsapp text-sm"></i>
                                        <span class="font-bold text-white">WhatsApp Chat Engine</span>
                                    </div>
                                    <span class="text-[10px]">AUTO-DISPATCH • 09:12 AM</span>
                                </div>

                                <div class="bg-[#121c17] border border-emerald-500/20 p-3.5 rounded-lg text-xs space-y-2 text-zinc-300">
                                    <p class="text-[11px] text-zinc-300 leading-relaxed">
                                        "Hi Sarah! Here is your official booking confirmation and digital boarding pass for tomorrow's <strong class="text-white">Nusa Penida Manta Ray Expedition</strong>:"
                                    </p>
                                    <div class="p-2.5 rounded bg-black/40 border border-white/[0.08] flex items-center justify-between">
                                        <div class="space-y-0.5">
                                            <div class="font-mono text-[#FFEF4D] font-bold text-[11px]">pass.travelengine.id/v/882</div>
                                            <div class="text-[10px] text-zinc-400">2 Passengers • Sanur Pier 3</div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-mono font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">PAID VIA QRIS</span>
                                    </div>
                                </div>

                                <p class="text-[11px] text-zinc-500 font-mono">
                                    {{ __('Guest opens link on phone — no app download or sign-up needed.') }}
                                </p>
                            </div>

                            <!-- Right: High-Fidelity Mobile Boarding Pass Mockup -->
                            <div class="md:col-span-6 max-w-sm mx-auto w-full rounded-2xl border border-white/[0.12] bg-[#0c0e14] p-4 sm:p-5 space-y-4 shadow-xl">
                                <div class="flex items-center justify-between border-b border-white/[0.08] pb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-xs font-black">
                                            <i class="fa-solid fa-compass"></i>
                                        </div>
                                        <span class="font-bold text-xs text-white">Nusa Fastboat Co.</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-mono font-bold bg-[#FFEF4D] text-[#090d16]">VALID PASS</span>
                                </div>

                                <div class="space-y-3 text-xs">
                                    <div class="space-y-0.5">
                                        <div class="text-[10px] font-mono uppercase text-zinc-500">EXCURSION</div>
                                        <div class="font-bold text-white">Manta Bay Snorkeling & Speedboat</div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 font-mono text-[11px]">
                                        <div>
                                            <div class="text-[10px] text-zinc-500 uppercase">PASSENGERS</div>
                                            <div class="text-zinc-200 font-bold">2 Pax (Sarah M.)</div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] text-zinc-500 uppercase">DEPARTURE</div>
                                            <div class="text-zinc-200 font-bold">07:30 AM WITA</div>
                                        </div>
                                    </div>

                                    <div class="p-2.5 rounded-lg bg-white/[0.03] border border-white/[0.06] text-[11px] space-y-1">
                                        <div class="flex items-center gap-1.5 text-zinc-300">
                                            <i class="fa-solid fa-location-dot text-[#FFEF4D] text-[10px]"></i>
                                            <span>Sanur Harbor Gate 3 (Staff in Yellow Uniform)</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Barcode Mockup -->
                                <div class="border-t border-dashed border-white/[0.15] pt-3 text-center space-y-1">
                                    <div class="h-10 flex items-center justify-center gap-1 opacity-80">
                                        <span class="w-1 h-8 bg-white"></span>
                                        <span class="w-2 h-8 bg-white"></span>
                                        <span class="w-0.5 h-8 bg-white"></span>
                                        <span class="w-1.5 h-8 bg-white"></span>
                                        <span class="w-1.5 h-8 bg-white"></span>
                                        <span class="w-1 h-8 bg-white"></span>
                                        <span class="w-3 h-8 bg-white"></span>
                                        <span class="w-0.5 h-8 bg-white"></span>
                                        <span class="w-2 h-8 bg-white"></span>
                                        <span class="w-1.5 h-8 bg-white"></span>
                                        <span class="w-1 h-8 bg-white"></span>
                                    </div>
                                    <div class="text-[10px] font-mono text-zinc-500">ETKT-BALI-88219-NUSA</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 03: HARBOR MANIFEST & CREW DISPATCH -->
                    <div x-show="activeTab === 'manifest'" x-cloak class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.08] pb-3">
                            <div class="space-y-0.5">
                                <div class="text-xs font-mono uppercase text-[#FFEF4D] font-bold">PRINT-READY DAILY MANIFEST</div>
                                <div class="text-sm font-bold text-white">Sanur Harbor Pier 3 — Speedboat Sea Falcon III (08:00 AM Departure)</div>
                            </div>
                            
                            <button type="button" @click="copyMagicLink()"
                                class="px-3 py-1.5 rounded-lg bg-white/[0.06] hover:bg-white/[0.1] border border-white/[0.12] text-xs font-mono text-zinc-200 transition flex items-center gap-2 cursor-pointer">
                                <i class="fa-solid fa-link text-[10px] text-[#FFEF4D]"></i>
                                <span x-text="magicLinkCopied ? 'Copied Magic Link!' : 'Share Captain Dispatch Link'"></span>
                            </button>
                        </div>

                        <!-- Manifest Table with horizontal scroll wrapper for mobile -->
                        <div class="overflow-x-auto rounded-xl border border-white/[0.08] no-scrollbar">
                            <table class="w-full text-left text-xs font-sans min-w-[620px]">
                                <thead class="bg-[#080a0f] text-[10px] font-mono uppercase text-zinc-400 border-b border-white/[0.08]">
                                    <tr>
                                        <th class="py-2.5 px-3">PAX #</th>
                                        <th class="py-2.5 px-3">GUEST NAME</th>
                                        <th class="py-2.5 px-3">HOTEL / PICKUP</th>
                                        <th class="py-2.5 px-3">CONTACT</th>
                                        <th class="py-2.5 px-3">DIET / NOTES</th>
                                        <th class="py-2.5 px-3 text-right">BOARDING STATUS</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/[0.06] bg-[#0c0e14]">
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-3 px-3 font-mono text-zinc-500">01-02</td>
                                        <td class="py-3 px-3 font-bold text-white">Sarah Miller (2 Pax)</td>
                                        <td class="py-3 px-3 text-zinc-400">Hilton Bali Nusa Dua (06:30)</td>
                                        <td class="py-3 px-3 font-mono text-zinc-400">+61 412 ••• 892</td>
                                        <td class="py-3 px-3 text-zinc-400">Vegetarian (1x)</td>
                                        <td class="py-3 px-3 text-right">
                                            <button type="button" @click="togglePaxStatus('pax1')"
                                                :class="manifestStatus['pax1'] === 'Boarded' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border-amber-500/30'"
                                                class="px-2.5 py-1 rounded-md text-[10px] font-mono font-bold border transition cursor-pointer"
                                                x-text="manifestStatus['pax1']">
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-3 px-3 font-mono text-zinc-500">03-06</td>
                                        <td class="py-3 px-3 font-bold text-white">David Kowalski (4 Pax)</td>
                                        <td class="py-3 px-3 text-zinc-400">Kayon Ubud Resort (06:00)</td>
                                        <td class="py-3 px-3 font-mono text-zinc-400">+48 601 ••• 310</td>
                                        <td class="py-3 px-3 text-zinc-400">2 Snorkel Fins Size 44</td>
                                        <td class="py-3 px-3 text-right">
                                            <button type="button" @click="togglePaxStatus('pax2')"
                                                :class="manifestStatus['pax2'] === 'Boarded' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border-amber-500/30'"
                                                class="px-2.5 py-1 rounded-md text-[10px] font-mono font-bold border transition cursor-pointer"
                                                x-text="manifestStatus['pax2']">
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-3 px-3 font-mono text-zinc-500">07-08</td>
                                        <td class="py-3 px-3 font-bold text-white">Budi Santoso (2 Pax)</td>
                                        <td class="py-3 px-3 text-zinc-400">Direct Port Arrival</td>
                                        <td class="py-3 px-3 font-mono text-zinc-400">+62 812 ••• 441</td>
                                        <td class="py-3 px-3 text-zinc-400">Needs Lifejacket S</td>
                                        <td class="py-3 px-3 text-right">
                                            <button type="button" @click="togglePaxStatus('pax3')"
                                                :class="manifestStatus['pax3'] === 'Boarded' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : (manifestStatus['pax3'] === 'Checked In' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-zinc-800 text-zinc-400 border-zinc-700')"
                                                class="px-2.5 py-1 rounded-md text-[10px] font-mono font-bold border transition cursor-pointer"
                                                x-text="manifestStatus['pax3']">
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 3-Part Ground Crew Sign-off Block -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 text-[11px] font-mono text-zinc-400">
                            <div class="p-2.5 rounded-lg border border-white/[0.06] bg-white/[0.02]">
                                <span class="text-[9px] uppercase text-zinc-500 block">CREW SIGN-OFF 01</span>
                                <span class="text-white font-bold block">Lead Tour Guide: Verified</span>
                            </div>
                            <div class="p-2.5 rounded-lg border border-white/[0.06] bg-white/[0.02]">
                                <span class="text-[9px] uppercase text-zinc-500 block">CREW SIGN-OFF 02</span>
                                <span class="text-white font-bold block">Driver & Boat: Ready</span>
                            </div>
                            <div class="p-2.5 rounded-lg border border-white/[0.06] bg-white/[0.02]">
                                <span class="text-[9px] uppercase text-zinc-500 block">CREW SIGN-OFF 03</span>
                                <span class="text-white font-bold block">Dispatch Officer: Cleared</span>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 04: DIRECT BANK PAYOUTS -->
                    <div x-show="activeTab === 'payouts'" x-cloak class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="p-4 rounded-xl bg-[#090b10] border border-white/[0.08] space-y-1">
                                <span class="text-[11px] font-mono uppercase text-zinc-500 block">SETTLED IN WALLET</span>
                                <span class="text-xl sm:text-2xl font-black font-mono text-emerald-400 block">Rp 14.850.000</span>
                                <span class="text-[10px] text-zinc-400 block">Ready for 1-tap withdrawal to bank</span>
                            </div>
                            <div class="p-4 rounded-xl bg-[#090b10] border border-white/[0.08] space-y-1">
                                <span class="text-[11px] font-mono uppercase text-zinc-500 block">DESTINATION ACCOUNT</span>
                                <span class="text-base font-bold text-white block">Indonesian Bank Account</span>
                                <span class="text-[11px] font-mono text-zinc-400 block">•••• 8821 (PT Nusantara Bahari)</span>
                            </div>
                            <div class="p-4 rounded-xl bg-[#090b10] border border-white/[0.08] space-y-1">
                                <span class="text-[11px] font-mono uppercase text-zinc-500 block">TICKET COMMISSION</span>
                                <span class="text-xl sm:text-2xl font-black font-mono text-[#FFEF4D] block">0.00%</span>
                                <span class="text-[10px] text-zinc-400 block">100% of listed price is disbursed</span>
                            </div>
                        </div>

                        <!-- Recent Completed Bookings & Payouts -->
                        <div class="space-y-2">
                            <div class="text-xs font-mono uppercase tracking-wider text-zinc-400">
                                {{ __('Recent Completed Payments (Last 24 Hours):') }}
                            </div>
                            <div class="space-y-2 font-mono text-xs">
                                <div class="p-3 rounded-lg bg-white/[0.02] border border-white/[0.06] flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <i class="fa-solid fa-arrow-down text-emerald-400 text-xs"></i>
                                        <div>
                                            <span class="text-white font-bold">QRIS Instant Payment (#NUSA-882)</span>
                                            <span class="text-zinc-500 text-[11px] block">Paid via QRIS • 2 mins ago</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-emerald-400 font-bold">+Rp 1.300.000</span>
                                        <span class="text-[10px] text-zinc-500 block">Fee: Rp 0 taken</span>
                                    </div>
                                </div>
                                <div class="p-3 rounded-lg bg-white/[0.02] border border-white/[0.06] flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <i class="fa-solid fa-arrow-down text-emerald-400 text-xs"></i>
                                        <div>
                                            <span class="text-white font-bold">Virtual Account Payment (#KOMODO-301)</span>
                                            <span class="text-zinc-500 text-[11px] block">Paid via Virtual Account • 48 mins ago</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-emerald-400 font-bold">+Rp 6.400.000</span>
                                        <span class="text-[10px] text-zinc-500 block">Fee: Rp 0 taken</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- OPERATOR REGIONAL TRUST GRID -->
        <section class="py-12 border-b border-white/[0.08] bg-[#0a0c12]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="text-center space-y-1">
                    <p class="text-xs font-mono uppercase tracking-widest text-[#FFEF4D]">{{ __('TRUSTED ACROSS INDONESIA’S ARCHIPELAGO') }}</p>
                    <p class="text-sm text-zinc-400">{{ __('Powering premier excursions from Bali to Flores and Raja Ampat') }}</p>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 border border-white/[0.08] rounded-xl overflow-hidden bg-[#0c0e14]">
                    <div class="p-4 text-center space-y-1 border-r border-b lg:border-b-0 border-white/[0.08]">
                        <div class="font-bold text-white text-xs">Komodo & Flores</div>
                        <div class="text-[10px] text-zinc-500 font-mono">Phinisi Liveaboards</div>
                    </div>
                    <div class="p-4 text-center space-y-1 border-b md:border-r lg:border-b-0 border-white/[0.08]">
                        <div class="font-bold text-white text-xs">Nusa Penida</div>
                        <div class="text-[10px] text-zinc-500 font-mono">Fastboats & Snorkel</div>
                    </div>
                    <div class="p-4 text-center space-y-1 border-r border-b md:border-b-0 lg:border-r border-white/[0.08]">
                        <div class="font-bold text-white text-xs">Mount Batur</div>
                        <div class="text-[10px] text-zinc-500 font-mono">4WD Jeep Safaris</div>
                    </div>
                    <div class="p-4 text-center space-y-1 border-b md:border-b-0 md:border-r border-white/[0.08]">
                        <div class="font-bold text-white text-xs">Raja Ampat</div>
                        <div class="text-[10px] text-zinc-500 font-mono">Diving Expeditions</div>
                    </div>
                    <div class="p-4 text-center space-y-1 border-r border-white/[0.08]">
                        <div class="font-bold text-white text-xs">Gili Islands</div>
                        <div class="text-[10px] text-zinc-500 font-mono">Island Ferries</div>
                    </div>
                    <div class="p-4 text-center space-y-1">
                        <div class="font-bold text-white text-xs">Yogyakarta</div>
                        <div class="text-[10px] text-zinc-500 font-mono">Heritage Tours</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 02: WHAT'S INCLUDED BENTO GRID -->
        <section id="capabilities" class="scroll-mt-16 py-16 md:py-24 px-4 sm:px-6 lg:px-8 border-b border-white/[0.08] blueprint-grid">
            <div class="max-w-7xl mx-auto space-y-12">
                
                <div class="max-w-3xl mx-auto text-center space-y-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white/[0.04] border border-white/[0.1] text-xs font-mono text-zinc-400">
                        <span class="text-[#FFEF4D] font-bold">02</span>
                        <span>{{ __('WHAT’S INCLUDED') }}</span>
                    </div>
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                        {{ __('Everything you need to run your tour business.') }}
                    </h2>
                    <p class="text-sm sm:text-base text-zinc-400 max-w-xl mx-auto">
                        {{ __('Your tour website, booking system, and payment gateway all work seamlessly together so you can focus on showing guests an unforgettable experience.') }}
                    </p>
                </div>

                <!-- 8-Tile Architectural Bento Grid -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

                    <!-- Tile 1: 1-Click Direct Booking & Payment Links (Spans 8 cols) -->
                    <div class="md:col-span-8 rounded-2xl border border-white/[0.08] bg-[#0c0e14] p-5 sm:p-8 flex flex-col justify-between space-y-6 relative overflow-hidden group hover:border-white/[0.16] transition">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-[#FFEF4D] text-[#090d16]">
                                WHAT’S INCLUDED 01
                            </div>
                            <h3 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                                {{ __('Pay links for chat & Direct Booking Generator') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed max-w-xl">
                                {{ __('Create Booking Link in one tap for guests chatting on WhatsApp, Instagram DM, phone, or walk-ins. The engine reserves capacity with a 30-minute hold countdown while the guest completes payment.') }}
                            </p>
                        </div>

                        <!-- Mini Code / Preview Window -->
                        <div class="rounded-xl border border-white/[0.08] bg-[#08090d] p-3 sm:p-4 font-mono text-xs space-y-2 overflow-hidden">
                            <div class="flex items-center justify-between text-zinc-500 text-[10px] border-b border-white/[0.06] pb-2">
                                <span>DIRECT BOOKING LINK</span>
                                <span class="text-emerald-400">30-MIN HOLD ACTIVE</span>
                            </div>
                            <div class="text-[#FFEF4D] truncate text-[11px] sm:text-xs">
                                https://{{ $platformDomain }}/reservations/RSV-88219/pay
                            </div>
                            <div class="text-zinc-500 text-[11px]">
                                // Guests book and pay themselves via QRIS, Virtual Accounts, or Cards.
                            </div>
                        </div>
                    </div>

                    <!-- Tile 2: Real-Time Seat Availability (Spans 4 cols) -->
                    <div class="md:col-span-4 rounded-2xl border border-white/[0.08] bg-[#0c0e14] p-5 sm:p-7 flex flex-col justify-between space-y-6 hover:border-white/[0.16] transition">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-[#FFEF4D] text-[#090d16]">
                                WHAT’S INCLUDED 02
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-white tracking-tight">
                                {{ __('Real-Time Seat Availability. Zero Overbooking.') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                                {{ __('Live seat tracking that prevents double-booking. When a guest books a tour or combo package, seats update instantly across your entire schedule.') }}
                            </p>
                        </div>

                        <div class="p-3.5 rounded-xl border border-white/[0.08] bg-[#08090d] space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400 font-mono">SEAT HOLD TIMER</span>
                                <span class="font-mono text-emerald-400 font-bold">29:45</span>
                            </div>
                            <div class="w-full bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-emerald-400 h-1.5 rounded-full" style="width: 90%"></div>
                            </div>
                            <div class="text-[10px] text-zinc-500 font-mono">
                                Instant seat sync across all your tours & packages
                            </div>
                        </div>
                    </div>

                    <!-- Tile 3: Daily Guest Manifests (Spans 4 cols) -->
                    <div class="md:col-span-4 rounded-2xl border border-white/[0.08] bg-[#0c0e14] p-5 sm:p-7 flex flex-col justify-between space-y-6 hover:border-white/[0.16] transition">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-[#FFEF4D] text-[#090d16]">
                                WHAT’S INCLUDED 03
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-white tracking-tight">
                                {{ __('Daily guest lists & A4 Run-Sheets') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                                {{ __('Clean, print-ready guest lists with check-in tick boxes and sign-off spaces for your tour guides, drivers, and boat crew every morning.') }}
                            </p>
                        </div>

                        <div class="p-3 rounded-xl border border-white/[0.08] bg-[#08090d] space-y-2 text-xs">
                            <div class="flex items-center gap-2 text-white font-bold">
                                <i class="fa-solid fa-print text-[#FFEF4D]"></i>
                                <span>Print-Ready Guest List</span>
                            </div>
                            <div class="font-mono text-[10px] text-zinc-400 truncate">
                                [ ] Check · Name · Hotel · Dietary · Sign-off
                            </div>
                        </div>
                    </div>

                    <!-- Tile 4: Automated Partner & Driver Alerts (Spans 4 cols) -->
                    <div class="md:col-span-4 rounded-2xl border border-white/[0.08] bg-[#0c0e14] p-5 sm:p-7 flex flex-col justify-between space-y-6 hover:border-white/[0.16] transition">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-[#FFEF4D] text-[#090d16]">
                                WHAT’S INCLUDED 04
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-white tracking-tight">
                                {{ __('Automated Partner & Driver Alerts') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                                {{ __('Work with partner boats, drivers, or dive centers? Assign them to any trip, and they automatically get booking details as soon as a guest pays.') }}
                            </p>
                        </div>

                        <div class="p-3 rounded-xl border border-white/[0.08] bg-[#08090d] space-y-2 text-xs">
                            <div class="flex items-center gap-2 text-emerald-400 font-bold">
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                                <span>Sent Instantly on Payment</span>
                            </div>
                            <div class="font-mono text-[10px] text-zinc-400 truncate">
                                partner@divecenter.id · Booking Details Attached
                            </div>
                        </div>
                    </div>

                    <!-- Tile 5: Guest CRM & Repeat Records (Spans 4 cols) -->
                    <div class="md:col-span-4 rounded-2xl border border-white/[0.08] bg-[#0c0e14] p-5 sm:p-7 flex flex-col justify-between space-y-6 hover:border-white/[0.16] transition">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-[#FFEF4D] text-[#090d16]">
                                WHAT’S INCLUDED 05
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-white tracking-tight">
                                {{ __('Guest CRM & Repeat Customer Records') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                                {{ __('Keep guest contact info, special dietary notes, and trip history in one easy list. Send coupons and automatically ask for a review after the trip.') }}
                            </p>
                        </div>

                        <div class="p-3 rounded-xl border border-white/[0.08] bg-[#08090d] space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white">Guest Directory</span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-mono bg-[#FFEF4D]/20 text-[#FFEF4D]">RETURNING GUEST</span>
                            </div>
                            <div class="font-mono text-[10px] text-zinc-400">
                                Total Spent: Rp 12.8M · 3 Bookings
                            </div>
                        </div>
                    </div>

                    <!-- Tile 6: Native Indonesian Bank Payouts (Spans 6 cols) -->
                    <div class="md:col-span-6 rounded-2xl border border-white/[0.08] bg-[#0c0e14] p-5 sm:p-8 flex flex-col justify-between space-y-6 hover:border-white/[0.16] transition">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-[#FFEF4D] text-[#090d16]">
                                WHAT’S INCLUDED 06
                            </div>
                            <h3 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                                {{ __('Payouts, 0% cut. Direct Bank Settlements') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                                {{ __('Nothing taken from your ticket. You keep 100% of the listed price. Direct payouts to your Indonesian bank account, with a small 5% platform fee paid by the guest at checkout.') }}
                            </p>
                        </div>

                        <!-- Generic Payment Badges -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center font-mono text-xs">
                            <div class="p-2.5 rounded-lg border border-white/[0.08] bg-[#08090d] space-y-0.5">
                                <div class="font-bold text-white text-[11px]">VIRTUAL ACCOUNTS</div>
                                <div class="text-[9px] text-zinc-500">Auto-Confirmed</div>
                            </div>
                            <div class="p-2.5 rounded-lg border border-white/[0.08] bg-[#08090d] space-y-0.5">
                                <div class="font-bold text-emerald-400 text-[11px]">QRIS INSTANT</div>
                                <div class="text-[9px] text-zinc-500">All E-Wallets</div>
                            </div>
                            <div class="p-2.5 rounded-lg border border-white/[0.08] bg-[#08090d] space-y-0.5">
                                <div class="font-bold text-white text-[11px]">BANK TRANSFERS</div>
                                <div class="text-[9px] text-zinc-500">All Major Banks</div>
                            </div>
                            <div class="p-2.5 rounded-lg border border-white/[0.08] bg-[#08090d] space-y-0.5">
                                <div class="font-bold text-white text-[11px]">CARDS (VISA / MC)</div>
                                <div class="text-[9px] text-zinc-500">International</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tile 7: Your Own Website & Branding (Spans 6 cols) -->
                    <div class="md:col-span-6 rounded-2xl border border-white/[0.08] bg-[#0c0e14] p-5 sm:p-8 flex flex-col justify-between space-y-6 hover:border-white/[0.16] transition">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-[#FFEF4D] text-[#090d16]">
                                WHAT’S INCLUDED 07
                            </div>
                            <h3 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                                {{ __('Your own website address (yourbrand.com)') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                                {{ __('Connect your own domain address (e.g. yourbrand.com) with free automatic security (SSL). Showcase your brand professionally with zero third-party logos.') }}
                            </p>
                        </div>

                        <div class="p-3.5 rounded-xl border border-white/[0.08] bg-[#08090d] font-mono text-xs flex items-center justify-between">
                            <div class="flex items-center gap-2 text-white truncate">
                                <i class="fa-solid fa-lock text-emerald-400 text-xs"></i>
                                <span class="truncate">https://tours.yourbrand.com</span>
                            </div>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 shrink-0">SECURE (SSL)</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- SECTION 03: PLATFORM FEATURES -->
        <section id="specs" class="scroll-mt-16 py-16 md:py-24 px-4 sm:px-6 lg:px-8 border-b border-white/[0.08] bg-[#090b10]">
            <div class="max-w-5xl mx-auto space-y-10">
                
                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white/[0.04] border border-white/[0.1] text-xs font-mono text-zinc-400">
                        <span class="text-[#FFEF4D] font-bold">03</span>
                        <span>{{ __('PLATFORM FEATURES') }}</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        {{ __('Everything included out of the box.') }}
                    </h2>
                    <p class="text-sm text-zinc-400 max-w-lg mx-auto">
                        {{ __('No extra plugins, hidden setup fees, or technical knowledge required.') }}
                    </p>
                </div>

                <div class="border border-white/[0.08] rounded-xl overflow-hidden bg-[#0c0e14]">
                    <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-white/[0.08]">
                        
                        <!-- Left Specs Column -->
                        <div class="divide-y divide-white/[0.08] text-xs font-mono">
                            <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-4">
                                <span class="text-zinc-500 sm:text-zinc-400 text-[11px] sm:text-xs font-mono uppercase tracking-wider sm:tracking-normal">{{ __('Storefront Website') }}</span>
                                <span class="text-white font-bold text-xs">{{ __('Ready-to-use mobile storefront') }}</span>
                            </div>
                            <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-4">
                                <span class="text-zinc-500 sm:text-zinc-400 text-[11px] sm:text-xs font-mono uppercase tracking-wider sm:tracking-normal">{{ __('Payment Gateway') }}</span>
                                <span class="text-white font-bold text-xs">QRIS, Virtual Accounts & Cards</span>
                            </div>
                            <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-4">
                                <span class="text-zinc-500 sm:text-zinc-400 text-[11px] sm:text-xs font-mono uppercase tracking-wider sm:tracking-normal">{{ __('Bank Payouts') }}</span>
                                <span class="text-white font-bold text-xs">Direct to Indonesian Bank Accounts</span>
                            </div>
                            <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-4">
                                <span class="text-zinc-500 sm:text-zinc-400 text-[11px] sm:text-xs font-mono uppercase tracking-wider sm:tracking-normal">{{ __('Supported Currencies') }}</span>
                                <span class="text-white font-bold text-xs">IDR (Rupiah), USD, AUD, EUR, SGD</span>
                            </div>
                        </div>

                        <!-- Right Specs Column -->
                        <div class="divide-y divide-white/[0.08] text-xs font-mono">
                            <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-4">
                                <span class="text-zinc-500 sm:text-zinc-400 text-[11px] sm:text-xs font-mono uppercase tracking-wider sm:tracking-normal">{{ __('Seat Overbooking Protection') }}</span>
                                <span class="text-emerald-400 font-bold text-xs">Live Seat Hold (Zero Overbooking)</span>
                            </div>
                            <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-4">
                                <span class="text-zinc-500 sm:text-zinc-400 text-[11px] sm:text-xs font-mono uppercase tracking-wider sm:tracking-normal">{{ __('Operator Tools') }}</span>
                                <span class="text-white font-bold text-xs">WhatsApp tickets, guest lists, calendar</span>
                            </div>
                            <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-4">
                                <span class="text-zinc-500 sm:text-zinc-400 text-[11px] sm:text-xs font-mono uppercase tracking-wider sm:tracking-normal">{{ __('Operator Commission') }}</span>
                                <span class="text-[#FFEF4D] font-bold text-xs">0% Ticket Cut (Keep 100%)</span>
                            </div>
                            <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-4">
                                <span class="text-zinc-500 sm:text-zinc-400 text-[11px] sm:text-xs font-mono uppercase tracking-wider sm:tracking-normal">{{ __('Website Security') }}</span>
                                <span class="text-white font-bold text-xs">Free Automatic Security (SSL)</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 04: PRICING & SUBSCRIPTION TIERS -->
        <section id="pricing" class="scroll-mt-16 py-16 md:py-24 px-4 sm:px-6 lg:px-8 border-b border-white/[0.08] blueprint-grid"
            x-data="{ billing_interval: 'monthly' }">
            <div class="max-w-7xl mx-auto space-y-12">
                
                <!-- Section Header & Billing Interval Toggle -->
                <div class="max-w-3xl mx-auto text-center space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white/[0.04] border border-white/[0.1] text-xs font-mono text-zinc-400">
                        <span class="text-[#FFEF4D] font-bold">04</span>
                        <span>{{ __('TRANSPARENT PRICING') }}</span>
                    </div>
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                        {{ __('Keep 100% of your ticket price.') }}
                    </h2>
                    <p class="text-sm sm:text-base text-zinc-400 max-w-xl mx-auto">
                        {{ __('Free to start with zero risk. Upgrade when you need team accounts and custom domains.') }}
                    </p>

                    <!-- Monthly / Yearly Billing Toggle -->
                    <div class="inline-flex items-center p-1 rounded-xl bg-[#0c0e14] border border-white/[0.12] text-xs font-mono">
                        <button type="button" @click="billing_interval = 'monthly'"
                            :class="billing_interval === 'monthly' ? 'bg-[#FFEF4D] text-[#090d16] font-bold' : 'text-zinc-400 hover:text-white'"
                            class="px-4 py-2 rounded-lg transition cursor-pointer">
                            {{ __('Monthly') }}
                        </button>
                        <button type="button" @click="billing_interval = 'yearly'"
                            :class="billing_interval === 'yearly' ? 'bg-[#FFEF4D] text-[#090d16] font-bold' : 'text-zinc-400 hover:text-white'"
                            class="px-4 py-2 rounded-lg transition flex items-center gap-1.5 cursor-pointer">
                            <span>{{ __('Yearly') }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-[#FFEF4D]/20 text-[#FFEF4D]">{{ __('Save 17%') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Pricing Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
                    @foreach ($plans as $plan)
                        @php
                            $priceMonthly = (float) $plan->price_monthly;
                            $priceYearly = (float) $plan->price_yearly;
                        @endphp
                        <div class="rounded-2xl border {{ $plan->is_popular ? 'border-[#FFEF4D] bg-[#0c0e14] ring-1 ring-[#FFEF4D]/20' : 'border-white/[0.08] bg-[#090b10]' }} p-4 sm:p-8 flex flex-col justify-between space-y-4 sm:space-y-6 relative">
                            
                            @if ($plan->is_popular)
                                <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-[#FFEF4D] text-[#090d16] text-[10px] font-mono font-black uppercase tracking-wider shadow-sm">
                                    {{ __('MOST POPULAR FOR OPERATORS') }}
                                </div>
                            @endif

                            <div class="space-y-3 sm:space-y-4">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-xl font-bold text-white">{{ $plan->name }}</h3>
                                    </div>
                                    <p class="text-xs text-zinc-400 min-h-0 sm:min-h-[32px]">
                                        @if ($plan->slug === 'starter')
                                            {{ __('For freelance tour guides. All the essentials to start taking direct reservations.') }}
                                        @elseif ($plan->slug === 'growth')
                                            {{ __('For freelancers with more tools, or a small group selling together.') }}
                                        @elseif ($plan->slug === 'agency')
                                            {{ __('For small to mid travel agencies running operations on their own brand.') }}
                                        @else
                                            {{ $plan->description ?? __('All the core tools needed to run your tour bookings.') }}
                                        @endif
                                    </p>
                                </div>

                                <!-- Dynamic Price -->
                                <div class="pt-2 border-t border-white/[0.06]">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-3xl sm:text-4xl font-black text-white font-mono tracking-tight"
                                            x-text="billing_interval === 'yearly' ? '{{ $plan->isFree() ? __('Free') : 'Rp ' . number_format($priceYearly, 0, ',', '.') }}' : '{{ $plan->isFree() ? __('Free') : 'Rp ' . number_format($priceMonthly, 0, ',', '.') }}'">
                                            {{ $plan->isFree() ? __('Free') : 'Rp ' . number_format($priceMonthly, 0, ',', '.') }}
                                        </span>
                                        <span class="text-xs text-zinc-500 font-mono" x-show="'{{ !$plan->isFree() }}'" x-text="billing_interval === 'yearly' ? '/year' : '/month'"></span>
                                    </div>
                                    <p class="text-[11px] text-zinc-500 font-mono pt-1">
                                        {{ __('0% commission taken from your tickets') }}
                                    </p>
                                </div>

                                <!-- Plan Limits Feature List -->
                                <div class="space-y-2 sm:space-y-2.5 pt-3 sm:pt-4 border-t border-white/[0.06] text-xs">
                                    <div class="flex items-center gap-2 text-zinc-300">
                                        <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                                        <span><strong>{{ $plan->slug === 'starter' ? '5 trips and activities' : $plan->listingLimitLabel() }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-zinc-300">
                                        <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                                        <span><strong>{{ $plan->slug === 'starter' ? 'You and 1 helper' : $plan->teamSeatLabel() }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-zinc-300">
                                        <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                                        <span>{{ __('Automated Bank Settlements') }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-zinc-300">
                                        <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                                        <span>{{ __('1-Click WhatsApp Booking Links') }}</span>
                                    </div>

                                    @if ($plan->slug === 'growth' || $plan->slug === 'agency')
                                        <div class="flex items-center gap-2 text-zinc-300">
                                            <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                                            <span>{{ __('Google Calendar live iCal feed sync') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-zinc-300">
                                            <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                                            <span>{{ __('Guest CRM & lifetime spend analytics') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-zinc-300">
                                            <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                                            <span>{{ __('Ask for a review after the trip') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-zinc-300">
                                            <i class="fa-solid fa-check text-emerald-400 text-xs shrink-0"></i>
                                            <span>{{ __('Coupons & promotional discounts') }}</span>
                                        </div>
                                    @endif
                                    
                                    @if ($plan->hasFeature('custom_domain'))
                                        <div class="flex items-center gap-2 text-[#FFEF4D] font-bold">
                                            <i class="fa-solid fa-globe text-xs shrink-0"></i>
                                            <span>{{ __('Your own website address (yourbrand.com)') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[#FFEF4D] font-bold">
                                            <i class="fa-solid fa-sparkles text-xs shrink-0"></i>
                                            <span>{{ __('Full custom branding (removes platform branding)') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[#FFEF4D]">
                                            <i class="fa-solid fa-robot text-xs shrink-0"></i>
                                            <span>{{ __('AI Search Discovery (/llms.txt)') }}</span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2 text-zinc-400">
                                            <i class="fa-solid fa-globe text-xs shrink-0 text-zinc-600"></i>
                                            <span>yourname.travelengine.id</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- CTA Button -->
                            <div class="space-y-2">
                                <a href="{{ route('register') }}"
                                    class="w-full h-11 rounded-xl flex items-center justify-center text-xs font-bold transition shadow-sm {{ $plan->is_popular ? 'bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black' : 'bg-white/[0.06] hover:bg-white/[0.1] text-white border border-white/[0.1]' }}"
                                    wire:navigate>
                                    {{ $registrationOpen ? ($plan->isFree() ? __('Start free') : __('Choose Plan')) : __('Coming soon') }}
                                </a>
                                @if ($plan->isFree())
                                    <div class="text-[10px] text-zinc-500 font-mono text-center">
                                        {{ __('Starter plan, cancel anytime') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Structured Feature Comparison Matrix (Collapsible) -->
                <div class="max-w-5xl mx-auto pt-6" x-data="{ expanded: false }">
                    <div class="text-center">
                        <button type="button" @click="expanded = !expanded"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-white/[0.12] bg-[#0c0e14] text-xs font-mono text-zinc-300 hover:text-white transition cursor-pointer">
                            <i class="fa-solid" :class="expanded ? 'fa-chevron-up' : 'fa-list-check text-[#FFEF4D]'"></i>
                            <span x-text="expanded ? '{{ __('Hide Detailed Feature Comparison') }}' : '{{ __('Compare All Plan Features & Limits') }}'"></span>
                        </button>
                    </div>

                    <div x-show="expanded" x-collapse class="mt-6 border border-white/[0.08] rounded-xl overflow-hidden bg-[#0c0e14]" style="display: none;">
                        <div class="overflow-x-auto no-scrollbar">
                            <table class="w-full text-left text-xs font-mono min-w-[540px]">
                                <thead class="bg-[#08090d] text-[11px] text-zinc-400 border-b border-white/[0.08]">
                                    <tr>
                                        <th class="py-3 px-4">{{ __('CAPABILITY / FEATURE') }}</th>
                                        @foreach ($plans as $plan)
                                            <th class="py-3 px-3 text-center {{ $plan->is_popular ? 'text-[#FFEF4D] bg-white/[0.02]' : '' }}">
                                                {{ $plan->name }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/[0.06]">
                                    <tr>
                                        <td class="py-3 px-4 text-zinc-300 font-sans font-medium">{{ __('Listing Capacity') }}</td>
                                        @foreach ($plans as $plan)
                                            <td class="py-3 px-3 text-center text-zinc-200 {{ $plan->is_popular ? 'bg-white/[0.02]' : '' }}">
                                                {{ $plan->listingLimitLabel() }}
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td class="py-3 px-4 text-zinc-300 font-sans font-medium">{{ __('Team Seats & Dispatchers') }}</td>
                                        @foreach ($plans as $plan)
                                            <td class="py-3 px-3 text-center text-zinc-200 {{ $plan->is_popular ? 'bg-white/[0.02]' : '' }}">
                                                {{ $plan->teamSeatLabel() }}
                                            </td>
                                        @endforeach
                                    </tr>
                                    @foreach ($planFeatureRows as $feature)
                                        <tr>
                                            <td class="py-3 px-4 text-zinc-300 font-sans font-medium">{{ $feature['label'] }}</td>
                                            @foreach ($plans as $plan)
                                                <td class="py-3 px-3 text-center {{ $plan->is_popular ? 'bg-white/[0.02]' : '' }}">
                                                    @if ($plan->hasFeature($feature['key']))
                                                        <i class="fa-solid fa-check text-emerald-400"></i>
                                                    @else
                                                        <i class="fa-solid fa-minus text-zinc-600"></i>
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

                <!-- Guarantee Callout -->
                <div class="max-w-2xl mx-auto p-4 rounded-xl border border-white/[0.08] bg-[#0c0e14] text-center space-y-1">
                    <div class="inline-flex items-center gap-2 text-xs font-bold text-[#FFEF4D]">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>{{ __('The 0% Ticket Commission Guarantee') }}</span>
                    </div>
                    <p class="text-xs text-zinc-400">
                        {{ __('You keep 100% of the listed price. A 5% platform fee is added at checkout.') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- SAMPLE SHOP & DEMO OPERATOR CALLOUT -->
        <section class="py-12 px-4 sm:px-6 lg:px-8 border-b border-white/[0.08] bg-[#07090e]">
            <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-6 p-6 rounded-2xl border border-white/[0.08] bg-[#0c0e14]">
                <div class="space-y-1 text-center sm:text-left">
                    <div class="text-xs font-mono uppercase text-[#FFEF4D] font-bold">{{ __('Interactive Lookaround') }}</div>
                    <h3 class="text-lg font-bold text-white">{{ __('Want to try a sample shop?') }}</h3>
                    <p class="text-xs text-zinc-400 max-w-md">
                        {{ __('See how a live guest storefront works or explore the operator desk with pre-seeded tours and bookings.') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-3 shrink-0">
                    <a href="{{ $demoStorefrontUrl }}" target="_blank" rel="noopener nofollow"
                        class="h-10 px-4 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-white border border-white/[0.1] font-mono text-xs font-bold transition flex items-center gap-2">
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-[#FFEF4D]"></i>
                        <span>{{ __('See a sample shop') }}</span>
                    </a>
                    <a href="{{ $demoOperatorLoginUrl }}" target="_blank" rel="noopener nofollow"
                        class="h-10 px-4 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-mono text-xs font-black transition flex items-center gap-2">
                        <i class="fa-solid fa-gauge text-[10px]"></i>
                        <span>{{ __('Try the operator desk') }}</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- SECTION 05: FREQUENTLY ASKED QUESTIONS -->
        <section id="faq" class="scroll-mt-16 py-16 md:py-24 px-4 sm:px-6 lg:px-8 border-b border-white/[0.08] bg-[#090b10]"
            x-data="{ activeAccordion: null }">
            <div class="max-w-3xl mx-auto space-y-10">
                
                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white/[0.04] border border-white/[0.1] text-xs font-mono text-zinc-400">
                        <span class="text-[#FFEF4D] font-bold">05</span>
                        <span>{{ __('FREQUENTLY ASKED QUESTIONS') }}</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        {{ __('Everything operators ask.') }}
                    </h2>
                </div>

                <div class="border border-white/[0.08] rounded-2xl overflow-hidden divide-y divide-white/[0.08] bg-[#0c0e14]">
                    
                    <!-- FAQ 1 -->
                    <div class="transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 1 ? null : 1"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left cursor-pointer">
                            <span class="text-sm sm:text-base font-bold text-white">
                                {{ __('How do payouts settle into my Indonesian bank account?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-500 transition-transform duration-200"
                                :class="activeAccordion === 1 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 1" x-collapse class="px-5 pb-5 text-sm text-zinc-400 leading-relaxed pt-1" style="display: none;">
                            {{ __('When a guest completes a booking using QRIS, Virtual Accounts, bank transfers, or credit cards, the money goes straight to your operator balance. Once the payment clears, you can withdraw funds directly into your Indonesian bank account anytime — 100% of your listed ticket price.') }}
                        </div>
                    </div>

                    <!-- FAQ 2 -->
                    <div class="transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 2 ? null : 2"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left cursor-pointer">
                            <span class="text-sm sm:text-base font-bold text-white">
                                {{ __('What is the platform fee?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-500 transition-transform duration-200"
                                :class="activeAccordion === 2 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 2" x-collapse class="px-5 pb-5 text-sm text-zinc-400 leading-relaxed pt-1" style="display: none;">
                            {{ __('Guests see it on the payment screen before they confirm. You keep 100% of the listed price. A 5% platform fee is added at checkout (capped at Rp 250.000). Nothing taken from your ticket.') }}
                        </div>
                    </div>

                    <!-- FAQ 3 -->
                    <div class="transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 3 ? null : 3"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left cursor-pointer">
                            <span class="text-sm sm:text-base font-bold text-white">
                                {{ __('Can I connect my own domain name (e.g. yourbrand.com)?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-500 transition-transform duration-200"
                                :class="activeAccordion === 3 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 3" x-collapse class="px-5 pb-5 text-sm text-zinc-400 leading-relaxed pt-1" style="display: none;">
                            {{ __('Yes! On the Agency plan, you can connect your existing domain name (e.g. yourbrand.com or tours.yourbrand.com). We provide free automatic security (SSL) so guests see a safe padlock with zero platform branding.') }}
                        </div>
                    </div>

                    <!-- FAQ 4 -->
                    <div class="transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 4 ? null : 4"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left cursor-pointer">
                            <span class="text-sm sm:text-base font-bold text-white">
                                {{ __('Do my boat captains and drivers need paid accounts?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-500 transition-transform duration-200"
                                :class="activeAccordion === 4 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 4" x-collapse class="px-5 pb-5 text-sm text-zinc-400 leading-relaxed pt-1" style="display: none;">
                            {{ __('No. You can send them a secure Captain & Driver Dispatch link for each day’s departure. It opens an interactive, live passenger manifest on their mobile browser without requiring a password or app installation.') }}
                        </div>
                    </div>

                    <!-- FAQ 5 -->
                    <div class="transition-colors">
                        <button type="button" @click="activeAccordion = activeAccordion === 5 ? null : 5"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left cursor-pointer">
                            <span class="text-sm sm:text-base font-bold text-white">
                                {{ __('Can I sync my booking departures with Google Calendar or Apple Calendar?') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-zinc-500 transition-transform duration-200"
                                :class="activeAccordion === 5 ? 'rotate-180 text-[#FFEF4D]' : ''"></i>
                        </button>
                        <div x-show="activeAccordion === 5" x-collapse class="px-5 pb-5 text-sm text-zinc-400 leading-relaxed pt-1" style="display: none;">
                            {{ __('Yes. TravelEngine provides a native 2-way live iCal feed. Paste your private feed URL into Google Calendar, Apple Calendar, or Outlook to see all confirmed passenger counts, hotel pickups, and departures directly on your phone schedule.') }}
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- CLOSING CTA: All-In-One Tour Platform Banner -->
        <section data-mobile-end class="py-16 md:py-24 px-4 sm:px-6 lg:px-8 blueprint-grid glow-radial-yellow text-center relative overflow-hidden">
            <div class="max-w-4xl mx-auto space-y-6">
                
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-[#FFEF4D]/10 border border-[#FFEF4D]/30 text-xs font-mono text-[#FFEF4D]">
                    <i class="fa-solid fa-compass text-[10px]"></i>
                    <span>TRAVELENGINE // ALL-IN-1 TOUR PLATFORM</span>
                </div>

                <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight">
                    {{ __('Launch your tour website and start taking bookings today.') }}
                </h2>

                <p class="text-base sm:text-lg text-zinc-400 max-w-xl mx-auto">
                    {{ __('Your complete website, booking engine, and payment gateway ready in 5 minutes. Direct bank payouts, 0% ticket commission, and zero setup fees.') }}
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    <a href="{{ route('register') }}"
                        class="w-full sm:w-auto h-12 px-8 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-sm transition flex items-center justify-center gap-2 cursor-pointer shadow-sm"
                        wire:navigate>
                        <span>{{ $registrationOpen ? __('Start free') : __('Coming soon') }}</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                    
                    <a href="{{ $demoStorefrontUrl }}" target="_blank" rel="noopener nofollow"
                        class="w-full sm:w-auto h-12 px-6 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] text-white border border-white/[0.12] font-semibold text-sm transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-[#FFEF4D]"></i>
                        <span>{{ __('Browse Live Demo Shop') }}</span>
                    </a>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-6 pt-4 text-xs font-mono text-zinc-400">
                    <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('No credit card required') }}</span>
                    <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('Free Starter plan forever') }}</span>
                    <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400 text-xs"></i> {{ __('Direct Indonesian bank payouts') }}</span>
                </div>
            </div>
        </section>

        <!-- FOOTER: Multi-Column Architectural Footer -->
        <footer class="border-t border-white/[0.08] bg-[#07090e] pt-10 pb-24 sm:py-12 px-4 sm:px-6 lg:px-8 text-xs font-mono text-zinc-500">
            <div class="max-w-7xl mx-auto grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-8 mb-8 sm:mb-12">
                
                <!-- Col 1: Brand & Tagline (Full width on mobile, spans 2 cols on lg) -->
                <div class="col-span-2 space-y-3.5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-sm font-black shrink-0">
                            <i class="fa-solid fa-compass"></i>
                        </div>
                        <span class="font-black text-base tracking-tight text-white font-sans">
                            {{ config('app.name', 'TravelEngine') }}
                        </span>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-mono bg-white/[0.06] border border-white/[0.1] text-zinc-400">
                            Platform
                        </span>
                    </div>
                    <p class="text-zinc-400 font-sans max-w-sm text-xs leading-relaxed">
                        {{ __('Your tour website (storefront), online booking engine, and payment gateway in one simple platform. Built exclusively for Indonesian tour and excursion operators.') }}
                    </p>
                    <div class="text-[11px] text-zinc-500 font-mono">
                        {{ __('Direct payouts to Indonesian bank accounts with 0% ticket commission.') }}
                    </div>
                </div>

                <!-- Col 2: Platform Links (Side-by-side with Operators on mobile) -->
                <div class="col-span-1 space-y-3">
                    <div class="font-bold text-white uppercase text-[10px] tracking-wider">{{ __('Platform') }}</div>
                    <ul class="space-y-2.5">
                        <li><a href="#sandbox" class="hover:text-zinc-300 transition block py-0.5">{{ __('How It Works') }}</a></li>
                        <li><a href="#capabilities" class="hover:text-zinc-300 transition block py-0.5">{{ __('What’s Included') }}</a></li>
                        <li><a href="#specs" class="hover:text-zinc-300 transition block py-0.5">{{ __('Platform Features') }}</a></li>
                        <li><a href="#pricing" class="hover:text-zinc-300 transition block py-0.5">{{ __('Pricing & Plans') }}</a></li>
                        <li><a href="#faq" class="hover:text-zinc-300 transition block py-0.5">{{ __('FAQ') }}</a></li>
                    </ul>
                </div>

                <!-- Col 3: Operators & Legal Links (Side-by-side with Platform on mobile) -->
                <div class="col-span-1 space-y-3">
                    <div class="font-bold text-white uppercase text-[10px] tracking-wider">{{ __('Operators') }}</div>
                    <ul class="space-y-2.5">
                        <li><a href="{{ route('login') }}" class="hover:text-zinc-300 transition block py-0.5" wire:navigate>{{ __('Operator Log in') }}</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-zinc-300 transition block py-0.5" wire:navigate>{{ $registrationOpen ? __('Start selling') : __('Coming soon') }}</a></li>
                        <li><a href="{{ route('legal.terms') }}" class="hover:text-zinc-300 transition block py-0.5">{{ __('Terms') }}</a></li>
                        <li><a href="{{ route('legal.privacy') }}" class="hover:text-zinc-300 transition block py-0.5">{{ __('Privacy') }}</a></li>
                        <li><a href="mailto:{{ \App\Models\PlatformSetting::current()->getOperatorSupportEmail() }}" class="hover:text-zinc-300 transition block py-0.5">{{ __('Support') }}</a></li>
                    </ul>
                </div>

            </div>

            <!-- Bottom Copyright & Trust Line -->
            <div class="max-w-7xl mx-auto pt-6 sm:pt-8 border-t border-white/[0.08] flex flex-col sm:flex-row items-center sm:justify-between gap-4 text-[11px] text-center sm:text-left">
                <div class="text-zinc-500">
                    &copy; {{ date('Y') }} {{ config('app.name', 'TravelEngine') }}. {{ __('All rights reserved.') }}
                </div>
                <div class="flex flex-wrap items-center justify-center sm:justify-end gap-2 text-zinc-400">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/[0.03] border border-white/[0.06] text-zinc-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#FFEF4D]"></span>
                        <span>{{ __('0% Ticket Cut') }}</span>
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-white/[0.03] border border-white/[0.06]">
                        {{ __('QRIS & Virtual Accounts') }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-white/[0.03] border border-white/[0.06]">
                        {{ __('Bank Transfers & Cards') }}
                    </span>
                </div>
            </div>
        </footer>

    </div>

    <!-- Mobile Floating Action Pill Dock -->
    <div class="fixed inset-x-0 bottom-0 z-40 md:hidden pointer-events-none p-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
        x-data="{ show: false }"
        x-init="const hero = document.querySelector('[data-mobile-hero]');
        const end = document.querySelector('[data-mobile-end]');
        const update = () => {
            const heroBox = hero?.getBoundingClientRect();
            const endBox = end?.getBoundingClientRect();
            const heroGone = heroBox ? heroBox.bottom < 56 : true;
            const endHere = endBox ? endBox.top < window.innerHeight * 0.8 : false;
            show = heroGone && !endHere;
        };
        update();
        window.addEventListener('scroll', update, { passive: true });"
        x-show="show" x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        :aria-hidden="(!show).toString()">
        <div class="max-w-sm mx-auto rounded-2xl border border-white/[0.15] bg-[#0c0e14]/90 backdrop-blur-xl p-1.5 shadow-2xl shadow-black/80 flex items-center justify-between gap-2 pointer-events-auto">
            <div class="pl-3 pr-1 py-1">
                <div class="text-[11px] font-mono font-bold text-white flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#FFEF4D] animate-pulse"></span>
                    <span>{{ __('0% Ticket Cut') }}</span>
                </div>
                <div class="text-[9px] text-zinc-400 font-mono">
                    {{ __('Keep 100% of price') }}
                </div>
            </div>
            <a href="{{ route('register') }}"
                class="h-9 px-4 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] text-xs font-black transition flex items-center gap-1.5 shadow-sm shrink-0"
                wire:navigate>
                <span>{{ $registrationOpen ? __('Start free') : __('Coming soon') }}</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>

    @livewireScripts
</body>

</html>
