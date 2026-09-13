@php
    /** @var \App\Models\Operator $agent */
    $agent = $agent ?? ($operator ?? null);
    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $cleanPhone = !empty($agent?->contact_whatsapp) ? $waService->normalizePhoneNumber($agent->contact_whatsapp) : null;
    $waMessage = "Hello {$agent?->name}, I am browsing your tour catalog and have an inquiry.";
    $waUrl = $cleanPhone ? $waService->buildWhatsAppUrl($agent->contact_whatsapp, $waMessage) : null;

    if ($agent) {
        $agent->loadCount([
            'packages as published_packages_count' => fn ($query) => $query->where('status', \App\Enums\ListingStatus::Published),
            'products as standalone_products_count' => fn ($query) => $query
                ->where('status', \App\Enums\ListingStatus::Published)
                ->where('sellable_standalone', true),
        ]);
    }
    $packagesCount = $agent?->published_packages_count ?? 0;
    $productsCount = $agent?->standalone_products_count ?? 0;
    $operatorLoginUrl = url('/login');
@endphp

@if (\App\Models\PlatformSetting::current()->isPlatformMaintenance())
    <div class="bg-amber-500 text-[#090d16] text-center text-xs sm:text-sm font-bold px-4 py-2">
        {{ __('Bookings and payments are paused for a short maintenance window. You can still browse.') }}
    </div>
@elseif ($agent?->isDemo())
    <div class="bg-slate-900 text-center text-xs sm:text-sm font-bold px-4 py-2 text-white flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
        <span>{{ __('Demo storefront — checkout is off. Catalog resets every day.') }}</span>
        <a href="{{ $operatorLoginUrl }}" class="underline decoration-white/40 underline-offset-2 hover:decoration-white">
            {{ __('Try the operator desk') }}
        </a>
    </div>
@endif

<!-- Sticky Header Navigation -->
<header
    x-data="{ mobileMenuOpen: false }"
    class="sticky top-0 z-40 bg-white/85 dark:bg-zinc-900/85 backdrop-blur-xl border-b border-slate-200/80 dark:border-zinc-800/80 transition-all select-none"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <!-- Brand Avatar & Title -->
        <a href="{{ route('home') }}" class="flex items-center gap-3 min-w-0 group py-1">
            <div class="h-10 w-10 rounded-2xl overflow-hidden border border-slate-200/80 dark:border-zinc-800 shadow-xs shrink-0 group-hover:scale-105 transition-transform bg-white dark:bg-zinc-800 p-1 flex items-center justify-center">
                <img src="{{ $agent?->logo_url ?? asset('favicon.png') }}" alt="{{ $agent?->name ?? config('app.name') }}" class="w-full h-full object-contain rounded-xl" />
            </div>
            <div class="flex flex-col min-w-0">
                <span class="font-black text-sm sm:text-base tracking-tight text-slate-900 dark:text-white truncate group-hover:text-brand-800 dark:group-hover:text-brand-400 transition-colors">
                    {{ $agent?->name ?? config('app.name') }}
                </span>
                <div class="flex items-center gap-1.5 text-[10px] text-emerald-600 dark:text-emerald-400 font-bold leading-none mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>{{ __('Verified Operator') }}</span>
                    @if ($agent?->location)
                        <span class="text-slate-300 dark:text-zinc-600">&bull;</span>
                        <span class="text-slate-500 dark:text-slate-400 font-normal truncate max-w-[120px] sm:max-w-none">{{ $agent->location }}</span>
                    @endif
                </div>
            </div>
        </a>

        <!-- Desktop Segmented Navigation Pills -->
        <nav class="hidden md:flex items-center gap-1 p-1 rounded-2xl bg-slate-100/70 dark:bg-zinc-800/60 border border-slate-200/60 dark:border-zinc-700/60 text-xs font-bold">
            @php
                $navActive = 'bg-brand-600 text-brand-foreground shadow-xs';
                $navIdle = 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-zinc-900/50';
                $badgeActive = 'bg-brand-foreground/20 text-brand-foreground';
                $badgeIdle = 'bg-slate-200/80 dark:bg-zinc-700 text-slate-600 dark:text-slate-300';
            @endphp
            <a
                href="{{ route('home') }}"
                class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('home') ? $navActive : $navIdle }}"
            >
                <i class="fa-solid fa-compass text-[11px]"></i>
                <span>{{ __('Catalog') }}</span>
            </a>

            @if ($packagesCount > 0)
                <a
                    href="{{ route('storefront.packages') }}"
                    class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('storefront.packages') || request()->routeIs('storefront.package') ? $navActive : $navIdle }}"
                >
                    <i class="fa-solid fa-cubes text-[11px]"></i>
                    <span>{{ __('Tour Packages') }}</span>
                    <span class="px-1.5 py-0.2 rounded-md text-[10px] font-black {{ request()->routeIs('storefront.packages') || request()->routeIs('storefront.package') ? $badgeActive : $badgeIdle }}">
                        {{ $packagesCount }}
                    </span>
                </a>
            @endif

            @if ($productsCount > 0)
                <a
                    href="{{ route('storefront.products') }}"
                    class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('storefront.products') || request()->routeIs('storefront.product') ? $navActive : $navIdle }}"
                >
                    <i class="fa-solid fa-compass text-[11px]"></i>
                    <span>{{ __('Single Activities') }}</span>
                    <span class="px-1.5 py-0.2 rounded-md text-[10px] font-black {{ request()->routeIs('storefront.products') || request()->routeIs('storefront.product') ? $badgeActive : $badgeIdle }}">
                        {{ $productsCount }}
                    </span>
                </a>
            @endif

            <a
                href="{{ route('storefront.find-booking') }}"
                class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('storefront.find-booking') ? $navActive : $navIdle }}"
            >
                <i class="fa-solid fa-ticket text-[11px]"></i>
                <span>{{ __('Find Booking') }}</span>
            </a>

            <a
                href="{{ route('storefront.terms') }}"
                class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('storefront.terms') ? $navActive : $navIdle }}"
            >
                <i class="fa-solid fa-shield-halved text-[11px]"></i>
                <span>{{ __('Terms') }}</span>
            </a>
        </nav>

        <!-- Right Header Actions (WhatsApp, demo desk, Mobile Toggle) -->
        <div class="flex items-center gap-2.5">
            @if ($agent?->isDemo())
                <a
                    href="{{ $operatorLoginUrl }}"
                    class="hidden sm:inline-flex h-9 px-3.5 rounded-xl border border-slate-200/80 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-slate-800 dark:text-zinc-100 font-bold text-xs shadow-xs hover:bg-slate-50 dark:hover:bg-zinc-800 transition items-center gap-1.5"
                >
                    <i class="fa-solid fa-gauge text-[11px] text-brand-800 dark:text-brand-400"></i>
                    <span>{{ __('Operator desk') }}</span>
                </a>
            @endif
            <!-- WhatsApp Chat Pill (Mobile / Tablet Header Only, Desktop uses Floating Button) -->
            @if ($waUrl)
                <a
                    href="{{ $waUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="lg:hidden h-9 px-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs shadow-xs hover:shadow-md transition-all inline-flex items-center gap-1.5 cursor-pointer"
                    title="{{ __('Chat with Operator on WhatsApp') }}"
                >
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                    <span class="hidden sm:inline">{{ __('Chat WhatsApp') }}</span>
                </a>
            @endif

            <!-- Mobile Hamburger Toggle Button -->
            <button
                type="button"
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="md:hidden h-9 w-9 inline-flex items-center justify-center rounded-xl border border-slate-200/80 dark:border-zinc-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-zinc-800 transition cursor-pointer"
                aria-label="Toggle navigation menu"
            >
                <i class="fa-solid text-sm" :class="mobileMenuOpen ? 'fa-xmark' : 'fa-bars'"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Navigation Drawer Dropdown -->
    <div
        x-show="mobileMenuOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        @click.away="mobileMenuOpen = false"
        class="md:hidden border-b border-slate-200/80 dark:border-zinc-800 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl px-4 py-4 space-y-3 shadow-2xl"
    >
        <!-- Nav Links Group -->
        <div class="space-y-1">
            @php
                $drawerActive = 'bg-brand-600 text-brand-foreground font-extrabold';
                $drawerIdle = 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800';
            @endphp
            <a
                href="{{ route('home') }}"
                class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('home') ? $drawerActive : $drawerIdle }}"
            >
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-compass w-4 text-center {{ request()->routeIs('home') ? '' : 'text-brand-800 dark:text-brand-400' }}"></i>
                    <span>{{ __('Catalog Home') }}</span>
                </div>
                <i class="fa-solid fa-chevron-right text-[10px] {{ request()->routeIs('home') ? 'text-brand-foreground/70' : 'text-slate-400' }}"></i>
            </a>

            @if ($packagesCount > 0)
                @php $packagesActive = request()->routeIs('storefront.packages') || request()->routeIs('storefront.package'); @endphp
                <a
                    href="{{ route('storefront.packages') }}"
                    class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ $packagesActive ? $drawerActive : $drawerIdle }}"
                >
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-cubes w-4 text-center {{ $packagesActive ? '' : 'text-brand-800 dark:text-brand-400' }}"></i>
                        <span>{{ __('Tour Packages') }}</span>
                    </div>
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black {{ $packagesActive ? 'bg-brand-foreground/20 text-brand-foreground' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300' }}">
                        {{ $packagesCount }}
                    </span>
                </a>
            @endif

            @if ($productsCount > 0)
                @php $productsActive = request()->routeIs('storefront.products') || request()->routeIs('storefront.product'); @endphp
                <a
                    href="{{ route('storefront.products') }}"
                    class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ $productsActive ? $drawerActive : $drawerIdle }}"
                >
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-compass w-4 text-center {{ $productsActive ? '' : 'text-brand-800 dark:text-brand-400' }}"></i>
                        <span>{{ __('Single Activities') }}</span>
                    </div>
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black {{ $productsActive ? 'bg-brand-foreground/20 text-brand-foreground' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300' }}">
                        {{ $productsCount }}
                    </span>
                </a>
            @endif

            <a
                href="{{ route('storefront.find-booking') }}"
                class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('storefront.find-booking') ? $drawerActive : $drawerIdle }}"
            >
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-ticket w-4 text-center {{ request()->routeIs('storefront.find-booking') ? '' : 'text-brand-800 dark:text-brand-400' }}"></i>
                    <span>{{ __('Find Booking') }}</span>
                </div>
                <i class="fa-solid fa-chevron-right text-[10px] {{ request()->routeIs('storefront.find-booking') ? 'text-brand-foreground/70' : 'text-slate-400' }}"></i>
            </a>

            <a
                href="{{ route('storefront.terms') }}"
                class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('storefront.terms') ? $drawerActive : $drawerIdle }}"
            >
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-shield-halved w-4 text-center {{ request()->routeIs('storefront.terms') ? '' : 'text-brand-800 dark:text-brand-400' }}"></i>
                    <span>{{ __('Terms & Policies') }}</span>
                </div>
                <i class="fa-solid fa-chevron-right text-[10px] {{ request()->routeIs('storefront.terms') ? 'text-brand-foreground/70' : 'text-slate-400' }}"></i>
            </a>

            @if ($agent?->isDemo())
                <a
                    href="{{ $operatorLoginUrl }}"
                    class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 transition"
                >
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-gauge w-4 text-center text-brand-800 dark:text-brand-400"></i>
                        <span>{{ __('Try the operator desk') }}</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                </a>
            @endif
        </div>
    </div>
</header>

@include('storefront.partials.flash')
