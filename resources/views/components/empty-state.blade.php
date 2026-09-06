@props([
    'title',
    'description' => null,
    'icon' => 'fa-inbox',
    'compact' => false,
])

<div {{ $attributes->class([
    'flex flex-col items-center justify-center rounded-2xl border border-dashed border-op-line bg-op-muted/50 text-center',
    $compact ? 'px-6 py-10' : 'px-6 py-16',
]) }}>
    <span
        class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-op-surface text-op-subtle"
        aria-hidden="true"
    >
        <i class="fa-solid {{ $icon }} text-xl"></i>
    </span>

    <h3 class="text-sm font-bold text-op-ink sm:text-base">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1.5 max-w-sm text-xs leading-relaxed text-op-subtle sm:text-sm">
            {{ $description }}
        </p>
    @endif

    @isset($actions)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
