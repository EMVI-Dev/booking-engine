{{-- HTML e-ticket. White card on the page. No QR. Follows html.dark. --}}
@php
    $voucherLayout = $voucherLayout ?? 'preview';
    $isPage = $voucherLayout === 'page';
    $bookable = $reservation->bookable;
    $tripTitle = $bookable?->name ?? $bookable?->title ?? __('Direct Booking');
    $coverUrl = $bookable?->cover_photo_url;
    $venue = filled($bookable?->location) ? $bookable->location : $agent->displayName;
    $isCancelled = $reservation->status === \App\Enums\ReservationStatus::Cancelled;
    $isWaiting = $reservation->status === \App\Enums\ReservationStatus::PendingConfirmation && $isPaid;
    $ticketPaid = $isPaid && ! $isCancelled;

    $statusLabel = match (true) {
        $isCancelled => __('Cancelled'),
        $isWaiting => __('Paid • waiting for the team'),
        $ticketPaid => __('Paid and confirmed'),
        ! empty($isExpired) => __('Hold Expired'),
        default => __('Payment due'),
    };
@endphp

<article
    class="e-voucher {{ $isCancelled ? 'e-voucher--cancelled' : '' }}"
    aria-label="{{ $isPage ? __('My ticket') : __('Your e-ticket') }}"
>
    <div class="e-voucher__top">
        <div class="e-voucher__intro">
            @if ($coverUrl)
                <img src="{{ $coverUrl }}" alt="" class="e-voucher__thumb" />
            @else
                <span class="e-voucher__thumb e-voucher__thumb--mark" aria-hidden="true">{{ strtoupper(substr($tripTitle, 0, 1)) }}</span>
            @endif

            <div class="e-voucher__intro-copy">
                <h2 class="e-voucher__title">{{ $tripTitle }}</h2>
                <p class="e-voucher__label">{{ __('Guest') }}</p>
                <p class="e-voucher__name">{{ $reservation->guest_name }}</p>
                <p class="e-voucher__status-line">{{ $statusLabel }}</p>
            </div>
        </div>

        <dl class="e-voucher__facts">
            <div>
                <dt>{{ __('Date') }}</dt>
                <dd>{{ $reservation->requested_date->format('j F Y') }}</dd>
            </div>
            <div>
                <dt>{{ __('People') }}</dt>
                <dd>{{ trans_choice(':count guest|:count guests', $reservation->pax_count) }}</dd>
            </div>
            <div>
                <dt>{{ __('Place') }}</dt>
                <dd>{{ $venue }}</dd>
            </div>
            <div>
                <dt>{{ $ticketPaid ? __('Paid') : __('Due') }}</dt>
                <dd>Rp {{ number_format($totalAmount, 0, ',', '.') }}</dd>
            </div>
        </dl>
    </div>

    <div class="e-voucher__perf">
        <span class="e-voucher-notch e-voucher-notch--left" aria-hidden="true"></span>
        <span class="e-voucher-notch e-voucher-notch--right" aria-hidden="true"></span>
    </div>

    <div class="e-voucher__bottom">
        <p class="e-voucher__id">{{ __('Booking ID') }} — #{{ $reservationCode }}</p>
        <p class="e-voucher__hint">
            {{ $isPage ? __('Show this page at check-in') : __('Show this code at check-in') }}
        </p>
    </div>
</article>
