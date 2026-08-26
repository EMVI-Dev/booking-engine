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
        'sm' => 'h-9 px-3 text-xs',
        'lg' => 'h-12 px-6 text-base',
        default => 'h-10 px-4 text-sm',
    };

    $variantClasses = match ($variant) {
        'secondary'
            => 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700 focus:ring-slate-400 border border-slate-200 dark:border-zinc-700',
        'outline'
            => 'bg-transparent border border-slate-300 dark:border-zinc-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-zinc-800 focus:ring-indigo-500',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 shadow-xs',
        'ghost'
            => 'bg-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 focus:ring-indigo-500',
        default
            => 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-600 dark:text-white dark:hover:bg-indigo-500 focus:ring-indigo-500 shadow-xs',
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
