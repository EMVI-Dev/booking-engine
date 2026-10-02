@props(['padded' => false])

<div
    {{ $attributes->class([
        'flex items-center gap-1.5 overflow-x-auto',
        'rounded-[8px] border border-op-line bg-op-surface p-1 shadow-none' => $padded,
    ]) }}
    role="tablist"
>
    {{ $slot }}
</div>
