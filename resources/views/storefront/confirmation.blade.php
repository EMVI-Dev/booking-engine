<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Booking Confirmation') }} - {{ $agent->name }}</title>
    <link rel="icon" href="{{ $agent->logo_url }}" />
    <link rel="apple-touch-icon" href="{{ $agent->logo_url }}" />
    @if (! empty($agent->brand_color))
        <style>
            :root {
                --brand-color: {{ $agent->brand_color }};
            }
        </style>
    @endif
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $latestPayment = $reservation->latestPayment;
        $isPaid = $latestPayment && $latestPayment->isPaid() && in_array($reservation->status, [\App\Enums\ReservationStatus::Confirmed, \App\Enums\ReservationStatus::PendingConfirmation]);
        $reservationCode = $reservation->code ?? ('RSV-' . strtoupper(substr($reservation->id, -8)));
        $termsSnapshot = $reservation->terms_snapshot ?? [];
        $totalAmount = isset($termsSnapshot['total_price'])
            ? (float) $termsSnapshot['total_price']
            : ($latestPayment ? (float) $latestPayment->amount : 0);
    @endphp
    @include('storefront.partials.tracking-scripts', [
        'agent' => $agent,
        'isConversion' => $isPaid,
        'conversionAmount' => (float) ($latestPayment?->amount ?? $totalAmount),
        'conversionTransactionId' => $reservationCode,
    ])
</head>
<body class="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 flex flex-col items-center justify-center p-4 selection:bg-brand-600 selection:text-white antialiased">
    @php
        $waService = app(\App\Services\WhatsAppDispatchService::class);
        $cleanPhone = $waService->normalizePhoneNumber($agent->contact_whatsapp);
        $waMessage = 'Hello ' . ($agent->name ?? 'Agent') . ', I have an inquiry for booking #' . $reservationCode . ' (' . $reservation->guest_name . ')';
        $waUrl = $cleanPhone !== '' ? $waService->buildWhatsAppUrl($agent->contact_whatsapp, $waMessage) : '#';
    @endphp

    <div class="w-full max-w-lg space-y-6">
        <!-- Status Header Card -->
        <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xl text-center space-y-4">
            @if ($reservation->status === \App\Enums\ReservationStatus::Confirmed && $isPaid)
                <!-- Confirmed & Paid -->
                <div class="w-16 h-16 rounded-3xl bg-emerald-100 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto text-3xl shadow-sm animate-bounce">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="space-y-1">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        {{ __('Payment Successful & Confirmed') }}
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ __('You are all set, :name!', ['name' => $reservation->guest_name]) }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Your reservation has been confirmed and locked with :agent.', ['agent' => $agent->name]) }}
                    </p>
                </div>
            @elseif ($reservation->status === \App\Enums\ReservationStatus::PendingConfirmation && $isPaid)
                <!-- Paid & Pending Confirmation -->
                <div class="w-16 h-16 rounded-3xl bg-amber-100 dark:bg-amber-950/80 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto text-3xl shadow-sm">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div class="space-y-1">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        {{ __('Payment Received • Pending Operator Review') }}
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ __('Booking Received, :name!', ['name' => $reservation->guest_name]) }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Your payment is verified. :agent is reviewing tour schedule & capacity, and will confirm your trip shortly.', ['agent' => $agent->name]) }}
                    </p>
                </div>
            @else
                <!-- Payment Pending / 30-Minute Hold -->
                @php
                    $isExpired = $reservation->hold_expires_at && $reservation->hold_expires_at->isPast();
                    $minutesLeft = $reservation->hold_expires_at && $reservation->hold_expires_at->isFuture()
                        ? max(1, (int) now()->diffInMinutes($reservation->hold_expires_at))
                        : 0;
                @endphp

                <div class="w-16 h-16 rounded-3xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto text-3xl shadow-sm">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="space-y-1">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        @if ($isExpired)
                            {{ __('Hold Expired') }}
                        @else
                            {{ __('Spot on 30-Min Hold (:minutes min left)', ['minutes' => $minutesLeft]) }}
                        @endif
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ $isExpired ? __('Hold Expired') : __('Complete Your Payment') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        @if ($isExpired)
                            {{ __('The 30-minute booking hold for this trip has expired. Please create a new booking.') }}
                        @else
                            {{ __('Your spot is held for 30 minutes. Please complete payment with QRIS, Virtual Account, or Card to lock in your trip.') }}
                        @endif
                    </p>
                </div>

                <!-- Prominent Retry / Pay Now Button -->
                @if (! $isExpired)
                    <div class="pt-2">
                        <a
                            href="{{ route('storefront.reservation.pay', $reservation) }}"
                            class="w-full h-12 inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-black text-sm shadow-md shadow-brand-500/25 transition cursor-pointer"
                        >
                            <i class="fa-solid fa-lock text-xs"></i>
                            <span>{{ __('Pay Now') }} &bull; Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                        </a>
                    </div>
                @endif
            @endif

            <!-- Receipt Breakdown Box -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-800 text-left space-y-2.5 text-xs">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-zinc-700 font-mono text-[11px] text-slate-400">
                    <span>{{ __('Booking Code') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">#{{ $reservationCode }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Booked Item') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white truncate max-w-[200px]">{{ $reservation->bookable->name ?? ($reservation->bookable->title ?? 'Direct Booking') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Trip Date') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $reservation->requested_date->format('l, F d, Y') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Guests / Pax') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $reservation->pax_count }} Persons</span>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-zinc-700">
                    <span class="font-bold text-slate-700 dark:text-slate-300">
                        {{ $isPaid ? __('Total Paid') : __('Amount Due') }}
                    </span>
                    <span class="text-base font-extrabold {{ $isPaid ? 'text-emerald-600 dark:text-emerald-400' : 'text-brand-600 dark:text-brand-400' }}">
                        Rp {{ number_format($totalAmount, 0, ',', '.') }}
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-2.5 pt-2">
                @if ($isPaid)
                    @php
                        $tripTitle = ($reservation->bookable->name ?? ($reservation->bookable->title ?? 'Tour Experience')) . ' - ' . $agent->name;
                        $calendarStart = $reservation->requested_date->copy()->setTime(8, 0)->format('Ymd\THis');
                        $calendarEnd = $reservation->requested_date->copy()->setTime(17, 0)->format('Ymd\THis');
                        $calendarDetails = "Booking Code: #" . $reservationCode . "\nGuest: " . $reservation->guest_name . " (" . $reservation->pax_count . " Persons)\nProvider: " . $agent->name . "\nPhone/WA: " . ($agent->contact_whatsapp ?? '-');
                        $calendarLocation = $agent->name;
                        $googleCalUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . urlencode($tripTitle) . '&dates=' . $calendarStart . '/' . $calendarEnd . '&details=' . urlencode($calendarDetails) . '&location=' . urlencode($calendarLocation);
                        $icsContent = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//" . config('app.name', 'Emvi') . "//Booking Engine//EN\r\nBEGIN:VEVENT\r\nSUMMARY:" . addcslashes($tripTitle, ",;") . "\r\nDESCRIPTION:" . addcslashes($calendarDetails, ",;\n") . "\r\nLOCATION:" . addcslashes($calendarLocation, ",;") . "\r\nDTSTART:" . $calendarStart . "\r\nDTEND:" . $calendarEnd . "\r\nSTATUS:CONFIRMED\r\nEND:VEVENT\r\nEND:VCALENDAR";
                        $icsDataUri = 'data:text/calendar;charset=utf8,' . rawurlencode($icsContent);
                    @endphp

                    <!-- Add to Calendar Buttons -->
                    <div class="grid grid-cols-2 gap-2">
                        <a
                            href="{{ $googleCalUrl }}"
                            target="_blank"
                            rel="noopener"
                            class="h-10 px-3 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-slate-200 font-bold text-xs transition border border-slate-200 dark:border-zinc-700 shadow-2xs"
                        >
                            <i class="fa-brands fa-google text-xs text-rose-500"></i>
                            <span>{{ __('Google Calendar') }}</span>
                        </a>
                        <a
                            href="{{ $icsDataUri }}"
                            download="tour-booking-{{ $reservationCode }}.ics"
                            class="h-10 px-3 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-slate-200 font-bold text-xs transition border border-slate-200 dark:border-zinc-700 shadow-2xs"
                        >
                            <i class="fa-brands fa-apple text-xs text-slate-700 dark:text-slate-300"></i>
                            <span>{{ __('Apple / Outlook') }}</span>
                        </a>
                    </div>
                @endif

                @if ($cleanPhone)
                    <a
                        href="{{ $waUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="w-full h-11 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition"
                    >
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>{{ __('Message Agent on WhatsApp') }}</span>
                    </a>
                @endif

                @php
                    $storefrontUrl = $agent ? $agent->getStorefrontUrl() : route('home');
                    $agentStoreName = $agent->name ?? __('Storefront');
                @endphp
                <a
                    href="{{ $storefrontUrl }}"
                    class="w-full h-10 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition"
                >
                    <i class="fa-solid fa-store text-xs"></i>
                    <span>{{ __('Return to :agent Storefront', ['agent' => $agentStoreName]) }}</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
