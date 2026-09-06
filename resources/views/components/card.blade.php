@props([
    'header' => null,
    'footer' => null,
])

<div {{ $attributes->class('op-card overflow-hidden') }}>
    @if ($header)
        <div class="border-b border-op-line bg-op-muted/60 px-6 py-4">
            {{ $header }}
        </div>
    @endif

    <div class="p-6">
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="flex items-center justify-between border-t border-op-line bg-op-muted/60 px-6 py-4">
            {{ $footer }}
        </div>
    @endif
</div>
