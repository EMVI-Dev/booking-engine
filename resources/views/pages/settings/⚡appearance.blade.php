<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Appearance & Theme')" :subheading="__('Choose your preferred theme mode for the operator portal')">
        <div class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4" x-data="{
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
                    :class="theme === 'light' ? 'border-[#12181E] bg-[#FFEF4D]/15 dark:border-[#FFEF4D] dark:bg-[#FFEF4D]/10' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#F4F5F7]/40 dark:bg-[#141821]/40 hover:bg-[#F4F5F7] dark:hover:bg-[#141821]'"
                    class="flex flex-col items-center gap-3 p-5 rounded-[8px] border text-sm font-semibold cursor-pointer transition shadow-none"
                >
                    <div class="w-full h-14 rounded-[6px] overflow-hidden border border-[#E4E5E9]">
                        <div class="h-full bg-[#F4F5F7] p-2">
                            <div class="h-full rounded-[4px] bg-white border border-[#E4E5E9]"></div>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-[6px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 flex items-center justify-center text-base">
                        <i class="fa-solid fa-sun"></i>
                    </div>
                    <span class="text-slate-900 dark:text-white">{{ __('Light Theme') }}</span>
                </button>

                <button
                    type="button"
                    @click="setTheme('dark')"
                    :class="theme === 'dark' ? 'border-[#12181E] bg-[#FFEF4D]/15 dark:border-[#FFEF4D] dark:bg-[#FFEF4D]/10' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#F4F5F7]/40 dark:bg-[#141821]/40 hover:bg-[#F4F5F7] dark:hover:bg-[#141821]'"
                    class="flex flex-col items-center gap-3 p-5 rounded-[8px] border text-sm font-semibold cursor-pointer transition shadow-none"
                >
                    <div class="w-full h-14 rounded-[6px] overflow-hidden border border-[#1E2433]">
                        <div class="h-full bg-[#0b0e12] p-2">
                            <div class="h-full rounded-[4px] bg-[#10141d] border border-[#1E2433]"></div>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-[6px] bg-[#1E2433] text-zinc-200 border border-[#2d3748] flex items-center justify-center text-base">
                        <i class="fa-solid fa-moon"></i>
                    </div>
                    <span class="text-slate-900 dark:text-white">{{ __('Dark Theme') }}</span>
                </button>

                <button
                    type="button"
                    @click="setTheme('system')"
                    :class="theme === 'system' ? 'border-[#12181E] bg-[#FFEF4D]/15 dark:border-[#FFEF4D] dark:bg-[#FFEF4D]/10' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#F4F5F7]/40 dark:bg-[#141821]/40 hover:bg-[#F4F5F7] dark:hover:bg-[#141821]'"
                    class="flex flex-col items-center gap-3 p-5 rounded-[8px] border text-sm font-semibold cursor-pointer transition shadow-none"
                >
                    <div class="w-full h-14 rounded-[6px] overflow-hidden border border-[#E4E5E9] dark:border-[#1E2433] flex">
                        <div class="w-1/2 h-full bg-[#F4F5F7] p-2">
                            <div class="h-full rounded-l-[4px] bg-white border border-[#E4E5E9]"></div>
                        </div>
                        <div class="w-1/2 h-full bg-[#0b0e12] p-2">
                            <div class="h-full rounded-r-[4px] bg-[#10141d] border border-[#1E2433]"></div>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-[6px] bg-[#E4E5E9]/60 dark:bg-[#1E2433] text-slate-600 dark:text-zinc-400 border border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center text-base">
                        <i class="fa-solid fa-desktop"></i>
                    </div>
                    <span class="text-slate-900 dark:text-white">{{ __('System Sync') }}</span>
                </button>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
