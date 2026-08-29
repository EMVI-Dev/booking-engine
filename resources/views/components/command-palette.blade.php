@props(['storefrontUrl' => '#'])

<div
    x-show="commandPaletteOpen"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="commandPaletteOpen = false"
    class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm flex justify-center items-start select-none"
>
    <div
        @click.outside="commandPaletteOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-4"
        class="w-full max-w-xl rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl overflow-hidden flex flex-col my-auto sm:my-8"
        x-data="{
            search: '',
            selectedIndex: 0,
            items: [
                { title: '{{ __('Create Booking & Payment Link') }}', cat: '{{ __('Quick Actions') }}', icon: 'fa-solid fa-plus text-indigo-500', action: 'create-link' },
                { title: '{{ __('Open Live Storefront') }}', cat: '{{ __('Quick Actions') }}', icon: 'fa-solid fa-store text-emerald-500', url: '{{ $storefrontUrl }}', external: true },
                { title: '{{ __('Dashboard Overview') }}', cat: '{{ __('Overview & Schedule') }}', icon: 'fa-solid fa-gauge-high text-indigo-500', url: '{{ route('dashboard') }}' },
                { title: '{{ __('Bookings & Reservations') }}', cat: '{{ __('Overview & Schedule') }}', icon: 'fa-solid fa-calendar-check text-indigo-500', url: '{{ route('reservations.index') }}' },
                { title: '{{ __('Calendar & Departures') }}', cat: '{{ __('Overview & Schedule') }}', icon: 'fa-solid fa-calendar-days text-indigo-500', url: '{{ route('calendar.index') }}' },
                { title: '{{ __('Wallet & Payout Settlements') }}', cat: '{{ __('Revenue & Customers') }}', icon: 'fa-solid fa-wallet text-emerald-500', url: '{{ route('wallet.index') }}' },
                { title: '{{ __('Guest CRM Directory') }}', cat: '{{ __('Revenue & Customers') }}', icon: 'fa-solid fa-address-book text-sky-500', url: '{{ route('guests.index') }}' },
                { title: '{{ __('Guest Reviews & Feedback') }}', cat: '{{ __('Revenue & Customers') }}', icon: 'fa-solid fa-star text-amber-500', url: '{{ route('reviews.index') }}' },
                { title: '{{ __('Tours & Package Listings') }}', cat: '{{ __('Storefront & Catalog') }}', icon: 'fa-solid fa-cubes text-indigo-500', url: '{{ route('packages.index') }}' },
                { title: '{{ __('Coupons & Promo Codes') }}', cat: '{{ __('Storefront & Catalog') }}', icon: 'fa-solid fa-ticket text-purple-500', url: '{{ route('coupons.index') }}' },
                { title: '{{ __('Brand Logo & Custom Theme') }}', cat: '{{ __('Storefront & Catalog') }}', icon: 'fa-solid fa-palette text-indigo-500', url: '{{ route('brand.edit') }}' },
                { title: '{{ __('Storefront & Policy Setup') }}', cat: '{{ __('Storefront & Catalog') }}', icon: 'fa-solid fa-sliders text-indigo-500', url: '{{ route('storefront-settings.edit') }}' },
                { title: '{{ __('Merchant Payment Gateways') }}', cat: '{{ __('Storefront & Catalog') }}', icon: 'fa-solid fa-credit-card text-indigo-500', url: '{{ route('payments.edit') }}' },
                { title: '{{ __('Subscription Plans & Upgrades') }}', cat: '{{ __('Account & Billing') }}', icon: 'fa-solid fa-crown text-purple-500', url: '{{ route('settings.plan') }}' },
                { title: '{{ __('Billing Details & Invoices') }}', cat: '{{ __('Account & Billing') }}', icon: 'fa-solid fa-file-invoice-dollar text-purple-500', url: '{{ route('settings.billing') }}' },
                { title: '{{ __('Profile & Account Details') }}', cat: '{{ __('Account & Settings') }}', icon: 'fa-solid fa-user-gear text-slate-500', url: '{{ route('profile.edit') }}' },
                { title: '{{ __('Security & Passkeys') }}', cat: '{{ __('Account & Settings') }}', icon: 'fa-solid fa-shield-halved text-slate-500', url: '{{ route('security.edit') }}' },
                { title: '{{ __('Appearance & Theme Mode') }}', cat: '{{ __('Account & Settings') }}', icon: 'fa-solid fa-circle-half-stroke text-slate-500', url: '{{ route('appearance.edit') }}' }
            ],
            get filteredItems() {
                if (!this.search.trim()) return this.items;
                const q = this.search.toLowerCase();
                return this.items.filter(i => i.title.toLowerCase().includes(q) || i.cat.toLowerCase().includes(q));
            },
            execute(item) {
                commandPaletteOpen = false;
                if (item.action === 'create-link') {
                    $dispatch('open-create-booking-link');
                } else if (item.external) {
                    window.open(item.url, '_blank');
                } else if (item.url) {
                    Livewire.navigate(item.url);
                }
            }
        }"
    >
        <!-- Search Input Bar Header -->
        <div class="relative flex items-center px-4 border-b border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/50">
            <i class="fa-solid fa-magnifying-glass text-slate-400 dark:text-slate-500 text-sm ml-1"></i>
            <input
                x-ref="searchInput"
                x-effect="if (commandPaletteOpen) { setTimeout(() => $refs.searchInput.focus(), 50); search = ''; selectedIndex = 0; }"
                x-model="search"
                @keydown.arrow-down.prevent="selectedIndex = Math.min(selectedIndex + 1, filteredItems.length - 1)"
                @keydown.arrow-up.prevent="selectedIndex = Math.max(selectedIndex - 1, 0)"
                @keydown.enter.prevent="if (filteredItems[selectedIndex]) execute(filteredItems[selectedIndex])"
                type="text"
                placeholder="{{ __('Type a command, page, or search feature... (ESC to close)') }}"
                class="w-full h-14 pl-3 pr-4 bg-transparent text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 border-none focus:outline-none focus:ring-0"
            />
            <button
                type="button"
                @click="commandPaletteOpen = false"
                class="px-2 py-1 text-[10px] font-mono font-bold text-slate-400 hover:text-slate-700 dark:hover:text-white rounded-lg bg-slate-200/70 dark:bg-zinc-800 transition cursor-pointer"
            >
                ESC
            </button>
        </div>

        <!-- Filtered Items Results List -->
        <div class="max-h-96 overflow-y-auto p-2 space-y-1">
            <template x-if="filteredItems.length === 0">
                <div class="p-8 text-center text-xs text-slate-400 dark:text-slate-500 space-y-1">
                    <i class="fa-solid fa-compass-slash text-2xl text-slate-300 dark:text-zinc-700 block mb-2"></i>
                    <p class="font-bold">{{ __('No matching portal commands found') }}</p>
                    <p class="text-[11px]">{{ __('Try searching for "bookings", "billing", "wallet", or "brand"') }}</p>
                </div>
            </template>

            <template x-for="(item, index) in filteredItems" :key="index">
                <div
                    @click="execute(item)"
                    @mouseenter="selectedIndex = index"
                    :class="selectedIndex === index ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800/60'"
                    class="p-3 rounded-2xl flex items-center justify-between transition-colors duration-150 cursor-pointer select-none"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span
                            :class="selectedIndex === index ? 'bg-indigo-100 dark:bg-indigo-900/60' : 'bg-slate-100 dark:bg-zinc-800'"
                            class="p-2 rounded-xl text-xs flex items-center justify-center shrink-0 w-8 h-8"
                        >
                            <i :class="item.icon"></i>
                        </span>
                        <div class="min-w-0">
                            <span class="font-extrabold text-xs block truncate" x-text="item.title"></span>
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium block truncate" x-text="item.cat"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <template x-if="selectedIndex === index">
                            <span class="text-[10px] font-mono font-bold text-indigo-500 flex items-center gap-1">
                                <span>{{ __('Jump to') }}</span>
                                <i class="fa-solid fa-turn-down-left text-[9px]"></i>
                            </span>
                        </template>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-300 dark:text-zinc-700"></i>
                    </div>
                </div>
            </template>
        </div>

        <!-- Command Palette Footer Hints -->
        <div class="px-4 py-3 border-t border-slate-100 dark:border-zinc-800/80 bg-slate-50/70 dark:bg-zinc-900/70 flex items-center justify-between text-[11px] text-slate-400 dark:text-slate-500 font-medium">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-[9px] font-mono bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded">↑</kbd>
                    <kbd class="px-1.5 py-0.5 text-[9px] font-mono bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded">↓</kbd>
                    <span>{{ __('Navigate') }}</span>
                </span>
                <span class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-[9px] font-mono bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded">↵</kbd>
                    <span>{{ __('Select') }}</span>
                </span>
            </div>
            <span class="flex items-center gap-1 text-[10px]">
                <i class="fa-solid fa-bolt text-amber-500"></i>
                <span>{{ __('Operator Quick Jump') }}</span>
            </span>
        </div>
    </div>
</div>
