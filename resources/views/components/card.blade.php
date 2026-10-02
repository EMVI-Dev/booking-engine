@props([
    'header' => null,
    'footer' => null,
])

<div {{ $attributes->class('op-card overflow-hidden') }}>
    @if ($header)
        <div class="border-b border-op-line bg-op-muted/50 px-5 py-3.5">
            {{ $header }}
        </div>
    @endif

    <div class="p-5 sm:p-6">
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="flex items-center justify-between border-t border-op-line bg-op-muted/50 px-5 py-3.5">
            {{ $footer }}
        </div>
    @endif
</div>
