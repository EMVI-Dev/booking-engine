<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Platform terms') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-ebony dark:text-slate-100">
    <main class="mx-auto max-w-2xl space-y-6 px-4 py-12">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ config('app.name') }}</p>
        <h1 class="text-3xl font-black tracking-tight">{{ __('Platform terms') }}</h1>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('Operators list trips and take bookings through this platform. We collect the guest payment, hold the listed price until the trip date, then send that listed price to the operator. A guest service fee is added at checkout on every plan.') }}
        </p>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('If a guest cancels inside the free-cancel window, we send the full amount back to the same payment method, usually within a few working days. After that window, the operator’s trip terms apply.') }}
        </p>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('Payouts of Rp 500.000 or more have no transfer fee. Smaller payouts include a Rp 2.500 bank fee. The smallest payout is Rp 50.000.') }}
        </p>
        <a href="{{ route('home') }}" class="inline-flex text-sm font-bold text-slate-900 underline dark:text-white">{{ __('Back home') }}</a>
    </main>
</body>
</html>
