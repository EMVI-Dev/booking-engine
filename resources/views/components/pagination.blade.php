@props(['paginator'])

@if ($paginator && $paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row items-center justify-between gap-4 py-3 select-none transition-all duration-200']) }}>
        <!-- Results Count Summary -->
        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1">
            <span>{{ __('Showing') }}</span>
            <span class="font-extrabold text-slate-900 dark:text-white">{{ $paginator->firstItem() ?? 0 }}</span>
            <span>{{ __('to') }}</span>
            <span class="font-extrabold text-slate-900 dark:text-white">{{ $paginator->lastItem() ?? 0 }}</span>
            <span>{{ __('of') }}</span>
            <span class="font-extrabold text-slate-900 dark:text-white">{{ $paginator->total() }}</span>
            <span>{{ __('results') }}</span>
        </div>

        <!-- Pagination Action Controls -->
        <div class="flex items-center gap-1.5 transition-opacity duration-200" wire:loading.class="opacity-70">
            {{-- Previous Page Button --}}
            @if ($paginator->onFirstPage())
                <span class="px-3 py-2 rounded-xl border border-slate-200/60 dark:border-zinc-800/80 text-xs font-bold text-slate-300 dark:text-zinc-600 bg-slate-50/50 dark:bg-zinc-900/50 cursor-not-allowed flex items-center gap-1.5">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    <span class="hidden sm:inline">{{ __('Previous') }}</span>
                </span>
            @else
                @if (method_exists($paginator, 'getPageName'))
                    <button
                        type="button"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        class="px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-800 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all duration-150 cursor-pointer shadow-2xs flex items-center gap-1.5 active:scale-95 disabled:opacity-50"
                    >
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        <span class="hidden sm:inline">{{ __('Previous') }}</span>
                    </button>
                @else
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        class="px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-800 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all duration-150 cursor-pointer shadow-2xs flex items-center gap-1.5 active:scale-95"
                    >
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        <span class="hidden sm:inline">{{ __('Previous') }}</span>
                    </a>
                @endif
            @endif

            {{-- Pagination Links --}}
            @if (method_exists($paginator, 'getUrlRange'))
                @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="h-9 min-w-[36px] px-3 inline-flex items-center justify-center rounded-xl bg-indigo-600 text-white font-extrabold text-xs shadow-xs transition-all duration-150">
                            {{ $page }}
                        </span>
                    @else
                        @if (method_exists($paginator, 'getPageName'))
                            <button
                                type="button"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                wire:loading.attr="disabled"
                                class="h-9 min-w-[36px] px-3 inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-indigo-600 dark:hover:text-indigo-400 font-bold text-xs transition-all duration-150 cursor-pointer shadow-2xs active:scale-95 disabled:opacity-50"
                            >
                                {{ $page }}
                            </button>
                        @else
                            <a
                                href="{{ $url }}"
                                class="h-9 min-w-[36px] px-3 inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-indigo-600 dark:hover:text-indigo-400 font-bold text-xs transition-all duration-150 cursor-pointer shadow-2xs active:scale-95"
                            >
                                {{ $page }}
                            </a>
                        @endif
                    @endif
                @endforeach
            @endif

            {{-- Next Page Button --}}
            @if ($paginator->hasMorePages())
                @if (method_exists($paginator, 'getPageName'))
                    <button
                        type="button"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        class="px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-800 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all duration-150 cursor-pointer shadow-2xs flex items-center gap-1.5 active:scale-95 disabled:opacity-50"
                    >
                        <span class="hidden sm:inline">{{ __('Next') }}</span>
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                @else
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        class="px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-800 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all duration-150 cursor-pointer shadow-2xs flex items-center gap-1.5 active:scale-95"
                    >
                        <span class="hidden sm:inline">{{ __('Next') }}</span>
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </a>
                @endif
            @else
                <span class="px-3 py-2 rounded-xl border border-slate-200/60 dark:border-zinc-800/80 text-xs font-bold text-slate-300 dark:text-zinc-600 bg-slate-50/50 dark:bg-zinc-900/50 cursor-not-allowed flex items-center gap-1.5">
                    <span class="hidden sm:inline">{{ __('Next') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </span>
            @endif
        </div>
    </div>
@endif
