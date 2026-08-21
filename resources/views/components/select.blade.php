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

    $baseClasses = 'h-10 w-full rounded-xl border text-xs sm:text-sm shadow-xs transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:bg-slate-100 dark:disabled:bg-zinc-800 disabled:cursor-not-allowed';
    $stateClasses = $error
        ? 'border-rose-500 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20 dark:border-rose-500 dark:text-rose-400'
        : 'border-slate-300 bg-white text-slate-900 placeholder-slate-400 focus:border-purple-600 focus:ring-purple-600/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-purple-400 dark:focus:ring-purple-400/10';

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
            :class="{ 'ring-2 ring-indigo-500/20 border-indigo-500 dark:border-indigo-400': open }"
        >
            <span
                class="truncate"
                :class="hasSelection ? 'font-medium text-slate-900 dark:text-white' : 'text-slate-400 dark:text-zinc-500'"
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
            class="absolute left-0 right-0 z-50 mt-1.5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/90 dark:border-zinc-800 shadow-xl overflow-hidden animate-fade-in"
            style="display: none;"
        >
            @if ($showSearchBox)
                <!-- Search Box -->
                <div class="p-2 border-b border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/30">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input
                            x-ref="searchInput"
                            type="text"
                            x-model="search"
                            placeholder="{{ __('Search...') }}"
                            class="w-full h-8 pl-8 pr-3 text-xs rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500"
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
                            ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 font-bold'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800/80'"
                    >
                        <span x-text="item.label" class="truncate"></span>
                        <i x-show="String(value) === String(item.value)" class="fa-solid fa-check text-indigo-600 dark:text-indigo-400 text-xs"></i>
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
