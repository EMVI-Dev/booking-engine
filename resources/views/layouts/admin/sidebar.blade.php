@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body
    class="min-h-screen bg-slate-50 dark:bg-[#0D0E12] text-slate-900 dark:text-slate-100 flex selection:bg-[#FFEF4D] selection:text-[#090d16] antialiased"
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
        class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-white dark:bg-[#090B10] border-r border-slate-200/80 dark:border-[#1e2433] transition-transform duration-200 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 shrink-0 select-none">
        
        <!-- Platform Admin Header (Fixed 64px) -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80 dark:border-[#1e2433] shrink-0 bg-slate-100/50 dark:bg-[#090B10]">
            <a href="{{ route('admin.platform.edit') }}" class="flex items-center gap-3 font-semibold text-sm group min-w-0" wire:navigate>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FFEF4D] text-[#090d16] font-black text-sm shadow-xs group-hover:scale-105 transition-transform duration-200 shrink-0">
                    <i class="fa-solid fa-compass text-lg"></i>
                </span>
                <div class="flex flex-col min-w-0">
                    <span class="font-bold text-sm truncate text-slate-900 dark:text-white leading-tight">
                        {{ config('app.name', 'TravelEngine') }} <span class="text-[#8a7808] dark:text-[#FFEF4D] font-extrabold">Admin</span>
                    </span>
                    <span class="text-[10px] text-[#8a7808] dark:text-[#FFEF4D] font-bold uppercase tracking-wider">
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
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            <!-- Section 1: Overview & Merchants -->
            <div class="space-y-1">
                <p class="px-3 text-xs font-bold tracking-wider uppercase text-slate-500 dark:text-zinc-400">
                    {{ __('Overview & Merchants') }}
                </p>

                <a href="{{ route('admin.dashboard') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.dashboard') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-[#141821] hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-chart-pie w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.dashboard') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-400' }}"></i>
                    <span class="truncate">{{ __('Revenue Dashboard') }}</span>
                </a>

                <a href="{{ route('admin.operators.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.operators.*') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-[#141821] hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-users-gear w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.operators.*') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-400' }}"></i>
                        <span class="truncate">{{ __('Operators Directory') }}</span>
                    </div>
                    @if ($totalOperators > 0)
                        <span class="h-5 px-2 text-xs font-black flex items-center justify-center rounded-full {{ request()->routeIs('admin.operators.*') ? 'bg-[#090d16] text-[#FFEF4D]' : 'bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30' }} shrink-0">
                            {{ $totalOperators }}
                        </span>
                    @endif
                </a>
            </div>

            <!-- Section 2: Finance & Commercial -->
            <div class="space-y-1">
                <p class="px-3 text-xs font-bold tracking-wider uppercase text-slate-500 dark:text-zinc-400">
                    {{ __('Finance & Commercial') }}
                </p>

                <a href="{{ route('admin.plans.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.plans.*') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-[#141821] hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-layer-group w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.plans.*') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-400' }}"></i>
                    <span class="truncate">{{ __('Subscription Plans') }}</span>
                </a>

                <a href="{{ route('admin.payouts.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.payouts.*') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-[#141821] hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-money-bill-transfer w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.payouts.*') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-400' }}"></i>
                        <span class="truncate">{{ __('Payout Requests') }}</span>
                    </div>
                    @php
                        $pendingPayouts = \App\Models\PayoutRequest::where('status', \App\Enums\PayoutStatus::Pending)->count();
                    @endphp
                    @if ($pendingPayouts > 0)
                        <span class="h-5 px-2 text-xs font-black flex items-center justify-center rounded-full bg-[#FFEF4D] text-[#090d16] shrink-0 shadow-xs">
                            {{ $pendingPayouts }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('admin.coupons.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.coupons.*') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-[#141821] hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-ticket w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.coupons.*') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-400' }}"></i>
                    <span class="truncate">{{ __('Promo Codes') }}</span>
                </a>

                <a href="{{ route('admin.payments.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.payments.*') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-[#141821] hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-credit-card w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.payments.*') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-400' }}"></i>
                    <span class="truncate">{{ __('Payment Gateway') }}</span>
                </a>
            </div>

            <!-- Section 3: Platform Config -->
            <div class="space-y-1">
                <p class="px-3 text-xs font-bold tracking-wider uppercase text-slate-500 dark:text-zinc-400">
                    {{ __('Platform Config') }}
                </p>

                <a href="{{ route('admin.announcements.index') }}" wire:navigate
                    class="h-10 px-3 flex items-center justify-between rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.announcements.*') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-[#141821] hover:text-slate-900 dark:hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-bullhorn w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.announcements.*') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-400' }}"></i>
                        <span class="truncate">{{ __('Broadcast Notices') }}</span>
                    </div>
                    @php
                        $activeAnnouncements = \App\Models\PlatformAnnouncement::active()->count();
                    @endphp
                    @if ($activeAnnouncements > 0)
                        <span class="h-5 px-2 text-xs font-black flex items-center justify-center rounded-full {{ request()->routeIs('admin.announcements.*') ? 'bg-[#090d16] text-[#FFEF4D]' : 'bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30' }} shrink-0">
                            {{ $activeAnnouncements }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('admin.platform.edit') }}" wire:navigate
                    class="h-10 px-3 flex items-center gap-3 rounded-xl text-sm font-semibold transition-all duration-150 {{ request()->routeIs('admin.platform.*') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-[#141821] hover:text-slate-900 dark:hover:text-white' }}">
                    <i class="fa-solid fa-sliders w-5 text-center text-sm shrink-0 {{ request()->routeIs('admin.platform.*') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-400' }}"></i>
                    <span class="truncate">{{ __('Platform Settings') }}</span>
                </a>
            </div>

            <!-- Section: System Health & Gateway State -->
            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] space-y-2">
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
        <div class="p-3 border-t border-slate-200/80 dark:border-[#1e2433] space-y-2 shrink-0 bg-white dark:bg-[#090B10]">
            <!-- Switch to Operator Portal Button -->
            <a href="{{ route('dashboard') }}" wire:navigate
                class="h-9 px-3 flex items-center justify-between rounded-xl text-xs font-bold bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-slate-300 transition-colors border border-slate-200 dark:border-[#1e2433] shadow-xs">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-arrow-right-arrow-left text-xs text-[#8a7808] dark:text-[#FFEF4D]"></i>
                    <span>{{ __('Switch to Operator Portal') }}</span>
                </span>
                <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
            </a>

            <!-- Admin Profile & Logout Dropdown -->
            <x-dropdown position="top" class="w-full">
                <x-slot name="trigger">
                    <button type="button"
                        class="w-full h-12 p-2 flex items-center gap-3 rounded-xl hover:bg-slate-100 dark:hover:bg-[#141821] transition-colors text-left cursor-pointer">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#FFEF4D] text-[#090d16] font-black text-xs shadow-xs shrink-0">
                            {{ auth()->user()?->initials() ?? 'AD' }}
                        </span>
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="font-bold text-xs truncate text-slate-900 dark:text-white leading-tight">
                                {{ auth()->user()?->name ?? 'Platform Admin' }}
                            </span>
                            <span class="text-[11px] text-slate-500 dark:text-zinc-400 truncate">
                                {{ auth()->user()?->email }}
                            </span>
                        </div>
                        <i class="fa-solid fa-ellipsis-vertical text-slate-400 text-xs shrink-0 pr-1"></i>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-item :href="route('admin.profile.edit')" wire:navigate>
                        <i class="fa-solid fa-user-shield mr-2 text-[#8a7808] dark:text-[#FFEF4D] text-xs"></i>
                        {{ __('Admin Profile & Security') }}
                    </x-dropdown-item>
                    <x-dropdown-item :href="route('appearance.edit')" wire:navigate>
                        <i class="fa-solid fa-circle-half-stroke mr-2 text-[#8a7808] dark:text-[#FFEF4D] text-xs"></i>
                        {{ __('Appearance & Theme') }}
                    </x-dropdown-item>
                    <div class="border-t border-slate-100 dark:border-[#1e2433] my-1"></div>
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
            class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80 dark:border-[#1e2433] lg:hidden bg-white/90 dark:bg-[#090B10]/90 backdrop-blur-md sticky top-0 z-30">
            <button x-on:click="sidebarOpen = true" type="button"
                class="h-10 w-10 flex items-center justify-center text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-[#141821] cursor-pointer">
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

        <!-- Platform Master Sticky Footer -->
        <footer class="sticky bottom-0 z-30 border-t border-slate-200/80 dark:border-[#1e2433] bg-white/95 dark:bg-[#090B10]/95 backdrop-blur-md py-3 sm:py-3.5 px-4 sm:px-6 lg:px-8 mt-auto shadow-md select-none">
            <div class="mx-auto w-full max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                <div class="flex items-center gap-2 text-center sm:text-left">
                    <span class="font-bold text-slate-800 dark:text-slate-200">EMVI Platform Master Engine</span>
                    <span class="text-slate-300 dark:text-zinc-700">&bull;</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#FFEF4D] animate-pulse"></span>
                        v1.0.0 Stable
                    </span>
                </div>

                <div class="flex items-center gap-3 text-center sm:text-right">
                    <span>&copy; {{ date('Y') }} EMVI Infrastructure &bull; All Rights Reserved</span>
                </div>
            </div>
        </footer>
    </div>
    @livewireScripts
</body>

</html>
