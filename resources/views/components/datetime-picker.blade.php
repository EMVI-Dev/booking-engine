@props([
    'placeholder' => null,
    'min' => null,
    'max' => null,
    'disabled' => false,
    'error' => false,
])

@php
    $wireModel = $attributes->wire('model')->value();
    $minDate = $min ?: date('Y-m-d');

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
        selectedDate: '',
        selectedHour: '12',
        selectedMinute: '00',
        currentYear: new Date().getFullYear(),
        currentMonth: new Date().getMonth(),
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
            this.parseValue(this.value);
            this.$watch('value', (val) => this.parseValue(val));
        },
        parseValue(val) {
            if (!val) {
                const now = new Date();
                const y = now.getFullYear();
                const m = String(now.getMonth() + 1).padStart(2, '0');
                const d = String(now.getDate()).padStart(2, '0');
                this.selectedDate = `${y}-${m}-${d}`;
                this.selectedHour = String(now.getHours()).padStart(2, '0');
                this.selectedMinute = String(now.getMinutes()).padStart(2, '0');
                return;
            }
            const cleanVal = val.replace('T', ' ');
            const parts = cleanVal.split(' ');
            if (parts.length >= 1) {
                this.selectedDate = parts[0];
                const dateParts = parts[0].split('-');
                if (dateParts.length === 3) {
                    this.currentYear = parseInt(dateParts[0], 10);
                    this.currentMonth = parseInt(dateParts[1], 10) - 1;
                }
            }
            if (parts.length >= 2) {
                const timeParts = parts[1].split(':');
                if (timeParts.length >= 2) {
                    this.selectedHour = timeParts[0].padStart(2, '0');
                    this.selectedMinute = timeParts[1].padStart(2, '0');
                }
            }
        },
        get formattedLabel() {
            if (!this.value) {
                return '{{ $placeholder ?? __('Select Date & Time...') }}';
            }
            const cleanVal = this.value.replace('T', ' ');
            const parts = cleanVal.split(' ');
            if (parts.length < 1) return this.value;

            const dateParts = parts[0].split('-');
            if (dateParts.length !== 3) return this.value;

            const d = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
            const dateFormatted = d.toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });

            const timeFormatted = parts[1] ? parts[1].substring(0, 5) : `${this.selectedHour}:${this.selectedMinute}`;
            return `${dateFormatted}, ${timeFormatted}`;
        },
        get daysInMonth() {
            const year = this.currentYear;
            const month = this.currentMonth;
            const firstDayIndex = new Date(year, month, 1).getDay();
            const totalDays = new Date(year, month + 1, 0).getDate();
            const days = [];

            for (let i = 0; i < firstDayIndex; i++) {
                days.push({ day: null, dateStr: null, isCurrentMonth: false, disabled: true });
            }

            for (let i = 1; i <= totalDays; i++) {
                const mStr = String(month + 1).padStart(2, '0');
                const dStr = String(i).padStart(2, '0');
                const dateStr = `${year}-${mStr}-${dStr}`;

                days.push({
                    day: i,
                    dateStr: dateStr,
                    isCurrentMonth: true,
                    disabled: false,
                    isSelected: this.selectedDate === dateStr,
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
        selectDay(dateStr) {
            this.selectedDate = dateStr;
            this.updateValue();
        },
        updateValue() {
            if (!this.selectedDate) return;
            const fullValue = `${this.selectedDate}T${this.selectedHour}:${this.selectedMinute}`;
            this.value = fullValue;
            this.$dispatch('input', fullValue);
            this.$dispatch('change', fullValue);
        },
        setPreset(preset) {
            const now = new Date();
            if (preset === 'now') {
                // now
            } else if (preset === '+1w') {
                now.setDate(now.getDate() + 7);
            } else if (preset === '+1m') {
                now.setMonth(now.getMonth() + 1);
            }
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            this.selectedDate = `${y}-${m}-${d}`;
            this.selectedHour = String(now.getHours()).padStart(2, '0');
            this.selectedMinute = String(now.getMinutes()).padStart(2, '0');
            this.currentYear = y;
            this.currentMonth = now.getMonth();
            this.updateValue();
            this.open = false;
        },
        placement: 'bottom',
        togglePicker() {
            if ({{ $disabled ? 'true' : 'false' }}) return;
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => {
                    const rect = this.$el.getBoundingClientRect();
                    const popoverHeight = 360;
                    const spaceBelow = window.innerHeight - rect.bottom;
                    if (spaceBelow < popoverHeight && rect.top > popoverHeight) {
                        this.placement = 'top';
                    } else {
                        this.placement = 'bottom';
                    }
                });
            }
        }
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative w-full"
>
    <!-- Trigger Button -->
    <button
        type="button"
        x-on:click="togglePicker()"
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->except(['wire:model', 'min', 'max'])->merge(['class' => $classes . ' px-3.5 flex items-center justify-between gap-2 text-left cursor-pointer select-none']) }}
        :class="{ 'ring-2 ring-purple-500/20 border-purple-500 dark:border-purple-400': open }"
    >
        <div class="flex items-center gap-2.5 truncate">
            <i class="fa-solid fa-calendar-clock text-xs text-purple-500 dark:text-purple-400 shrink-0"></i>
            <span
                class="truncate text-xs font-mono font-medium"
                :class="value ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-zinc-500'"
                x-text="formattedLabel"
            ></span>
        </div>
        <i class="fa-solid fa-chevron-down text-[11px] text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': open }"></i>
    </button>

    <!-- Dropdown Popover -->
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1 scale-98"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-98"
        :class="placement === 'top' ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
        class="absolute right-0 z-50 w-72 sm:w-80 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl p-4 space-y-3 animate-fade-in"
        style="display: none;"
    >
        <!-- Calendar Month Navigation Header -->
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-zinc-800">
            <button
                type="button"
                x-on:click="prevMonth"
                class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 flex items-center justify-center text-slate-600 dark:text-slate-300 text-xs transition cursor-pointer"
            >
                <i class="fa-solid fa-chevron-left text-[10px]"></i>
            </button>

            <span class="font-bold text-xs text-slate-900 dark:text-white" x-text="`${monthNames[currentMonth]} ${currentYear}`"></span>

            <button
                type="button"
                x-on:click="nextMonth"
                class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 flex items-center justify-center text-slate-600 dark:text-slate-300 text-xs transition cursor-pointer"
            >
                <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </button>
        </div>

        <!-- Quick Presets -->
        <div class="flex items-center gap-1.5 overflow-x-auto text-[10px]">
            <button
                type="button"
                x-on:click="setPreset('now')"
                class="px-2 py-1 rounded-md bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold transition cursor-pointer shrink-0"
            >
                {{ __('Now') }}
            </button>
            <button
                type="button"
                x-on:click="setPreset('+1w')"
                class="px-2 py-1 rounded-md bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold transition cursor-pointer shrink-0"
            >
                {{ __('+1 Week') }}
            </button>
            <button
                type="button"
                x-on:click="setPreset('+1m')"
                class="px-2 py-1 rounded-md bg-purple-50 dark:bg-purple-950/70 hover:bg-purple-100 text-purple-700 dark:text-purple-300 font-bold transition cursor-pointer shrink-0"
            >
                {{ __('+1 Month') }}
            </button>
        </div>

        <!-- Day Names Header -->
        <div class="grid grid-cols-7 gap-1 text-center font-bold text-[10px] text-slate-400 dark:text-zinc-500">
            <template x-for="day in dayNames" :key="day">
                <span x-text="day" class="py-0.5"></span>
            </template>
        </div>

        <!-- Days Grid -->
        <div class="grid grid-cols-7 gap-1 text-center">
            <template x-for="(cell, index) in daysInMonth" :key="index">
                <div>
                    <template x-if="cell.day">
                        <button
                            type="button"
                            x-on:click="selectDay(cell.dateStr)"
                            class="w-full h-7 rounded-lg font-bold text-xs flex items-center justify-center transition-all cursor-pointer"
                            :class="{
                                'bg-purple-600 text-white shadow-xs scale-105': cell.isSelected,
                                'text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-zinc-800': !cell.isSelected,
                                'border border-purple-400 dark:border-purple-600': cell.isToday && !cell.isSelected
                            }"
                        >
                            <span x-text="cell.day"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        <!-- Time Picker Section -->
        <div class="pt-2 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-2">
            <span class="text-[11px] font-bold text-slate-500 dark:text-zinc-400 flex items-center gap-1">
                <i class="fa-solid fa-clock text-[10px] text-purple-500"></i>
                {{ __('Time (24h)') }}
            </span>
            <div class="flex items-center gap-1 font-mono text-xs">
                <!-- Hour Select -->
                <select
                    x-model="selectedHour"
                    x-on:change="updateValue()"
                    class="h-7 px-1.5 rounded-lg bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-900 dark:text-white font-bold text-xs focus:ring-1 focus:ring-purple-500 cursor-pointer"
                >
                    <template x-for="h in Array.from({length: 24}, (_, i) => String(i).padStart(2, '0'))" :key="h">
                        <option :value="h" x-text="h"></option>
                    </template>
                </select>
                <span class="font-bold text-slate-400">:</span>
                <!-- Minute Select -->
                <select
                    x-model="selectedMinute"
                    x-on:change="updateValue()"
                    class="h-7 px-1.5 rounded-lg bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-900 dark:text-white font-bold text-xs focus:ring-1 focus:ring-purple-500 cursor-pointer"
                >
                    <template x-for="m in ['00','05','10','15','20','25','30','35','40','45','50','55']" :key="m">
                        <option :value="m" x-text="m"></option>
                    </template>
                </select>
            </div>
        </div>

        <!-- Done CTA Button -->
        <div class="pt-1">
            <button
                type="button"
                x-on:click="open = false"
                class="w-full py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs transition cursor-pointer shadow-xs"
            >
                {{ __('Done') }}
            </button>
        </div>
    </div>
</div>
