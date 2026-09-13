@props([
    'coverUrl' => null,
    'galleryUrls' => [],
    'alt' => '',
])

@php
    /** @var list<string> $images */
    $images = collect([$coverUrl])
        ->filter()
        ->merge(collect($galleryUrls ?? [])->filter())
        ->values()
        ->all();
    $thumbOffset = filled($coverUrl) ? 1 : 0;
@endphp

@if (count($images) > 0)
    <div
        x-data="{
            open: false,
            index: 0,
            images: @js($images),
            openAt(i) {
                this.index = i;
                this.open = true;
            },
            close() {
                this.open = false;
            },
            prev() {
                this.index = (this.index - 1 + this.images.length) % this.images.length;
            },
            next() {
                this.index = (this.index + 1) % this.images.length;
            },
        }"
        @keydown.escape.window="if (open) close()"
        class="overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2 p-2"
    >
        @if ($coverUrl)
            <button
                type="button"
                @click="openAt(0)"
                class="group relative aspect-video sm:aspect-21/9 w-full rounded-2xl overflow-hidden bg-slate-100 dark:bg-zinc-800 cursor-zoom-in focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
            >
                <img
                    src="{{ $coverUrl }}"
                    alt="{{ $alt }}"
                    fetchpriority="high"
                    decoding="async"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
                <span
                    class="absolute bottom-2.5 right-2.5 inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-black/55 text-white text-[10px] font-bold backdrop-blur-sm">
                    <i class="fa-solid fa-expand text-[9px]"></i>
                    {{ __('View') }}
                </span>
            </button>
        @endif

        @if (! empty($galleryUrls))
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 {{ $coverUrl ? 'pt-1' : '' }}">
                @foreach ($galleryUrls as $gIndex => $gUrl)
                    <button
                        type="button"
                        @click="openAt({{ $thumbOffset + $gIndex }})"
                        class="aspect-video rounded-xl overflow-hidden bg-slate-100 dark:bg-zinc-800 border border-slate-200/60 dark:border-zinc-700 cursor-zoom-in focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    >
                        <img
                            src="{{ $gUrl }}"
                            alt="{{ $alt }}"
                            loading="lazy"
                            decoding="async"
                            class="w-full h-full object-cover hover:scale-105 transition-transform"
                        />
                    </button>
                @endforeach
            </div>
        @endif

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-950/90 backdrop-blur-sm"
            @click.self="close()"
            role="dialog"
            aria-modal="true"
            :aria-label="{{ json_encode(__('Photo gallery')) }}"
        >
            <button
                type="button"
                @click="close()"
                class="absolute top-3 right-3 sm:top-5 sm:right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer"
                title="{{ __('Close') }}"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

            @if (count($images) > 1)
                <button
                    type="button"
                    @click.stop="prev()"
                    class="absolute left-2 sm:left-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer"
                    title="{{ __('Previous') }}"
                >
                    <i class="fa-solid fa-chevron-left text-sm"></i>
                </button>
                <button
                    type="button"
                    @click.stop="next()"
                    class="absolute right-2 sm:right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer"
                    title="{{ __('Next') }}"
                >
                    <i class="fa-solid fa-chevron-right text-sm"></i>
                </button>
            @endif

            <img
                :src="images[index]"
                alt="{{ $alt }}"
                class="max-h-[85vh] max-w-full rounded-xl object-contain shadow-2xl"
                @click.stop
            />

            @if (count($images) > 1)
                <p class="absolute bottom-4 left-1/2 -translate-x-1/2 text-xs font-bold text-white/80">
                    <span x-text="index + 1"></span> / {{ count($images) }}
                </p>
            @endif
        </div>
    </div>
@endif
