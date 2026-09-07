@props([
    'href',
])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class('op-back-link inline-flex h-9 cursor-pointer items-center gap-2 rounded-xl px-3 text-sm font-semibold text-op-ink hover:bg-op-muted focus:outline-none focus:ring-2 focus:ring-brand-400/60') }}
>
    <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
    <span>{{ $slot }}</span>
</a>
