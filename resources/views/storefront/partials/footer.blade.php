{{--
    Storefront Shared Footer
    Expects: $agent (Agent model)
--}}

<!-- Footer -->
<footer
    class="mt-auto border-t border-slate-200/80 dark:border-zinc-800 bg-white/70 dark:bg-zinc-900/70 backdrop-blur-md">
    <!-- Platform Ad Row -->
    <div class="border-b border-slate-100 dark:border-zinc-800 py-5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 text-[11px] font-black uppercase tracking-wider border border-indigo-100 dark:border-indigo-900">
                    <i class="fa-solid fa-compass text-[10px]"></i>
                    {{ __('Powered by') }}
                </span>
                <div>
                    <span
                        class="font-black text-sm text-slate-900 dark:text-white tracking-tight">{{ config('app.name') }}</span>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-none mt-0.5">
                        {{ __('The direct booking engine for tour operators') }}</p>
                </div>
            </div>
            <a href="{{ route('register') }}"
                class="inline-flex items-center gap-2 h-9 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm shadow-indigo-500/30 transition-all hover:scale-105 shrink-0">
                <i class="fa-solid fa-store text-[11px]"></i>
                {{ __('Create Your Free Storefront') }}
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>

    <!-- Copyright & Nav Row -->
    <div class="py-5">
        <div
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400">
            <p>&copy; {{ date('Y') }} {{ $agent->name }}. {{ __('All rights reserved.') }}</p>
            <div class="flex items-center gap-4 text-[11px]">
                <a href="{{ route('home') }}" class="hover:text-brand-600 transition-colors">{{ __('Home') }}</a>
                <span>&bull;</span>
                <a href="{{ route('storefront.packages') }}"
                    class="hover:text-brand-600 transition-colors">{{ __('All Packages') }}</a>
                <span>&bull;</span>
                <a href="{{ route('storefront.products') }}"
                    class="hover:text-brand-600 transition-colors">{{ __('Activities & Rentals') }}</a>
                <span>&bull;</span>
                <a href="{{ route('storefront.terms') }}"
                    class="hover:text-brand-600 transition-colors">{{ __('Terms & Policies') }}</a>
            </div>
        </div>
    </div>
</footer>
