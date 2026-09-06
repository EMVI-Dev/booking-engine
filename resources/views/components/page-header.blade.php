@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<div {{ $attributes->class('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="flex min-w-0 items-start gap-3">
        @if ($icon)
            <span
                class="mt-0.5 hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-op-muted text-op-subtle sm:inline-flex"
                aria-hidden="true"
            >
                <i class="fa-solid {{ $icon }} text-base"></i>
            </span>
        @endif

        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold tracking-tight text-op-ink sm:text-2xl">
                {{ $title }}
            </h1>

            @if ($subtitle)
                <p class="mt-0.5 text-xs text-op-subtle sm:text-sm">
                    {{ $subtitle }}
                </p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
