@props([
    'title' => __('Desktop Management Recommended'),
    'description' => __('This section contains detailed configurations, layout controls, and file uploads that are best managed on a computer or laptop screen.'),
    'icon' => 'fa-solid fa-laptop',
])

<div class="lg:hidden p-6 sm:p-8 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 text-center space-y-5 shadow-xs my-3 animate-fade-in">
    <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-500/10 via-indigo-500/20 to-purple-500/10 text-indigo-600 dark:text-indigo-400 mx-auto flex items-center justify-center text-2xl border border-indigo-200/60 dark:border-indigo-800/60 shadow-inner">
        <i class="{{ $icon }}"></i>
    </div>

    <div class="space-y-1.5">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">
            {{ $title }}
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-xs mx-auto leading-relaxed">
            {{ $description }}
        </p>
    </div>

    <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-2.5" x-data="{ copied: false }">
        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="w-full sm:w-auto h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold flex items-center justify-center gap-2 shadow-xs transition cursor-pointer"
        >
            <i class="fa-solid fa-arrow-left text-[11px]"></i>
            <span>{{ __('Back to Dashboard') }}</span>
        </a>

        <button
            type="button"
            @click="navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2500)"
            class="w-full sm:w-auto h-10 px-4 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 text-xs font-semibold flex items-center justify-center gap-2 transition cursor-pointer"
        >
            <i class="fa-solid" :class="copied ? 'fa-check text-emerald-500' : 'fa-copy'"></i>
            <span x-text="copied ? '{{ __('Link Copied!') }}' : '{{ __('Copy Link for Desktop') }}'"></span>
        </button>
    </div>
</div>
