@props([
    'sidebar' => false,
    'href' => '#',
])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-3 font-semibold text-zinc-900 dark:text-zinc-100']) }}>
    <span class="flex aspect-square size-8 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 font-bold text-sm">
        B
    </span>
    <span class="text-sm font-semibold tracking-tight">{{ config('app.name', 'Booking Engine') }}</span>
</a>
