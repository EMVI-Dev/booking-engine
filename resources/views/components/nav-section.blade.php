@props(['title'])

<div {{ $attributes->class('space-y-1') }}>
    <p class="op-section-label">{{ $title }}</p>
    {{ $slot }}
</div>
