@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body
    class="op-shell op-palette-ebony flex min-h-dvh flex-col bg-canvas text-stone-900 antialiased selection:bg-brand-400 selection:text-brand-foreground dark:bg-canvas-dark dark:text-zinc-100"
    x-data="{ sidebarOpen: false }">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-xl focus:bg-white focus:px-4 focus:py-2.5 focus:text-sm focus:font-bold focus:text-slate-900 focus:shadow-lg focus:outline-2 focus:outline-offset-2 focus:outline-brand-500">
        {{ __('Skip to main content') }}
    </a>

    <x-toast />

    <div x-show="sidebarOpen" x-cloak x-on:click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-[#12181E]/60 backdrop-blur-xs lg:hidden"></div>

    @php
        $platform = \App\Models\PlatformSetting::current();
        $dokuMode = $platform->getDokuMode();
        $totalOperators = \App\Models\Operator::count();
        $pendingPayouts = \App\Models\PayoutRequest::where('status', \App\Enums\PayoutStatus::Pending)->count();
        $activeAnnouncements = \App\Models\PlatformAnnouncement::active()->count();
    @endphp

    <div class="flex min-h-0 w-full flex-1">
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="op-sidebar fixed inset-y-0 left-0 z-50 flex w-72 shrink-0 select-none flex-col border-r border-op-line bg-op-sidebar transition-transform duration-200 ease-in-out print:hidden lg:sticky lg:top-0 lg:translate-x-0"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-op-line px-4">
            <a href="{{ route('admin.dashboard') }}" class="group flex min-w-0 items-center gap-3 text-sm font-semibold" wire:navigate>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-400 text-sm font-bold text-brand-foreground">
                    <i class="fa-solid fa-compass"></i>
                </span>
                <div class="flex min-w-0 flex-col">
                    <span class="truncate text-sm font-bold leading-tight text-op-ink">
                        {{ config('app.name', 'TravelEngine') }}
                    </span>
                    <span class="truncate text-[11px] font-normal text-op-subtle">
                        {{ __('Admin') }}
                    </span>
                </div>
            </a>
            <button x-on:click="sidebarOpen = false" type="button"
                class="rounded-lg p-1.5 text-op-subtle hover:text-op-ink lg:hidden">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-4 select-none">
            <x-nav-section :title="__('Overview')">
                <x-nav-link :href="route('admin.dashboard')" icon="fa-chart-pie" :active="request()->routeIs('admin.dashboard')">
                    {{ __('Dashboard') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.operators.index')" icon="fa-users-gear" :active="request()->routeIs('admin.operators.*')" :badge="$totalOperators">
                    {{ __('Operators') }}
                </x-nav-link>
            </x-nav-section>

            <x-nav-section :title="__('Money')">
                <x-nav-link :href="route('admin.plans.index')" icon="fa-layer-group" :active="request()->routeIs('admin.plans.*')">
                    {{ __('Plans') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.payouts.index')" icon="fa-money-bill-transfer" :active="request()->routeIs('admin.payouts.*')" :badge="$pendingPayouts">
                    {{ __('Payouts') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.coupons.index')" icon="fa-ticket" :active="request()->routeIs('admin.coupons.*')">
                    {{ __('Coupons') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.payments.index')" icon="fa-credit-card" :active="request()->routeIs('admin.payments.*')">
                    {{ __('Guest payments') }}
                </x-nav-link>
            </x-nav-section>

            <x-nav-section :title="__('Platform')">
                <x-nav-link :href="route('admin.announcements.index')" icon="fa-bullhorn" :active="request()->routeIs('admin.announcements.*')" :badge="$activeAnnouncements">
                    {{ __('Notices') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.platform.edit')" icon="fa-sliders" :active="request()->routeIs('admin.platform.*')">
                    {{ __('Settings') }}
                </x-nav-link>
            </x-nav-section>

            <div class="rounded-xl bg-white/5 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-op-subtle">{{ __('Checkout') }}</p>
                <div class="mt-2 flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-op-ink">{{ __('Guest checkout') }}</span>
                    <span @class([
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                        'bg-emerald-400/15 text-emerald-300' => $dokuMode->value === 'live',
                        'bg-amber-400/15 text-amber-300' => $dokuMode->value !== 'live',
                    ])>
                        <span @class([
                            'h-1.5 w-1.5 rounded-full',
                            'bg-emerald-400' => $dokuMode->value === 'live',
                            'bg-amber-400' => $dokuMode->value !== 'live',
                        ])></span>
                        {{ $dokuMode->value === 'live' ? __('Live') : __('Test') }}
                    </span>
                </div>
            </div>
        </nav>

        <div class="relative z-20 shrink-0 p-3">
            <a href="{{ route('dashboard') }}" wire:navigate
                class="mb-2 flex h-9 w-full items-center justify-center gap-2 rounded-xl bg-white/5 text-xs font-semibold text-op-ink hover:bg-white/10">
                <i class="fa-solid fa-arrow-right-arrow-left text-xs"></i>
                <span>{{ __('Operator Portal') }}</span>
            </a>

            <x-dropdown align="top" width="full">
                <x-slot name="trigger">
                    <button type="button"
                        class="group flex w-full cursor-pointer items-center gap-2.5 rounded-xl bg-white/5 p-2.5 text-start hover:bg-white/10">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-400 text-xs font-bold text-brand-foreground">
                            {{ auth()->user()?->initials() ?? 'AD' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold leading-tight text-op-ink">
                                {{ auth()->user()?->name ?? 'Platform Admin' }}
                            </p>
                            <p class="mt-0.5 truncate text-xs leading-tight text-op-subtle">
                                {{ auth()->user()?->email }}
                            </p>
                        </div>
                        <i class="fa-solid fa-chevron-up shrink-0 text-[10px] text-op-subtle"></i>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-item :href="route('admin.profile.edit')" wire:navigate>
                        <i class="fa-solid fa-user-shield mr-2 text-xs text-op-subtle"></i>
                        {{ __('Your profile') }}
                    </x-dropdown-item>
                    <x-dropdown-item :href="route('appearance.edit')" wire:navigate>
                        <i class="fa-solid fa-circle-half-stroke mr-2 text-xs text-op-subtle"></i>
                        {{ __('Appearance') }}
                    </x-dropdown-item>
                    <div class="my-1 border-t border-op-line"></div>
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <x-dropdown-item>
                            <i class="fa-solid fa-right-from-bracket mr-2 text-xs text-rose-500"></i>
                            {{ __('Log Out') }}
                        </x-dropdown-item>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-op-line px-4 lg:hidden">
            <button x-on:click="sidebarOpen = true" type="button"
                class="flex h-10 w-10 items-center justify-center rounded-xl text-op-subtle hover:bg-op-muted hover:text-op-ink">
                <i class="fa-solid fa-bars text-base"></i>
            </button>
            <span class="truncate px-2 text-sm font-bold text-op-ink">
                {{ __('Admin') }}
            </span>
            <div class="w-10"></div>
        </header>

        <main id="main-content" tabindex="-1" class="w-full flex-1 overflow-y-auto px-3 py-4 sm:p-6 lg:p-8">
            <div class="mx-auto w-full max-w-7xl">
                {{ $slot }}
            </div>
        </main>

        <footer class="mt-auto border-t border-op-line px-4 py-3 text-xs text-op-subtle sm:px-6 lg:px-8">
            <div class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-2 sm:flex-row">
                <span class="font-semibold text-op-ink">EMVI</span>
                <span>&copy; {{ date('Y') }}</span>
            </div>
        </footer>
    </div>
    </div>
    @livewireScripts
</body>

</html>
