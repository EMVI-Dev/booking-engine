@props(['href' => null, 'icon' => null])

@php
$classes = 'flex w-full items-center gap-2 px-3 py-2 text-start text-xs font-medium text-[#1C2024] dark:text-slate-200 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] focus:outline-none focus:bg-[#F4F5F6] dark:focus:bg-[#1E2433] transition-colors duration-150 cursor-pointer whitespace-nowrap';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <span class="shrink-0 text-slate-400 dark:text-slate-500">{!! $icon !!}</span>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="submit" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <span class="shrink-0 text-slate-400 dark:text-slate-500">{!! $icon !!}</span>
        @endif
        {{ $slot }}
    </button>
@endif
