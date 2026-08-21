@props([
    'sidebar' => false,
    'href' => '#',
])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-3 font-semibold text-zinc-900 dark:text-zinc-100']) }}>
    <span class="flex aspect-square size-8 items-center justify-center rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white text-sm shadow-xs">
        <i class="fa-solid fa-compass"></i>
    </span>
    <span class="text-sm font-semibold tracking-tight">{{ config('app.name', 'Booking Engine') }}</span>
</a>
