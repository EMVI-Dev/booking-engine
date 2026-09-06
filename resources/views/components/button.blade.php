@props([
    'variant' => 'primary', // primary, secondary, outline, danger, ghost
    'size' => 'md', // xs, sm, md, lg
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'left',
])

@php
    $baseClasses =
        'inline-flex items-center justify-center font-semibold rounded-xl transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer gap-2 select-none whitespace-nowrap shrink-0';

    $sizeClasses = match ($size) {
        'xs' => 'h-8 px-2.5 text-xs',
        'sm' => 'h-9 px-3.5 text-xs',
        'lg' => 'h-11 sm:h-12 px-5 sm:px-6 text-sm sm:text-base rounded-2xl',
        default => 'h-10 px-4 text-xs sm:text-sm',
    };

    $variantClasses = match ($variant) {
        'secondary'
            => 'bg-op-muted text-op-ink hover:bg-op-line focus:ring-op-subtle border border-op-line shadow-xs',
        'outline'
            => 'bg-transparent border border-op-line text-op-ink hover:bg-op-muted focus:ring-brand-400',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 shadow-xs',
        'ghost'
            => 'bg-transparent text-op-subtle hover:bg-op-muted hover:text-op-ink focus:ring-brand-400',
        default
            => 'bg-brand-400 text-brand-foreground hover:bg-brand-500 focus:ring-brand-400 shadow-xs',
    };

    $classes = "{$baseClasses} {$sizeClasses} {$variantClasses}";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon && $iconPosition === 'left')
            <span class="shrink-0">{!! $icon !!}</span>
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right')
            <span class="shrink-0">{!! $icon !!}</span>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon && $iconPosition === 'left')
            <span class="shrink-0">{!! $icon !!}</span>
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right')
            <span class="shrink-0">{!! $icon !!}</span>
        @endif
    </button>
@endif
