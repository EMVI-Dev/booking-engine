@props([
    'active' => false,
    'icon' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if ($href)
        href="{{ $href }}"
        wire:navigate
    @else
        type="button"
    @endif
    {{ $attributes->class('op-tab') }}
    @if ($active) aria-current="page" @endif
>
    @if ($icon)
        <i class="fa-solid {{ $icon }} op-tab-icon text-xs"></i>
    @endif
    {{ $slot }}
</{{ $tag }}>
