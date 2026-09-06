<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Page not open yet') }} — {{ $agent->displayName }}</title>
    <link rel="icon" href="{{ $agent->logoUrl }}" />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('storefront.partials.brand-theme')
</head>
<body class="flex h-full flex-col justify-between bg-slate-50 font-sans text-slate-800 antialiased dark:bg-zinc-950 dark:text-zinc-200">
    <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-900/80">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:h-20 sm:px-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800">
                    <img src="{{ $agent->logoUrl }}" alt="{{ $agent->name }}" class="h-full w-full object-cover" />
                </div>
                <div>
                    <h1 class="text-sm leading-tight font-extrabold text-slate-900 sm:text-base dark:text-white">
                        {{ $agent->displayName }}
                    </h1>
                    <span class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-amber-600 uppercase sm:text-xs dark:text-amber-400">
                        <i class="fa-solid fa-clock text-[9px]"></i>
                        <span>{{ __('Not open yet') }}</span>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="my-auto flex flex-1 items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-md animate-fade-in space-y-6 text-center">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl border border-amber-200/80 bg-amber-50 text-3xl text-amber-600 shadow-sm shadow-amber-500/10 dark:border-amber-900/60 dark:bg-amber-950/70 dark:text-amber-400">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>

            <div class="space-y-2.5">
                <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    {{ __('This page is not open yet') }}
                </h2>
                <p class="mx-auto max-w-sm text-xs leading-relaxed text-slate-600 sm:text-sm dark:text-slate-400">
                    @if (($reason ?? 'setup') === 'pending')
                        {{ __('This operator is still being reviewed. Please check back soon.') }}
                    @else
                        {{ __('The operator is still finishing their details. Guests cannot book here yet.') }}
                    @endif
                </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-2">
                @if (auth()->user()?->canOperate($agent, 'manageSettings'))
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex h-10 cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-[#FFEF4D] px-4 text-xs font-bold text-[#12181E] transition hover:bg-[#fae639]"
                    >
                        {{ __('Finish setup') }}
                    </a>
                @else
                    <a
                        href="{{ url('/') }}"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-100 px-4 text-xs font-bold text-slate-700 transition hover:bg-slate-200 dark:bg-zinc-800 dark:text-slate-300 dark:hover:bg-zinc-700"
                    >
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        <span>{{ __('Go home') }}</span>
                    </a>
                @endif
            </div>
        </div>
    </main>

    <footer class="border-t border-slate-200/60 py-6 text-center text-xs text-slate-400 dark:border-zinc-800/60">
        <p>&copy; {{ date('Y') }} {{ $agent->displayName }}.</p>
    </footer>
</body>
</html>
