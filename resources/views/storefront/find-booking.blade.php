<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-clip w-full max-w-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
    <title>{{ __('Find Your Booking') }} &bull; {{ $agent->name }}</title>
    <meta name="robots" content="noindex, nofollow" />
    <link rel="canonical" href="{{ route('storefront.find-booking') }}" />
    <link rel="icon" href="{{ $agent->logo_url }}" />
    @include('storefront.partials.brand-theme')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-slate-100 antialiased">
    @include('storefront.partials.navbar')

    <main class="flex-1 w-full max-w-lg mx-auto px-4 py-10 sm:py-16">
        <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 p-6 sm:p-8 space-y-6">
            <div class="space-y-2 text-center">
                <div class="w-14 h-14 rounded-2xl bg-brand-50 dark:bg-brand-950 text-brand-700 dark:text-brand-300 flex items-center justify-center mx-auto text-2xl">
                    <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ __('Find your booking') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Enter the booking code from your email or WhatsApp message, plus the name, email, or phone used at checkout.') }}
                </p>
            </div>

            <form method="POST" action="{{ route('storefront.find-booking.lookup') }}" class="space-y-4">
                @csrf

                <div>
                    <x-label for="code" :value="__('Booking code')" required />
                    <x-input
                        id="code"
                        name="code"
                        type="text"
                        value="{{ old('code') }}"
                        placeholder="RSV-XXXXXXXX"
                        class="h-12 font-mono uppercase tracking-wider"
                        autocomplete="off"
                        required
                        :error="$errors->has('code')"
                    />
                    <x-input-error :messages="$errors->get('code')" />
                </div>

                <div>
                    <x-label for="contact" :value="__('Email, phone, or lead guest name')" required />
                    <x-input
                        id="contact"
                        name="contact"
                        type="text"
                        value="{{ old('contact') }}"
                        placeholder="{{ __('sarah@example.com or 0812…') }}"
                        class="h-12"
                        required
                        :error="$errors->has('contact')"
                    />
                    <x-input-error :messages="$errors->get('contact')" />
                </div>

                <button
                    type="submit"
                    class="w-full h-12 inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-600 hover:bg-brand-700 text-brand-foreground font-black text-sm shadow-none transition"
                >
                    <i class="fa-solid fa-magnifying-glass text-xs" aria-hidden="true"></i>
                    <span>{{ __('Open my e-ticket') }}</span>
                </button>
            </form>

            @if ($agent->contact_whatsapp)
                <p class="text-center text-xs text-slate-500">
                    {{ __('Lost your code?') }}
                    <a href="{{ app(\App\Services\WhatsAppDispatchService::class)->buildWhatsAppUrl($agent->contact_whatsapp, 'Hello '.$agent->name.', I need help finding my booking.') }}"
                        target="_blank"
                        rel="noopener"
                        class="font-bold text-emerald-600 hover:underline">
                        {{ __('Message us on WhatsApp') }}
                    </a>
                </p>
            @endif
        </div>
    </main>

    @include('storefront.partials.footer')
    @livewireScripts
</body>
</html>
