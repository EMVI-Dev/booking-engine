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
        'inline-flex items-center justify-center font-medium rounded-[6px] transition-colors duration-150 focus:outline-none focus:ring-1 focus:ring-offset-0 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer gap-2 select-none whitespace-nowrap shrink-0 shadow-none';

    $sizeClasses = match ($size) {
        'xs' => 'h-7 px-2.5 text-[11px]',
        'sm' => 'h-8 px-3 text-xs',
        'lg' => 'h-10 px-4 text-sm',
        default => 'h-8.5 px-3.5 text-xs sm:text-[13px]',
    };

    $variantClasses = match ($variant) {
        'secondary'
            => 'bg-white dark:bg-[#141821] text-[#1C2024] dark:text-slate-200 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] focus:ring-[#FFEF4D]',
        'outline'
            => 'bg-transparent border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-slate-200 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] focus:ring-[#FFEF4D]',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-500 focus:ring-rose-500',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-500',
        'ghost'
            => 'bg-transparent text-[#60646C] dark:text-slate-400 hover:text-[#1C2024] dark:hover:text-white hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] focus:ring-[#FFEF4D]',
        default
            => 'bg-[#FFEF4D] text-[#12181E] hover:bg-[#F3E13A] focus:ring-[#FFEF4D]',
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
