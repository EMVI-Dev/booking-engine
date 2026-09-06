@props([
    'href',
    'icon',
    'active' => false,
    'badge' => null,
])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class('op-mobile-nav') }}
    @if ($active) aria-current="page" @endif
>
    <span class="relative">
        <i class="fa-solid {{ $icon }} text-base"></i>
        @if (filled($badge) && $badge !== 0 && $badge !== '0')
            <span class="absolute -top-1 -right-2 inline-flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-brand-400 px-1 text-[9px] font-bold text-brand-foreground">
                {{ $badge }}
            </span>
        @endif
    </span>
    <span class="tracking-tight">{{ $slot }}</span>
</a>
