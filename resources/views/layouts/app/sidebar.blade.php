@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['platformTracking' => false])
</head>

<body
    class="op-shell op-palette-ebony flex min-h-dvh flex-col bg-canvas text-stone-900 antialiased selection:bg-brand-400 selection:text-brand-foreground dark:bg-canvas-dark dark:text-zinc-100"
    @if (auth()->user()?->isAdmin()) style="--op-chrome-offset: 2.5rem" @endif
    x-data="{ mobileMenuOpen: false, commandPaletteOpen: false }" @keydown.window.cmd.k.prevent="commandPaletteOpen = true"
    @keydown.window.ctrl.k.prevent="commandPaletteOpen = true">

    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-xl focus:bg-white focus:px-4 focus:py-2.5 focus:text-sm focus:font-bold focus:text-slate-900 focus:shadow-lg focus:outline-2 focus:outline-offset-2 focus:outline-brand-500 dark:focus:bg-[#141721] dark:focus:text-white">
        {{ __('Skip to main content') }}
    </a>

    <x-toast />

    @php
        /** @var \App\Models\Operator|null $currentOperator */
        $currentOperator = auth()->user()?->currentOperator();
        $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
        $storefrontUrl = $currentOperator
            ? request()->getScheme() . '://' . $currentOperator->slug . '.' . $platformDomain
            : '#';
        if ($currentOperator) {
            $currentOperator->loadCount([
                'packages',
                'products',
                'vendors',
                'reservations as open_reservations_count' => fn ($query) => $query->whereIn('status', [
                    \App\Enums\ReservationStatus::Confirmed->value,
                    \App\Enums\ReservationStatus::PendingConfirmation->value,
                    \App\Enums\ReservationStatus::PaymentPending->value,
                ]),
            ]);
        }
        $packagesCount = $currentOperator?->packages_count ?? 0;
        $productsCount = $currentOperator?->products_count ?? 0;
        $vendorsCount = $currentOperator?->vendors_count ?? 0;
        $couponsCount = $currentOperator
            ? \App\Models\PlatformCoupon::forGuest()->where('operator_id', $currentOperator->id)->active()->count()
            : 0;
        $reservationsCount = $currentOperator?->open_reservations_count ?? 0;
        $operatorSupportEmail = \App\Models\PlatformSetting::current()->getOperatorSupportEmail();
        $availableBalance = $currentOperator ? $currentOperator->getAvailableBalance() : 0;
        $canManageTeam = $currentOperator && auth()->user()?->roleOn($currentOperator)?->allows('manageTeam');
        $operatorPlan = $currentOperator?->getPlan();
        $walletBadge = $availableBalance > 0
            ? ($availableBalance >= 1_000_000
                ? number_format($availableBalance / 1_000_000, 1).'jt'
                : number_format($availableBalance / 1000, 0).'k')
            : null;
    @endphp

    @if (auth()->user()?->isAdmin())
        <!-- Full-Width Admin Session Banner (Topmost, Fixed 40px) -->
        <div
            class="h-10 px-3 sm:px-6 bg-stone-100 dark:bg-zinc-900 border-b border-line dark:border-line-dark text-stone-700 dark:text-zinc-200 text-xs font-semibold flex items-center justify-between gap-2 z-40 shrink-0 select-none">
            <div class="flex items-center gap-2 min-w-0">
                <span class="flex h-6 w-6 items-center justify-center rounded-[6px] bg-[#FFEF4D] text-[#12181E] text-[10px] font-medium shrink-0">
                    <i class="fa-solid fa-compass"></i>
                </span>
                <div class="flex items-center gap-1.5 min-w-0 text-xs truncate">
                    <span class="hidden sm:inline text-[#60646C] dark:text-slate-400 font-normal whitespace-nowrap">{{ __('Admin Session: Managing Operator') }}</span>
                    <span class="sm:hidden text-[#60646C] dark:text-slate-400 font-normal whitespace-nowrap">{{ __('Managing:') }}</span>
                    <strong class="text-[#1C2024] dark:text-white font-medium truncate">{{ $currentOperator->name ?? 'Default Operator' }}</strong>
                </div>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <a href="{{ route('admin.operators.index') }}" wire:navigate title="{{ __('Switch Operator') }}"
                    class="h-7 px-2 sm:px-2.5 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#F4F5F6] text-[#1C2024] dark:text-slate-200 border border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium transition inline-flex items-center gap-1.5 cursor-pointer shadow-none">
                    <i class="fa-solid fa-users-gear text-[#8B8D98] text-[10px]"></i>
                    <span class="hidden md:inline">{{ __('Switch Operator') }}</span>
                    <span class="md:hidden hidden xs:inline">{{ __('Switch') }}</span>
                </a>
                <a href="{{ route('admin.platform.edit') }}" wire:navigate title="{{ __('Platform Admin') }}"
                    class="h-7 px-2 sm:px-2.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-[11px] font-medium transition inline-flex items-center gap-1.5 cursor-pointer shadow-none">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span class="hidden md:inline">{{ __('Platform Admin') }}</span>
                    <span class="md:hidden">{{ __('Admin') }}</span>
                </a>
            </div>
        </div>
    @endif

    @if ($currentOperator?->isInSubscriptionGracePeriod())
        <div class="no-print shrink-0 border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-center text-xs font-semibold text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
            {{ __('Your paid plan ran out. The booking page stays open for 3 extra days. Pay the bill to keep Growth or Agency features.') }}
            <a href="{{ route('settings.plan') }}" class="ml-1 underline" wire:navigate>{{ __('Open billing') }}</a>
        </div>
    @endif

    <div class="flex-1 flex min-h-0 w-full">
        <!-- Sticky Desktop Sidebar (Purely Desktop) -->
        <aside
            class="op-sidebar op-desk-chrome hidden w-72 min-h-0 shrink-0 select-none flex-col border-r border-op-line bg-op-sidebar sticky top-0 print:hidden">
            <div class="flex h-16 shrink-0 items-center px-4 border-b border-op-line">
                <a href="{{ route('dashboard') }}" class="group flex min-w-0 items-center gap-3 text-sm font-semibold"
                    wire:navigate>
                    @if ($currentOperator?->logo_url)
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-op-line bg-op-surface p-0.5">
                            <img src="{{ $currentOperator->logo_url }}" alt="{{ $currentOperator->name }}"
                                class="h-full w-full rounded-[6px] object-contain" />
                        </div>
                    @else
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D] text-xs font-medium text-[#12181E]">
                            {{ strtoupper(substr($currentOperator->name ?? config('app.name', 'T'), 0, 1)) }}
                        </span>
                    @endif
                    <div class="flex min-w-0 flex-col">
                        <span class="truncate text-sm font-bold leading-tight text-op-ink">
                            {{ $currentOperator->name ?? config('app.name', 'TravelEngine') }}
                        </span>
                        <span class="truncate text-[11px] font-normal text-op-subtle">
                            {{ __('Operator Portal') }}
                        </span>
                    </div>
                </a>
            </div>

            <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-4 select-none">
                <div>
                    @if (request()->routeIs('reservations.*'))
                        <x-button type="button" @click="$dispatch('open-create-booking-link')" class="w-full">
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>{{ __('Create Booking Link') }}</span>
                        </x-button>
                    @else
                        <x-button :href="route('reservations.index', ['create' => 1])" class="w-full" wire:navigate>
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>{{ __('Create Booking Link') }}</span>
                        </x-button>
                    @endif
                </div>

                <x-nav-section :title="__('Operations')">
                    <x-nav-link :href="route('dashboard')" icon="fa-gauge-high" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <x-nav-link :href="route('reservations.index')" icon="fa-calendar-check" :active="request()->routeIs('reservations.*')" :badge="$reservationsCount">
                        {{ __('Bookings') }}
                    </x-nav-link>

                    <x-nav-link :href="route('calendar.index')" icon="fa-calendar-days" :active="request()->routeIs('calendar.*')" :title="__('Calendar & Schedule')">
                        {{ __('Calendar') }}
                    </x-nav-link>

                    <x-nav-link :href="route('wallet.index')" icon="fa-wallet" :active="request()->routeIs('wallet.*')" :badge="$walletBadge" badge-tone="success" :title="__('Wallet & Payouts')">
                        {{ __('Wallet') }}
                    </x-nav-link>
                </x-nav-section>

                <x-nav-section :title="__('Catalog & Marketing')">
                    <x-nav-link :href="route('products.index')" icon="fa-compass" :active="request()->routeIs('products.*')" :badge="$productsCount" :title="__('Single Activities')">
                        {{ __('Activities') }}
                    </x-nav-link>

                    <x-nav-link :href="route('packages.index')" icon="fa-cubes" :active="request()->routeIs('packages.*')" :badge="$packagesCount" :title="__('Tour Packages')">
                        {{ __('Packages') }}
                    </x-nav-link>

                    <x-nav-link :href="route('coupons.index')" icon="fa-ticket" :active="request()->routeIs('coupons.*')" :badge="$couponsCount" :title="__('Coupons & Discounts')">
                        {{ __('Coupons') }}
                    </x-nav-link>

                    <x-nav-link :href="route('vendors.index')" icon="fa-handshake" :active="request()->routeIs('vendors.*')" :badge="$vendorsCount" :title="__('Vendors & Suppliers')">
                        {{ __('Vendors') }}
                    </x-nav-link>
                </x-nav-section>

                <x-nav-section :title="__('Business & Settings')">
                    <x-nav-link :href="route('guests.index')" icon="fa-address-book" :active="request()->routeIs('guests.*')">
                        {{ __('Guest CRM') }}
                        @if ($currentOperator && ! $currentOperator->hasFeature('guest_crm'))
                            <x-slot:meta>
                                <x-plan-badge title="{{ __('Upgrade to Growth to unlock Guest CRM & lifetime spend analytics') }}" />
                            </x-slot:meta>
                        @endif
                    </x-nav-link>

                    @if ($canManageTeam)
                        <x-nav-link :href="route('settings.team')" icon="fa-users" :active="request()->routeIs('settings.team')" :title="__('Team')">
                            {{ __('Team') }}
                        </x-nav-link>
                    @endif

                    <x-nav-link :href="route('brand.edit')" icon="fa-sliders" :active="request()->routeIs('brand.edit', 'storefront-settings.edit', 'review-settings.edit')" :title="__('Storefront Settings')">
                        {{ __('Storefront') }}
                    </x-nav-link>

                    <x-nav-link :href="route('settings.billing')" icon="fa-file-invoice-dollar" :active="request()->routeIs('settings.billing', 'payments.edit', 'settings.plan', 'settings.plan.checkout')" :title="__('Subscription & Billing')">
                        {{ __('Billing') }}
                    </x-nav-link>
                </x-nav-section>
            </nav>

            <div class="relative z-20 shrink-0 p-3">
                <x-dropdown align="top" width="full">
                    <x-slot name="trigger">
                        <button type="button"
                            class="group flex w-full cursor-pointer items-center gap-2.5 rounded-[6px] bg-white/5 p-2 text-start hover:bg-white/10 transition shadow-none">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] bg-[#FFEF4D] text-xs font-medium text-[#12181E]">
                                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-medium leading-tight text-op-ink">
                                    {{ auth()->user()->name ?? 'User' }}</p>
                                <p class="mt-0.5 truncate text-[11px] leading-tight text-op-subtle">
                                    {{ auth()->user()->email ?? '' }}</p>
                            </div>
                            <i class="fa-solid fa-chevron-up shrink-0 text-[10px] text-op-subtle"></i>
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
                                <i class="fa-brands fa-searchengin mr-2 text-brand-700 dark:text-brand-400 text-sm"></i>
                                <span class="font-bold text-op-ink">{{ __('Platform Admin') }}</span>
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
        <div class="flex-1 flex flex-col min-w-0 min-h-screen">
            <!-- Mobile Top Header (Fixed 56px) -->
            <header
                class="op-touch-nav min-h-14 px-4 sm:px-6 flex items-center justify-between border-b border-stone-200 dark:border-zinc-800 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md sticky top-0 z-30 select-none print:hidden pt-[env(safe-area-inset-top)]">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0" wire:navigate>
                    @if ($currentOperator?->logo_url)
                        <img src="{{ $currentOperator->logo_url }}" alt="{{ $currentOperator->name }}"
                            class="h-7 w-7 rounded-[6px] object-contain border border-slate-200 dark:border-[#262d3d] p-0.5 shrink-0 bg-white" />
                    @else
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-[6px] bg-[#FFEF4D] text-[#12181E] font-medium text-xs shrink-0">
                            {{ strtoupper(substr($currentOperator->name ?? 'T', 0, 1)) }}
                        </span>
                    @endif
                    <span
                        class="font-medium text-xs truncate text-slate-900 dark:text-white max-w-[180px] xs:max-w-[240px]">
                        {{ $currentOperator->name ?? config('app.name', 'TravelEngine') }}
                    </span>
                </a>

                <div class="flex items-center gap-2 shrink-0">
                    @if (request()->routeIs('reservations.*'))
                        <button type="button" @click="$dispatch('open-create-booking-link')"
                            class="h-8 px-2.5 inline-flex items-center gap-1 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-xs font-medium shadow-none cursor-pointer"
                            title="{{ __('Create Booking & Payment Link') }}">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>{{ __('Link') }}</span>
                        </button>
                    @else
                        <a href="{{ route('reservations.index', ['create' => 1]) }}" wire:navigate
                            class="h-8 px-2.5 inline-flex items-center gap-1 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-xs font-medium shadow-none cursor-pointer"
                            title="{{ __('Create Booking & Payment Link') }}">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>{{ __('Link') }}</span>
                        </a>
                    @endif

                    @if ($currentOperator)
                        <a href="{{ $storefrontUrl }}" target="_blank" rel="noopener"
                            class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-slate-200 text-xs font-medium shadow-none">
                            <i class="fa-solid fa-store text-xs text-[#8B8D98]"></i>
                            <span>{{ __('Live') }}</span>
                        </a>
                    @endif
                </div>
            </header>

            <!-- Desktop Top Header Bar (Exact 64px matching Desktop Sidebar Brand Header) -->
            <header
                class="op-desk-chrome hidden h-16 items-center justify-between px-6 2xl:px-8 border-b border-stone-200 dark:border-zinc-800 bg-white/90 dark:bg-zinc-950/90 backdrop-blur-md sticky top-0 z-30 select-none print:hidden">
                <!-- Left: Storefront URL with 1-Click Launch & Copy -->
                <div class="flex items-center gap-3 min-w-0" x-data="{ copied: false }">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-[6px] bg-[#F4F5F6] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-xs font-normal text-[#1C2024] dark:text-slate-300 shadow-none">
                        <i class="fa-solid fa-globe text-[11px] text-[#8B8D98]"></i>
                        <a href="{{ $storefrontUrl }}" target="_blank" rel="noopener"
                            class="truncate max-w-xs sm:max-w-md text-[#1C2024] dark:text-slate-200 hover:text-black dark:hover:text-white transition font-mono text-xs"
                            title="{{ __('Open live storefront in new tab') }}">
                            {{ $storefrontUrl }}
                        </a>
                        <a href="{{ $storefrontUrl }}" target="_blank" rel="noopener"
                            class="p-0.5 text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition"
                            title="{{ __('Open in new tab') }}">
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                        </a>
                        <span class="text-[#E4E5E9] dark:text-[#1E2433]">|</span>
                        <button type="button"
                            @click="navigator.clipboard.writeText('{{ $storefrontUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="p-0.5 hover:text-[#1C2024] dark:hover:text-white text-[#8B8D98] transition cursor-pointer"
                            title="{{ __('Copy link') }}">
                            <i class="fa-solid"
                                :class="copied ? 'fa-check text-emerald-500' : 'fa-copy text-[11px]'"></i>
                        </button>
                    </div>
                    <span x-show="copied" x-cloak
                        class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 animate-fade-in">
                        {{ __('Copied!') }}
                    </span>

                    <!-- Quick Command Search Trigger (⌘K) -->
                    <button type="button" @click="commandPaletteOpen = true"
                        class="h-8 px-3 inline-flex items-center gap-2 rounded-[6px] bg-[#F4F5F6] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-[#60646C] hover:text-[#1C2024] dark:hover:text-white text-xs font-normal transition-colors cursor-pointer group shadow-none"
                        title="{{ __('Search pages or actions (⌘K)') }}">
                        <i
                            class="fa-solid fa-magnifying-glass text-[11px] text-[#8B8D98] group-hover:text-[#1C2024] dark:group-hover:text-[#FFEF4D] transition-colors"></i>
                        <span class="hidden xl:inline text-xs">{{ __('Search or jump to...') }}</span>
                        <kbd
                            class="px-1.5 py-0.5 text-[10px] font-mono font-medium text-[#8B8D98] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] rounded-[4px] shadow-none">⌘K</kbd>
                    </button>
                </div>

                <!-- Right: Active Plan Badge & Quick Action -->
                <div class="flex items-center gap-3 shrink-0">
                    @if ($currentOperator && $operatorPlan)
                        <a href="{{ route('settings.plan') }}" wire:navigate
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] text-xs font-medium bg-white hover:bg-[#F4F5F6] text-[#1C2024] border border-[#E4E5E9] dark:bg-[#141821] dark:text-slate-200 dark:border-[#1E2433] transition shadow-none"
                            title="{{ __('Manage Subscription Tier') }}">
                            <i class="fa-solid fa-crown text-[10px]"></i>
                            <span>{{ $operatorPlan->name }}</span>
                        </a>
                    @endif
                </div>
            </header>

            <!-- Main Workspace -->
            <main
                id="main-content"
                tabindex="-1"
                class="op-desk-pad flex-1 w-full px-4 py-5 sm:px-6 lg:px-8 lg:py-6 pb-[calc(6rem+env(safe-area-inset-bottom))] print:m-0 print:w-full print:max-w-none print:p-0">
                <div class="mx-auto w-full max-w-7xl space-y-6 print:max-w-none print:space-y-0">
                    @php
                        $platformAnnouncements = \App\Models\PlatformAnnouncement::forOperator($currentOperator)->get();
                    @endphp

                    @if ($currentOperator?->isDemo())
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 print:hidden dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
                            <p class="font-bold">{{ __('Demo operator') }}</p>
                            <p class="mt-1 text-xs text-amber-800 dark:text-amber-200/80">{{ __('Look around freely. Checkout is off, and this catalog resets every day.') }}</p>
                        </div>
                    @endif

                    @if ($platformAnnouncements->isNotEmpty())
                        <div class="space-y-3 print:hidden">
                            @foreach ($platformAnnouncements as $announcement)
                                @php
                                    $bannerClasses = match ($announcement->type) {
                                        'critical'
                                            => 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800/80 text-rose-800 dark:text-rose-200',
                                        'warning'
                                            => 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/80 text-amber-800 dark:text-amber-200',
                                        'success'
                                            => 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/80 text-emerald-800 dark:text-emerald-200',
                                        default
                                            => 'bg-white dark:bg-[#0C0E13] border-slate-200/80 dark:border-[#1e2433] text-slate-800 dark:text-zinc-200',
                                    };
                                    $iconClasses = match ($announcement->type) {
                                        'critical' => 'fa-solid fa-triangle-exclamation text-rose-500',
                                        'warning' => 'fa-solid fa-circle-exclamation text-amber-500',
                                        'success' => 'fa-solid fa-circle-check text-emerald-500',
                                        default => 'fa-solid fa-bullhorn text-stone-500',
                                    };
                                @endphp
                                <div x-data="{ dismissed: false }" x-show="!dismissed"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                    class="p-4 rounded-2xl border {{ $bannerClasses }} shadow-xs flex items-start justify-between gap-3 text-xs">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div
                                            class="w-8 h-8 rounded-lg bg-stone-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                                            <i class="{{ $iconClasses }} text-sm"></i>
                                        </div>
                                        <div class="space-y-0.5 min-w-0">
                                            <h4
                                                class="font-semibold text-xs uppercase tracking-wide text-stone-900 dark:text-white">
                                                {{ $announcement->title }}
                                            </h4>
                                            <p class="leading-relaxed text-slate-600 dark:text-zinc-300">
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

                    <livewire:operator-setup-banner />

                    {{ $slot }}
                </div>
            </main>

            <!-- Mobile Menu Modal Drawer -->
            <div x-show="mobileMenuOpen" x-cloak class="op-touch-nav relative z-50" role="dialog" aria-modal="true">
                <!-- Dim Backdrop -->
                <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" x-on:click="mobileMenuOpen = false"
                    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

                <!-- Bottom Sheet Content -->
                <div class="fixed inset-x-0 bottom-0 z-50 p-3 sm:p-4 max-h-[85vh] overflow-y-auto pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <div x-show="mobileMenuOpen" x-cloak
                        x-transition:enter="transition ease-out duration-250 transform"
                        x-transition:enter-start="translate-y-full opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100"
                        x-transition:leave="transition ease-in duration-200 transform"
                        x-transition:leave-start="translate-y-0 opacity-100"
                        x-transition:leave-end="translate-y-full opacity-0" x-on:click.away="mobileMenuOpen = false"
                        class="w-full max-w-lg mx-auto rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none p-5 space-y-4">
                        <!-- Header -->
                        <div
                            class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                            <div class="flex items-center gap-3 min-w-0">
                                @if ($currentOperator?->logo_url)
                                    <div
                                        class="h-9 w-9 rounded-[8px] overflow-hidden border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] p-0.5 shrink-0 flex items-center justify-center">
                                        <img src="{{ $currentOperator->logo_url }}"
                                            alt="{{ $currentOperator->name }}"
                                            class="w-full h-full object-contain rounded-[6px]" />
                                    </div>
                                @else
                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-[8px] bg-[#FFEF4D] text-[#12181E] font-medium text-xs shrink-0">
                                        {{ strtoupper(substr($currentOperator->name ?? 'T', 0, 1)) }}
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <h3 class="font-bold text-sm text-slate-900 dark:text-white truncate">
                                        {{ $currentOperator->name ?? config('app.name', 'TravelEngine') }}
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 truncate">
                                        {{ auth()->user()->email ?? '' }}
                                    </p>
                                </div>
                            </div>

                            <button type="button" x-on:click="mobileMenuOpen = false"
                                class="h-8 w-8 rounded-full bg-slate-100 dark:bg-[#141721] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition cursor-pointer">
                                <i class="fa-solid fa-xmark text-sm"></i>
                            </button>
                        </div>

                        <!-- Live Storefront Quick Link -->
                        @if ($currentOperator)
                            <a href="{{ $storefrontUrl }}" target="_blank" rel="noopener"
                                class="h-10 px-3.5 flex items-center justify-between rounded-xl bg-brand-400 hover:bg-brand-500 text-brand-foreground text-xs font-semibold">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-store"></i>
                                    <span>{{ __('Open Live Storefront') }}</span>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[11px]"></i>
                            </a>
                        @endif

                        <!-- Secondary Navigation Grid (2x2) -->
                        <div class="grid grid-cols-2 gap-2.5 pt-1">
                            <!-- Single Activities -->
                            <a href="{{ route('products.index') }}" wire:navigate
                                x-on:click="mobileMenuOpen = false"
                                class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 hover:border-stone-300 dark:hover:border-zinc-600 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 transition space-y-1 block">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                        <i class="fa-solid fa-compass"></i>
                                    </span>
                                    @if ($productsCount > 0)
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400">
                                            {{ $productsCount }}
                                        </span>
                                    @endif
                                </div>
                                <span
                                    class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Single Activities') }}</span>
                                <span
                                    class="text-[10px] text-slate-400 block">{{ __('Daily sessions & items') }}</span>
                            </a>

                            <!-- Tour Packages -->
                            <a href="{{ route('packages.index') }}" wire:navigate
                                x-on:click="mobileMenuOpen = false"
                                class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 hover:border-stone-300 dark:hover:border-zinc-600 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 transition space-y-1 block">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                        <i class="fa-solid fa-cubes"></i>
                                    </span>
                                    @if ($packagesCount > 0)
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400">
                                            {{ $packagesCount }}
                                        </span>
                                    @endif
                                </div>
                                <span
                                    class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Tour Packages') }}</span>
                                <span class="text-[10px] text-slate-400 block">{{ __('Curated packages') }}</span>
                            </a>

                            <!-- Wallet & Payouts -->
                            <a href="{{ route('wallet.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                                class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 hover:border-stone-300 dark:hover:border-zinc-600 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 transition space-y-1 block">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                        <i class="fa-solid fa-wallet"></i>
                                    </span>
                                    @if ($availableBalance > 0)
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400">
                                            Rp {{ number_format($availableBalance / 1000, 0) }}k
                                        </span>
                                    @endif
                                </div>
                                <span
                                    class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Wallet & Payouts') }}</span>
                                <span
                                    class="text-[10px] text-slate-400 block">{{ __('Balance & settlements') }}</span>
                            </a>

                            <!-- Vendors & Suppliers -->
                            <a href="{{ route('vendors.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                                class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 hover:border-stone-300 dark:hover:border-zinc-600 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 transition space-y-1 block">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                        <i class="fa-solid fa-handshake"></i>
                                    </span>
                                    @if ($vendorsCount > 0)
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400">
                                            {{ $vendorsCount }}
                                        </span>
                                    @endif
                                </div>
                                <span
                                    class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Vendors') }}</span>
                                <span
                                    class="text-[10px] text-slate-400 block">{{ __('Activity suppliers') }}</span>
                            </a>

                        </div>

                        <!-- Guest CRM -->
                        <a href="{{ route('guests.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                    <i class="fa-solid fa-address-book"></i>
                                </span>
                                <div>
                                    <span
                                        class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Guest CRM') }}</span>
                                    <span
                                        class="text-[10px] text-slate-400 block">{{ __('Contacts, notes, lifetime spend') }}</span>
                                </div>
                            </div>
                            @if ($currentOperator && !$currentOperator->hasFeature('guest_crm'))
                                <span
                                    title="{{ __('Upgrade to Growth to unlock Guest CRM & lifetime spend analytics') }}"
                                    class="px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase bg-brand-400 text-brand-foreground flex items-center gap-1">
                                    <i class="fa-solid fa-lock text-[8px]"></i>
                                    <span>{{ __('Growth') }}</span>
                                </span>
                            @else
                                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                            @endif
                        </a>

                        <!-- Coupons & Discounts Link -->
                        <a href="{{ route('coupons.index') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
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

                        @if ($canManageTeam)
                            <a href="{{ route('settings.team') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                                class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                        <i class="fa-solid fa-users"></i>
                                    </span>
                                    <div>
                                        <span
                                            class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Team') }}</span>
                                        <span
                                            class="text-[10px] text-slate-400 block">{{ __('People who help run this shop') }}</span>
                                    </div>
                                </div>
                                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                            </a>
                        @endif

                        <!-- Storefront Settings Link -->
                        <a href="{{ route('brand.edit') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                    <i class="fa-solid fa-sliders"></i>
                                </span>
                                <div>
                                    <span
                                        class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Storefront Settings') }}</span>
                                        <span
                                            class="text-[10px] text-slate-400 block">{{ __('Branding, policies, guest page') }}</span>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        </a>

                        <a href="{{ route('payments.edit') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                    <i class="fa-solid fa-building-columns"></i>
                                </span>
                                <div>
                                    <span
                                        class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Payout bank account') }}</span>
                                    <span
                                        class="text-[10px] text-slate-400 block">{{ __('Where we send your money') }}</span>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        </a>

                        <!-- Subscription & Billing Link -->
                        <a href="{{ route('settings.billing') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                            class="p-3 rounded-xl border border-stone-200 dark:border-zinc-800 bg-stone-50/50 dark:bg-zinc-900 hover:bg-stone-100 dark:hover:bg-zinc-800 flex items-center justify-between transition">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="p-1.5 rounded-lg bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400 text-xs">
                                    <i class="fa-solid fa-crown"></i>
                                </span>
                                <div>
                                    <span
                                        class="font-bold text-xs text-slate-800 dark:text-slate-200 block">{{ __('Subscription & Billing') }}</span>
                                    <span
                                        class="text-[10px] text-slate-400 block">{{ __('Plan, payout bank & invoices') }}</span>
                                </div>
                            </div>
                            @if ($operatorPlan)
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-300 shrink-0">
                                    {{ $operatorPlan->name }}
                                </span>
                            @else
                                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                            @endif
                        </a>

                        <!-- User Account & Logout Footer -->
                        <div
                            class="pt-2 border-t border-stone-100 dark:border-zinc-800 flex items-center justify-between gap-3">
                            <a href="{{ route('profile.edit') }}" wire:navigate x-on:click="mobileMenuOpen = false"
                                class="h-9 px-3 rounded-xl bg-stone-100 dark:bg-zinc-800 hover:bg-stone-200 dark:hover:bg-zinc-700 text-xs font-semibold text-stone-700 dark:text-zinc-300 inline-flex items-center gap-2 transition">
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

            <!-- Operator Dashboard Sticky Footer (Exact 64px matching Sidebar Footer) -->
            <footer
                class="op-desk-chrome sticky bottom-0 z-30 mb-16 mt-auto hidden h-11 items-center border-t border-op-line bg-op-sidebar/90 px-6 select-none backdrop-blur-md print:hidden">
                <div class="mx-auto flex w-full max-w-7xl items-center justify-between text-xs text-op-subtle">
                    <span>
                        &copy; {{ date('Y') }}
                        <strong class="font-semibold text-op-ink">{{ $currentOperator->name ?? config('app.name', 'TravelEngine') }}</strong>
                    </span>
                    <div class="flex items-center gap-4">
                        <a href="mailto:{{ $operatorSupportEmail }}"
                            class="inline-flex items-center gap-1.5 font-semibold text-op-ink hover:underline">
                            <i class="fa-solid fa-life-ring text-xs text-brand-500"></i>
                            {{ __('Support') }}
                        </a>
                        <a href="https://{{ $platformDomain }}" target="_blank"
                            class="inline-flex items-center gap-1.5 font-semibold text-op-ink hover:underline"
                            title="{{ __('Tour Operator & Direct Booking Engine Platform') }}">
                            <i class="fa-solid fa-compass text-xs text-brand-500"></i>
                            {{ config('app.name', 'TravelEngine') }}
                        </a>
                    </div>
                </div>
            </footer>
        </div>

        <!-- Phone and tablet bottom navigation. Desktop sidebar only on a wide pointer. -->
        <nav
            x-show="!mobileMenuOpen"
            class="op-touch-nav fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md border-t border-stone-200 dark:border-zinc-800 min-h-16 h-auto flex items-center justify-around px-2 select-none print:hidden pb-[env(safe-area-inset-bottom)]">
            <x-mobile-nav-item :href="route('dashboard')" icon="fa-gauge-high" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-mobile-nav-item>

            <x-mobile-nav-item :href="route('reservations.index')" icon="fa-calendar-check" :active="request()->routeIs('reservations.*')" :badge="$reservationsCount">
                {{ __('Bookings') }}
            </x-mobile-nav-item>

            <!-- Center Raised Action: Create Booking Link -->
            <div class="relative flex flex-col items-center">
                @if (request()->routeIs('reservations.*'))
                    <button type="button" @click="$dispatch('open-create-booking-link')"
                        class="w-10 h-10 rounded-[8px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:scale-95 text-[#12181E] shadow-none flex items-center justify-center -mt-3.5 transition-all cursor-pointer border border-[#12181E]/10"
                        title="{{ __('Create Booking Link') }}">
                        <i class="fa-solid fa-plus text-sm"></i>
                    </button>
                @else
                    <a href="{{ route('reservations.index', ['create' => 1]) }}" wire:navigate
                        class="w-10 h-10 rounded-[8px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:scale-95 text-[#12181E] shadow-none flex items-center justify-center -mt-3.5 transition-all cursor-pointer border border-[#12181E]/10"
                        title="{{ __('Create Booking Link') }}">
                        <i class="fa-solid fa-plus text-sm"></i>
                    </a>
                @endif
                <span
                    class="text-[9px] font-extrabold text-slate-800 dark:text-zinc-300 tracking-tight mt-0.5">{{ __('New Link') }}</span>
            </div>

            <x-mobile-nav-item :href="route('calendar.index')" icon="fa-calendar-days" :active="request()->routeIs('calendar.*')">
                {{ __('Calendar') }}
            </x-mobile-nav-item>

            <!-- More Menu Drawer Toggle -->
            <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
                class="flex flex-col items-center justify-center gap-1 w-14 py-1 rounded-xl text-center transition-all text-slate-500 dark:text-zinc-400 hover:text-slate-800 dark:hover:text-slate-200 cursor-pointer">
                <i class="fa-solid fa-bars-staggered text-base"></i>
                <span class="text-[10px] tracking-tight">{{ __('Menu') }}</span>
            </button>
        </nav>

        <x-command-palette :storefrontUrl="$storefrontUrl" :canManageTeam="$canManageTeam" />
        @livewireScripts
</body>

</html>
