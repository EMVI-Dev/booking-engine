<div class="space-y-6 w-full max-w-4xl">
    <!-- Top Profile & Security Tab Bar -->
    <div class="flex items-center gap-1.5 p-1.5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-x-auto select-none">
        <a
            href="{{ route('profile.edit') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2.5 rounded-xl text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('profile.edit') ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-user text-xs {{ request()->routeIs('profile.edit') ? 'text-white' : 'text-slate-400' }}"></i>
            <span>{{ __('Profile') }}</span>
        </a>

        <a
            href="{{ route('security.edit') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2.5 rounded-xl text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('security.edit') ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-shield-halved text-xs {{ request()->routeIs('security.edit') ? 'text-white' : 'text-slate-400' }}"></i>
            <span>{{ __('Security & 2FA') }}</span>
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
