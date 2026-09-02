@php
    /** @var \App\Models\Operator $agent */
    $agent = $agent ?? ($operator ?? null);
    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $cleanPhone = !empty($agent?->contact_whatsapp) ? $waService->normalizePhoneNumber($agent->contact_whatsapp) : null;
    $waMessage = "Hello {$agent?->name}, I am browsing your tour catalog and have an inquiry.";
    $waUrl = $cleanPhone ? $waService->buildWhatsAppUrl($agent->contact_whatsapp, $waMessage) : null;

    $packagesCount = $agent ? $agent->packages()->where('status', \App\Enums\ListingStatus::Published)->count() : 0;
    $productsCount = $agent ? $agent->products()->where('status', \App\Enums\ListingStatus::Published)->where('sellable_standalone', true)->count() : 0;
@endphp

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
                <span class="font-black text-sm sm:text-base tracking-tight text-slate-900 dark:text-white truncate group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
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
            <!-- Home / Catalog -->
            <a
                href="{{ route('home') }}"
                class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('home') ? 'bg-white dark:bg-zinc-900 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-zinc-900/50' }}"
            >
                <i class="fa-solid fa-compass text-[11px]"></i>
                <span>{{ __('Catalog') }}</span>
            </a>

            <!-- Tour Packages -->
            <a
                href="{{ route('storefront.packages') }}"
                class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('storefront.packages') || request()->routeIs('storefront.package') ? 'bg-white dark:bg-zinc-900 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-zinc-900/50' }}"
            >
                <i class="fa-solid fa-cubes text-[11px]"></i>
                <span>{{ __('Tour Packages') }}</span>
                @if ($packagesCount > 0)
                    <span class="px-1.5 py-0.2 rounded-md text-[10px] font-black {{ request()->routeIs('storefront.packages') || request()->routeIs('storefront.package') ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300' : 'bg-slate-200/80 dark:bg-zinc-700 text-slate-600 dark:text-slate-300' }}">
                        {{ $packagesCount }}
                    </span>
                @endif
            </a>

            <!-- Single Activities -->
            <a
                href="{{ route('storefront.products') }}"
                class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('storefront.products') || request()->routeIs('storefront.product') ? 'bg-white dark:bg-zinc-900 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-zinc-900/50' }}"
            >
                <i class="fa-solid fa-compass text-[11px]"></i>
                <span>{{ __('Single Activities') }}</span>
                @if ($productsCount > 0)
                    <span class="px-1.5 py-0.2 rounded-md text-[10px] font-black {{ request()->routeIs('storefront.products') || request()->routeIs('storefront.product') ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300' : 'bg-slate-200/80 dark:bg-zinc-700 text-slate-600 dark:text-slate-300' }}">
                        {{ $productsCount }}
                    </span>
                @endif
            </a>

            <!-- Terms -->
            <a
                href="{{ route('storefront.terms') }}"
                class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 {{ request()->routeIs('storefront.terms') ? 'bg-white dark:bg-zinc-900 text-brand-600 dark:text-brand-400 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-zinc-900/50' }}"
            >
                <i class="fa-solid fa-shield-halved text-[11px]"></i>
                <span>{{ __('Terms & Policies') }}</span>
            </a>
        </nav>

        <!-- Right Header Actions (WhatsApp & Mobile Toggle) -->
        <div class="flex items-center gap-2.5">
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

            <!-- Optional Mobile Booking Action Button (for package/product view) -->
            @if ($bookAction ?? false)
                <button
                    type="button"
                    @click="mobileBookingOpen = true"
                    class="lg:hidden h-9 px-3.5 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                >
                    <i class="fa-solid fa-calendar-check text-[11px]"></i>
                    <span>{{ __('Book Now') }}</span>
                </button>
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
            <a
                href="{{ route('home') }}"
                class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('home') ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/70 dark:text-brand-300 font-extrabold' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800' }}"
            >
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-compass w-4 text-center text-brand-600 dark:text-brand-400"></i>
                    <span>{{ __('Catalog Home') }}</span>
                </div>
                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
            </a>

            <a
                href="{{ route('storefront.packages') }}"
                class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('storefront.packages') || request()->routeIs('storefront.package') ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/70 dark:text-brand-300 font-extrabold' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800' }}"
            >
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-cubes w-4 text-center text-brand-600 dark:text-brand-400"></i>
                    <span>{{ __('Tour Packages') }}</span>
                </div>
                @if ($packagesCount > 0)
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300">
                        {{ $packagesCount }}
                    </span>
                @endif
            </a>

            <a
                href="{{ route('storefront.products') }}"
                class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('storefront.products') || request()->routeIs('storefront.product') ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/70 dark:text-brand-300 font-extrabold' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800' }}"
            >
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-compass w-4 text-center text-brand-600 dark:text-brand-400"></i>
                    <span>{{ __('Single Activities') }}</span>
                </div>
                @if ($productsCount > 0)
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300">
                        {{ $productsCount }}
                    </span>
                @endif
            </a>

            <a
                href="{{ route('storefront.terms') }}"
                class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('storefront.terms') ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/70 dark:text-brand-300 font-extrabold' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800' }}"
            >
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-shield-halved w-4 text-center text-brand-600 dark:text-brand-400"></i>
                    <span>{{ __('Terms & Policies') }}</span>
                </div>
                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
            </a>
        </div>

        <!-- WhatsApp Direct Contact Banner in Mobile Drawer -->
        @if ($waUrl)
            <div class="pt-2 border-t border-slate-100 dark:border-zinc-800">
                <a
                    href="{{ $waUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="w-full h-11 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-extrabold text-xs shadow-xs transition flex items-center justify-center gap-2"
                >
                    <i class="fa-brands fa-whatsapp text-base"></i>
                    <span>{{ __('Chat with :operator on WhatsApp', ['operator' => $agent?->name ?? 'Us']) }}</span>
                </a>
            </div>
        @endif
    </div>
</header>
