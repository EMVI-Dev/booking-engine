{{-- Storefront FAQ: generated from the operator's settings plus their own questions. --}}
@php
    $faqItems = app(\App\Services\StorefrontFaqService::class)->itemsFor($agent);
@endphp
@if ($faqItems !== [])
    <section id="faq" class="space-y-5 pt-6 border-t border-slate-200/80 dark:border-zinc-800" aria-label="{{ __('Frequently asked questions') }}">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div>
                <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ __('Frequently Asked Questions') }}
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Helpful answers regarding bookings, payments, and tour policies.') }}
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-zinc-800 text-[11px] font-bold text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-zinc-700/60 self-start sm:self-auto">
                <i class="fa-solid fa-circle-question text-brand-700 dark:text-brand-400 text-xs"></i>
                <span>{{ __(':count Questions', ['count' => count($faqItems)]) }}</span>
            </span>
        </div>

        <div class="rounded-2xl sm:rounded-3xl border border-slate-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs divide-y divide-slate-100 dark:divide-zinc-800/80 overflow-hidden">
            @foreach ($faqItems as $item)
                <details class="group transition-colors open:bg-slate-50/60 dark:open:bg-zinc-800/25">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6 text-sm sm:text-base font-bold text-slate-900 dark:text-white select-none focus:outline-none">
                        <span>{{ $item['question'] }}</span>
                        <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-400 group-open:text-brand-700 dark:group-open:text-brand-300 group-open:bg-brand-50 dark:group-open:bg-brand-950/60 transition-all shrink-0">
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 group-open:rotate-180" aria-hidden="true"></i>
                        </span>
                    </summary>
                    <div class="px-5 sm:px-6 pb-5 sm:pb-6 pt-0 text-xs sm:text-sm leading-relaxed text-slate-600 dark:text-slate-300 whitespace-pre-line">
                        <p>{{ $item['answer'] }}</p>
                    </div>
                </details>
            @endforeach
        </div>
    </section>
@endif
