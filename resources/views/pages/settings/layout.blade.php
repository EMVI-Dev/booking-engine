<div class="space-y-6 w-full">
    <!-- Top Profile & Security Tab Bar -->
    <div class="flex items-center gap-1.5 p-1.5 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-x-auto select-none">
        <a
            href="{{ route('profile.edit') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2.5 rounded-xl text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('profile.edit') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-[#141721] hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-user text-xs {{ request()->routeIs('profile.edit') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-500' }}"></i>
            <span>{{ __('Profile') }}</span>
        </a>

        <a
            href="{{ route('security.edit') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2.5 rounded-xl text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('security.edit') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-[#141721] hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-shield-halved text-xs {{ request()->routeIs('security.edit') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-500' }}"></i>
            <span>{{ __('Security & 2FA') }}</span>
        </a>

        <a
            href="{{ route('appearance.edit') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2.5 rounded-xl text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('appearance.edit') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-[#141721] hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-circle-half-stroke text-xs {{ request()->routeIs('appearance.edit') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-500' }}"></i>
            <span>{{ __('Appearance') }}</span>
        </a>
    </div>

    <!-- Main Profile Settings Content Area -->
    <div class="w-full">
        @if (isset($heading))
            <div class="mb-4">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $heading }}</h2>
                @if (isset($subheading))
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $subheading }}</p>
                @endif
            </div>
        @endif

        <div class="w-full">
            {{ $slot }}
        </div>
    </div>
</div>
