@props([
    'options' => [],
    'placeholder' => null,
    'searchable' => null, // true, false, or null (auto: true if > 5 items)
    'disabled' => false,
    'error' => false,
])

@php
    // Normalize options array into [{value: ..., label: ...}] format
    $normalizedOptions = [];
    if (!empty($options)) {
        foreach ($options as $key => $val) {
            if (is_array($val) && isset($val['value'])) {
                $normalizedOptions[] = [
                    'value' => (string) $val['value'],
                    'label' => (string) ($val['label'] ?? $val['value']),
                ];
            } elseif (is_numeric($key)) {
                $normalizedOptions[] = [
                    'value' => (string) $val,
                    'label' => (string) $val,
                ];
            } else {
                $normalizedOptions[] = [
                    'value' => (string) $key,
                    'label' => (string) $val,
                ];
            }
        }
    }

    $wireModel = $attributes->wire('model')->value();

    $showSearchBox = $searchable === true || ($searchable === null && count($normalizedOptions) >= 6);

    $baseClasses = 'op-input disabled:cursor-not-allowed disabled:opacity-60';
    $stateClasses = $error
        ? 'border-rose-500 text-rose-700'
        : '';

    $classes = "{$baseClasses} {$stateClasses}";
@endphp

@if (!empty($normalizedOptions))
    <!-- Custom Aligned Select with Interactive Dropdown Menu -->
    <div
        x-data="{
            open: false,
            search: '',
            value: @if($wireModel) @entangle($attributes->wire('model')) @else '' @endif,
            placeholderText: '{{ $placeholder ?? __('Select an option...') }}',
            options: {{ Js::from($normalizedOptions) }},
            get selectedLabel() {
                const found = this.options.find(o => String(o.value) === String(this.value));
                return found ? found.label : this.placeholderText;
            },
            get hasSelection() {
                return this.options.some(o => String(o.value) === String(this.value));
            },
            get filteredOptions() {
                if (!this.search) return this.options;
                const s = this.search.toLowerCase();
                return this.options.filter(o => o.label.toLowerCase().includes(s) || String(o.value).toLowerCase().includes(s));
            },
            selectOption(val) {
                if ({{ $disabled ? 'true' : 'false' }}) return;
                this.value = val;
                this.open = false;
                this.search = '';
                this.$dispatch('input', val);
                this.$dispatch('change', val);
            }
        }"
        x-on:click.outside="open = false"
        x-on:keydown.escape.window="open = false"
        class="relative w-full"
    >
        <!-- Trigger Button -->
        <button
            type="button"
            x-on:click="if (!{{ $disabled ? 'true' : 'false' }}) { open = !open; if (open && {{ $showSearchBox ? 'true' : 'false' }}) $nextTick(() => $refs.searchInput?.focus()); }"
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->except('wire:model')->merge(['class' => $classes . ' px-3.5 flex items-center justify-between gap-2 text-left cursor-pointer select-none']) }}
            :class="{ 'ring-2 ring-brand-400/30 border-brand-500': open }"
        >
            <span
                class="truncate"
                :class="hasSelection ? 'font-medium text-op-ink' : 'text-op-subtle'"
                x-text="selectedLabel"
            ></span>
            <i class="fa-solid fa-chevron-down text-[11px] text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': open }"></i>
        </button>

        <!-- Dropdown Menu -->
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1 scale-98"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-98"
            class="absolute left-0 right-0 z-50 mt-1.5 overflow-hidden rounded-2xl border border-op-line bg-op-surface shadow-xl animate-fade-in"
            style="display: none;"
        >
            @if ($showSearchBox)
                <!-- Search Box -->
                <div class="border-b border-op-line bg-op-muted/50 p-2">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-op-subtle"></i>
                        <input
                            x-ref="searchInput"
                            type="text"
                            x-model="search"
                            placeholder="{{ __('Search...') }}"
                            class="h-8 w-full rounded-lg border border-op-line bg-op-inset pl-8 pr-3 text-xs text-op-ink placeholder:text-op-subtle focus:outline-none focus:ring-1 focus:ring-brand-400"
                        />
                    </div>
                </div>
            @endif

            <!-- Options List -->
            <div class="max-h-56 overflow-y-auto p-1.5 space-y-0.5">
                <template x-for="item in filteredOptions" :key="item.value">
                    <button
                        type="button"
                        x-on:click="selectOption(item.value)"
                        class="w-full px-3 py-2 rounded-xl text-left text-xs sm:text-sm font-medium flex items-center justify-between gap-2 transition-colors cursor-pointer"
                        :class="String(value) === String(item.value)
                            ? 'bg-amber-50 text-stone-900 font-semibold dark:bg-amber-400/10 dark:text-amber-50'
                            : 'text-op-ink hover:bg-op-muted'"
                    >
                        <span x-text="item.label" class="truncate"></span>
                        <i x-show="String(value) === String(item.value)" class="fa-solid fa-check text-xs text-amber-600 dark:text-amber-300"></i>
                    </button>
                </template>

                <div x-show="filteredOptions.length === 0" class="py-4 px-3 text-center text-xs text-slate-400">
                    <i class="fa-solid fa-inbox text-base mb-1 block opacity-50"></i>
                    {{ __('No matching options found') }}
                </div>
            </div>
        </div>
    </div>
@else
    <!-- Native Styled Select Wrapper for direct slot markup -->
    <div class="relative w-full">
        <select
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->merge(['class' => $classes . ' px-3.5 pr-9 appearance-none cursor-pointer']) }}
        >
            @if ($placeholder)
                <option value="" disabled selected>{{ $placeholder }}</option>
            @endif

            {{ $slot }}
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 dark:text-zinc-500">
            <i class="fa-solid fa-chevron-down text-[11px]"></i>
        </div>
    </div>
@endif
