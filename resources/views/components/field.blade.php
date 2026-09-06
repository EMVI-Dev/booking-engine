@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'required' => false,
])

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <x-label :value="$label" :required="$required" :for="$name" />
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-op-subtle">{{ $hint }}</p>
    @endif

    @if ($name)
        <x-input-error :messages="$errors->get($name)" />
    @endif
</div>
