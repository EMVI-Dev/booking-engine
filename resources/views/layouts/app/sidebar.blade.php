@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body
    class="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 flex selection:bg-indigo-500 selection:text-white antialiased"
    x-data="{ mobileMenuOpen: false, commandPaletteOpen: false }" @keydown.window.cmd.k.prevent="commandPaletteOpen = true"
    @keydown.window.ctrl.k.prevent="commandPaletteOpen = true">

    @php
        /** @var \App\Models\Operator|null $currentOperator */
        $currentOperator = auth()->user()?->currentOperator();
        $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
        $storefrontUrl = $currentOperator
            ? request()->getScheme() . '://' . $currentOperator->slug . '.' . $platformDomain
            : '#';
        $packagesCount = $currentOperator ? $currentOperator->packages()->count() : 0;
        $productsCount = $currentOperator ? $currentOperator->products()->count() : 0;
        $couponsCount = $currentOperator
            ? \App\Models\PlatformCoupon::where('operator_id', $currentOperator->id)->active()->count()
            : 0;
        $reservationsCount = $currentOperator
            ? $currentOperator
                ->reservations()
                ->whereIn('status', [
                    \App\Enums\ReservationStatus::Confirmed->value,
                    \App\Enums\ReservationStatus::PendingConfirmation->value,
                    \App\Enums\ReservationStatus::PaymentPending->value,
                ])
                ->count()
            : 0;
        $availableBalance = $currentOperator ? $currentOperator->getAvailableBalance() : 0;
        $operatorPlan = $currentOperator?->getPlan();
    @endphp

    <!-- Sticky Desktop Sidebar (Purely Desktop) -->
    <aside
        class="hidden lg:flex flex-col w-64 bg-white dark:bg-zinc-900 border-r border-slate-200/80 dark:border-zinc-800 lg:sticky lg:top-0 lg:h-screen shrink-0 select-none print:hidden">
        <!-- Brand Header (Fixed 64px) -->
        <div
            class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80 dark:border-zinc-800 shrink-0">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-semibold text-sm group min-w-0"
                wire:navigate>
                @if ($currentOperator?->logo_url)
                    <div
                        class="h-9 w-9 rounded-xl overflow-hidden border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-0.5 shrink-0 shadow-2xs group-hover:scale-105 transition-transform duration-200 flex items-center justify-center">
                        <img src="{{ $currentOperator->logo_url }}" alt="{{ $currentOperator->name }}"
                            class="w-full h-full object-contain rounded-lg" />
                    </div>
                @else
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white font-black text-sm shadow-sm group-hover:scale-105 transition-transform duration-200 shrink-0">
                        {{ strtoupper(substr($currentOperator->name ?? config('app.name', 'T'), 0, 1)) }}
                    </span>
                @endif
                <div class="flex flex-col min-w-0">
                    <span class="font-bold text-sm truncate text-slate-900 dark:text-white leading-tight">
                        {{ $currentOperator->name ?? config('app.name', 'TravelEngine') }}
                    </span>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 font-normal truncate">
                        {{ __('Operator Portal') }}
                    </span>
                </div>
            </a>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-4 py-4 space-y-6 overflow-y-auto select-none">
            <!-- Primary Quick Action: Create Booking & Payment Link -->
            <div class="mb-2">
                @if (request()->routeIs('reservations.*'))
                    <button type="button" @click="$dispatch('open-create-booking-link')"
                        class="w-full h-10 px-3.5 flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-98 text-white text-xs font-black shadow-sm shadow-indigo-500/25 hover:shadow-indigo-500/35 transition-all cursor-pointer">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>{{ __('Create Booking Link') }}</span>
                    </button>
                @else
                    <a href="{{ route('reservations.index', ['create' => 1]) }}" wire:navigate
                        class="w-full h-10 px-3.5 flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-98 text-white text-xs font-black shadow-sm shadow-indigo-500/25 hover:shadow-indigo-500/35 transition-all cursor-pointer">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>{{ __('Create Booking Link') }}</span>
                    </a>
                @endif
            </div>

            <!-- Section 1: Operations -->
            <div class="space-y-1">
                <p class="px-3 text-[10px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-500">
                    {{ __('Operations') }}
                </p>

                <a href="{{ route('dashboard') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-gauge-high w-5 text-center text-sm shrink-0 {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Dashboard') }}</span>
                </a>

                <a href="{{ route('reservations.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('reservations.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-calendar-check w-5 text-center text-sm shrink-0 {{ request()->routeIs('reservations.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Bookings') }}</span>
                    </div>
                    @if ($reservationsCount > 0)
                        <span
                            class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 shrink-0">
                            {{ $reservationsCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('calendar.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('calendar.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-calendar-days w-5 text-center text-sm shrink-0 {{ request()->routeIs('calendar.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Calendar & Schedule') }}</span>
                </a>

                <a href="{{ route('wallet.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('wallet.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-wallet w-5 text-center text-sm shrink-0 {{ request()->routeIs('wallet.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Wallet & Payouts') }}</span>
                    </div>
                    @if ($availableBalance > 0)
                        <span
                            class="h-5 px-2 text-[10px] font-extrabold flex items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300 shrink-0">
                            Rp {{ number_format($availableBalance / 1000, 0) }}k
                        </span>
                    @endif
                </a>
            </div>

            <!-- Section 2: Catalog & Marketing -->
            <div class="space-y-1">
                <p class="px-3 text-[10px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-500">
                    {{ __('Catalog & Marketing') }}
                </p>

                <a href="{{ route('packages.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('packages.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-cubes w-5 text-center text-sm shrink-0 {{ request()->routeIs('packages.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Tour Packages') }}</span>
                    </div>
                    @if ($packagesCount > 0)
                        <span
                            class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 shrink-0">
                            {{ $packagesCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('products.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('products.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-compass w-5 text-center text-sm shrink-0 {{ request()->routeIs('products.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Single Activities') }}</span>
                    </div>
                    @if ($productsCount > 0)
                        <span
                            class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 shrink-0">
                            {{ $productsCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('coupons.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('coupons.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-ticket w-5 text-center text-sm shrink-0 {{ request()->routeIs('coupons.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Coupons & Discounts') }}</span>
                    </div>
                    @if ($couponsCount > 0)
                        <span
                            class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 shrink-0">
                            {{ $couponsCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('reviews.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('reviews.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i
                        class="fa-solid fa-star w-5 text-center text-sm shrink-0 {{ request()->routeIs('reviews.*') ? 'text-amber-500' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Guest Reviews') }}</span>
                </a>
            </div>

            <!-- Section 3: Business & Settings -->
            <div class="space-y-1">
                <p class="px-3 text-[10px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-500">
                    {{ __('Business & Settings') }}
                </p>

                <a href="{{ route('guests.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('guests.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-address-book w-5 text-center text-sm shrink-0 {{ request()->routeIs('guests.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Guest CRM') }}</span>
                    </div>
                    @if ($currentOperator && !$currentOperator->hasFeature('guest_crm'))
                        <span
                            title="{{ __('Upgrade to Pro Operator to unlock Guest CRM & lifetime spend analytics') }}"
                            class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 flex items-center gap-1">
                            <i class="fa-solid fa-lock text-[8px] text-indigo-500"></i>
                            <span>{{ __('Pro') }}</span>
                        </span>
                    @endif
                </a>

                <a href="{{ route('brand.edit') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('brand.edit', 'storefront-settings.edit', 'payments.edit') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-sliders w-5 text-center text-sm shrink-0 {{ request()->routeIs('brand.edit', 'storefront-settings.edit', 'payments.edit') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Storefront Settings') }}</span>
                    </div>
                </a>

                <a href="{{ route('settings.plan') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('settings.plan', 'settings.plan.checkout', 'settings.billing') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i
                            class="fa-solid fa-crown w-5 text-center text-sm shrink-0 {{ request()->routeIs('settings.plan', 'settings.plan.checkout', 'settings.billing') ? 'text-amber-500' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Subscription & Billing') }}</span>
                    </div>
                </a>
            </div>
        </nav>

        <!-- Sidebar Footer: User Profile -->
        <div class="p-3 border-t border-slate-200/80 dark:border-zinc-800 shrink-0 bg-white dark:bg-zinc-900">

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
                            <span
                                class="font-bold text-purple-600 dark:text-purple-400">{{ __('Platform Admin') }}</span>
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
            <div
                class="px-4 sm:px-6 py-2 bg-gradient-to-r from-purple-700 to-indigo-700 text-white text-xs font-semibold flex flex-wrap items-center justify-between gap-2 shadow-xs z-30 shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="p-1 rounded-md bg-white/20 text-white text-[10px]">
                        <i class="fa-solid fa-compass"></i>
                    </span>
                    <span class="truncate">
                        {{ __('Admin Session: Managing Operator') }} <strong
                            class="text-white underline font-bold">{{ $currentOperator->name ?? 'Default Operator' }}</strong>
                    </span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.operators.index') }}" wire:navigate
                        class="px-2.5 py-1 rounded-lg bg-white/20 hover:bg-white/30 text-white text-[11px] font-bold transition">
                        <i class="fa-solid fa-users-gear mr-1"></i>
                        {{ __('Switch Operator') }}
                    </a>
                    <a href="{{ route('admin.platform.edit') }}" wire:navigate
                        class="px-2.5 py-1 rounded-lg bg-white text-purple-700 hover:bg-purple-50 text-[11px] font-bold transition shadow-xs">
                        <i class="fa-solid fa-arrow-left mr-1"></i>
                        {{ __('Platform Admin') }}
                    </a>
                </div>
            </div>
        @endif

        <!-- Mobile Top Header -->
        <header
            class="h-14 px-4 sm:px-6 flex items-center justify-between border-b border-slate-200/80 dark:border-zinc-800 lg:hidden bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md sticky top-0 z-30 select-none print:hidden">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0" wire:navigate>
                @if ($currentOperator?->logo_url)
                    <img src="{{ $currentOperator->logo_url }}" alt="{{ $currentOperator->name }}"
                        class="h-7 w-7 rounded-lg object-contain border border-slate-200 dark:border-zinc-700 p-0.5 shrink-0 bg-white" />
                @else
                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-600 text-white font-black text-xs shrink-0">
                        {{ strtoupper(substr($currentOperator->name ?? 'T', 0, 1)) }}
                    </span>
                @endif
                <span class="font-bold text-xs truncate text-slate-900 dark:text-white max-w-[180px] xs:max-w-[240px]">
                    {{ $currentOperator->name ?? config('app.name', 'TravelEngine') }}
                </span>
            </a>

            <div class="flex items-center gap-2 shrink-0">
                @if (request()->routeIs('reservations.*'))
                    <button type="button" @click="$dispatch('open-create-booking-link')"
                        class="h-8 px-2.5 inline-flex items-center gap-1 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs cursor-pointer"
                        title="{{ __('Create Booking & Payment Link') }}">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>{{ __('Link') }}</span>
                    </button>
                @else
                    <a href="{{ route('reservations.index', ['create' => 1]) }}" wire:navigate
                        class="h-8 px-2.5 inline-flex items-center gap-1 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs cursor-pointer"
                        title="{{ __('Create Booking & Payment Link') }}">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>{{ __('Link') }}</span>
                    </a>
                @endif

                @if ($currentOperator)
                    <a href="{{ $storefrontUrl }}" target="_blank" rel="noopener"
                        class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60 text-xs font-bold shadow-2xs">
                        <i class="fa-solid fa-store text-xs"></i>
                        <span>{{ __('Live') }}</span>
                    </a>
                @endif
            </div>
        </header>

        <!-- Desktop Top Header Bar -->
        <header
            class="hidden lg:flex h-16 items-center justify-between px-6 lg:px-8 border-b border-slate-200/80 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md sticky top-0 z-30 select-none print:hidden">
            <!-- Left: Storefront URL with 1-Click Launch & Copy -->
            <div class="flex items-center gap-3 min-w-0" x-data="{ copied: false }">
                <div
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800/80 border border-slate-200/80 dark:border-zinc-700/60 text-xs font-medium text-slate-700 dark:text-slate-300 shadow-2xs">
                    <i class="fa-solid fa-globe text-[11px] text-indigo-500"></i>
                    <a href="{{ $storefrontUrl }}" target="_blank" rel="noopener"
                        class="truncate max-w-xs sm:max-w-md hover:text-indigo-600 dark:hover:text-indigo-400 transition font-mono"
                        title="{{ __('Open live storefront in new tab') }}">
                        {{ $storefrontUrl }}
                    </a>
                    <a href="{{ $storefrontUrl }}" target="_blank" rel="noopener"
                        class="p-0.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                        title="{{ __('Open in new tab') }}">
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    </a>
                    <span class="text-slate-300 dark:text-zinc-700">|</span>
                    <button type="button"
                        @click="navigator.clipboard.writeText('{{ $storefrontUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="p-0.5 hover:text-indigo-600 dark:hover:text-indigo-400 text-slate-400 transition cursor-pointer"
                        title="{{ __('Copy link') }}">
                        <i class="fa-solid" :class="copied ? 'fa-check text-emerald-500' : 'fa-copy text-[11px]'"></i>
                    </button>
                </div>
                <span x-show="copied" x-cloak
                    class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 animate-fade-in">
                    {{ __('Copied!') }}
                </span>

                <!-- Quick Command Search Trigger (⌘K) -->
                <button type="button" @click="commandPaletteOpen = true"
                    class="h-9 px-3.5 inline-flex items-center gap-2 rounded-xl bg-slate-100 dark:bg-zinc-800/80 border border-slate-200/80 dark:border-zinc-700/60 text-slate-500 hover:text-slate-900 dark:hover:text-white text-xs font-medium transition-all cursor-pointer shadow-2xs group"
                    title="{{ __('Search pages or actions (⌘K)') }}">
                    <i
                        class="fa-solid fa-magnifying-glass text-[11px] text-slate-400 group-hover:text-indigo-500 transition-colors"></i>
                    <span class="hidden xl:inline">{{ __('Search or jump to...') }}</span>
                    <kbd
                        class="px-1.5 py-0.5 text-[10px] font-mono font-extrabold text-slate-400 dark:text-slate-500 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-md shadow-2xs">⌘K</kbd>
                </button>
            </div>

            <!-- Right: Active Plan Badge & Quick Action -->
            <div class="flex items-center gap-3 shrink-0">
                @if ($currentOperator && $operatorPlan)
                    <a href="{{ route('settings.plan') }}" wire:navigate
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/70 dark:border-indigo-800/60 hover:bg-indigo-100 transition shadow-2xs"
                        title="{{ __('Manage Subscription Tier') }}">
                        <i class="fa-solid fa-crown text-[10px] text-amber-500"></i>
                        <span>{{ $operatorPlan->name }}</span>
                    </a>
                @endif
            </div>
        </header>

        <!-- Main Workspace -->
        <main
            class="flex-1 px-4 py-4 sm:px-6 sm:py-6 lg:p-8 pb-24 lg:pb-8 w-full print:p-0 print:m-0 print:w-full print:max-w-none">
            <div class="w-full max-w-7xl mx-auto space-y-6 print:max-w-none print:space-y-0">
                @php
                    $platformAnnouncements = \App\Models\PlatformAnnouncement::forOperator($currentOperator)->get();
                @endphp

                @if ($platformAnnouncements->isNotEmpty())
                    <div class="space-y-3 print:hidden">
                        @foreach ($platformAnnouncements as $announcement)
                            @php
                                $bannerClasses = match ($announcement->type) {
                                    'critical'
                                        => 'bg-rose-50 dark:bg-rose-950/60 border-rose-200 dark:border-rose-900 text-rose-900 dark:text-rose-200',
                                    'warning'
                                        => 'bg-amber-50 dark:bg-amber-950/60 border-amber-200 dark:border-amber-900 text-amber-900 dark:text-amber-200',
                                    'success'
                                        => 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-200 dark:border-emerald-900 text-emerald-900 dark:text-emerald-200',
                                    default
                                        => 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-200 dark:border-indigo-900 text-indigo-900 dark:text-indigo-200',
                                };
                                $iconClasses = match ($announcement->type) {
                                    'critical' => 'fa-solid fa-triangle-exclamation text-rose-600 dark:text-rose-400',
                                    'warning' => 'fa-solid fa-circle-exclamation text-amber-600 dark:text-amber-400',
                                    'success' => 'fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400',
                                    default => 'fa-solid fa-bullhorn text-indigo-600 dark:text-indigo-400',
                                };
                            @endphp
                            <div x-data="{ dismissed: false }" x-show="!dismissed"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                class="p-4 rounded-2xl border {{ $bannerClasses }} shadow-xs flex items-start justify-between gap-3 text-xs">
                                <div class="flex items-start gap-3 min-w-0">
                                    <i class="{{ $iconClasses }} text-base mt-0.5 shrink-0"></i>
                                    <div class="space-y-0.5 min-w-0">
                                        <h4 class="font-bold text-xs uppercase tracking-wide">
                                            {{ $announcement->title }}
                                        </h4>
                                        <p class="leading-relaxed opacity-90">
                                            {{ $announcement->message }}
                                        </p>
                                    </div>
                                </div>

                                @if ($announcement->is_dismissible)
                                    <button type="button" @click="dismissed = true"
                                        class="p-1 rounded-lg hover:bg-black/10 dark:hover:bg-white/10 transition cursor-pointer shrink-0 opacity-70 hover:opacity-100"
                                        title="{{ __('Dismiss') }}">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>

        <!-- Mobile Menu Modal Drawer -->
        <div x-show="mobileMenuOpen" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
            <!-- Dim Backdrop -->
            <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" x-on:click="mobileMenuOpen = false"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

            <!-- Bottom Sheet Content -->
            <div class="fixed inset-x-0 bottom-0 z-50 p-3 sm:p-4 max-h-[85vh] overflow-y-auto">
                <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-250 transform"
                    x-transition:enter-start="translate-y-full opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-200 transform"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="translate-y-full opacity-0" x-on:click.away="mobileMenuOpen = false"
                    class="w-full max-w-lg mx-auto rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl p-5 space-y-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                        <div class="flex items-center gap-3 min-w-0">
                            @if ($currentOperator?->logo_url)
                                <div
                                    class="h-10 w-10 rounded-xl overflow-hidden border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-0.5 shrink-0 flex items-center justify-center">
                                    <img src="{{ $currentOperator->logo_url }}" alt="{{ $currentOperator->name }}"
                                        class="w-full h-full object-contain rounded-lg" />
                                </div>
                            @else
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-black text-sm shrink-0">
                                    {{ strtoupper(substr($currentOperator->name ?? 'T', 0, 1)) }}
                                </span>
                            @endif
                            <div class="min-w-0">
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white truncate">
                                    {{ $currentOperator->name ?? config('app.name', 'TravelEngine') }}
                                </h3>
                                <p class="text-xs text-slate-500 truncate">
                                    {{ auth()->user()->email ?? '' }}
                                </p>
                            </div>
                        </div>

                        <button type="button" x-on:click="mobileMenuOpen = false"
                            class="h-8 w-8 rounded-full bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition cursor-pointer">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Live Storefront Quick Link -->
                    @if ($currentOperator)
                        <a href="{{ $storefrontUrl }}" target="_blank" rel="noopener"
                            class="h-10 px-3.5 flex items-center justify-between rounded-2xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60 text-xs font-bold shadow-2xs">
                            <div class="flex items-center gap-2.5">
                                <i class="fa-solid fa-store text-indigo-600 dark:text-indigo-400"></i>
                                <span>{{ __('Open Live Storefront') }}</span>
                            </div>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[11px]"></i>
                        </a>
                    @endif

                    <!-- Secondary Navigation Grid (2x2) -->
                    <div class="grid grid-cols-2 gap-2.5 pt-1">
                        <!-- Tour Packages -->
                        <a href="{{ route('packages.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 hover:border-indigo-200 dark:hover:border-indigo-900 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20 transition space-y-1 block">
                            <div class="flex items-center justify-between">
                                <span
                                    class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 text-xs">
                                    <i class="fa-solid fa-cubes"></i>
                                </span>
                                @if ($packagesCount > 0)
                                    <span class="text-[10px] font-bold text-slate-500">
                                        {{ $packagesCount }}
                                    </span>
                                @endif
                            </div>
                            <span
                                class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Tour Packages') }}</span>
                            <span class="text-[10px] text-slate-400 block">{{ __('Curated packages') }}</span>
                        </a>

                        <!-- Single Activities -->
                        <a href="{{ route('products.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 hover:border-indigo-200 dark:hover:border-indigo-900 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20 transition space-y-1 block">
                            <div class="flex items-center justify-between">
                                <span
                                    class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 text-xs">
                                    <i class="fa-solid fa-compass"></i>
                                </span>
                                @if ($productsCount > 0)
                                    <span class="text-[10px] font-bold text-slate-500">
                                        {{ $productsCount }}
                                    </span>
                                @endif
                            </div>
                            <span
                                class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Single Activities') }}</span>
                            <span class="text-[10px] text-slate-400 block">{{ __('Daily sessions & items') }}</span>
                        </a>

                        <!-- Wallet & Payouts -->
                        <a href="{{ route('wallet.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 hover:border-indigo-200 dark:hover:border-indigo-900 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20 transition space-y-1 block">
                            <div class="flex items-center justify-between">
                                <span
                                    class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 text-xs">
                                    <i class="fa-solid fa-wallet"></i>
                                </span>
                                @if ($availableBalance > 0)
                                    <span class="text-[10px] font-extrabold text-emerald-600 dark:text-emerald-400">
                                        Rp {{ number_format($availableBalance / 1000, 0) }}k
                                    </span>
                                @endif
                            </div>
                            <span
                                class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Wallet & Payouts') }}</span>
                            <span class="text-[10px] text-slate-400 block">{{ __('Balance & settlements') }}</span>
                        </a>

                        <!-- Guest Reviews -->
                        <a href="{{ route('reviews.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 hover:border-indigo-200 dark:hover:border-indigo-900 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20 transition space-y-1 block">
                            <span
                                class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/80 text-amber-500 text-xs inline-block">
                                <i class="fa-solid fa-star"></i>
                            </span>
                            <span
                                class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Guest Reviews') }}</span>
                            <span class="text-[10px] text-slate-400 block">{{ __('Ratings & feedback') }}</span>
                        </a>
                    </div>

                    <!-- Guest CRM Directory -->
                    <a href="{{ route('guests.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                        class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-slate-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 text-xs">
                                <i class="fa-solid fa-address-book"></i>
                            </span>
                            <div>
                                <span
                                    class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Guest CRM Directory') }}</span>
                                <span
                                    class="text-[10px] text-slate-400 block">{{ __('Customer profiles & lifetime spend') }}</span>
                            </div>
                        </div>
                        @if ($currentOperator && !$currentOperator->hasFeature('guest_crm'))
                            <span
                                title="{{ __('Upgrade to Pro Operator to unlock Guest CRM & lifetime spend analytics') }}"
                                class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 flex items-center gap-1">
                                <i class="fa-solid fa-lock text-[8px] text-indigo-500"></i>
                                <span>{{ __('Pro') }}</span>
                            </span>
                        @else
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        @endif
                    </a>

                    <!-- Coupons & Discounts Link -->
                    <a href="{{ route('coupons.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                        class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-slate-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 text-xs">
                                <i class="fa-solid fa-ticket"></i>
                            </span>
                            <div>
                                <span
                                    class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Coupons & Discounts') }}</span>
                                <span
                                    class="text-[10px] text-slate-400 block">{{ __('Promo codes & seasonal discounts') }}</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                    </a>

                    <!-- Storefront Settings Link -->
                    <a href="{{ route('brand.edit') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                        class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-slate-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                                <i class="fa-solid fa-sliders"></i>
                            </span>
                            <div>
                                <span
                                    class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Storefront Settings') }}</span>
                                <span
                                    class="text-[10px] text-slate-400 block">{{ __('Branding, policies, payment keys') }}</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                    </a>

                    <!-- Subscription & Billing Link -->
                    <a href="{{ route('settings.plan') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                        class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-slate-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 text-xs">
                                <i class="fa-solid fa-crown text-amber-500"></i>
                            </span>
                            <div>
                                <span
                                    class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Subscription & Billing') }}</span>
                                <span
                                    class="text-[10px] text-slate-400 block">{{ __('Plan tier, features & invoices') }}</span>
                            </div>
                        </div>
                        @if ($operatorPlan)
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 shrink-0">
                                {{ $operatorPlan->name }}
                            </span>
                        @else
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        @endif
                    </a>

                    <!-- User Account & Logout Footer -->
                    <div
                        class="pt-2 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-3">
                        <a href="{{ route('profile.edit') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="h-9 px-3 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-xs font-semibold text-slate-700 dark:text-slate-300 inline-flex items-center gap-2 transition">
                            <i class="fa-solid fa-user-gear text-xs text-slate-400"></i>
                            <span>{{ __('Account Profile') }}</span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="h-9 px-3 rounded-xl bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 dark:hover:bg-rose-900/60 text-xs font-bold text-rose-600 dark:text-rose-400 inline-flex items-center gap-1.5 transition cursor-pointer">
                                <i class="fa-solid fa-right-from-bracket text-xs"></i>
                                <span>{{ __('Log Out') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operator Dashboard Sticky Footer -->
        <footer
            class="sticky bottom-0 z-30 mb-16 lg:mb-0 border-t border-slate-200/80 dark:border-zinc-800 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md py-3 sm:py-3.5 px-4 sm:px-6 lg:px-8 mt-auto shadow-md select-none print:hidden">
            <div
                class="mx-auto w-full max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                <!-- Left: Operator Copyright & Real-time Status -->
                <div class="flex flex-wrap items-center gap-3 text-center sm:text-left">
                    <span class="font-medium">
                        &copy; {{ date('Y') }} <strong
                            class="text-slate-800 dark:text-slate-200">{{ $currentOperator->name ?? config('app.name', 'TravelEngine') }}</strong>
                    </span>
                    <span class="hidden sm:inline text-slate-300 dark:text-zinc-700">&bull;</span>
                    <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ __('System Operational') }}
                    </span>
                </div>

                <!-- Right: Platform Advertisement & Branding -->
                <div class="flex items-center gap-2 text-center sm:text-right">
                    <span class="text-slate-400 dark:text-slate-500">
                        {{ __('Powered by') }}
                    </span>
                    <a href="https://{{ $platformDomain }}" target="_blank"
                        class="inline-flex items-center gap-1.5 font-extrabold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition group"
                        title="{{ __('Tour Operator & Direct Booking Engine Platform') }}">
                        <i
                            class="fa-solid fa-compass text-indigo-500 text-xs group-hover:scale-110 transition-transform"></i>
                        <span>{{ config('app.name', 'TravelEngine') }}</span>
                        <span class="hidden md:inline font-normal text-slate-400 dark:text-slate-500">&mdash;
                            {{ __('The Direct Booking & Tour Management Engine') }}</span>
                    </a>
                </div>
            </div>
        </footer>
    </div>

    <!-- Mobile Sticky Bottom Navigation Bar (lg:hidden) -->
    <nav
        class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-t border-slate-200/80 dark:border-zinc-800 h-16 flex items-center justify-around px-2 select-none shadow-lg print:hidden">
        <!-- Dashboard -->
        <a href="{{ route('dashboard') }}" wire:navigate
            class="flex flex-col items-center justify-center gap-1 w-14 py-1 rounded-xl text-center transition-all {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
            <i class="fa-solid fa-gauge-high text-base"></i>
            <span class="text-[10px] tracking-tight">{{ __('Dashboard') }}</span>
        </a>

        <!-- Bookings -->
        <a href="{{ route('reservations.index') }}" wire:navigate
            class="flex flex-col items-center justify-center gap-1 w-14 py-1 rounded-xl text-center transition-all relative {{ request()->routeIs('reservations.*') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
            <div class="relative">
                <i class="fa-solid fa-calendar-check text-base"></i>
                @if ($reservationsCount > 0)
                    <span
                        class="absolute -top-1 -right-2 h-3.5 min-w-[14px] px-1 text-[9px] font-black flex items-center justify-center rounded-full bg-indigo-600 text-white">
                        {{ $reservationsCount }}
                    </span>
                @endif
            </div>
            <span class="text-[10px] tracking-tight">{{ __('Bookings') }}</span>
        </a>

        <!-- Center Raised Action: Create Booking Link -->
        <div class="relative flex flex-col items-center">
            @if (request()->routeIs('reservations.*'))
                <button type="button" @click="$dispatch('open-create-booking-link')"
                    class="w-12 h-12 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:scale-90 text-white shadow-lg shadow-indigo-600/30 flex items-center justify-center -mt-5 transition-all cursor-pointer border-2 border-white dark:border-zinc-900"
                    title="{{ __('Create Booking Link') }}">
                    <i class="fa-solid fa-plus text-base"></i>
                </button>
            @else
                <a href="{{ route('reservations.index', ['create' => 1]) }}" wire:navigate
                    class="w-12 h-12 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:scale-90 text-white shadow-lg shadow-indigo-600/30 flex items-center justify-center -mt-5 transition-all cursor-pointer border-2 border-white dark:border-zinc-900"
                    title="{{ __('Create Booking Link') }}">
                    <i class="fa-solid fa-plus text-base"></i>
                </a>
            @endif
            <span
                class="text-[9px] font-extrabold text-indigo-600 dark:text-indigo-400 tracking-tight mt-0.5">{{ __('New Link') }}</span>
        </div>

        <!-- Calendar -->
        <a href="{{ route('calendar.index') }}" wire:navigate
            class="flex flex-col items-center justify-center gap-1 w-14 py-1 rounded-xl text-center transition-all {{ request()->routeIs('calendar.*') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
            <i class="fa-solid fa-calendar-days text-base"></i>
            <span class="text-[10px] tracking-tight">{{ __('Calendar') }}</span>
        </a>

        <!-- More Menu Drawer Toggle -->
        <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
            class="flex flex-col items-center justify-center gap-1 w-14 py-1 rounded-xl text-center transition-all text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 cursor-pointer">
            <i class="fa-solid fa-bars-staggered text-base"></i>
            <span class="text-[10px] tracking-tight">{{ __('Menu') }}</span>
        </button>
    </nav>

    <x-command-palette :storefrontUrl="$storefrontUrl" />
    @livewireScripts
</body>

</html>
