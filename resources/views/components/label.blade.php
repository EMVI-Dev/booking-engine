@props(['value', 'required' => false])

<label {{ $attributes->merge(['class' => 'mb-1 block text-sm font-medium text-op-ink']) }}>
    {{ $value ?? $slot }}
    @if($required)
        <span class="text-red-500 ml-0.5">*</span>
    @endif
</label>
