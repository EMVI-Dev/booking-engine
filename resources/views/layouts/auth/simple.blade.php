<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-[#FFEF4D] selection:text-[#101730] transition-colors duration-200">

        <div class="flex min-h-svh flex-col items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-lg space-y-6">
                <!-- Platform Brand Header -->
                <a href="{{ route('home') }}" class="flex items-center justify-center gap-3 font-semibold group" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FFEF4D] text-[#2A428C] shadow-md text-lg group-hover:scale-105 transition-transform duration-200 font-black">
                        <i class="fa-solid fa-compass"></i>
                    </span>
                    <div class="flex flex-col text-start">
                        <span class="text-base font-bold tracking-tight text-slate-900 dark:text-white">{{ config('app.name', 'TravelEngine') }}</span>
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
