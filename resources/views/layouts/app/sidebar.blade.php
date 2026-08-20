@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body
    class="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 flex selection:bg-indigo-500 selection:text-white antialiased"
    x-data="{ sidebarOpen: false }">
    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen" x-cloak x-on:click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs lg:hidden"></div>

    @php
        /** @var \App\Models\Agent|null $currentAgent */
        $currentAgent = auth()->user()?->currentAgent();
        $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
        $storefrontUrl = $currentAgent
            ? request()->getScheme() . '://' . $currentAgent->slug . '.' . $platformDomain
            : '#';
        $packagesCount = $currentAgent ? $currentAgent->packages()->count() : 0;
        $productsCount = $currentAgent ? $currentAgent->products()->count() : 0;
        $reservationsCount = $currentAgent ? $currentAgent->reservations()->whereIn('status', [
            \App\Enums\ReservationStatus::Confirmed->value,
            \App\Enums\ReservationStatus::PendingConfirmation->value,
            \App\Enums\ReservationStatus::PaymentPending->value,
        ])->count() : 0;
    @endphp

    <!-- Sticky Desktop Sidebar / Slide-over Mobile Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-white dark:bg-zinc-900 border-r border-slate-200/80 dark:border-zinc-800 transition-transform duration-200 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 shrink-0 select-none">
        <!-- Brand Header (Fixed 64px) -->
        <div
            class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80 dark:border-zinc-800 shrink-0">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-semibold text-sm group min-w-0"
                wire:navigate>
                @if ($currentAgent?->logo_url)
                    <div class="h-9 w-9 rounded-xl overflow-hidden border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-0.5 shrink-0 shadow-2xs group-hover:scale-105 transition-transform duration-200 flex items-center justify-center">
                        <img src="{{ $currentAgent->logo_url }}" alt="{{ $currentAgent->name }}" class="w-full h-full object-contain rounded-lg" />
                    </div>
                @else
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white font-black text-sm shadow-sm group-hover:scale-105 transition-transform duration-200 shrink-0">
                        {{ strtoupper(substr($currentAgent->name ?? config('app.name', 'B'), 0, 1)) }}
                    </span>
                @endif
                <div class="flex flex-col min-w-0">
                    <span class="font-bold text-sm truncate text-slate-900 dark:text-white leading-tight">
                        {{ $currentAgent->name ?? config('app.name', 'Booking Engine') }}
                    </span>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 font-normal truncate">
                        {{ __('Agent Portal') }}
                    </span>
                </div>
            </a>
            <button x-on:click="sidebarOpen = false" type="button"
                class="lg:hidden text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">
            <!-- Section: Main -->
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-500">
                    {{ __('Main') }}
                </p>

                <a href="{{ route('dashboard') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-gauge-high w-5 text-center text-sm shrink-0 {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Dashboard') }}</span>
                </a>
            </div>

            <!-- Section: Catalog & Inventory -->
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-500">
                    {{ __('Catalog & Inventory') }}
                </p>

                <a href="{{ route('products.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('products.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-box-open w-5 text-center text-sm shrink-0 {{ request()->routeIs('products.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Activities & Inventory') }}</span>
                    </div>
                    @if ($productsCount > 0)
                        <span
                            class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 shrink-0">
                            {{ $productsCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('packages.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('packages.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-cubes w-5 text-center text-sm shrink-0 {{ request()->routeIs('packages.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Tour Packages & Combos') }}</span>
                    </div>
                    @if ($packagesCount > 0)
                        <span
                            class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 shrink-0">
                            {{ $packagesCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('calendar.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('calendar.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-calendar-days w-5 text-center text-sm shrink-0 {{ request()->routeIs('calendar.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Calendar & Availability') }}</span>
                </a>
            </div>

            <!-- Section: Bookings & Reviews -->
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-500">
                    {{ __('Bookings & Guests') }}
                </p>

                <a href="{{ route('reservations.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('reservations.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-calendar-check w-5 text-center text-sm shrink-0 {{ request()->routeIs('reservations.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Bookings & Reservations') }}</span>
                    </div>
                    @if ($reservationsCount > 0)
                        <span
                            class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 shrink-0">
                            {{ $reservationsCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('guests.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('guests.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-address-book w-5 text-center text-sm shrink-0 {{ request()->routeIs('guests.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Guest Directory') }}</span>
                </a>

                <a href="{{ route('reviews.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('reviews.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-star w-5 text-center text-sm shrink-0 {{ request()->routeIs('reviews.*') ? 'text-amber-500' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Guest Reviews') }}</span>
                </a>
            </div>

            <!-- Section: Settings / Configuration -->
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-500">
                    {{ __('Configuration') }}
                </p>

                <a href="{{ route('brand.edit') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('brand.edit') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-paintbrush w-5 text-center text-sm shrink-0 {{ request()->routeIs('brand.edit') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Brand Settings') }}</span>
                </a>

                <a href="{{ route('storefront-settings.edit') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('storefront-settings.edit') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-store w-5 text-center text-sm shrink-0 {{ request()->routeIs('storefront-settings.edit') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Storefront Settings') }}</span>
                </a>

                <a href="{{ route('payments.edit') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('payments.edit') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-credit-card w-5 text-center text-sm shrink-0 {{ request()->routeIs('payments.edit') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Payment Gateways') }}</span>
                </a>
            </div>
        </nav>

        <!-- Sidebar Footer: Storefront Action Button & User Profile -->
        <div class="p-3 border-t border-slate-200/80 dark:border-zinc-800 space-y-2 shrink-0 bg-white dark:bg-zinc-900">
            <!-- Storefront Button (Above Profile) -->
            @if ($currentAgent)
                <a href="{{ $storefrontUrl }}" target="_blank"
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/80 transition-colors border border-indigo-200/60 dark:border-indigo-800/60 shadow-xs">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="fa-solid fa-store text-indigo-600 dark:text-indigo-400 text-xs"></i>
                        <span class="truncate font-bold">{{ __('Live Storefront') }}</span>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-indigo-500 shrink-0"></i>
                </a>
            @endif

            <!-- User Profile Dropdown -->
            <x-dropdown align="top" width="full">
                <x-slot name="trigger">
                    <button
                        class="h-12 w-full flex items-center gap-2.5 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-zinc-800/80 transition text-start cursor-pointer border border-transparent hover:border-slate-200 dark:hover:border-zinc-700">
                        <div
                            class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 font-bold text-xs flex items-center justify-center shrink-0">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">
                                {{ auth()->user()->name ?? 'User' }}</p>
                            <p class="text-[11px] text-slate-500 truncate">{{ auth()->user()->email ?? '' }}</p>
                        </div>
                        <i class="fa-solid fa-chevron-up text-[10px] text-slate-400 shrink-0"></i>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-item :href="route('profile.edit')" wire:navigate>
                        <i class="fa-solid fa-user-gear mr-2 text-slate-400 text-xs"></i>
                        {{ __('Profile & Account') }}
                    </x-dropdown-item>
                    <x-dropdown-item :href="route('security.edit')" wire:navigate>
                        <i class="fa-solid fa-shield-halved mr-2 text-slate-400 text-xs"></i>
                        {{ __('Security & Passkeys') }}
                    </x-dropdown-item>
                    <x-dropdown-item :href="route('appearance.edit')" wire:navigate>
                        <i class="fa-solid fa-circle-half-stroke mr-2 text-slate-400 text-xs"></i>
                        {{ __('Appearance') }}
                    </x-dropdown-item>
                    @if (Auth::user()?->isAdmin())
                        <div class="border-t border-slate-100 dark:border-zinc-800 my-1"></div>
                        <x-dropdown-item :href="route('admin.platform.edit')" wire:navigate>
                            <i class="fa-brands fa-searchengin mr-2 text-purple-600 text-sm"></i>
                            <span class="font-bold text-purple-600 dark:text-purple-400">{{ __('Platform Admin') }}</span>
                        </x-dropdown-item>
                    @endif
                    <div class="border-t border-slate-100 dark:border-zinc-800 my-1"></div>
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <x-dropdown-item>
                            <i class="fa-solid fa-right-from-bracket mr-2 text-rose-500 text-xs"></i>
                            {{ __('Log Out') }}
                        </x-dropdown-item>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        @if (auth()->user()?->isAdmin())
            <div class="px-4 sm:px-6 py-2 bg-gradient-to-r from-purple-700 to-indigo-700 text-white text-xs font-semibold flex flex-wrap items-center justify-between gap-2 shadow-xs z-30 shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="p-1 rounded-md bg-white/20 text-white text-[10px]">
                        <i class="fa-brands fa-searchengin"></i>
                    </span>
                    <span class="truncate">
                        {{ __('Admin Session: Managing Agent') }} <strong class="text-white underline font-bold">{{ $currentAgent->name ?? 'Default Agent' }}</strong>
                    </span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.agents.index') }}" wire:navigate class="px-2.5 py-1 rounded-lg bg-white/20 hover:bg-white/30 text-white text-[11px] font-bold transition">
                        <i class="fa-solid fa-users-gear mr-1"></i>
                        {{ __('Switch Agent') }}
                    </a>
                    <a href="{{ route('admin.platform.edit') }}" wire:navigate class="px-2.5 py-1 rounded-lg bg-white text-purple-700 hover:bg-purple-50 text-[11px] font-bold transition shadow-xs">
                        <i class="fa-solid fa-arrow-left mr-1"></i>
                        {{ __('Platform Admin') }}
                    </a>
                </div>
            </div>
        @endif
        <!-- Mobile Header -->
        <header
            class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80 dark:border-zinc-800 lg:hidden bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md sticky top-0 z-30">
            <button x-on:click="sidebarOpen = true" type="button"
                class="h-10 w-10 flex items-center justify-center text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                <i class="fa-solid fa-bars text-base"></i>
            </button>
            <div class="flex items-center gap-2 min-w-0 px-2">
                @if ($currentAgent?->logo_url)
                    <img src="{{ $currentAgent->logo_url }}" alt="{{ $currentAgent->name }}" class="w-6 h-6 object-contain rounded-md shrink-0" />
                @endif
                <span class="font-bold text-sm text-slate-900 dark:text-white truncate">
                    {{ $currentAgent->name ?? config('app.name', 'Booking Engine') }}
                </span>
            </div>
            @if ($currentAgent)
                <a href="{{ $storefrontUrl }}" target="_blank"
                    class="h-9 px-3 inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 text-xs font-bold">
                    <i class="fa-solid fa-store text-xs"></i>
                    <span class="hidden sm:inline">{{ __('Storefront') }}</span>
                </a>
            @else
                <div class="w-10"></div>
            @endif
        </header>

        <main class="flex-1 p-6 lg:p-8">
            <div class="mx-auto w-full max-w-7xl">
                {{ $slot }}
            </div>
        </main>

        <!-- Agent Dashboard Sticky Footer (Platform Advertisement & System Status) -->
        <footer class="sticky bottom-0 z-20 border-t border-slate-200/80 dark:border-zinc-800 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md py-3 sm:py-3.5 px-4 sm:px-6 lg:px-8 mt-auto shadow-xs select-none">
            <div class="mx-auto w-full max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                <!-- Left: Operator Copyright & Real-time Status -->
                <div class="flex flex-wrap items-center gap-3 text-center sm:text-left">
                    <span class="font-medium">
                        &copy; {{ date('Y') }} <strong class="text-slate-800 dark:text-slate-200">{{ $currentAgent->name ?? config('app.name', 'Booking Engine') }}</strong>
                    </span>
                    <span class="hidden sm:inline text-slate-300 dark:text-zinc-700">&bull;</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ __('System Operational') }}
                    </span>
                </div>

                <!-- Right: Platform Advertisement & Branding -->
                <div class="flex items-center gap-2 text-center sm:text-right">
                    <span class="text-slate-400 dark:text-slate-500">
                        {{ __('Powered by') }}
                    </span>
                    <a href="https://{{ $platformDomain }}" target="_blank" class="inline-flex items-center gap-1.5 font-extrabold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition group" title="{{ __('Tour Operator & Direct Booking Engine Platform') }}">
                        <i class="fa-solid fa-bolt text-indigo-500 text-xs group-hover:scale-110 transition-transform"></i>
                        <span>{{ config('app.name', 'Booking Engine') }}</span>
                        <span class="hidden md:inline font-normal text-slate-400 dark:text-slate-500">&mdash; {{ __('The Direct Booking & Tour Management Engine') }}</span>
                    </a>
                </div>
            </div>
        </footer>
    </div>
    @livewireScripts
</body>

</html>
