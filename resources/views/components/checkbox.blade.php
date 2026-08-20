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

<div class="flex items-start gap-3 select-none group">
    <div class="flex items-center h-5 shrink-0">
        <input
            id="{{ $id }}"
            type="checkbox"
            {{ $name ? 'name='.$name : '' }}
            {{ $checked ? 'checked' : '' }}
            {{ $attributes->merge([
                'class' => 'h-4 w-4 rounded-md border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-brand-600 dark:text-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-0 dark:focus:ring-offset-zinc-900 transition-all duration-150 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ' . ($error ? 'border-rose-500 dark:border-rose-500 focus:ring-rose-500/20' : '')
            ]) }}
        />
    </div>

    @if ($label || $description || $slot->isNotEmpty())
        <label for="{{ $id }}" class="text-xs sm:text-sm font-medium cursor-pointer text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">
            @if ($label)
                <span class="block font-semibold text-slate-900 dark:text-slate-100">{{ $label }}</span>
            @endif

            @if ($description)
                <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 font-normal mt-0.5 leading-relaxed">{{ $description }}</p>
            @endif

            {{ $slot }}
        </label>
    @endif
</div>
