@props(['placeholder' => null])

<div class="relative">
    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-op-subtle"></i>
    <input
        type="search"
        {{ $attributes->merge([
            'class' => 'op-input pl-10',
            'placeholder' => $placeholder ?? __('Search...'),
        ]) }}
    />
</div>
