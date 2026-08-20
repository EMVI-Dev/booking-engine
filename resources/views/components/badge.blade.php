@props([
    'variant' => 'neutral', // neutral, success, warning, danger, info, primary
    'size' => 'md', // sm, md
])

@php
$baseClasses = 'inline-flex items-center font-medium rounded-full';

$sizeClasses = match($size) {
    'sm' => 'px-2 py-0.5 text-xs',
    default => 'px-2.5 py-1 text-xs',
};

$variantClasses = match($variant) {
    'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
    'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 dark:border-amber-800',
    'danger' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-400 border border-red-200 dark:border-red-800',
    'info' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200 dark:border-blue-800',
    'primary' => 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900',
    default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700',
};

$classes = "{$baseClasses} {$sizeClasses} {$variantClasses}";
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
