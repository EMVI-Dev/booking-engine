{{--
    Storefront Single Desktop Floating WhatsApp Button
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
        <div class="hidden lg:block fixed bottom-6 right-6 z-40 select-none">
            <a href="{{ $floatingWaUrl }}"
                target="_blank"
                rel="noopener"
                class="h-12 px-4.5 inline-flex items-center gap-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-extrabold text-xs shadow-xl shadow-emerald-600/25 transition-all hover:scale-105 active:scale-95 border border-emerald-400/30 cursor-pointer"
                title="{{ __('Chat directly on WhatsApp') }}">
                <i class="fa-brands fa-whatsapp text-lg"></i>
                <span>{{ __('Chat with Us') }}</span>
            </a>
        </div>
    @endif
@endif
