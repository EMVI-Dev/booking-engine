@php
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $waOperatorUrl = $operator->phone
        ? $waService->buildWhatsAppUrl($operator->phone, "Hello {$operator->name}, this is regarding booking #{$code} for {$reservation->guest_name}.")
        : null;
    $waGuestUrl = $reservation->guest_contact
        ? $waService->buildWhatsAppUrl($reservation->guest_contact, "Hello {$reservation->guest_name}, this is regarding your upcoming activity booking #{$code} through {$operator->name}.")
        : null;
    $isCancelled = $reservation->status === \App\Enums\ReservationStatus::Cancelled;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Vendor Dispatch Sheet') }} &bull; #{{ $code }} &bull; {{ $operator->name }}</title>
    <meta name="robots" content="noindex, nofollow" />
    @if ($operator->logo_url)
        <link rel="icon" href="{{ $operator->logo_url }}" />
    @endif
    @include('storefront.partials.brand-theme', ['agent' => $operator])
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 dark:bg-zinc-950 text-slate-800 dark:text-slate-100 antialiased py-8 px-4 sm:px-6">
    <div class="max-w-xl mx-auto space-y-6">

        <!-- Print & Header Toolbar -->
        <div class="flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-slate-900 text-white dark:bg-white dark:text-slate-900">
                    {{ __('Vendor Dispatch Sheet') }}
                </span>
            </div>
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-zinc-800 shadow-xs cursor-pointer transition"
            >
                <i class="fa-solid fa-print"></i>
                <span>{{ __('Print Manifest') }}</span>
            </button>
        </div>

        <!-- Main Dispatch Card -->
        <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 overflow-hidden shadow-lg">

            <!-- Card Top Banner / Operator Brand -->
            <div class="p-6 sm:p-7 border-b border-slate-100 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/70 dark:bg-zinc-800/40">
                <div class="flex items-center gap-3.5">
                    @if ($operator->logo_url)
                        <img src="{{ $operator->logo_url }}" alt="{{ $operator->name }}" class="w-12 h-12 rounded-2xl object-cover border border-slate-200 dark:border-zinc-700 bg-white" />
                    @else
                        <div class="w-12 h-12 rounded-2xl bg-brand-500 text-brand-foreground flex items-center justify-center font-black text-xl">
                            {{ substr($operator->name, 0, 1) }}
                        </div>
                    @endif
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Booked via Operator') }}</div>
                        <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white leading-tight">
                            {{ $operator->name }}
                        </h1>
                    </div>
                </div>

                <div>
                    @if ($isCancelled)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400">
                            <i class="fa-solid fa-ban text-[10px]"></i>
                            {{ __('Cancelled') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                            {{ __('Confirmed / Paid') }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">

                <!-- Booking Meta Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800">
                    <div>
                        <div class="text-[10px] font-bold uppercase text-slate-400">{{ __('Booking Code') }}</div>
                        <div class="text-sm font-mono font-black text-slate-900 dark:text-white">#{{ $code }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase text-slate-400">{{ __('Activity Date') }}</div>
                        <div class="text-sm font-bold text-slate-900 dark:text-white">{{ $reservation->requested_date->format('M d, Y') }}</div>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <div class="text-[10px] font-bold uppercase text-slate-400">{{ __('Total Pax') }}</div>
                        <div class="text-sm font-bold text-slate-900 dark:text-white">{{ $reservation->pax_count }} {{ __('Guests') }}</div>
                    </div>
                </div>

                <!-- Section: Booked Activities -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        {{ __('Assigned Activity / Experience') }}
                    </h3>
                    <div class="space-y-2">
                        @if ($reservation->bookable instanceof \App\Models\Product)
                            <div class="p-4 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center text-sm">
                                        <i class="fa-solid fa-compass"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $reservation->bookable->name }}</div>
                                        @if ($reservation->bookable->location)
                                            <div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                                <i class="fa-solid fa-location-dot text-[10px]"></i>
                                                <span>{{ $reservation->bookable->location }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif ($reservation->bookable instanceof \App\Models\Package)
                            <div class="p-4 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-3">
                                <div class="font-bold text-slate-900 dark:text-white text-sm flex items-center gap-2">
                                    <i class="fa-solid fa-cubes text-amber-500"></i>
                                    <span>{{ $reservation->bookable->title }} (Package)</span>
                                </div>
                                <div class="pl-3 border-l-2 border-slate-200 dark:border-zinc-700 space-y-2">
                                    @foreach ($reservation->bookable->products as $prod)
                                        <div class="text-xs flex items-center justify-between text-slate-700 dark:text-slate-300">
                                            <span>• {{ $prod->name }}</span>
                                            @if ($prod->vendor)
                                                <span class="text-[10px] font-semibold text-slate-400">({{ $prod->vendor->name }})</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Section: Lead Guest Information -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        {{ __('Guest Manifest Details') }}
                    </h3>
                    <div class="p-5 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <div class="text-base font-black text-slate-900 dark:text-white">
                                    {{ $reservation->guest_name }}
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ __('Party of :count people', ['count' => $reservation->pax_count]) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2 pt-1 sm:pt-0 no-print">
                                @if ($waGuestUrl)
                                    <a href="{{ $waGuestUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-bold text-xs hover:bg-emerald-100 transition">
                                        <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                                        <span>{{ __('WhatsApp Guest') }}</span>
                                    </a>
                                @endif
                                @if ($reservation->guest_contact)
                                    <a href="tel:{{ $reservation->guest_contact }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-200 transition">
                                        <i class="fa-solid fa-phone text-xs"></i>
                                        <span>{{ __('Call') }}</span>
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800 text-xs">
                            <div>
                                <span class="text-slate-400">{{ __('Contact Phone:') }}</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200 ml-1">{{ $reservation->guest_contact ?: __('Not provided') }}</span>
                            </div>
                            @if ($reservation->guest_email)
                                <div>
                                    <span class="text-slate-400">{{ __('Email:') }}</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 ml-1">{{ $reservation->guest_email }}</span>
                                </div>
                            @endif
                        </div>

                        @if ($reservation->notes)
                            <div class="p-3 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/70 dark:border-amber-900/40 text-xs text-amber-900 dark:text-amber-200">
                                <span class="font-bold block mb-0.5">{{ __('Guest Notes & Special Requests:') }}</span>
                                <span>&ldquo;{{ $reservation->notes }}&rdquo;</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Section: Operator / Booker Contact Card -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        {{ __('Booker / Operator Coordination') }}
                    </h3>
                    <div class="p-5 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-800/40 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div class="font-black text-sm text-slate-900 dark:text-white">{{ $operator->name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ __('Originating tour operator / guide') }}</div>
                            </div>
                            <div class="flex items-center gap-2 no-print">
                                @if ($waOperatorUrl)
                                    <a href="{{ $waOperatorUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition">
                                        <i class="fa-brands fa-whatsapp"></i>
                                        <span>{{ __('Message Operator') }}</span>
                                    </a>
                                @endif
                                @if ($operator->phone)
                                    <a href="tel:{{ $operator->phone }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-slate-200 font-bold text-xs hover:bg-slate-50 transition">
                                        <i class="fa-solid fa-phone text-xs"></i>
                                        <span>{{ __('Call') }}</span>
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-slate-200/60 dark:border-zinc-700/60 text-xs">
                            @if ($operator->email)
                                <div>
                                    <span class="text-slate-400">{{ __('Operator Email:') }}</span>
                                    <a href="mailto:{{ $operator->email }}" class="font-semibold text-brand-600 dark:text-brand-400 hover:underline ml-1">
                                        {{ $operator->email }}
                                    </a>
                                </div>
                            @endif
                            @if ($operator->phone)
                                <div>
                                    <span class="text-slate-400">{{ __('Phone / WhatsApp:') }}</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 ml-1">{{ $operator->phone }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer note -->
            <div class="px-6 py-4 bg-slate-50 dark:bg-zinc-800/60 border-t border-slate-100 dark:border-zinc-800 text-center text-xs text-slate-400">
                {{ __('This vendor dispatch sheet is provided for operational activity coordination.') }}
            </div>
        </div>
    </div>
</body>
</html>
