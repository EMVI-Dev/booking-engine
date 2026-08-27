<div class="space-y-4">
    <!-- Storefront Settings Navigation Tab Bar -->
    <div class="flex items-center gap-1.5 p-1.5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-x-auto select-none">
        <a
            href="{{ route('brand.edit') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('brand.edit') ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-paintbrush text-xs {{ request()->routeIs('brand.edit') ? 'text-white' : 'text-indigo-500' }}"></i>
            <span>{{ __('Brand & Identity') }}</span>
        </a>

        <a
            href="{{ route('storefront-settings.edit') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('storefront-settings.edit') ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-store text-xs {{ request()->routeIs('storefront-settings.edit') ? 'text-white' : 'text-indigo-500' }}"></i>
            <span>{{ __('Storefront & Policies') }}</span>
        </a>

        <a
            href="{{ route('payments.edit') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('payments.edit') ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-credit-card text-xs {{ request()->routeIs('payments.edit') ? 'text-white' : 'text-indigo-500' }}"></i>
            <span>{{ __('Payment Gateways') }}</span>
        </a>
    </div>
</div>
