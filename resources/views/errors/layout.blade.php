<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>@yield('title', __('Error')) - {{ config('app.name', 'TravelEngine') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Automated System Dark / Light Theme Sync -->
    <script>
        (function () {
            function applyTheme() {
                var stored = localStorage.getItem('theme');
                if (stored === 'light') {
                    document.documentElement.classList.remove('dark');
                } else if (stored === 'system') {
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } else {
                    document.documentElement.classList.add('dark');
                }
            }
            applyTheme();
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                    var stored = localStorage.getItem('theme');
                    if (!stored || stored === 'system') {
                        applyTheme();
                    }
                });
            }
        })();
    </script>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-[#FFEF4D] selection:text-[#101730] transition-colors duration-200">
    <div class="flex min-h-svh flex-col items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-lg space-y-6">
            <!-- Platform Brand Header -->
            <a href="{{ url('/') }}" class="flex items-center justify-center gap-3 font-semibold group">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FFEF4D] text-[#12181E] shadow-md text-lg group-hover:scale-105 transition-transform duration-200 font-black">
                    <i class="fa-solid fa-compass"></i>
                </span>
                <div class="flex flex-col text-start">
                    <span class="text-base font-bold tracking-tight text-slate-900 dark:text-white">{{ config('app.name', 'TravelEngine') }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-normal">{{ __('For tour operators') }}</span>
                </div>
            </a>

            <!-- Error Container Card -->
            <div class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 dark:shadow-black/40 border border-slate-200/80 dark:border-zinc-800 text-center space-y-6">
                <!-- Status Badge -->
                <div class="flex justify-center">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#FFEF4D]/15 text-amber-900 dark:bg-[#FFEF4D]/10 dark:text-[#FFEF4D] border border-[#FFEF4D]/40 dark:border-[#FFEF4D]/30">
                        <i class="@yield('icon', 'fa-solid fa-circle-exclamation') text-xs"></i>
                        <span>@yield('code', 'Error')</span>
                    </span>
                </div>

                <!-- Icon Illustration Circle -->
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-[#FFEF4D]/15 text-[#8a7808] dark:bg-[#FFEF4D]/10 dark:text-[#FFEF4D] border border-[#FFEF4D]/40 dark:border-[#FFEF4D]/30 text-3xl">
                    <i class="@yield('icon', 'fa-solid fa-circle-exclamation')"></i>
                </div>

                <!-- Content -->
                <div class="space-y-2">
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                        @yield('heading', __('Something went wrong'))
                    </h1>
                    <p class="text-sm sm:text-base text-slate-600 dark:text-slate-400 leading-relaxed max-w-sm mx-auto">
                        @yield('message', __('An unexpected error occurred. Please try again later.'))
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    @hasSection('actions')
                        @yield('actions')
                    @else
                        <a href="{{ url('/') }}"
                           class="w-full sm:w-auto inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-sm font-bold transition duration-150 shadow-sm">
                            <i class="fa-solid fa-house text-xs"></i>
                            {{ __('Back home') }}
                        </a>
                        <button type="button"
                                onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ url('/') }}'"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-slate-300 text-sm font-semibold transition duration-150">
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                            {{ __('Go back') }}
                        </button>
                    @endif
                </div>
            </div>

            <!-- Footer note -->
            <div class="text-center">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Need assistance?') }}
                    <a href="{{ url('/') }}" class="font-medium text-slate-700 dark:text-slate-300 hover:underline">
                        {{ __('Return to overview') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
