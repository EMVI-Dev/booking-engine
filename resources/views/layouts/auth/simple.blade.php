<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            .blueprint-grid {
                background-image: 
                    linear-gradient(to right, rgba(255, 255, 255, 0.04) 1px, transparent 1px),
                    linear-gradient(to bottom, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
                background-size: 48px 48px;
            }
            .glow-radial-yellow {
                background: radial-gradient(circle at 50% 0%, rgba(255, 239, 77, 0.12) 0%, rgba(255, 239, 77, 0.02) 40%, transparent 70%);
            }
        </style>
    </head>
    <body class="min-h-screen bg-[#08090d] text-zinc-100 antialiased selection:bg-[#FFEF4D] selection:text-[#090d16] font-sans relative overflow-x-clip blueprint-grid glow-radial-yellow">

        <div class="flex min-h-svh flex-col items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-lg space-y-6 relative">
                
                <!-- Platform Brand Header -->
                <a href="{{ route('home') }}" class="flex items-center justify-center gap-3 font-semibold group select-none" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FFEF4D] text-[#090d16] shadow-sm text-base group-hover:scale-105 transition-transform duration-200 font-black">
                        <i class="fa-solid fa-compass"></i>
                    </span>
                    <div class="flex flex-col text-start">
                        <div class="flex items-center gap-2">
                            <span class="text-base font-black tracking-tight text-white leading-none">{{ config('app.name', 'TravelEngine') }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase tracking-wider bg-white/[0.06] border border-white/[0.12] text-[#FFEF4D]">Platform</span>
                        </div>
                        <span class="text-xs text-zinc-400 font-normal font-mono pt-0.5">{{ __('Operator Console & Desk') }}</span>
                    </div>
                </a>

                <!-- Auth Container Card (Laravel Cloud Architectural Style) -->
                <div class="relative bg-[#0c0e14]/90 backdrop-blur-xl rounded-2xl p-6 sm:p-8 shadow-2xl border border-white/[0.08] animate-fade-in overflow-hidden">
                    <!-- Corner Crosshairs -->
                    <div class="absolute top-2 left-2 text-zinc-700 font-mono text-[9px] select-none pointer-events-none">+</div>
                    <div class="absolute top-2 right-2 text-zinc-700 font-mono text-[9px] select-none pointer-events-none">+</div>
                    <div class="absolute bottom-2 left-2 text-zinc-700 font-mono text-[9px] select-none pointer-events-none">+</div>
                    <div class="absolute bottom-2 right-2 text-zinc-700 font-mono text-[9px] select-none pointer-events-none">+</div>

                    {{ $slot }}
                </div>

                <!-- Footer Back Link -->
                <div class="flex items-center justify-between text-xs font-mono text-zinc-500 px-2">
                    <a href="{{ route('home') }}" class="hover:text-zinc-300 transition flex items-center gap-1.5" wire:navigate>
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        <span>{{ __('Back to platform home') }}</span>
                    </a>
                    <span>&copy; {{ date('Y') }} {{ config('app.name', 'TravelEngine') }}</span>
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
