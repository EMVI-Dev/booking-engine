@props([
    'placeholder' => null,
    'min' => null,
    'max' => null,
    'disabled' => false,
    'error' => false,
    'presets' => true,
])

@php
    $wireModel = $attributes->wire('model')->value();
    $minDate = $min ?: date('Y-m-d');
    $maxDate = $max ?: date('Y-m-d', strtotime('+1 year'));

    $baseClasses = 'h-10 w-full rounded-xl border text-xs sm:text-sm shadow-xs transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:bg-slate-100 dark:disabled:bg-zinc-800 disabled:cursor-not-allowed';
    $stateClasses = $error
        ? 'border-rose-500 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20 dark:border-rose-500 dark:text-rose-400'
        : 'border-slate-300 bg-white text-slate-900 placeholder-slate-400 focus:border-purple-600 focus:ring-purple-600/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-purple-400 dark:focus:ring-purple-400/10';

    $classes = "{$baseClasses} {$stateClasses}";
@endphp

<div
    x-data="{
        open: false,
        value: @if($wireModel) @entangle($attributes->wire('model')) @else '' @endif,
        currentYear: new Date().getFullYear(),
        currentMonth: new Date().getMonth(),
        minDate: '{{ $minDate }}',
        maxDate: '{{ $maxDate }}',
        monthNames: [
            '{{ __('January') }}', '{{ __('February') }}', '{{ __('March') }}',
            '{{ __('April') }}', '{{ __('May') }}', '{{ __('June') }}',
            '{{ __('July') }}', '{{ __('August') }}', '{{ __('September') }}',
            '{{ __('October') }}', '{{ __('November') }}', '{{ __('December') }}'
        ],
        dayNames: [
            '{{ __('Su') }}', '{{ __('Mo') }}', '{{ __('Tu') }}',
            '{{ __('We') }}', '{{ __('Th') }}', '{{ __('Fr') }}', '{{ __('Sa') }}'
        ],
        init() {
            if (this.value) {
                const parts = this.value.split('-');
                if (parts.length === 3) {
                    this.currentYear = parseInt(parts[0], 10);
                    this.currentMonth = parseInt(parts[1], 10) - 1;
                }
            } else if (this.minDate) {
                const parts = this.minDate.split('-');
                if (parts.length === 3) {
                    this.currentYear = parseInt(parts[0], 10);
                    this.currentMonth = parseInt(parts[1], 10) - 1;
                }
            }

            this.$watch('value', (val) => {
                if (val) {
                    const parts = val.split('-');
                    if (parts.length === 3) {
                        this.currentYear = parseInt(parts[0], 10);
                        this.currentMonth = parseInt(parts[1], 10) - 1;
                    }
                }
            });
        },
        get formattedLabel() {
            if (!this.value) {
                return '{{ $placeholder ?? __('Select Trip Date...') }}';
            }
            const parts = this.value.split('-');
            if (parts.length !== 3) return this.value;
            const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            return d.toLocaleDateString(undefined, {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },
        get daysInMonth() {
            const year = this.currentYear;
            const month = this.currentMonth;
            const firstDayIndex = new Date(year, month, 1).getDay();
            const totalDays = new Date(year, month + 1, 0).getDate();
            const days = [];

            // Padding before month
            for (let i = 0; i < firstDayIndex; i++) {
                days.push({ day: null, dateStr: null, isCurrentMonth: false, disabled: true });
            }

            // Current month days
            for (let i = 1; i <= totalDays; i++) {
                const mStr = String(month + 1).padStart(2, '0');
                const dStr = String(i).padStart(2, '0');
                const dateStr = `${year}-${mStr}-${dStr}`;

                let isDisabled = false;
                if (this.minDate && dateStr < this.minDate) isDisabled = true;
                if (this.maxDate && dateStr > this.maxDate) isDisabled = true;

                days.push({
                    day: i,
                    dateStr: dateStr,
                    isCurrentMonth: true,
                    disabled: isDisabled,
                    isSelected: this.value === dateStr,
                    isToday: dateStr === new Date().toISOString().split('T')[0]
                });
            }

            return days;
        },
        prevMonth() {
            if (this.currentMonth === 0) {
                this.currentMonth = 11;
                this.currentYear--;
            } else {
                this.currentMonth--;
            }
        },
        nextMonth() {
            if (this.currentMonth === 11) {
                this.currentMonth = 0;
                this.currentYear++;
            } else {
                this.currentMonth++;
            }
        },
        selectDate(dateStr) {
            if ({{ $disabled ? 'true' : 'false' }}) return;
            this.value = dateStr;
            this.open = false;
            this.$dispatch('input', dateStr);
            this.$dispatch('change', dateStr);
        },
        setPreset(daysFromNow) {
            const d = new Date();
            d.setDate(d.getDate() + daysFromNow);
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const dateStr = `${y}-${m}-${day}`;
            if (!this.minDate || dateStr >= this.minDate) {
                this.selectDate(dateStr);
            }
        }
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative w-full"
>
    <!-- Display Button Trigger -->
    <button
        type="button"
        x-on:click="if (!{{ $disabled ? 'true' : 'false' }}) { open = !open; }"
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->except(['wire:model', 'min', 'max', 'presets'])->merge(['class' => $classes . ' px-3.5 flex items-center justify-between gap-2.5 text-left cursor-pointer select-none']) }}
        :class="{ 'ring-2 ring-indigo-500/20 border-indigo-500 dark:border-indigo-400': open }"
    >
        <div class="flex items-center gap-2.5 truncate">
            <i class="fa-solid fa-calendar-days text-xs text-indigo-500 dark:text-indigo-400 shrink-0"></i>
            <span
                class="truncate"
                :class="value ? 'font-bold text-slate-900 dark:text-white' : 'text-slate-400 dark:text-zinc-500'"
                x-text="formattedLabel"
            ></span>
        </div>
        <i class="fa-solid fa-chevron-down text-[11px] text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': open }"></i>
    </button>

    <!-- Interactive Calendar Popover -->
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1 scale-98"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-98"
        class="absolute left-0 z-50 mt-1.5 w-72 sm:w-80 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl p-4 space-y-3 animate-fade-in"
        style="display: none;"
    >
        <!-- Calendar Header (Month / Year Navigation) -->
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-zinc-800">
            <button
                type="button"
                x-on:click="prevMonth"
                class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 flex items-center justify-center text-slate-600 dark:text-slate-300 text-xs transition cursor-pointer"
            >
                <i class="fa-solid fa-chevron-left text-[10px]"></i>
            </button>

            <span class="font-extrabold text-xs sm:text-sm text-slate-900 dark:text-white" x-text="`${monthNames[currentMonth]} ${currentYear}`"></span>

            <button
                type="button"
                x-on:click="nextMonth"
                class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 flex items-center justify-center text-slate-600 dark:text-slate-300 text-xs transition cursor-pointer"
            >
                <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </button>
        </div>

        <!-- Quick Presets -->
        @if ($presets)
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[10px]">
                <button
                    type="button"
                    x-on:click="setPreset(0)"
                    class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold transition cursor-pointer shrink-0"
                >
                    {{ __('Today') }}
                </button>
                <button
                    type="button"
                    x-on:click="setPreset(1)"
                    class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold transition cursor-pointer shrink-0"
                >
                    {{ __('Tomorrow') }}
                </button>
                <button
                    type="button"
                    x-on:click="setPreset(2)"
                    class="px-2 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 font-bold transition cursor-pointer shrink-0"
                >
                    {{ __('+2 Days') }}
                </button>
            </div>
        @endif

        <!-- Day Names Grid Header -->
        <div class="grid grid-cols-7 gap-1 text-center font-bold text-[11px] text-slate-400 dark:text-zinc-500">
            <template x-for="day in dayNames" :key="day">
                <span x-text="day" class="py-1"></span>
            </template>
        </div>

        <!-- Day Cells Grid -->
        <div class="grid grid-cols-7 gap-1 text-center">
            <template x-for="(cell, index) in daysInMonth" :key="index">
                <div>
                    <template x-if="cell.day">
                        <button
                            type="button"
                            x-on:click="if (!cell.disabled) selectDate(cell.dateStr)"
                            :disabled="cell.disabled"
                            class="w-full h-8 rounded-xl font-bold text-xs flex items-center justify-center transition-all cursor-pointer"
                            :class="{
                                'bg-indigo-600 text-white shadow-xs scale-105': cell.isSelected,
                                'text-slate-300 dark:text-zinc-700 cursor-not-allowed opacity-40': cell.disabled,
                                'text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-zinc-800': !cell.disabled && !cell.isSelected,
                                'border border-indigo-400 dark:border-indigo-600': cell.isToday && !cell.isSelected
                            }"
                        >
                            <span x-text="cell.day"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
