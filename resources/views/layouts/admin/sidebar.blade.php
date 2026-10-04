@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['platformTracking' => false])
</head>

<body
    class="op-shell op-palette-ebony flex min-h-dvh flex-col bg-canvas text-stone-900 antialiased selection:bg-brand-400 selection:text-brand-foreground dark:bg-canvas-dark dark:text-zinc-100"
    x-data="{ sidebarOpen: false }">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-[6px] focus:bg-white focus:px-4 focus:py-2 focus:text-xs focus:font-semibold focus:text-slate-900 focus:shadow-none focus:outline-2 focus:outline-offset-2 focus:outline-[#FFEF4D]">
        {{ __('Skip to main content') }}
    </a>

    <x-toast />

    <div x-show="sidebarOpen" x-cloak x-on:click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-[#12181E]/60 backdrop-blur-xs lg:hidden"></div>

    @php
        $platform = \App\Models\PlatformSetting::current();
        $totalOperators = \App\Models\Operator::count();
        $pendingPayouts = app(\App\Services\AdminMetricsService::class)->payoutTotals(\App\Enums\PayoutStatus::Pending)['count'];
        $activeAnnouncements = \App\Models\PlatformAnnouncement::active()->count();
    @endphp

    <div class="flex min-h-0 w-full flex-1">
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="op-sidebar fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 select-none flex-col border-r border-op-line bg-[#12181e] transition-transform duration-200 ease-in-out print:hidden lg:sticky lg:top-0 lg:translate-x-0"
    >
        {{-- Brand Header --}}
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-white/[0.08] px-4">
            <a href="{{ route('admin.dashboard') }}" class="group flex min-w-0 items-center gap-2.5 text-sm font-semibold" wire:navigate>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D] text-xs font-bold text-[#12181E] shadow-none">
                    <i class="fa-solid fa-compass"></i>
                </span>
                <div class="flex min-w-0 flex-col">
                    <span class="truncate text-xs font-semibold leading-tight text-white">
                        {{ config('app.name', 'TravelEngine') }}
                    </span>
                    <span class="truncate text-[10px] font-medium text-white/50">
                        {{ __('Admin') }}
                    </span>
                </div>
            </a>
            <button x-on:click="sidebarOpen = false" type="button"
                class="rounded-[6px] p-1 text-white/50 hover:text-white lg:hidden">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        {{-- Nav Links --}}
        <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-3 select-none">
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

                <x-nav-link :href="route('admin.subscriptions.index')" icon="fa-receipt" :active="request()->routeIs('admin.subscriptions.*')">
                    {{ __('Subscriptions') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.payouts.index')" icon="fa-money-bill-transfer" :active="request()->routeIs('admin.payouts.*')" :badge="$pendingPayouts">
                    {{ __('Operator payouts') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.coupons.index')" icon="fa-ticket" :active="request()->routeIs('admin.coupons.*')">
                    {{ __('Coupons') }}
                </x-nav-link>
            </x-nav-section>

            <x-nav-section :title="__('Platform')">
                <x-nav-link :href="route('admin.announcements.index')" icon="fa-bullhorn" :active="request()->routeIs('admin.announcements.*')" :badge="$activeAnnouncements">
                    {{ __('Notices') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.admins.index')" icon="fa-shield-halved" :active="request()->routeIs('admin.admins.*')">
                    {{ __('Administrators') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.audit-log.index')" icon="fa-clipboard-list" :active="request()->routeIs('admin.audit-log.*')">
                    {{ __('Audit log') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.platform.edit')" icon="fa-sliders" :active="request()->routeIs('admin.platform.*')">
                    {{ __('Settings') }}
                </x-nav-link>
            </x-nav-section>
        </nav>

        {{-- Footer Controls & User Menu --}}
        <div class="relative z-20 shrink-0 border-t border-white/[0.08] p-3">
            <a href="{{ route('dashboard') }}" wire:navigate
                class="mb-2 flex h-8 w-full items-center justify-center gap-2 rounded-[6px] border border-white/10 bg-white/5 text-xs font-medium text-white/80 hover:text-white hover:bg-white/10 transition shadow-none">
                <i class="fa-solid fa-arrow-right-arrow-left text-[11px]"></i>
                <span>{{ __('Operator Portal') }}</span>
            </a>

            <x-dropdown align="top" width="full">
                <x-slot name="trigger">
                    <button type="button"
                        class="group flex w-full cursor-pointer items-center gap-2.5 rounded-[6px] border border-white/10 bg-white/5 p-2 text-start hover:bg-white/10 transition shadow-none">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[6px] bg-[#FFEF4D] text-xs font-semibold text-[#12181E] shadow-none">
                            {{ auth()->user()?->initials() ?? 'AD' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-medium leading-tight text-white">
                                {{ auth()->user()?->name ?? 'Platform Admin' }}
                            </p>
                            <p class="mt-0.5 truncate text-[11px] leading-tight text-white/50">
                                {{ auth()->user()?->email }}
                            </p>
                        </div>
                        <i class="fa-solid fa-chevron-up shrink-0 text-[10px] text-white/40"></i>
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

    <div class="flex min-h-screen min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] px-4 lg:hidden">
            <button x-on:click="sidebarOpen = true" type="button"
                class="flex h-9 w-9 items-center justify-center rounded-[6px] text-[#60646C] hover:bg-[#FAFAFB] dark:hover:bg-[#141821] hover:text-[#1C2024] dark:hover:text-white">
                <i class="fa-solid fa-bars text-sm"></i>
            </button>
            <span class="truncate px-2 text-xs font-semibold text-[#1C2024] dark:text-white">
                {{ __('Admin') }}
            </span>
            <div class="w-9"></div>
        </header>

        <main id="main-content" tabindex="-1" class="w-full flex-1 px-3 py-4 pb-16 sm:p-6 sm:pb-16 lg:p-8 lg:pb-16">
            <div class="mx-auto w-full max-w-7xl">
                {{ $slot }}
            </div>
        </main>

        <footer
            class="sticky bottom-0 z-30 mt-auto flex h-10 items-center border-t border-[#E4E5E9] dark:border-[#1E2433] bg-white/95 dark:bg-[#10141d]/95 px-4 text-xs text-[#60646C] dark:text-zinc-400 select-none backdrop-blur-md sm:px-6 lg:px-8 print:hidden">
            <div class="mx-auto flex w-full max-w-7xl items-center justify-between">
                <span class="font-medium text-[#1C2024] dark:text-white">
                    {{ config('app.name', 'TravelEngine') }}
                    <span class="font-normal text-[#60646C] dark:text-zinc-400">{{ __('by EMVI Technologies') }}</span>
                </span>
                <span class="text-[11px]">&copy; {{ date('Y') }}</span>
            </div>
        </footer>
    </div>
    </div>
    @livewireScripts
</body>

</html>
