<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased selection:bg-indigo-500 selection:text-white">
        <!-- Header -->
        <header class="border-b border-zinc-800 sticky top-0 z-50 bg-zinc-950/80 backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-zinc-950 font-black text-lg">
                        B
                    </span>
                    <span class="font-bold text-lg tracking-tight">{{ config('app.name', 'Booking Engine') }}</span>
                </div>

                <nav class="flex items-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-zinc-300 hover:text-white transition" wire:navigate>
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-zinc-300 hover:text-white transition" wire:navigate>
                            {{ __('Log In') }}
                        </a>
                        <x-button :href="route('register')" size="sm" variant="primary" wire:navigate>
                            {{ __('Get Started') }}
                        </x-button>
                    @endauth
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main>
            <section class="relative pt-24 pb-20 sm:pt-32 sm:pb-28 overflow-hidden">
                <div class="absolute inset-0 -z-10 flex items-center justify-center">
                    <div class="w-[600px] h-[600px] rounded-full bg-gradient-to-tr from-indigo-600/20 via-sky-500/10 to-transparent blur-3xl"></div>
                </div>

                <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center space-y-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-zinc-800 bg-zinc-900/60 text-xs font-medium text-zinc-300 backdrop-blur-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ __('Direct Branded Storefronts for Tour Operators & Guides') }}
                    </div>

                    <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-white leading-tight">
                        Powering direct tour bookings with <span class="bg-gradient-to-r from-indigo-400 via-sky-300 to-teal-300 bg-clip-text text-transparent">zero friction</span>.
                    </h1>

                    <p class="text-lg sm:text-xl text-zinc-400 max-w-2xl mx-auto font-normal leading-relaxed">
                        Each agent gets their own branded booking storefront, realtime daily inventory tracking, instant DOKU split settlement, and automated calendar holds.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                        <x-button :href="route('register')" size="lg" variant="primary" class="w-full sm:w-auto" wire:navigate>
                            {{ __('Create Your Tour Storefront') }}
                            <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </x-button>
                        <x-button :href="route('login')" size="lg" variant="secondary" class="w-full sm:w-auto" wire:navigate>
                            {{ __('Agent Login') }}
                        </x-button>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
