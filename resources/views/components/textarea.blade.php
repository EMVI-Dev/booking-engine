@props([
    'disabled' => false,
    'error' => false,
    'rows' => 3,
])

@php
    $baseClasses = 'w-full rounded-[6px] border px-3 py-2 text-xs sm:text-[13px] shadow-none transition-colors duration-150 focus:outline-none focus:border-[#FFEF4D] focus:ring-1 focus:ring-[#FFEF4D] disabled:bg-[#F4F5F6] dark:disabled:bg-[#141821] disabled:cursor-not-allowed';
    $stateClasses = $error
        ? 'border-rose-500 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20 dark:border-rose-500 dark:text-rose-400'
        : 'border-[#E4E5E9] bg-white text-[#1C2024] placeholder-[#8B8D98] dark:border-[#1E2433] dark:bg-[#10141d] dark:text-white dark:placeholder-slate-500';

    $classes = "{$baseClasses} {$stateClasses}";
@endphp

<textarea rows="{{ $rows }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</textarea>
