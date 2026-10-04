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
        $waMessage = __('Hello :name, I would like to ask about your tours.', ['name' => $agent->name]);
        $waUrl = $cleanPhone ? $waService->buildWhatsAppUrl($agent->contact_whatsapp, $waMessage) : null;
        $socialLinks = $agent->getSocialLinks();
    @endphp
    @include('storefront.partials.seo', [
        'agent' => $agent,
        'title' => __('Contact & Inquiries').' · '.$agent->name,
        'description' => __('Send an inquiry or contact :name directly regarding tours, custom trips, and private charters.', ['name' => $agent->name]),
        'url' => route('storefront.contact'),
        'type' => 'website',
        'share' => $share,
        'schema' => $seo->contactGraph($agent),
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
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">
        <!-- Breadcrumb & Header Hero -->
        <div class="space-y-3">
            <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                <a href="{{ route('home') }}" class="hover:text-brand-800 dark:hover:text-brand-400">{{ __('Home') }}</a>
                <span>&rsaquo;</span>
                <span class="text-slate-900 dark:text-white font-bold">{{ __('Contact') }}</span>
            </nav>

            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-800 dark:text-brand-400 mb-1">
                        <i class="fa-solid fa-envelope-open-text"></i>
                        <span>{{ __('Get in Touch') }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __('Contact & Group Inquiries') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                        {{ __('Have a question, need a custom itinerary, or planning a private charter? Send a message or chat with us directly.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 2-Column Contact Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left Column: Form or WhatsApp CTA -->
            <div class="lg:col-span-7 xl:col-span-8 space-y-6">
                @if ($isOpen)
                    <div class="space-y-4">
                        <livewire:storefront.contact-form :operator="$agent" :key="'contact-page-'.$agent->id" />
                    </div>
                @else
                    <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
                        <div class="space-y-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>{{ __('Fastest Response on WhatsApp') }}</span>
                            </span>
                            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white">
                                {{ __('Chat directly with our team') }}
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                                {{ __('We are always available on WhatsApp for quick questions, customized tour recommendations, private group charters, and live bookings.') }}
                            </p>
                        </div>

                        @if ($waUrl)
                            <div class="pt-2">
                                <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer"
                                    class="h-12 px-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-sm transition shadow-md hover:shadow-lg">
                                    <i class="fa-brands fa-whatsapp text-lg"></i>
                                    <span>{{ __('Open WhatsApp Chat') }}</span>
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Right Column: Operator Channels Card -->
            <div class="lg:col-span-5 xl:col-span-4 space-y-5">
                <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-6">
                    <div class="flex items-center gap-3.5 pb-4 border-b border-slate-100 dark:border-zinc-800">
                        @if ($agent->logo_url)
                            <img src="{{ $agent->logo_url }}" alt="{{ $agent->name }}"
                                class="w-12 h-12 rounded-2xl object-contain border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1 shrink-0" />
                        @else
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-lg shrink-0 shadow-xs"
                                style="background-color: {{ $agent->brand_color }}; color: {{ $agent->brand_foreground_color }};">
                                {{ strtoupper(substr($agent->name, 0, 1)) }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white truncate">
                                {{ $agent->name }}
                            </h3>
                            <div class="flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-bold leading-none mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>{{ __('Verified Operator') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <div class="space-y-4 text-xs">
                        @if ($waUrl)
                            <div class="flex items-start gap-3">
                                <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                </span>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('WhatsApp') }}</span>
                                    <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer"
                                        class="font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition">
                                        {{ $agent->contact_whatsapp }}
                                    </a>
                                </div>
                            </div>
                        @endif

                        @if ($agent->location)
                            <div class="flex items-start gap-3">
                                <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-location-dot text-sm"></i>
                                </span>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Location') }}</span>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $agent->location }}</span>
                                </div>
                            </div>
                        @endif

                    </div>

                    <!-- Social Channels -->
                    @if (! empty($socialLinks['instagram']) || ! empty($socialLinks['facebook']) || ! empty($socialLinks['tiktok']) || ! empty($socialLinks['youtube']))
                        <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 space-y-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">{{ __('Follow Us') }}</span>
                            <div class="flex flex-wrap items-center gap-2">
                                @if (!empty($socialLinks['instagram']))
                                    <a href="{{ $socialLinks['instagram'] }}" target="_blank" rel="noopener noreferrer"
                                        class="h-8 px-3 rounded-xl bg-slate-50 dark:bg-zinc-800 text-pink-600 dark:text-pink-400 font-bold text-xs inline-flex items-center gap-1.5 hover:bg-slate-100 dark:hover:bg-zinc-700 transition">
                                        <i class="fa-brands fa-instagram text-sm"></i>
                                        <span>{{ __('Instagram') }}</span>
                                    </a>
                                @endif
                                @if (!empty($socialLinks['facebook']))
                                    <a href="{{ $socialLinks['facebook'] }}" target="_blank" rel="noopener noreferrer"
                                        class="h-8 px-3 rounded-xl bg-slate-50 dark:bg-zinc-800 text-blue-600 dark:text-blue-400 font-bold text-xs inline-flex items-center gap-1.5 hover:bg-slate-100 dark:hover:bg-zinc-700 transition">
                                        <i class="fa-brands fa-facebook text-sm"></i>
                                        <span>{{ __('Facebook') }}</span>
                                    </a>
                                @endif
                                @if (!empty($socialLinks['tiktok']))
                                    <a href="{{ $socialLinks['tiktok'] }}" target="_blank" rel="noopener noreferrer"
                                        class="h-8 px-3 rounded-xl bg-slate-50 dark:bg-zinc-800 text-slate-900 dark:text-white font-bold text-xs inline-flex items-center gap-1.5 hover:bg-slate-100 dark:hover:bg-zinc-700 transition">
                                        <i class="fa-brands fa-tiktok text-sm"></i>
                                        <span>{{ __('TikTok') }}</span>
                                    </a>
                                @endif
                                @if (!empty($socialLinks['youtube']))
                                    <a href="{{ $socialLinks['youtube'] }}" target="_blank" rel="noopener noreferrer"
                                        class="h-8 px-3 rounded-xl bg-slate-50 dark:bg-zinc-800 text-red-600 dark:text-red-400 font-bold text-xs inline-flex items-center gap-1.5 hover:bg-slate-100 dark:hover:bg-zinc-700 transition">
                                        <i class="fa-brands fa-youtube text-sm"></i>
                                        <span>{{ __('YouTube') }}</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <!-- FAQ Quick Jump Card -->
                <div class="p-5 rounded-3xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/60 dark:border-zinc-800 flex items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <span class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Common Questions') }}</span>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Check our FAQ for answers about payments, tickets and cancellations.') }}</p>
                    </div>
                    <a href="{{ route('storefront.faq') }}"
                        class="h-8 px-3.5 rounded-xl bg-white dark:bg-zinc-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-zinc-700 font-bold text-xs inline-flex items-center gap-1 hover:bg-slate-100 dark:hover:bg-zinc-700 transition shrink-0 shadow-xs">
                        <span>{{ __('FAQ') }}</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>

    @include('storefront.partials.footer')
    @livewireScripts
</body>

</html>
