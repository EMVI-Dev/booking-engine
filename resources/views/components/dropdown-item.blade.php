@props(['href' => null, 'icon' => null])

@php
$classes = 'flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800/80 hover:text-slate-900 dark:hover:text-white focus:outline-none focus:bg-slate-100 dark:focus:bg-zinc-800 transition duration-150 ease-in-out cursor-pointer whitespace-nowrap';
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
