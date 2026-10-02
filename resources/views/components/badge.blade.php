@props([
    'variant' => 'neutral', // neutral, success, warning, danger, info, primary
    'size' => 'md', // sm, md
])

@php
$baseClasses = 'inline-flex items-center font-medium rounded-[4px] border border-transparent shadow-none';

$sizeClasses = match($size) {
    'sm' => 'px-1.5 py-0.5 text-[11px]',
    default => 'px-2 py-0.5 text-xs',
};

$variantClasses = match($variant) {
    'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/80',
    'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border-amber-200 dark:border-amber-800/80',
    'danger' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 border-rose-200 dark:border-rose-800/80',
    'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-400 border-sky-200 dark:border-sky-800/80',
    'primary' => 'bg-[#FFEF4D] text-[#12181E] border-transparent font-medium',
    default => 'bg-[#F4F5F6] text-[#60646C] dark:bg-[#141821] dark:text-slate-300 border-[#E4E5E9] dark:border-[#1E2433]',
};

$classes = "{$baseClasses} {$sizeClasses} {$variantClasses}";
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
