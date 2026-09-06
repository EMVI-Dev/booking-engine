{{--
    Storefront floating WhatsApp button.

    Shown on every breakpoint: WhatsApp is the primary enquiry channel and most guests
    browse on a phone. On small screens it collapses to a circular icon button so it
    does not cover the booking call to action.

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
        <div class="fixed bottom-5 right-4 lg:bottom-6 lg:right-6 z-40 select-none">
            <a href="{{ $floatingWaUrl }}"
                target="_blank"
                rel="noopener"
                aria-label="{{ __('Chat with :name on WhatsApp', ['name' => $agent->name]) }}"
                class="h-13 w-13 lg:h-12 lg:w-auto lg:px-4.5 inline-flex items-center justify-center gap-2.5 rounded-full lg:rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-extrabold text-xs shadow-xl shadow-emerald-600/25 transition-all hover:scale-105 active:scale-95 border border-emerald-400/30 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
                title="{{ __('Chat directly on WhatsApp') }}">
                <i class="fa-brands fa-whatsapp text-2xl lg:text-lg" aria-hidden="true"></i>
                <span class="hidden lg:inline">{{ __('Chat with Us') }}</span>
            </a>
        </div>
    @endif
@endif
