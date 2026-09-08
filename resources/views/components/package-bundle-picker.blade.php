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
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    {{ __('In this package (:count)', ['count' => $selected->count()]) }}
                </p>
                <div class="space-y-2">
                    @foreach ($selected as $prod)
                        <div
                            wire:key="bundled-{{ $prod->id }}"
                            class="p-3.5 rounded-2xl border border-sky-500 bg-sky-50/50 dark:bg-sky-950/30 ring-1 ring-sky-500/20 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $prod->name }}</p>
                                <p class="text-[10px] text-slate-400">
                                    {{ $prod->category ?? __('Item') }} &bull; {{ __('Max :count/day', ['count' => $prod->capacity_per_day]) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <div class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 px-2 py-1 rounded-xl border border-slate-200 dark:border-zinc-700">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">{{ __('Qty:') }}</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max="1000"
                                        wire:model="selectedProducts.{{ $prod->id }}"
                                        class="w-14 h-9 text-sm text-center font-bold rounded-md border-0 bg-slate-50 dark:bg-zinc-800 focus:ring-1 focus:ring-sky-500"
                                    />
                                </div>
                                <button
                                    type="button"
                                    wire:click="toggleProductSelection('{{ $prod->id }}')"
                                    class="h-9 w-9 rounded-xl border border-slate-200 dark:border-zinc-700 text-slate-500 hover:text-rose-600 hover:border-rose-300 flex items-center justify-center cursor-pointer"
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
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Nothing in this package yet. Search or tap an activity to add it.') }}
            </p>
        @endif

        @if ($requiresSearch)
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ trans_choice(':count more activity in your catalog. Search by name to add it.|:count more activities in your catalog. Search by name to add them.', $remainingCount, ['count' => $remainingCount]) }}
            </p>
        @elseif ($catalog->isNotEmpty())
            <div class="space-y-2">
                @if (trim((string) $search) !== '')
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        {{ __('Matches') }}
                    </p>
                @endif
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-80 overflow-y-auto">
                    @foreach ($catalog as $prod)
                        <button
                            type="button"
                            wire:key="catalog-{{ $prod->id }}"
                            wire:click="toggleProductSelection('{{ $prod->id }}')"
                            class="p-3.5 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:border-slate-300 text-left flex items-center gap-3 cursor-pointer min-h-14"
                        >
                            <span class="h-9 w-9 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 flex items-center justify-center shrink-0 text-slate-400">
                                <i class="fa-solid fa-plus text-xs"></i>
                            </span>
                            <span class="min-w-0">
                                <span class="text-xs font-bold text-slate-900 dark:text-white truncate block">{{ $prod->name }}</span>
                                <span class="text-[10px] text-slate-400 block">
                                    {{ $prod->category ?? __('Item') }} &bull; {{ __('Max :count/day', ['count' => $prod->capacity_per_day]) }}
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @elseif (trim((string) $search) !== '')
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('No activities match ":query".', ['query' => $search]) }}
            </p>
        @endif
    @else
        <div class="p-6 rounded-2xl border border-dashed border-slate-200 dark:border-zinc-800 text-center text-xs text-slate-400">
            {{ $emptyCopy }}
        </div>
    @endif
</div>
