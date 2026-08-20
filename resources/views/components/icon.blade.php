@props(['name', 'type' => 'solid'])

@php
    $class = $name;
    if (! str_contains($name, 'fa-')) {
        $prefix = match($type) {
            'brand', 'brands' => 'fa-brands',
            'regular' => 'fa-regular',
            default => 'fa-solid',
        };
        $class = "{$prefix} fa-{$name}";
    }
@endphp

<i {{ $attributes->merge(['class' => "{$class} inline-block align-middle"]) }}></i>
