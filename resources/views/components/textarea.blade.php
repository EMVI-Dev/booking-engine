@props([
    'disabled' => false,
    'error' => false,
    'rows' => 3,
])

@php
    $baseClasses = 'w-full rounded-lg border px-3.5 py-2 text-sm shadow-xs transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:bg-zinc-100 dark:disabled:bg-zinc-800 disabled:cursor-not-allowed';
    $stateClasses = $error
        ? 'border-red-500 text-red-900 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500 dark:text-red-400'
        : 'border-zinc-300 bg-white text-zinc-900 placeholder-zinc-400 focus:border-zinc-900 focus:ring-zinc-900/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-zinc-100 dark:focus:ring-zinc-100/10';

    $classes = "{$baseClasses} {$stateClasses}";
@endphp

<textarea rows="{{ $rows }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</textarea>
