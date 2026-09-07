<x-layouts::auth :title="__('Coming soon')">
    @php
        $maintenance = \App\Models\PlatformSetting::current()->isPlatformMaintenance();
    @endphp
    <div class="flex flex-col gap-6 text-center">
        <div class="space-y-2">
            <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#FFEF4D]/15 text-[#8a7808] dark:bg-[#FFEF4D]/10 dark:text-[#FFEF4D] border border-[#FFEF4D]/40 dark:border-[#FFEF4D]/30">
                <i class="fa-solid {{ $maintenance ? 'fa-pause' : 'fa-clock' }} text-xs"></i>
                {{ $maintenance ? __('Paused') : __('Coming soon') }}
            </span>
            <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                {{ $maintenance ? __('Operator sign-up is paused') : __('We are preparing the operator portal') }}
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                @if ($maintenance)
                    {{ __('Storefront bookings and new operator accounts are paused for a short maintenance window. Existing operators can still log in. Admin still works.') }}
                @else
                    {{ __('TravelEngine is live as a preview. You can look around, and existing operators (including the demo desk) can log in. New operator accounts are not open yet.') }}
                @endif
            </p>
        </div>

        <div class="flex flex-col gap-3">
            <a href="{{ route('home') }}"
                class="inline-flex items-center justify-center h-10 px-4 rounded-lg bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] text-sm font-black transition"
                wire:navigate>
                {{ __('Back to the homepage') }}
            </a>
        </div>
    </div>
</x-layouts::auth>
