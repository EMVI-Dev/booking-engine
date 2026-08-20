<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-indigo-500 selection:text-white transition-colors duration-200">
        <!-- Background Ambient Glow -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
            <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[700px] h-[500px] bg-gradient-to-b from-indigo-500/10 via-sky-500/5 to-transparent rounded-full blur-3xl dark:from-indigo-600/15 dark:via-sky-500/5"></div>
        </div>

        <div class="flex min-h-svh flex-col items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-lg space-y-6">
                <!-- Platform Brand Header -->
                <a href="{{ route('home') }}" class="flex items-center justify-center gap-3 font-semibold group" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-md font-black text-lg group-hover:scale-105 transition-transform duration-200">
                        B
                    </span>
                    <div class="flex flex-col text-start">
                        <span class="text-base font-bold tracking-tight text-slate-900 dark:text-white">{{ config('app.name', 'Booking Engine') }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-normal">{{ __('Storefront Platform') }}</span>
                    </div>
                </a>

                <!-- Auth Container Card -->
                <div class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 dark:shadow-black/40 border border-slate-200/80 dark:border-zinc-800 animate-fade-in">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
