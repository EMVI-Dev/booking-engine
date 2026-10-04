{{-- Storefront photo gallery (count capped by plan). Photos are WebP from MediaStore. --}}
@php
    $galleryService = app(\App\Services\StorefrontGalleryService::class);
    $galleryPhotos = $galleryService->visiblePhotos($agent);
@endphp
@if ($galleryPhotos->isNotEmpty())
    <section id="gallery" class="space-y-5 pt-6 border-t border-slate-200/80 dark:border-zinc-800" aria-label="{{ __('Photo gallery') }}"
        x-data="{
            open: null,
            photos: @js($galleryPhotos->map(fn ($photo) => ['src' => $galleryService->url($photo), 'caption' => (string) $photo->caption])->values()),
            prev() { if (this.open > 0) this.open--; },
            next() { if (this.open < this.photos.length - 1) this.open++; }
        }"
        x-on:keydown.arrow-left.window="if (open !== null) prev()"
        x-on:keydown.arrow-right.window="if (open !== null) next()"
        x-on:keydown.escape.window="open = null">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div>
                <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ __('Photo Gallery') }}
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Real moments and sights captured across our destinations and tours.') }}
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-zinc-800 text-[11px] font-bold text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-zinc-700/60 self-start sm:self-auto">
                <i class="fa-solid fa-camera text-brand-700 dark:text-brand-400 text-xs"></i>
                <span>{{ __(':count Photos', ['count' => $galleryPhotos->count()]) }}</span>
            </span>
        </div>

        {{-- 3-Column Square Grid on all screens --}}
        <div class="grid grid-cols-3 gap-2 sm:gap-3 lg:gap-4">
            @foreach ($galleryPhotos as $index => $photo)
                <button type="button" x-on:click="open = {{ $index }}"
                    class="group relative aspect-square overflow-hidden rounded-2xl sm:rounded-3xl bg-slate-100 dark:bg-zinc-800 border border-slate-200/70 dark:border-zinc-800 shadow-xs hover:shadow-xl hover:border-brand-400 dark:hover:border-brand-500 transition-all duration-300 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                    aria-label="{{ __('View photo :number', ['number' => $index + 1]) }}">
                    <img src="{{ $galleryService->url($photo) }}" alt="{{ $photo->caption ?: $agent->name }}" loading="lazy" decoding="async"
                        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />

                    {{-- Hover Overlay with Expand Pill --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-between p-2.5 sm:p-3.5 pointer-events-none">
                        <div class="flex justify-end">
                            <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/90 dark:bg-zinc-900/90 text-slate-800 dark:text-white flex items-center justify-center text-[10px] sm:text-xs shadow-md backdrop-blur-md">
                                <i class="fa-solid fa-expand"></i>
                            </span>
                        </div>
                        @if ($photo->caption)
                            <p class="text-[10px] sm:text-xs text-white/95 font-medium line-clamp-1 drop-shadow-sm text-left">
                                {{ $photo->caption }}
                            </p>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Lightbox Modal --}}
        <div x-show="open !== null" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click.self="open = null"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-950/90 backdrop-blur-md select-none"
            role="dialog" aria-modal="true" aria-label="{{ __('Photo preview') }}">

            {{-- Top Toolbar --}}
            <div class="absolute top-4 left-4 right-4 flex items-center justify-between pointer-events-none z-10">
                <span class="px-3.5 py-1.5 rounded-full bg-white/10 text-white text-xs font-bold backdrop-blur-md border border-white/10 shadow-sm">
                    <span x-text="open + 1"></span> / <span x-text="photos.length"></span>
                </span>
                <button type="button" x-on:click="open = null"
                    class="pointer-events-auto w-10 h-10 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/30 text-white border border-white/15 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer shadow-lg hover:scale-105"
                    aria-label="{{ __('Close') }}">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            {{-- Navigation Prev Button --}}
            <button type="button" x-show="open > 0" x-on:click="prev()"
                class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 z-10 w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/30 text-white border border-white/15 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer shadow-xl hover:scale-105"
                aria-label="{{ __('Previous photo') }}">
                <i class="fa-solid fa-chevron-left text-sm sm:text-base"></i>
            </button>

            {{-- Navigation Next Button --}}
            <button type="button" x-show="open < photos.length - 1" x-on:click="next()"
                class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 z-10 w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/30 text-white border border-white/15 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer shadow-xl hover:scale-105"
                aria-label="{{ __('Next photo') }}">
                <i class="fa-solid fa-chevron-right text-sm sm:text-base"></i>
            </button>

            {{-- Photo & Caption Container --}}
            <template x-if="open !== null">
                <figure class="max-h-full max-w-5xl flex flex-col items-center justify-center my-auto p-2" x-on:click.outside="open = null">
                    <img :src="photos[open].src" :alt="photos[open].caption || {{ \Illuminate\Support\Js::from($agent->name) }}"
                        class="max-h-[75vh] sm:max-h-[80vh] w-auto max-w-full rounded-2xl sm:rounded-3xl border border-white/15 shadow-2xl object-contain" />
                    <figcaption x-show="photos[open].caption" x-text="photos[open].caption"
                        class="mt-3.5 text-center text-xs sm:text-sm text-slate-200 font-medium max-w-2xl px-4 py-2 rounded-xl bg-black/50 border border-white/10 backdrop-blur-md leading-relaxed shadow-lg">
                    </figcaption>
                </figure>
            </template>
        </div>
    </section>
@endif
