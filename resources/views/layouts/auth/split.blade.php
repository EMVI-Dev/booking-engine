<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased">
        <div class="relative grid min-h-screen flex-col items-center justify-center lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col bg-zinc-900 p-10 text-white dark:border-r dark:border-zinc-800 lg:flex">
                <div class="absolute inset-0 bg-zinc-900"></div>
                <a href="{{ route('home') }}" class="relative z-20 flex items-center gap-2 text-lg font-bold" wire:navigate>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-zinc-900 font-bold">
                        B
                    </span>
                    {{ config('app.name', 'Booking Engine') }}
                </a>
                <div class="relative z-20 mt-auto">
                    <blockquote class="space-y-2">
                        <p class="text-lg">&ldquo;The simplest, fastest tour booking engine for guides and agencies.&rdquo;</p>
                    </blockquote>
                </div>
            </div>
            <div class="p-6 lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center gap-6 sm:w-[350px]">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
