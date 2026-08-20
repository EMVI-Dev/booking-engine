@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center space-y-1">
    <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $title }}</h1>
    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
</div>
