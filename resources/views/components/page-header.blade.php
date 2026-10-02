@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<div {{ $attributes->class('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="flex min-w-0 items-start gap-3">
        @if ($icon)
            <span
                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/40"
                aria-hidden="true"
            >
                <i class="fa-solid {{ $icon }} text-sm"></i>
            </span>
        @endif

        <div class="min-w-0">
            <h1 class="truncate text-[20px] font-medium text-op-ink leading-tight">
                {{ $title }}
            </h1>

            @if ($subtitle)
                <p class="mt-1 text-[13px] text-op-subtle font-normal">
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
