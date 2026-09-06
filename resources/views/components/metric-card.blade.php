@props([
    'label',
    'value' => null,
    'hint' => null,
    'icon' => null,
    'tone' => 'neutral',
    'href' => null,
])

@php
    $featured = $tone === 'featured';

    $iconWell = match ($tone) {
        'featured' => 'bg-[#ffef4d] text-[#12181e]',
        'brand' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200',
        'ebony' => 'bg-ebony text-[#e8e4d4]',
        'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
        'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200',
        'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
        default => 'bg-op-muted text-op-ink',
    };

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href)
        href="{{ $href }}"
        wire:navigate
    @endif
    {{ $attributes->class([$featured ? 'op-metric-featured' : 'op-card', 'op-metric space-y-2.5 p-5']) }}
>
    <div class="flex items-center justify-between gap-2">
        <span @class(['op-metric-label text-[11px] font-semibold uppercase tracking-wider sm:text-xs', 'text-op-subtle' => ! $featured])>
            {{ $label }}
        </span>
        @if ($icon)
            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-2xl text-xs {{ $iconWell }}">
                <i class="fa-solid {{ $icon }}"></i>
            </span>
        @endif
    </div>

    <div class="flex min-w-0 items-baseline gap-1.5">
        <p @class(['op-metric-value truncate text-2xl font-bold sm:text-3xl', 'text-op-ink' => ! $featured])>
            {{ $value ?? $slot }}
        </p>
        @isset($suffix)
            <span @class(['op-metric-suffix text-xs font-semibold', 'text-op-subtle' => ! $featured])>{{ $suffix }}</span>
        @endisset
    </div>

    @if ($hint || isset($meta))
        <div @class(['op-metric-hint truncate text-[11px]', 'text-op-subtle' => ! $featured])>
            {{ $meta ?? $hint }}
        </div>
    @endif
</{{ $tag }}>
