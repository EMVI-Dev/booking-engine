{{--
    Storefront floating WhatsApp button.

    Desktop only. The header already has WhatsApp on phones, and a floating
    button sat on top of the booking bar.

    Expects: $agent (Operator model)
--}}

@if (!empty($agent?->contact_whatsapp))
    @php
        $waService = app(\App\Services\WhatsAppDispatchService::class);
        $cleanPhone = $waService->normalizePhoneNumber($agent->contact_whatsapp);
    @endphp
    @if ($cleanPhone !== '')
        @php
            $waMessage = "Hello {$agent->name}, I have a question about your tours.";
            $floatingWaUrl = $waService->buildWhatsAppUrl($agent->contact_whatsapp, $waMessage);
        @endphp
        {{-- Desktop only. Phones already have the header WhatsApp control, and this button sat on the booking bar. --}}
        <div class="fixed bottom-5 right-5 z-40 hidden select-none lg:block">
            <a href="{{ $floatingWaUrl }}"
                target="_blank"
                rel="noopener"
                aria-label="{{ __('Chat with :name on WhatsApp', ['name' => $agent->name]) }}"
                class="h-10 px-3.5 inline-flex items-center justify-center gap-2 rounded-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs shadow-lg shadow-emerald-900/20 transition-all hover:scale-105 active:scale-95 border border-emerald-400/30 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
                title="{{ __('Chat directly on WhatsApp') }}">
                <i class="fa-brands fa-whatsapp text-base" aria-hidden="true"></i>
                <span>{{ __('Chat with Us') }}</span>
            </a>
        </div>
    @endif
@endif
