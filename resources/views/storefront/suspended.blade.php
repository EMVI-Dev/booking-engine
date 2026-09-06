<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Storefront Suspended') }} — {{ $agent->displayName }}</title>
    <link rel="icon" href="{{ $agent->logoUrl }}" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('storefront.partials.brand-theme')
</head>
<body class="h-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-200 font-sans antialiased flex flex-col justify-between">
    <!-- Top Branded Header -->
    <header class="w-full border-b border-slate-200/80 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 sm:h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 flex items-center justify-center overflow-hidden shrink-0">
                    <img src="{{ $agent->logoUrl }}" alt="{{ $agent->name }}" class="w-full h-full object-cover" />
                </div>
                <div>
                    <h1 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white leading-tight">
                        {{ $agent->displayName }}
                    </h1>
                    <span class="text-[10px] sm:text-xs text-rose-600 dark:text-rose-400 font-bold uppercase tracking-wider flex items-center gap-1">
                        <i class="fa-solid fa-ban text-[9px]"></i>
                        <span>{{ __('Storefront Offline') }}</span>
                    </span>
                </div>
            </div>

            @if ($agent->contact_whatsapp)
                <a
                    href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $agent->contact_whatsapp) }}"
                    target="_blank"
                    class="h-9 px-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold text-xs transition flex items-center gap-2 border border-emerald-200/60 dark:border-emerald-800/60"
                >
                    <i class="fa-brands fa-whatsapp text-sm text-emerald-600 dark:text-emerald-400"></i>
                    <span class="hidden sm:inline">{{ __('Contact Support') }}</span>
                </a>
            @endif
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 my-auto">
        <div class="max-w-md w-full text-center space-y-6 animate-fade-in">
            <!-- Warning Badge Tile -->
            <div class="w-20 h-20 rounded-3xl bg-rose-50 dark:bg-rose-950/70 border border-rose-200/80 dark:border-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-3xl shadow-sm shadow-rose-500/10">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <!-- Notice Copy -->
            <div class="space-y-2.5">
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ __('Storefront Temporarily Suspended') }}
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed max-w-sm mx-auto">
                    {{ __('This operator storefront is currently paused and cannot accept new online bookings or departure requests at this time.') }}
                </p>
            </div>

            <!-- Existing Bookings Notice Box -->
            <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs text-left space-y-2">
                <div class="flex items-center gap-2 text-slate-900 dark:text-white font-bold text-xs">
                    <i class="fa-solid fa-circle-info text-indigo-500"></i>
                    <span>{{ __('Have an existing booking?') }}</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-normal">
                    {{ __('Existing confirmed reservations remain recorded. Please reach out directly to the operator via their verified WhatsApp line for trip coordination.') }}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                @if ($agent->contact_whatsapp)
                    <a
                        href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $agent->contact_whatsapp) }}"
                        target="_blank"
                        class="w-full sm:w-auto h-10 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2"
                    >
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>{{ __('Message on WhatsApp') }}</span>
                    </a>
                @endif

                <a
                    href="{{ url('/') }}"
                    class="w-full sm:w-auto h-10 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition flex items-center justify-center gap-1.5"
                >
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>{{ __('Platform Home') }}</span>
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 border-t border-slate-200/60 dark:border-zinc-800/60 text-center text-xs text-slate-400">
        <p>&copy; {{ date('Y') }} {{ $agent->displayName }}@if ($agent->showsPlatformBranding()). {{ __('Powered by Booking Engine.') }}@endif</p>
    </footer>
</body>
</html>
