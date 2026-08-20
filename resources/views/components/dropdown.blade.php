@props([
    'align' => 'right',
    'width' => 'full',
    'contentClasses' => 'py-1.5 bg-white dark:bg-zinc-900'
])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0 mt-2',
    'top' => 'bottom-full mb-2 origin-bottom start-0 end-0 w-full',
    'top-right' => 'bottom-full mb-2 origin-bottom-right end-0',
    'top-left' => 'bottom-full mb-2 origin-bottom-left start-0',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0 mt-2',
};

$widthClasses = match ($width) {
    '48' => 'w-48',
    '56' => 'w-56',
    '64' => 'w-64',
    'full' => 'w-full',
    default => $width,
};
@endphp

<div class="relative w-full" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1 scale-98"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-1 scale-98"
         class="absolute z-50 {{ $widthClasses }} rounded-2xl shadow-xl shadow-slate-900/10 dark:shadow-black/50 border border-slate-200 dark:border-zinc-800 {{ $alignmentClasses }}"
         style="display: none;"
    >
        <div class="rounded-2xl ring-1 ring-black/5 overflow-hidden {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
