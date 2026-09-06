@php
    $latestPayment = $reservation->latestPayment;
    $isPaid = $latestPayment && $latestPayment->isPaid() && in_array($reservation->status, [\App\Enums\ReservationStatus::Confirmed, \App\Enums\ReservationStatus::PendingConfirmation]);
    $reservationCode = $reservation->code ?? ('RSV-' . strtoupper(substr($reservation->id, -8)));
    $termsSnapshot = $reservation->terms_snapshot ?? [];
    $totalAmount = isset($termsSnapshot['total_price'])
        ? (float) $termsSnapshot['total_price']
        : ($latestPayment ? (float) $latestPayment->amount : 0);
    $shouldPrint = request()->boolean('print');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('My ticket') }} - {{ $reservationCode }} - {{ $agent->displayName }}</title>
    <meta name="robots" content="noindex, nofollow" />
    <link rel="icon" href="{{ $agent->logo_url }}" />
    <link rel="apple-touch-icon" href="{{ $agent->logo_url }}" />
    @include('storefront.partials.brand-theme')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="e-ticket-page min-h-screen bg-slate-50 text-ebony antialiased dark:bg-ebony dark:text-white">
    <div class="e-ticket-page__frame">
        <div class="no-print e-ticket-page__toolbar">
            <a href="{{ route('storefront.reservation.receipt', $reservation) }}" class="e-ticket-page__back">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>{{ __('My ticket') }}</span>
            </a>
        </div>

        @include('storefront.partials.e-voucher', ['voucherLayout' => 'page'])

        <button
            type="button"
            onclick="window.print()"
            class="no-print e-ticket-page__download"
        >
            {{ __('Download my ticket') }}
        </button>
    </div>

    @if ($shouldPrint)
        <script>
            window.addEventListener('load', function () {
                window.setTimeout(function () {
                    window.print();
                }, 250);
            });
        </script>
    @endif
</body>
</html>
