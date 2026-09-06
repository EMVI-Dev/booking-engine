@props([
    'sidebar' => false,
    'href' => '#',
])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-3 font-semibold text-zinc-900 dark:text-zinc-100']) }}>
    <span class="flex aspect-square size-8 items-center justify-center rounded-xl bg-[#FFEF4D] text-[#12181E] text-sm shadow-xs font-black">
        <i class="fa-solid fa-compass"></i>
    </span>
    <span class="text-sm font-semibold tracking-tight">{{ config('app.name', 'TravelEngine') }}</span>
</a>
