<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />

    @php
        $seo = app(\App\Services\StorefrontSeoService::class);
        $share = $seo->shareImage($agent);
        $waService = app(\App\Services\WhatsAppDispatchService::class);
        $cleanPhone = !empty($agent->contact_whatsapp) ? $waService->normalizePhoneNumber($agent->contact_whatsapp) : null;
        $waMessage = __('Hello :name, I have a question about your tours.', ['name' => $agent->name]);
        $waUrl = $cleanPhone ? $waService->buildWhatsAppUrl($agent->contact_whatsapp, $waMessage) : null;
    @endphp
    @include('storefront.partials.seo', [
        'agent' => $agent,
        'title' => __('Frequently Asked Questions').' · '.$agent->name,
        'description' => __('Helpful answers regarding bookings, payments, and tour policies for :name.', ['name' => $agent->name]),
        'url' => route('storefront.faq'),
        'type' => 'website',
        'share' => $share,
        'schema' => $seo->faqGraph($agent),
    ])

    @include('storefront.partials.brand-theme')

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('storefront.partials.tracking-scripts', ['agent' => $agent])
    @livewireStyles
</head>

<body x-data="{ mobileMenuOpen: false }"
    class="min-h-screen flex flex-col sf-canvas text-slate-900 dark:text-slate-100 antialiased selection:bg-brand-600 selection:text-brand-foreground overflow-x-clip w-full max-w-full">
    @include('storefront.partials.navbar')

    <!-- Main Content Area -->
    <main class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">
        <!-- Breadcrumb & Header Hero -->
        <div class="space-y-3">
            <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                <a href="{{ route('home') }}" class="hover:text-brand-800 dark:hover:text-brand-400">{{ __('Home') }}</a>
                <span>&rsaquo;</span>
                <span class="text-slate-900 dark:text-white font-bold">{{ __('FAQ') }}</span>
            </nav>

            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-800 dark:text-brand-400 mb-1">
                        <i class="fa-solid fa-circle-question"></i>
                        <span>{{ __('Help & Questions') }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __('Frequently Asked Questions') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                        {{ __('Helpful answers regarding bookings, payments, cancellation, and tour policies for :name.', ['name' => $agent->name]) }}
                    </p>
                </div>
                @if (count($faqItems) > 0)
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 text-xs font-bold text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-zinc-700/60 self-start sm:self-auto shrink-0 shadow-xs">
                        <i class="fa-solid fa-circle-question text-brand-700 dark:text-brand-400 text-xs"></i>
                        <span>{{ __(':count Questions', ['count' => count($faqItems)]) }}</span>
                    </span>
                @endif
            </div>
        </div>

        @if (count($faqItems) > 0)
            {{-- Accessible Accordion --}}
            <div id="faq" class="rounded-2xl sm:rounded-3xl border border-slate-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs divide-y divide-slate-100 dark:divide-zinc-800/80 overflow-hidden">
                @foreach ($faqItems as $item)
                    <details class="group transition-colors open:bg-slate-50/60 dark:open:bg-zinc-800/25">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6 text-sm sm:text-base font-bold text-slate-900 dark:text-white select-none focus:outline-none">
                            <span>{{ $item['question'] }}</span>
                            <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-400 group-open:text-brand-700 dark:group-open:text-brand-300 group-open:bg-brand-50 dark:group-open:bg-brand-950/60 transition-all shrink-0">
                                <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 group-open:rotate-180" aria-hidden="true"></i>
                            </span>
                        </summary>
                        <div class="px-5 sm:px-6 pb-5 sm:pb-6 pt-0 text-xs sm:text-sm leading-relaxed text-slate-600 dark:text-slate-300 whitespace-pre-line">
                            <p>{{ $item['answer'] }}</p>
                        </div>
                    </details>
                @endforeach
            </div>
        @else
            <div class="p-8 sm:p-12 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 text-center space-y-4 max-w-xl mx-auto shadow-xs">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-slate-500 mx-auto flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Questions & Answers') }}</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('We are currently updating our FAQ. Feel free to contact us with any questions!') }}
                    </p>
                </div>
            </div>
        @endif

        {{-- Still Have Questions Help Card --}}
        <div class="p-6 sm:p-8 rounded-3xl bg-gradient-to-br from-slate-900 via-brand-950 to-slate-900 border border-slate-800/80 text-white shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div class="space-y-1.5 max-w-xl">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-white/10 text-brand-200 border border-white/15">
                    <i class="fa-solid fa-headset text-xs"></i>
                    {{ __('Direct Support') }}
                </span>
                <h3 class="text-lg sm:text-xl font-bold text-white">{{ __('Still have questions?') }}</h3>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                    {{ __('Cannot find the answer you are looking for? Reach out directly to :name on WhatsApp or send us an inquiry.', ['name' => $agent->name]) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                @if ($waUrl)
                    <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer"
                        class="h-10 px-5 inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs transition shadow-md">
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>{{ __('Chat on WhatsApp') }}</span>
                    </a>
                @endif
                @if (app(\App\Services\StorefrontPagesService::class)->has($agent, 'contact'))
                    <a href="{{ route('storefront.contact') }}"
                        class="h-10 px-4 inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/15 font-bold text-xs transition backdrop-blur-md">
                        <i class="fa-solid fa-envelope-open-text text-xs"></i>
                        <span>{{ __('Send Inquiry') }}</span>
                    </a>
                @endif
            </div>
        </div>
    </main>

    @include('storefront.partials.footer')
    @livewireScripts
</body>

</html>
