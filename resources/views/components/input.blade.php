@props([
    'disabled' => false,
    'error' => false,
    'icon' => null,
])

@php
    $baseClasses = 'op-input disabled:cursor-not-allowed disabled:opacity-60';
    $stateClasses = $error
        ? 'border-rose-500 text-rose-700'
        : '';

    $classes = "{$baseClasses} {$stateClasses}";
@endphp

@if ($icon)
    <div class="relative rounded-xl">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-zinc-500">
            {!! $icon !!}
        </div>
        <input {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes . ' pl-10']) }}>
    </div>
@else
    <input {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>
@endif
