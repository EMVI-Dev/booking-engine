<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Booking Confirmation') }} - {{ $agent->name }}</title>
    @if ($agent->logo)
        <link rel="icon" href="{{ Storage::url($agent->logo) }}" />
        <link rel="apple-touch-icon" href="{{ Storage::url($agent->logo) }}" />
    @endif
    @if (! empty($agent->brand_color))
        <style>
            :root {
                --brand-color: {{ $agent->brand_color }};
            }
        </style>
    @endif
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 flex flex-col items-center justify-center p-4 selection:bg-brand-600 selection:text-white antialiased">
    @php
        $latestPayment = $reservation->latestPayment;
        $isPaid = $latestPayment && $latestPayment->isPaid();
        $reservationCode = $reservation->code ?? ('RSV-' . strtoupper(substr($reservation->id, -8)));
        $cleanPhone = preg_replace('/[^0-9]/', '', (string) ($agent->contact_whatsapp ?? ''));
        $waMessage = 'Hello ' . ($agent->name ?? 'Agent') . ', I have confirmed booking #' . $reservationCode . ' for ' . $reservation->guest_name;
        $waUrl = $cleanPhone !== '' ? 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($waMessage) : '#';
    @endphp

    <div class="w-full max-w-lg space-y-6">
        <!-- Status Header Card -->
        <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xl text-center space-y-4">
            @if ($reservation->status === \App\Enums\ReservationStatus::Confirmed)
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
            @elseif ($reservation->status === \App\Enums\ReservationStatus::PendingConfirmation)
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
                <div class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 flex items-center justify-center mx-auto text-3xl shadow-sm">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="space-y-1">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300 border border-slate-200 dark:border-zinc-700">
                        {{ $reservation->status->label() }}
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ __('Reservation Status: :status', ['status' => $reservation->status->label()]) }}
                    </h1>
                </div>
            @endif

            <!-- Receipt Breakdown Box -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-800 text-left space-y-2.5 text-xs">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-zinc-700 font-mono text-[11px] text-slate-400">
                    <span>{{ __('Booking Code') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">#{{ $reservationCode }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Booked Item') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $reservation->bookable->name ?? ($reservation->bookable->title ?? 'Direct Booking') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Trip Date') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $reservation->requested_date->format('l, F d, Y') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Guests / Pax') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $reservation->pax_count }} Persons</span>
                </div>
                @if ($latestPayment)
                    <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-zinc-700">
                        <span class="font-bold text-slate-700 dark:text-slate-300">{{ __('Total Paid') }}</span>
                        <span class="text-base font-extrabold text-brand-600 dark:text-brand-400">
                            Rp {{ number_format((float) $latestPayment->amount, 0, ',', '.') }}
                        </span>
                    </div>
                @endif
            </div>

            <!-- Actions -->
            <div class="space-y-3 pt-2">
                @if ($cleanPhone)
                    <a
                        href="{{ $waUrl }}"
                        target="_blank"
                        class="w-full h-11 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition"
                    >
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>{{ __('Message Agent on WhatsApp') }}</span>
                    </a>
                @endif

                <a
                    href="{{ route('home') }}"
                    class="w-full h-10 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition"
                >
                    <i class="fa-solid fa-store text-xs"></i>
                    <span>{{ __('Return to Storefront') }}</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
