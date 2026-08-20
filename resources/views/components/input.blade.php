@props([
    'disabled' => false,
    'error' => false,
    'icon' => null,
])

@php
    $baseClasses = 'h-10 w-full rounded-xl border px-3.5 text-sm shadow-xs transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:bg-slate-100 dark:disabled:bg-zinc-800 disabled:cursor-not-allowed';
    $stateClasses = $error
        ? 'border-rose-500 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20 dark:border-rose-500 dark:text-rose-400'
        : 'border-slate-300 bg-white text-slate-900 placeholder-slate-400 focus:border-brand-600 focus:ring-brand-600/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-brand-400 dark:focus:ring-brand-400/10';

    $classes = "{$baseClasses} {$stateClasses}";
@endphp

@if ($icon)
    <div class="relative rounded-xl shadow-xs">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-zinc-500">
            {!! $icon !!}
        </div>
        <input {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes . ' pl-10']) }}>
    </div>
@else
    <input {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>
@endif
