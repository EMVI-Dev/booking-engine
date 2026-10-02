@props([
    'title' => __('Desktop Management Recommended'),
    'description' => __('This section contains detailed configurations, layout controls, and file uploads that are best managed on a computer or laptop screen.'),
    'icon' => 'fa-solid fa-laptop',
])

<div class="lg:hidden p-6 sm:p-8 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] text-center space-y-4 shadow-none my-3">
    <div class="w-14 h-14 rounded-[8px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] mx-auto flex items-center justify-center text-xl border border-[#FFEF4D]/40">
        <i class="{{ $icon }}"></i>
    </div>

    <div class="space-y-1.5">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">
            {{ $title }}
        </h3>
        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] max-w-xs mx-auto leading-relaxed">
            {{ $description }}
        </p>
    </div>

    <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-2.5" x-data="{ copied: false }">
        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="w-full sm:w-auto h-9 px-4 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-xs font-semibold flex items-center justify-center gap-2 shadow-none transition cursor-pointer"
        >
            <i class="fa-solid fa-arrow-left text-[11px]"></i>
            <span>{{ __('Back to Dashboard') }}</span>
        </a>

        <button
            type="button"
            @click="navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2500)"
            class="w-full sm:w-auto h-9 px-4 rounded-[6px] bg-[#F4F5F7] hover:bg-[#E4E5E9] dark:bg-[#151a26] dark:hover:bg-[#1E2433] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] text-xs font-semibold flex items-center justify-center gap-2 transition cursor-pointer shadow-none"
        >
            <i class="fa-solid" :class="copied ? 'fa-check text-emerald-500' : 'fa-copy'"></i>
            <span x-text="copied ? '{{ __('Link Copied!') }}' : '{{ __('Copy Link for Desktop') }}'"></span>
        </button>
    </div>
</div>
