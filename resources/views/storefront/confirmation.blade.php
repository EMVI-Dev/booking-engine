<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Booking Confirmation') }} - {{ $agent->name }}</title>
    <meta name="robots" content="noindex, nofollow" />
    <link rel="icon" href="{{ $agent->logo_url }}" />
    <link rel="apple-touch-icon" href="{{ $agent->logo_url }}" />
    @include('storefront.partials.brand-theme')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $latestPayment = $reservation->latestPayment;
        $isPaid = $reservation->hasValidTicket();
        $reservationCode = $reservation->code ?? ('RSV-' . strtoupper(substr($reservation->id, -8)));
        $termsSnapshot = $reservation->terms_snapshot ?? [];
        $totalAmount = $reservation->getQuotedTotal();
    @endphp
    @include('storefront.partials.tracking-scripts', [
        'agent' => $agent,
        'isConversion' => $isPaid,
        'conversionAmount' => (float) ($latestPayment?->amount ?? $totalAmount),
        'conversionTransactionId' => $reservationCode,
        'redactedPageLocation' => url('/booking-confirmed'),
    ])
</head>
<body class="min-h-screen sf-canvas dark:bg-ebony text-slate-900 dark:text-slate-100 flex flex-col items-center justify-center p-4 selection:bg-brand-600 selection:text-brand-foreground antialiased">
    @php
        $waService = app(\App\Services\WhatsAppDispatchService::class);
        $cleanPhone = $waService->normalizePhoneNumber($agent->contact_whatsapp);
        $waMessage = 'Hello ' . ($agent->name ?? 'Agent') . ', I have an enquiry for booking #' . $reservationCode . ' (' . $reservation->guest_name . ')';
        $waUrl = $cleanPhone !== '' ? $waService->buildWhatsAppUrl($agent->contact_whatsapp, $waMessage) : '#';
        $isExpired = $reservation->hold_expires_at && $reservation->hold_expires_at->isPast();
        $minutesLeft = $reservation->hold_expires_at && $reservation->hold_expires_at->isFuture()
            ? max(1, (int) now()->diffInMinutes($reservation->hold_expires_at))
            : 0;
    @endphp

    <div class="w-full max-w-md space-y-5">
        <div class="no-print space-y-1 text-center">
            @if ($latestPayment?->isRefunded())
                <h1 class="text-balance text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    {{ __('Booking Refunded') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('This payment was refunded to your original payment method. Contact :agent if you have any questions.', ['agent' => $agent->name]) }}
                </p>
            @elseif ($reservation->status === \App\Enums\ReservationStatus::Declined)
                <h1 class="text-balance text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    {{ __('Booking Declined') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('This booking was declined by :agent. If you made a payment, it has been refunded.', ['agent' => $agent->name]) }}
                </p>
            @elseif ($reservation->status === \App\Enums\ReservationStatus::Completed)
                <h1 class="text-balance text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    {{ __('Trip Completed') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Thank you for joining this experience with :agent! We hope you had a great trip.', ['agent' => $agent->name]) }}
                </p>
            @elseif ($reservation->status === \App\Enums\ReservationStatus::Cancelled)
                <h1 class="text-balance text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    {{ __('This booking is cancelled') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Message :agent if you want to book again.', ['agent' => $agent->name]) }}
                </p>
            @elseif ($reservation->status === \App\Enums\ReservationStatus::Confirmed && $isPaid)
                <h1 class="text-balance text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    {{ __('You are all set, :name!', ['name' => $reservation->guest_name]) }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Your trip with :agent is booked.', ['agent' => $agent->name]) }}
                </p>
            @elseif ($reservation->status === \App\Enums\ReservationStatus::PendingConfirmation && $isPaid)
                <h1 class="text-balance text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    {{ __('We have your booking, :name!', ['name' => $reservation->guest_name]) }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Your payment went through. :agent is checking the date and will confirm your trip shortly.', ['agent' => $agent->name]) }}
                </p>
            @else
                <h1 class="text-balance text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    {{ $isExpired ? __('Hold Expired') : __('Complete Your Payment') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @if ($isExpired)
                        {{ __('The 30-minute booking hold for this trip has expired. Please create a new booking.') }}
                    @else
                        {{ __('Spot held for :minutes more minutes. Pay with QRIS, bank transfer, or card to lock in your trip.', ['minutes' => $minutesLeft]) }}
                    @endif
                </p>
            @endif
        </div>

        @include('storefront.partials.e-voucher')

        <div class="no-print space-y-2.5">
            @if (! $isPaid && ! $isExpired && ! $latestPayment?->isRefunded() && ! in_array($reservation->status, [\App\Enums\ReservationStatus::Cancelled, \App\Enums\ReservationStatus::Declined, \App\Enums\ReservationStatus::Completed], true))
                <a
                    href="{{ route('storefront.reservation.pay', $reservation) }}"
                    class="flex h-12 w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-brand-600 text-sm font-black text-brand-foreground transition hover:bg-brand-700 active:bg-brand-800"
                >
                    <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                    <span>{{ __('Pay Now') }} &bull; Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                </a>
            @endif

            @if ($isPaid)
                <a
                    href="{{ route('storefront.reservation.ticket', ['reservation' => $reservation, 'print' => 1]) }}"
                    class="inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-slate-900 text-xs font-bold text-white transition dark:bg-white dark:text-slate-900"
                >
                    <i class="fa-solid fa-ticket text-xs" aria-hidden="true"></i>
                    <span>{{ __('Open e-ticket') }}</span>
                </a>
            @endif
            @if ($isPaid)
                @php
                    $tripTitle = ($reservation->bookable->name ?? ($reservation->bookable->title ?? 'Tour Experience')) . ' - ' . $agent->name;
                    $calendarStart = $reservation->requested_date->copy()->setTime(8, 0)->format('Ymd\THis');
                    $calendarEnd = $reservation->requested_date->copy()->setTime(17, 0)->format('Ymd\THis');
                    $calendarDetails = "Booking Code: #" . $reservationCode . "\nGuest: " . $reservation->guest_name . " (" . $reservation->pax_count . " Persons)\nProvider: " . $agent->name . "\nPhone/WA: " . ($agent->contact_whatsapp ?? '-');
                    $calendarLocation = $agent->name;
                    $googleCalUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . urlencode($tripTitle) . '&dates=' . $calendarStart . '/' . $calendarEnd . '&details=' . urlencode($calendarDetails) . '&location=' . urlencode($calendarLocation);
                    $calendarProduct = $agent->showsPlatformBranding()
                        ? (string) config('app.name')
                        : preg_replace('/[^A-Za-z0-9 ]+/', '', (string) $agent->name);
                    $icsContent = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//" . $calendarProduct . "//Booking Engine//EN\r\nBEGIN:VEVENT\r\nSUMMARY:" . addcslashes($tripTitle, ",;") . "\r\nDESCRIPTION:" . addcslashes($calendarDetails, ",;\n") . "\r\nLOCATION:" . addcslashes($calendarLocation, ",;") . "\r\nDTSTART:" . $calendarStart . "\r\nDTEND:" . $calendarEnd . "\r\nSTATUS:CONFIRMED\r\nEND:VEVENT\r\nEND:VCALENDAR";
                    $icsDataUri = 'data:text/calendar;charset=utf8,' . rawurlencode($icsContent);
                @endphp

                <div class="grid grid-cols-2 gap-2">
                    <a
                        href="{{ $googleCalUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-10 min-w-0 cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs font-bold text-slate-800 transition hover:bg-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-slate-200 dark:hover:bg-zinc-700"
                    >
                        <i class="fa-brands fa-google text-xs text-rose-500" aria-hidden="true"></i>
                        <span>{{ __('Google') }}</span>
                    </a>
                    <a
                        href="{{ $icsDataUri }}"
                        download="tour-booking-{{ $reservationCode }}.ics"
                        class="inline-flex h-10 min-w-0 cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs font-bold text-slate-800 transition hover:bg-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-slate-200 dark:hover:bg-zinc-700"
                    >
                        <i class="fa-brands fa-apple text-xs text-slate-700 dark:text-slate-300" aria-hidden="true"></i>
                        <span>{{ __('Apple') }}</span>
                    </a>
                </div>
            @endif

            @if ($cleanPhone)
                <a
                    href="{{ $waUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-emerald-600 text-xs font-bold text-white transition hover:bg-emerald-700"
                >
                    <i class="fa-brands fa-whatsapp text-sm" aria-hidden="true"></i>
                    <span>{{ __('Message the team on WhatsApp') }}</span>
                </a>
            @endif

            @php
                $storefrontUrl = $agent ? $agent->getStorefrontUrl() : route('home');
                $agentStoreName = $agent->name ?? __('Storefront');
            @endphp
            <a
                href="{{ $storefrontUrl }}"
                class="inline-flex h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 transition hover:bg-slate-200 dark:bg-zinc-800 dark:text-slate-300 dark:hover:bg-zinc-700"
            >
                <i class="fa-solid fa-store text-xs" aria-hidden="true"></i>
                <span>{{ __('Back to :agent', ['agent' => $agentStoreName]) }}</span>
            </a>

            @if ($reservation->canGuestCancel())
                <form method="POST" action="{{ route('storefront.reservation.cancel', $reservation) }}" class="pt-2">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white text-xs font-bold text-rose-600 transition dark:border-rose-800 dark:bg-zinc-900 dark:text-rose-300"
                        onclick="return confirm(@js(__('Cancel this booking?')))"
                    >
                        {{ $reservation->status === \App\Enums\ReservationStatus::PaymentPending
                            ? __('Cancel this unpaid booking')
                            : __('Cancel free of charge') }}
                    </button>
                </form>
                @if ($reservation->getCancellationCutoffTime() && $reservation->status !== \App\Enums\ReservationStatus::PaymentPending)
                    <p class="text-[11px] text-slate-400">
                        {{ __('Free cancel until :when. The money goes back to the same payment method, usually within a few working days.', ['when' => $reservation->getCancellationCutoffTime()->timezone(config('app.timezone'))->format('M j, Y g:i A')]) }}
                    </p>
                @endif
            @endif

            @error('reservation')
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 flex items-start gap-2.5" role="alert">
                    <i class="fa-solid fa-circle-exclamation mt-0.5 text-rose-500 shrink-0 text-sm" aria-hidden="true"></i>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            @if (session('error'))
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 flex items-start gap-2.5" role="alert">
                    <i class="fa-solid fa-circle-exclamation mt-0.5 text-rose-500 shrink-0 text-sm" aria-hidden="true"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300 flex items-start gap-2.5" role="status">
                    <i class="fa-solid fa-circle-check mt-0.5 text-emerald-500 shrink-0 text-sm" aria-hidden="true"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <p class="text-[11px] text-slate-400">
                {{ __('Lost this page later? Open Find Booking on the storefront and enter :code plus your email or phone.', ['code' => $reservationCode]) }}
            </p>
        </div>
    </div>
</body>
</html>
