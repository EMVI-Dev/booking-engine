@props(['padded' => false])

<div
    {{ $attributes->class([
        'flex items-center gap-1.5 overflow-x-auto',
        'rounded-2xl border border-op-line bg-op-surface p-1.5 shadow-xs' => $padded,
    ]) }}
    role="tablist"
>
    {{ $slot }}
</div>
