<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Appearance & Theme')" :subheading="__('Choose your preferred theme mode for the operator portal')">
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-line dark:border-line-dark shadow-xs space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" x-data="{
                theme: localStorage.getItem('theme') || 'dark',
                setTheme(val) {
                    this.theme = val;
                    localStorage.setItem('theme', val);
                    if (val === 'dark') {
                        document.documentElement.classList.add('dark');
                    } else if (val === 'light') {
                        document.documentElement.classList.remove('dark');
                    } else {
                        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                            document.documentElement.classList.add('dark');
                        } else {
                            document.documentElement.classList.remove('dark');
                        }
                    }
                }
            }">
                <button
                    type="button"
                    @click="setTheme('light')"
                    :class="theme === 'light' ? 'ring-2 ring-brand-400 bg-amber-50 border-brand-300' : 'border-line dark:border-line-dark bg-white dark:bg-zinc-900'"
                    class="flex flex-col items-center gap-3 p-5 rounded-2xl border text-sm font-semibold cursor-pointer transition-all hover:border-brand-300"
                >
                    <div class="w-full h-14 rounded-xl overflow-hidden border border-line">
                        <div class="h-full bg-[#efece4] p-2">
                            <div class="h-full rounded-lg bg-white border border-[#ddd6c8]"></div>
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-sun"></i>
                    </div>
                    <span class="text-stone-900 dark:text-white">{{ __('Light Theme') }}</span>
                </button>

                <button
                    type="button"
                    @click="setTheme('dark')"
                    :class="theme === 'dark' ? 'ring-2 ring-brand-400 bg-amber-50 dark:bg-amber-400/10 border-brand-300' : 'border-line dark:border-line-dark bg-white dark:bg-zinc-900'"
                    class="flex flex-col items-center gap-3 p-5 rounded-2xl border text-sm font-semibold cursor-pointer transition-all hover:border-brand-300"
                >
                    <div class="w-full h-14 rounded-xl overflow-hidden border border-[#4a4c56]">
                        <div class="h-full bg-[#1b1c21] p-2">
                            <div class="h-full rounded-lg bg-[#2d2f38] border border-[#4a4c56]"></div>
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-zinc-800 text-zinc-200 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-moon"></i>
                    </div>
                    <span class="text-stone-900 dark:text-white">{{ __('Dark Theme') }}</span>
                </button>

                <button
                    type="button"
                    @click="setTheme('system')"
                    :class="theme === 'system' ? 'ring-2 ring-brand-400 bg-amber-50 dark:bg-amber-400/10 border-brand-300' : 'border-line dark:border-line-dark bg-white dark:bg-zinc-900'"
                    class="flex flex-col items-center gap-3 p-5 rounded-2xl border text-sm font-semibold cursor-pointer transition-all hover:border-brand-300"
                >
                    <div class="w-10 h-10 rounded-xl bg-stone-100 dark:bg-zinc-800 text-stone-500 dark:text-zinc-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-desktop"></i>
                    </div>
                    <span class="text-stone-900 dark:text-white">{{ __('System Sync') }}</span>
                </button>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
