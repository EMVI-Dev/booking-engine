@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'description' => null,
    'checked' => false,
    'error' => false,
])

@php
    $id = $id ?? ($name ?? 'checkbox-' . \Illuminate\Support\Str::random(8));
@endphp

<div class="flex items-start gap-3 select-none group py-2.5">
    <div class="flex items-center h-6 shrink-0">
        <input
            id="{{ $id }}"
            type="checkbox"
            {{ $name ? 'name='.$name : '' }}
            {{ $checked ? 'checked' : '' }}
            {{ $attributes->merge([
                'class' => 'h-4 w-4 rounded-[4px] border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-[#12181E] focus:ring-1 focus:ring-[#FFEF4D] focus:ring-offset-0 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ' . ($error ? 'border-rose-500' : '')
            ]) }}
        />
    </div>

    @if ($label || $description || $slot->isNotEmpty())
        <label for="{{ $id }}" class="text-xs sm:text-sm font-medium cursor-pointer text-[#1C2024] dark:text-slate-200 group-hover:text-[#1C2024] dark:group-hover:text-white transition-colors">
            @if ($label)
                <span class="block font-medium text-[#1C2024] dark:text-white">{{ $label }}</span>
            @endif

            @if ($description)
                <p class="text-[11px] sm:text-xs text-[#60646C] dark:text-slate-400 font-normal mt-0.5 leading-relaxed">{{ $description }}</p>
            @endif

            {{ $slot }}
        </label>
    @endif
</div>
