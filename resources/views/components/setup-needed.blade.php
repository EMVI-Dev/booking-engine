@props([
    'needed' => false,
    'anchor' => null,
    'hint' => null,
])

@php
    $hintText = $hint ?? __('Needed for bookings — save to confirm');
@endphp

<div
    @if (filled($anchor)) id="{{ $anchor }}" @endif
    @if ($needed) data-setup-needed="true" @endif
    {{ $attributes->class([
        'scroll-mt-24 transition-[box-shadow,background-color,border-color] duration-200',
        'rounded-[1.35rem] border-2 border-[#FFEF4D] bg-[#FFEF4D]/20 p-1.5 shadow-[0_0_0_4px_rgba(255,239,77,0.35)] dark:bg-[#FFEF4D]/10 dark:shadow-[0_0_0_4px_rgba(255,239,77,0.2)]' => $needed,
    ]) }}
>
    @if ($needed)
        <div class="mb-2 inline-flex items-center gap-1.5 rounded-lg bg-[#FFEF4D] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-[#12181E]">
            <i class="fa-solid fa-circle-exclamation text-[9px]" aria-hidden="true"></i>
            <span>{{ $hintText }}</span>
        </div>
    @endif

    {{ $slot }}
</div>
