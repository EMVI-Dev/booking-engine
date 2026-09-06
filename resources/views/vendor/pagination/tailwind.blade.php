{{--
    Shared paginator for both Livewire components and plain Blade pages.

    Every control is a real anchor carrying the page URL. Inside a Livewire component
    the `wire:click.prevent` handler takes over and paginates without a page load;
    outside one the directive is inert and the href navigates normally. Rendering
    Livewire-only buttons here would leave server-rendered pages (the storefront
    catalog) with controls that do nothing.
--}}
@php
    $pageName = $paginator->getPageName();
    $controlClasses = 'px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-800 hover:text-brand-700 dark:hover:text-brand-400 transition-all duration-150 cursor-pointer shadow-2xs flex items-center gap-1.5 active:scale-95';
    $disabledClasses = 'px-3 py-2 rounded-xl border border-slate-200/60 dark:border-zinc-800/80 text-xs font-bold text-slate-300 dark:text-zinc-600 bg-slate-50/50 dark:bg-zinc-900/50 cursor-not-allowed flex items-center gap-1.5';
    $pageClasses = 'h-9 min-w-[36px] px-3 inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-brand-700 dark:hover:text-brand-400 font-bold text-xs transition-all duration-150 cursor-pointer shadow-2xs active:scale-95';
@endphp

@if ($paginator->hasPages())
    <nav
        role="navigation"
        aria-label="{{ __('Page navigation') }}"
        class="flex flex-col sm:flex-row items-center justify-between gap-4 py-3 select-none transition-all duration-200"
    >
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
            {{-- Previous Page --}}
            @if ($paginator->onFirstPage())
                <span class="{{ $disabledClasses }}" aria-disabled="true">
                    <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                    <span class="hidden sm:inline">{{ __('Previous') }}</span>
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    wire:click.prevent="previousPage('{{ $pageName }}')"
                    wire:loading.attr="disabled"
                    class="{{ $controlClasses }}"
                    aria-label="{{ __('Go to previous page') }}"
                >
                    <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                    <span class="hidden sm:inline">{{ __('Previous') }}</span>
                </a>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 py-1 text-xs font-bold text-slate-400 dark:text-slate-500" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span
                                aria-current="page"
                                class="h-9 min-w-[36px] px-3 inline-flex items-center justify-center rounded-xl bg-brand-600 text-brand-foreground font-extrabold text-xs shadow-xs transition-all duration-150"
                            >
                                {{ $page }}
                            </span>
                        @else
                            <a
                                href="{{ $url }}"
                                wire:click.prevent="gotoPage({{ $page }}, '{{ $pageName }}')"
                                wire:loading.attr="disabled"
                                class="{{ $pageClasses }}"
                                aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                            >
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page --}}
            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    wire:click.prevent="nextPage('{{ $pageName }}')"
                    wire:loading.attr="disabled"
                    class="{{ $controlClasses }}"
                    aria-label="{{ __('Go to next page') }}"
                >
                    <span class="hidden sm:inline">{{ __('Next') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                </a>
            @else
                <span class="{{ $disabledClasses }}" aria-disabled="true">
                    <span class="hidden sm:inline">{{ __('Next') }}</span>
                    <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                </span>
            @endif
        </div>
    </nav>
@endif
