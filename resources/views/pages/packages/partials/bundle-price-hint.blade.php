@php
    $hasActivities = $this->selectedProducts !== [] && $this->bundledSeparateTotal > 0;
@endphp

@if ($hasActivities && $this->showsBundlePriceHint)
    @php
        $packagePrice = (float) $this->price;
        $separateFormatted = number_format($this->bundledSeparateTotal, 0, ',', '.');
        $packageFormatted = number_format($packagePrice, 0, ',', '.');
        $savings = $this->bundledSeparateTotal - $packagePrice;
        $savingsFormatted = number_format(abs($savings), 0, ',', '.');
        $rangeLowFormatted = number_format($this->bundledPriceRangeLow, 0, ',', '.');
        $rangeHighFormatted = number_format($this->bundledPriceRangeHigh, 0, ',', '.');
        $suggestedFormatted = number_format($this->bundledSuggestedPrice, 0, ',', '.');
        $isWarning = $this->showsBundlePriceWarning;
    @endphp

    <div @class([
        'rounded-2xl p-3.5 sm:p-4 space-y-3.5',
        'bg-amber-100 dark:bg-amber-500/15 border-2 border-amber-400 dark:border-amber-400/60 text-amber-950 dark:text-amber-100' => $isWarning,
        'bg-op-muted border border-op-line text-op-ink' => !$isWarning,
    ])>
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span @class([
                    'w-8 h-8 rounded-xl flex items-center justify-center shrink-0',
                    'bg-amber-400 text-amber-950' => $isWarning,
                    'bg-brand-400 text-brand-foreground' => !$isWarning,
                ])>
                    <i @class([
                        'text-sm',
                        'fa-solid fa-triangle-exclamation' => $isWarning,
                        'fa-solid fa-lightbulb' => !$isWarning,
                    ])></i>
                </span>
                <p @class([
                    'text-[11px] font-bold uppercase tracking-wider truncate',
                    'text-amber-800 dark:text-amber-200' => $isWarning,
                    'text-op-subtle' => !$isWarning,
                ])>
                    {{ $isWarning ? __('Price looks high') : __('Smart price suggestion') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2">
            <div @class([
                'rounded-xl px-2.5 py-2 space-y-0.5',
                'bg-amber-50/80 dark:bg-amber-950/30' => $isWarning,
                'bg-op-surface border border-op-line' => !$isWarning,
            ])>
                <p class="text-[10px] font-bold uppercase tracking-wider text-op-subtle">{{ __('Separate') }}</p>
                <p class="text-xs sm:text-sm font-bold tabular-nums truncate">Rp {{ $separateFormatted }}</p>
            </div>
            <div @class([
                'rounded-xl px-2.5 py-2 space-y-0.5',
                'bg-amber-50/80 dark:bg-amber-950/30' => $isWarning,
                'bg-op-surface border border-op-line' => !$isWarning,
            ])>
                <p class="text-[10px] font-bold uppercase tracking-wider text-op-subtle">{{ __('Package') }}</p>
                <p class="text-xs sm:text-sm font-bold tabular-nums truncate">Rp {{ $packageFormatted }}</p>
            </div>
            <div @class([
                'rounded-xl px-2.5 py-2 space-y-0.5',
                'bg-amber-50/80 dark:bg-amber-950/30' => $isWarning,
                'bg-op-surface border border-op-line' => !$isWarning,
            ])>
                <p class="text-[10px] font-bold uppercase tracking-wider text-op-subtle">
                    {{ $savings >= 0 ? __('Guest saves') : __('Over by') }}
                </p>
                <p @class([
                    'text-xs sm:text-sm font-bold tabular-nums truncate',
                    'text-amber-900 dark:text-amber-100' => $isWarning || $savings < 0,
                    'text-op-ink' => !$isWarning && $savings >= 0,
                ])>
                    Rp {{ $savingsFormatted }}
                </p>
            </div>
        </div>

        @if ($isWarning)
            <p class="text-xs leading-relaxed">
                {{ __('Guests may prefer booking these activities one by one. Try a package price in the recommended range.') }}
            </p>
        @endif

        <div @class([
            'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl px-3 py-2.5',
            'bg-amber-50/90 dark:bg-amber-950/40' => $isWarning,
            'bg-op-surface border border-op-line' => !$isWarning,
        ])>
            <div class="min-w-0 space-y-0.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-op-subtle">
                    {{ __('Recommended') }}
                </p>
                <p class="text-xs font-semibold tabular-nums">
                    Rp {{ $rangeLowFormatted }} – Rp {{ $rangeHighFormatted }}
                </p>
                <p class="text-sm font-bold tabular-nums">
                    {{ __('Suggested: Rp :price', ['price' => $suggestedFormatted]) }}
                </p>
            </div>

            <button type="button" wire:click="applySuggestedBundlePrice" @class([
                'h-11 sm:h-9 px-3.5 w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-xl text-xs font-bold transition cursor-pointer shrink-0',
                'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-amber-950' => $isWarning,
                'bg-brand-400 hover:bg-brand-500 text-brand-foreground' => !$isWarning,
            ])>
                <i class="fa-solid fa-wand-magic-sparkles text-[10px]"></i>
                <span>{{ __('Use suggested price') }}</span>
            </button>
        </div>
    </div>
@elseif (!$hasActivities)
    <div
        class="h-full rounded-2xl border border-dashed border-op-line bg-op-muted/40 px-3.5 py-3 text-center text-xs leading-relaxed text-op-subtle flex items-center justify-center">
        {{ __('Add activities above and we’ll suggest a guest-friendly package price.') }}
    </div>
@endif
