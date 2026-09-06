<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Privacy') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-ebony dark:text-slate-100">
    <main class="mx-auto max-w-2xl space-y-6 px-4 py-12">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ config('app.name') }}</p>
        <h1 class="text-3xl font-black tracking-tight">{{ __('Privacy') }}</h1>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('We collect guest names, emails, phone numbers, and booking details so operators can run the trip and so we can take payment, send receipts, and handle refunds. We do not sell that information.') }}
        </p>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('Payments are processed by DOKU. Bank details you add for payouts are used only to send your trip money. You can ask us to correct or delete personal data that is not needed to keep a legal payment record.') }}
        </p>
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('This follows Indonesia’s personal data rules (UU PDP). Questions: write to :email.', ['email' => config('mail.from.address', 'hello@example.com')]) }}
        </p>
        <a href="{{ route('home') }}" class="inline-flex text-sm font-bold text-slate-900 underline dark:text-white">{{ __('Back home') }}</a>
    </main>
</body>
</html>
