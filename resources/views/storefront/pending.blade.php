<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Storefront Under Verification') }} — {{ $agent->displayName }}</title>
    <link rel="icon" href="{{ $agent->logoUrl }}" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --brand-color: {{ $agent->brand_color ?: '#4f46e5' }};
        }
    </style>
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
                    <span class="text-[10px] sm:text-xs text-amber-600 dark:text-amber-400 font-bold uppercase tracking-wider flex items-center gap-1">
                        <i class="fa-solid fa-clock text-[9px]"></i>
                        <span>{{ __('Coming Soon') }}</span>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 my-auto">
        <div class="max-w-md w-full text-center space-y-6 animate-fade-in">
            <!-- Clock Badge Tile -->
            <div class="w-20 h-20 rounded-3xl bg-amber-50 dark:bg-amber-950/70 border border-amber-200/80 dark:border-amber-900/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto text-3xl shadow-sm shadow-amber-500/10">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>

            <!-- Notice Copy -->
            <div class="space-y-2.5">
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ __('Storefront Under Verification') }}
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed max-w-sm mx-auto">
                    {{ __('This operator storefront is undergoing onboarding verification and will be published online shortly.') }}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-center gap-3 pt-2">
                <a
                    href="{{ url('/') }}"
                    class="h-10 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition flex items-center justify-center gap-1.5"
                >
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>{{ __('Platform Home') }}</span>
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 border-t border-slate-200/60 dark:border-zinc-800/60 text-center text-xs text-slate-400">
        <p>&copy; {{ date('Y') }} {{ $agent->displayName }}. {{ __('Powered by Booking Engine.') }}</p>
    </footer>
</body>
</html>
