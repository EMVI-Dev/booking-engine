<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Secure Online Checkout') }} - {{ $agent->name }}</title>
    <link rel="icon" href="{{ $agent->logo_url }}" />
    <link rel="apple-touch-icon" href="{{ $agent->logo_url }}" />
    @include('storefront.partials.brand-theme')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-900 text-brand-foreground flex flex-col items-center justify-center p-4 selection:bg-brand-600 selection:text-brand-foreground antialiased">
    <div class="w-full max-w-md space-y-6">
        <!-- Brand Header -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-600 shadow-xl text-brand-foreground text-2xl font-black mb-1">
                <i class="fa-solid fa-credit-card"></i>
            </div>
            <h1 class="text-2xl font-black tracking-tight">{{ __('DOKU Secure Checkout') }}</h1>
            <p class="text-xs text-slate-400">
                {{ __('Processing reservation for :agent', ['agent' => $agent->name]) }}
            </p>
        </div>

        <!-- Order Summary Card -->
        <div class="p-6 rounded-3xl bg-slate-800/80 border border-slate-700/80 shadow-2xl space-y-4 backdrop-blur-md">
            <div class="flex items-center justify-between pb-3 border-b border-slate-700">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Invoice Reference') }}</span>
                    <p class="font-mono text-xs font-bold text-brand-400">{{ $payment->gateway_ref }}</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-amber-400/20 text-amber-300 border border-amber-400/30">
                    {{ __('Sandbox Mode') }}
                </span>
            </div>

            <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between text-slate-300">
                    <span>{{ __('Reservation Code') }}</span>
                    <span class="font-mono font-bold text-indigo-300">#{{ $reservation->code ?? substr($reservation->id, -8) }}</span>
                </div>
                <div class="flex items-center justify-between text-slate-300">
                    <span>{{ __('Experience Item') }}</span>
                    <span class="font-bold text-white truncate max-w-[180px]">{{ $reservation->bookable->name ?? ($reservation->bookable->title ?? 'Direct Booking') }}</span>
                </div>
                <div class="flex items-center justify-between text-slate-300">
                    <span>{{ __('Trip Date') }}</span>
                    <span class="font-bold text-white">{{ $reservation->requested_date->format('M d, Y') }}</span>
                </div>
                <div class="flex items-center justify-between text-slate-300">
                    <span>{{ __('Total Guests') }}</span>
                    <span class="font-bold text-white">{{ $reservation->pax_count }} Pax</span>
                </div>
                <div class="flex items-center justify-between text-slate-300">
                    <span>{{ __('Lead Guest') }}</span>
                    <span class="font-bold text-white">{{ $reservation->guest_name }}</span>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-700 flex items-center justify-between">
                <span class="text-xs text-slate-400 uppercase font-bold">{{ __('Amount Due') }}</span>
                <span class="text-2xl font-black text-white">
                    Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                </span>
            </div>

            <!-- Simulation Actions Form -->
            <form action="{{ route('storefront.payment.simulate.confirm') }}" method="POST" class="space-y-3 pt-3">
                @csrf
                <input type="hidden" name="reservation_id" value="{{ $reservation->id }}" />
                <input type="hidden" name="status" value="SUCCESS" />

                <button
                    type="submit"
                    class="w-full h-12 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 text-white font-bold text-sm shadow-lg shadow-emerald-500/20 transition cursor-pointer"
                >
                    <i class="fa-solid fa-circle-check"></i>
                    <span>{{ __('Simulate Successful Payment') }}</span>
                </button>
            </form>

            <form action="{{ route('storefront.payment.simulate.confirm') }}" method="POST">
                @csrf
                <input type="hidden" name="reservation_id" value="{{ $reservation->id }}" />
                <input type="hidden" name="status" value="FAILED" />

                <button
                    type="submit"
                    class="w-full h-10 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-700/60 hover:bg-slate-700 text-slate-400 hover:text-white font-semibold text-xs transition cursor-pointer"
                >
                    <i class="fa-solid fa-xmark"></i>
                    <span>{{ __('Simulate Failed / Expired Payment') }}</span>
                </button>
            </form>
        </div>

        <p class="text-center text-[11px] text-slate-500">
            <i class="fa-solid fa-shield-halved mr-1 text-emerald-400"></i>
            {{ __('End-to-End Encrypted via DOKU Payment Gateway') }}
        </p>
    </div>
</body>
</html>
