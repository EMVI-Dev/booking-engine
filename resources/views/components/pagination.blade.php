@props(['paginator'])

@if ($paginator && $paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row items-center justify-between gap-4 py-3 select-none transition-all duration-200']) }}>
        <!-- Results Count Summary -->
        <div class="text-xs text-[#60646C] dark:text-slate-400 font-normal flex items-center gap-1">
            <span>{{ __('Showing') }}</span>
            <span class="font-medium text-[#1C2024] dark:text-white">{{ $paginator->firstItem() ?? 0 }}</span>
            <span>{{ __('to') }}</span>
            <span class="font-medium text-[#1C2024] dark:text-white">{{ $paginator->lastItem() ?? 0 }}</span>
            <span>{{ __('of') }}</span>
            <span class="font-medium text-[#1C2024] dark:text-white">{{ $paginator->total() }}</span>
            <span>{{ __('results') }}</span>
        </div>

        <!-- Pagination Action Controls -->
        <div class="flex items-center gap-1.5 transition-opacity duration-200" wire:loading.class="opacity-70">
            {{-- Previous Page Button --}}
            @if ($paginator->onFirstPage())
                <span class="h-8 px-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] text-xs font-normal text-[#8B8D98] bg-[#F4F5F6]/50 dark:bg-[#141821]/50 cursor-not-allowed flex items-center gap-1.5 shadow-none">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    <span class="hidden sm:inline">{{ __('Previous') }}</span>
                </span>
            @else
                @if (method_exists($paginator, 'getPageName'))
                    <button
                        type="button"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        class="h-8 px-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-xs font-medium text-[#1C2024] dark:text-slate-200 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] transition shadow-none cursor-pointer flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        <span class="hidden sm:inline">{{ __('Previous') }}</span>
                    </button>
                @else
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        class="h-8 px-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-xs font-medium text-[#1C2024] dark:text-slate-200 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] transition shadow-none cursor-pointer flex items-center gap-1.5"
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
                        <span class="h-8 min-w-[32px] px-2.5 inline-flex items-center justify-center rounded-[6px] bg-[#FFEF4D] text-[#12181E] font-medium text-xs shadow-none">
                            {{ $page }}
                        </span>
                    @else
                        @if (method_exists($paginator, 'getPageName'))
                            <button
                                type="button"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                wire:loading.attr="disabled"
                                class="h-8 min-w-[32px] px-2.5 inline-flex items-center justify-center rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-[#1C2024] dark:text-slate-300 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] font-medium text-xs transition shadow-none cursor-pointer disabled:opacity-50"
                            >
                                {{ $page }}
                            </button>
                        @else
                            <a
                                href="{{ $url }}"
                                class="h-8 min-w-[32px] px-2.5 inline-flex items-center justify-center rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-[#1C2024] dark:text-slate-300 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] font-medium text-xs transition shadow-none cursor-pointer"
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
                        class="h-8 px-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-xs font-medium text-[#1C2024] dark:text-slate-200 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] transition shadow-none cursor-pointer flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <span class="hidden sm:inline">{{ __('Next') }}</span>
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                @else
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        class="h-8 px-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-xs font-medium text-[#1C2024] dark:text-slate-200 hover:bg-[#F4F5F6] dark:hover:bg-[#1E2433] transition shadow-none cursor-pointer flex items-center gap-1.5"
                    >
                        <span class="hidden sm:inline">{{ __('Next') }}</span>
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </a>
                @endif
            @else
                <span class="h-8 px-2.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] text-xs font-normal text-[#8B8D98] bg-[#F4F5F6]/50 dark:bg-[#141821]/50 cursor-not-allowed flex items-center gap-1.5 shadow-none">
                    <span class="hidden sm:inline">{{ __('Next') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </span>
            @endif
        </div>
    </div>
@endif
