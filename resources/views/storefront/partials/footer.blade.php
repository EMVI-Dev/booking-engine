{{--
    Storefront Clean Minimal Footer
    Expects: $agent (Operator model)
--}}

@php
    $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $cleanPhone = !empty($agent->contact_whatsapp) ? $waService->normalizePhoneNumber($agent->contact_whatsapp) : null;
    $waMessage = 'Hello ' . ($agent->name ?? 'Tour Operator') . ', I have an inquiry regarding your tour packages.';
    $waUrl = $cleanPhone ? $waService->buildWhatsAppUrl($agent->contact_whatsapp, $waMessage) : null;
@endphp

<footer
    class="mt-auto border-t border-slate-200/80 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md select-none py-8 px-4 text-center">
    <div class="max-w-4xl mx-auto space-y-4 text-xs text-slate-500 dark:text-slate-400">
        <!-- Minimal Links Pill Row -->
        <div class="flex flex-wrap items-center justify-center gap-1.5 text-xs">
            <a href="{{ route('home') }}" class="px-3 py-1.5 rounded-xl hover:text-brand-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-compass text-[11px] text-slate-400"></i>
                <span>{{ __('Catalog') }}</span>
            </a>
            <a href="{{ route('storefront.packages') }}" class="px-3 py-1.5 rounded-xl hover:text-brand-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-cubes text-[11px] text-slate-400"></i>
                <span>{{ __('Tour Packages') }}</span>
            </a>
            <a href="{{ route('storefront.products') }}" class="px-3 py-1.5 rounded-xl hover:text-brand-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-person-swimming text-[11px] text-slate-400"></i>
                <span>{{ __('Activities & Rentals') }}</span>
            </a>
            <a href="{{ route('storefront.terms') }}" class="px-3 py-1.5 rounded-xl hover:text-brand-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-shield-halved text-[11px] text-slate-400"></i>
                <span>{{ __('Terms & Policies') }}</span>
            </a>
            @if ($waUrl)
                <a href="{{ $waUrl }}" target="_blank" rel="noopener"
                    class="px-3 py-1.5 rounded-xl text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/60 transition inline-flex items-center gap-1.5 font-bold">
                    <i class="fa-brands fa-whatsapp text-xs"></i>
                    <span>{{ __('WhatsApp Support') }}</span>
                </a>
            @endif
        </div>

        <!-- Subtle Copyright & Platform Attribution -->
        <div
            class="text-[11px] text-slate-400 dark:text-slate-500 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 pt-2 border-t border-slate-100 dark:border-zinc-800/80">
            <span>&copy; {{ date('Y') }} <strong
                    class="text-slate-700 dark:text-slate-300 font-bold">{{ $agent->name }}</strong></span>
            <span>&bull;</span>
            <span>{{ __('Powered by') }}
                <a href="https://{{ $platformDomain }}" target="_blank" rel="noopener"
                    class="text-slate-700 dark:text-slate-300 font-bold hover:text-brand-600 dark:hover:text-brand-400 hover:underline inline-flex items-center gap-1">
                    <i class="fa-solid fa-compass text-brand-600 dark:text-brand-400 text-[10px]"></i>
                    {{ config('app.name', 'TravelEngine') }}
                </a>
            </span>
        </div>
    </div>
</footer>

@include('storefront.partials.floating-whatsapp')
