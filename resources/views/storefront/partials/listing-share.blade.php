@props([
    'agent',
    'listingTitle',
    'pageUrl',
])

@php
    /** @var \App\Models\Operator $agent */
    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $cleanPhone = filled($agent->contact_whatsapp)
        ? $waService->normalizePhoneNumber($agent->contact_whatsapp)
        : null;
    $askMessage = __('Hello :name, I have a question about :title — :url', [
        'name' => $agent->name,
        'title' => $listingTitle,
        'url' => $pageUrl,
    ]);
    $askUrl = $cleanPhone
        ? $waService->buildWhatsAppUrl($agent->contact_whatsapp, $askMessage)
        : null;
@endphp

<div class="flex flex-wrap items-center gap-2 pt-1" x-data="{ copied: false }">
    <button
        type="button"
        @click="navigator.clipboard.writeText(@js($pageUrl)); copied = true; setTimeout(() => copied = false, 2500)"
        class="h-9 px-3 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer"
    >
        <i class="fa-solid text-[10px]" :class="copied ? 'fa-check text-emerald-500' : 'fa-link'"></i>
        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy link'))"></span>
    </button>

    @if ($askUrl)
        <a
            href="{{ $askUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="h-9 px-3 inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-100 dark:hover:bg-emerald-950 text-emerald-700 dark:text-emerald-400 text-xs font-semibold transition"
        >
            <i class="fa-brands fa-whatsapp text-sm"></i>
            <span>{{ __('Ask about this trip') }}</span>
        </a>
    @endif
</div>
