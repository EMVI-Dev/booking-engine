@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body
    class="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 flex selection:bg-purple-500 selection:text-white antialiased"
    x-data="{ sidebarOpen: false }">
    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen" x-cloak x-on:click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs lg:hidden"></div>

    @php
        $platform = \App\Models\PlatformSetting::current();
        $dokuMode = $platform->getDokuMode();
        $totalOperators = \App\Models\Operator::count();
    @endphp

    <!-- Sticky Desktop Sidebar / Slide-over Mobile Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-white dark:bg-zinc-900 border-r border-slate-200/80 dark:border-zinc-800 transition-transform duration-200 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 shrink-0 select-none">
        
        <!-- Platform Admin Header (Fixed 64px) -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80 dark:border-zinc-800 shrink-0 bg-purple-50/50 dark:bg-purple-950/20">
            <a href="{{ route('admin.platform.edit') }}" class="flex items-center gap-3 font-semibold text-sm group min-w-0" wire:navigate>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-600 text-white font-black text-sm shadow-sm group-hover:scale-105 transition-transform duration-200 shrink-0">
                    <i class="fa-solid fa-compass text-lg"></i>
                </span>
                <div class="flex flex-col min-w-0">
                    <span class="font-bold text-sm truncate text-slate-900 dark:text-white leading-tight">
                        {{ config('app.name', 'TravelEngine') }} <span class="text-purple-600 dark:text-purple-400 font-extrabold">Admin</span>
                    </span>
                    <span class="text-[10px] text-purple-700 dark:text-purple-300 font-bold uppercase tracking-wider">
                        {{ __('Platform Master') }}
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
            <!-- Section: Platform Management -->
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-bold tracking-wider uppercase text-purple-600 dark:text-purple-400">
                    {{ __('Platform Control') }}
                </p>

                <a href="{{ route('admin.dashboard') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.dashboard') ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-chart-pie w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.dashboard') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Revenue Dashboard') }}</span>
                </a>

                <a href="{{ route('admin.operators.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.operators.*') ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-users-gear w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.operators.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Operators Management') }}</span>
                    </div>
                    @if ($totalOperators > 0)
                        <span class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 shrink-0">
                            {{ $totalOperators }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('admin.plans.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.plans.*') ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-layer-group w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.plans.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Subscription Plans') }}</span>
                </a>

                <a href="{{ route('admin.announcements.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.announcements.*') ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-bullhorn w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.announcements.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Broadcast Notices') }}</span>
                    </div>
                    @php
                        $activeAnnouncements = \App\Models\PlatformAnnouncement::active()->count();
                    @endphp
                    @if ($activeAnnouncements > 0)
                        <span class="h-5 px-2 text-[11px] font-bold flex items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 shrink-0">
                            {{ $activeAnnouncements }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('admin.coupons.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.coupons.*') ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-ticket w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.coupons.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Promo Codes') }}</span>
                </a>

                <a href="{{ route('admin.payouts.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.payouts.*') ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-money-bill-transfer w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.payouts.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                        <span class="truncate">{{ __('Payout Requests') }}</span>
                    </div>
                    @php
                        $pendingPayouts = \App\Models\PayoutRequest::where('status', \App\Enums\PayoutStatus::Pending)->count();
                    @endphp
                    @if ($pendingPayouts > 0)
                        <span class="h-5 px-2 text-[11px] font-black flex items-center justify-center rounded-full bg-amber-500 text-white shrink-0">
                            {{ $pendingPayouts }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('admin.platform.edit') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.platform.*') ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-sliders w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.platform.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Platform Settings') }}</span>
                </a>

                <a href="{{ route('admin.payments.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.payments.*') ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-credit-card w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.payments.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"></i>
                    <span class="truncate">{{ __('Payment Gateways') }}</span>
                </a>
            </div>

            <!-- Section: System Health & Gateway State -->
            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 space-y-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Gateway Status') }}</span>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">DOKU Gateway</span>
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $dokuMode->value === 'live' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $dokuMode->value === 'live' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        {{ strtoupper($dokuMode->value) }}
                    </span>
                </div>
            </div>
        </nav>

        <!-- Sidebar Footer: Switch Portal & Profile -->
        <div class="p-3 border-t border-slate-200/80 dark:border-zinc-800 space-y-2 shrink-0 bg-white dark:bg-zinc-900">
            <!-- Switch to Operator Portal Button -->
            <a href="{{ route('dashboard') }}" wire:navigate
                class="h-9 px-3 flex items-center justify-between rounded-xl text-xs font-bold bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition-colors border border-slate-200 dark:border-zinc-700 shadow-xs">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-arrow-right-arrow-left text-xs text-indigo-500"></i>
                    <span>{{ __('Switch to Operator Portal') }}</span>
                </span>
                <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
            </a>

            <!-- Admin Profile & Logout Dropdown -->
            <x-dropdown position="top" class="w-full">
                <x-slot name="trigger">
                    <button type="button"
                        class="w-full h-12 p-2 flex items-center gap-3 rounded-xl hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors text-left cursor-pointer">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-600 text-white font-bold text-xs shadow-xs shrink-0">
                            {{ auth()->user()?->initials() ?? 'AD' }}
                        </span>
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="font-bold text-xs truncate text-slate-900 dark:text-white leading-tight">
                                {{ auth()->user()?->name ?? 'Platform Admin' }}
                            </span>
                            <span class="text-[11px] text-purple-600 dark:text-purple-400 truncate">
                                {{ auth()->user()?->email }}
                            </span>
                        </div>
                        <i class="fa-solid fa-ellipsis-vertical text-slate-400 text-xs shrink-0 pr-1"></i>
                    </button>
                </x-slot>

                <x-slot name="content">
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
        <!-- Mobile Header -->
        <header
            class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80 dark:border-zinc-800 lg:hidden bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md sticky top-0 z-30">
            <button x-on:click="sidebarOpen = true" type="button"
                class="h-10 w-10 flex items-center justify-center text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                <i class="fa-solid fa-bars text-base"></i>
            </button>
            <span class="font-bold text-sm text-slate-900 dark:text-white truncate px-2">
                {{ __('Platform Administration') }}
            </span>
            <div class="w-10"></div>
        </header>

        <main class="flex-1 px-3 py-4 sm:p-6 lg:p-8 overflow-y-auto w-full">
            <div class="mx-auto w-full max-w-7xl">
                {{ $slot }}
            </div>
        </main>
    </div>
    @livewireScripts
</body>

</html>
