@php
    $operator = $reservation->operator;
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $bookableTitle = $reservation->bookable?->name ?? ($reservation->bookable?->title ?? 'Direct Booking');
    $reservationUrl = route('reservations.index');
    $platformColor = '#FFEF4D';
    $platformInk = '#101730';

    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $waGuestUrl = $reservation->guest_contact
        ? $waService->buildWhatsAppUrl($reservation->guest_contact, "Hello {$reservation->guest_name}, this is {$operator->name} about your cancelled booking #{$code} for {$bookableTitle}.")
        : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Booking Cancelled') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                    <x-email.brand-header
                        :background="$platformColor"
                        :foreground="$platformInk"
                        :logo-url="url('/favicon.png')"
                        :logo-alt="config('app.name')"
                        :eyebrow="__('Operator Notification')"
                        :title="__('Booking Cancelled')"
                    />

                    <!-- Summary -->
                    <tr>
                        <td style="padding: 28px 28px 16px 28px;">
                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569;">
                                {{ __(':guest cancelled this booking from their receipt, inside your free-cancel window. Their seats are open again.', ['guest' => $reservation->guest_name]) }}
                            </p>

                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Booking Code') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: {{ $platformInk }}; font-family: monospace;">#{{ $code }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Lead Guest') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->guest_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Booked Item') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $bookableTitle }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Trip Date') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 800; color: {{ $platformInk }}; text-decoration: line-through;">{{ $reservation->requested_date->format('l, M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Party Size') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->pax_count }} Pax</td>
                                </tr>
                                @if ($refundAmount > 0)
                                    <tr>
                                        <td colspan="2" style="padding-top: 10px; border-top: 1px solid #e2e8f0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Refunded to the guest') }}</td>
                                        <td align="right" style="padding: 4px 0; font-size: 13px; font-weight: 800; color: #0f172a;">
                                            Rp {{ number_format($refundAmount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- Actions -->
                    <tr>
                        <td style="padding: 0 28px 28px 28px; text-align: center;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                @if ($waGuestUrl)
                                    <tr>
                                        <td style="padding-bottom: 10px;">
                                            <a href="{{ $waGuestUrl }}" target="_blank" style="display: block; width: 100%; box-sizing: border-box; background-color: #25d366; color: #ffffff; text-decoration: none; padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 800; text-align: center;">
                                                {{ __('Message Guest on WhatsApp') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>
                                        <a href="{{ $reservationUrl }}" target="_blank" style="display: block; width: 100%; box-sizing: border-box; background-color: {{ $platformColor }}; color: {{ $platformInk }}; text-decoration: none; padding: 12px 20px; border-radius: 14px; font-size: 12px; font-weight: 700; text-align: center;">
                                            {{ __('Open in Operator Portal') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 28px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 11px; color: #94a3b8;">
                            <p style="margin: 0;">
                                {{ __('The earning for this booking has been taken back out of your wallet escrow.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
