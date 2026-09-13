@props([
    'hasCatalog',
    'selected',
    'catalog',
    'remainingCount',
    'requiresSearch',
    'search',
    'emptyCopy',
])

<div class="space-y-4">
    @if ($hasCatalog)
        <x-search-input
            wire:model.live.debounce.300ms="bundleSearch"
            :placeholder="__('Search activities to add')"
            autocomplete="off"
        />

        @if ($selected->isNotEmpty())
            <div class="space-y-2">
                <p class="text-[10px] font-bold uppercase tracking-wider text-op-subtle">
                    {{ __('In this package (:count)', ['count' => $selected->count()]) }}
                </p>
                <div class="space-y-2">
                    @foreach ($selected as $prod)
                        <div
                            wire:key="bundled-{{ $prod->id }}"
                            class="p-3 sm:p-3.5 rounded-2xl border border-brand-400/70 bg-brand-400/10 dark:bg-brand-400/5 ring-1 ring-brand-400/25 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="text-sm sm:text-xs font-bold text-op-ink truncate">{{ $prod->name }}</p>
                                <p class="text-[10px] text-op-subtle">
                                    {{ $prod->category ?? __('Item') }} &bull; {{ __('Max :count/day', ['count' => $prod->capacity_per_day]) }}
                                </p>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-2 w-full sm:w-auto shrink-0">
                                <div class="flex items-center gap-1.5 bg-op-surface px-2 py-1 rounded-xl border border-op-line">
                                    <span class="text-[10px] font-bold text-op-subtle uppercase">{{ __('Qty:') }}</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max="1000"
                                        wire:model.live="selectedProducts.{{ $prod->id }}"
                                        class="w-14 h-9 text-sm text-center font-bold rounded-md border-0 bg-op-muted focus:ring-1 focus:ring-brand-400"
                                    />
                                </div>
                                <button
                                    type="button"
                                    wire:click="toggleProductSelection('{{ $prod->id }}')"
                                    class="h-11 w-11 sm:h-9 sm:w-9 rounded-xl border border-op-line text-op-subtle hover:text-rose-600 hover:border-rose-300 flex items-center justify-center cursor-pointer"
                                    title="{{ __('Remove') }}"
                                >
                                    <i class="fa-solid fa-xmark text-xs"></i>
                                    <span class="sr-only">{{ __('Remove :name', ['name' => $prod->name]) }}</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-xs text-op-subtle">
                {{ __('Nothing in this package yet. Search or tap an activity to add it.') }}
            </p>
        @endif

        @if ($requiresSearch)
            <p class="text-xs text-op-subtle">
                {{ trans_choice(':count more activity in your catalog. Search by name to add it.|:count more activities in your catalog. Search by name to add them.', $remainingCount, ['count' => $remainingCount]) }}
            </p>
        @elseif ($catalog->isNotEmpty())
            <div class="space-y-2">
                @if (trim((string) $search) !== '')
                    <p class="text-[10px] font-bold uppercase tracking-wider text-op-subtle">
                        {{ __('Matches') }}
                    </p>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 max-h-72 sm:max-h-80 overflow-y-auto overscroll-contain">
                    @foreach ($catalog as $prod)
                        <button
                            type="button"
                            wire:key="catalog-{{ $prod->id }}"
                            wire:click="toggleProductSelection('{{ $prod->id }}')"
                            class="p-3 sm:p-3.5 rounded-2xl border border-op-line bg-op-muted/60 hover:border-op-line-strong text-left flex items-center gap-3 cursor-pointer min-h-14"
                        >
                            <span class="h-9 w-9 rounded-xl border border-op-line bg-op-surface flex items-center justify-center shrink-0 text-op-subtle">
                                <i class="fa-solid fa-plus text-xs"></i>
                            </span>
                            <span class="min-w-0">
                                <span class="text-xs font-bold text-op-ink truncate block">{{ $prod->name }}</span>
                                <span class="text-[10px] text-op-subtle block">
                                    {{ $prod->category ?? __('Item') }} &bull; {{ __('Max :count/day', ['count' => $prod->capacity_per_day]) }}
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @elseif (trim((string) $search) !== '')
            <p class="text-xs text-op-subtle">
                {{ __('No activities match ":query".', ['query' => $search]) }}
            </p>
        @endif
    @else
        <div class="p-6 rounded-2xl border border-dashed border-op-line text-center text-xs text-op-subtle">
            {{ $emptyCopy }}
        </div>
    @endif
</div>
