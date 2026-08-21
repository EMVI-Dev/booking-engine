@php
    $agent = $reservation->agent;
    $brandColor = $agent->brand_color ?? '#4f46e5';
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $payment = $reservation->latestPayment;
    $bookableTitle = $reservation->bookable?->name ?? ($reservation->bookable?->title ?? 'Direct Booking');
    $receiptUrl = route('storefront.reservation.receipt', $reservation);

    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $waUrl = $agent->contact_whatsapp ? $waService->getConfirmationUrl($reservation) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Your Booking Confirmation') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                    <!-- Brand Top Header -->
                    <tr>
                        <td style="background-color: {{ $brandColor }}; padding: 32px 24px; text-align: center;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                {{ $agent->name ?? config('app.name') }}
                            </h1>
                            <p style="margin: 6px 0 0 0; font-size: 13px; color: rgba(255, 255, 255, 0.85); font-weight: 500;">
                                {{ __('Booking Confirmation & E-Voucher') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Success Hero -->
                    <tr>
                        <td style="padding: 32px 28px 20px 28px; text-align: center;">
                            <div style="display: inline-block; width: 56px; height: 56px; line-height: 56px; border-radius: 18px; background-color: #ecfdf5; color: #059669; font-size: 26px; font-weight: bold; margin-bottom: 16px;">
                                ✓
                            </div>
                            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">
                                {{ __('You are all set, :name!', ['name' => $reservation->guest_name]) }}
                            </h2>
                            <p style="margin: 8px 0 0 0; font-size: 13px; color: #64748b;">
                                {{ __('Your reservation has been confirmed and locked for your upcoming trip.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Order Card -->
                    <tr>
                        <td style="padding: 0 28px 24px 28px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Booking Reference') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: #4f46e5; font-family: monospace;">
                                        #{{ $code }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Booked Experience') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $bookableTitle }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Trip Date') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->requested_date->format('l, M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Guests (Pax)') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->pax_count }} Persons</td>
                                </tr>
                                @if ($payment)
                                    <tr>
                                        <td colspan="2" style="padding-top: 10px; border-top: 1px solid #e2e8f0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; font-size: 13px; font-weight: 800; color: #0f172a;">{{ __('Total Paid') }}</td>
                                        <td align="right" style="padding: 4px 0; font-size: 15px; font-weight: 900; color: #059669;">
                                            Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- CTA Buttons -->
                    <tr>
                        <td style="padding: 0 28px 28px 28px; text-align: center;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                @if ($waUrl)
                                    <tr>
                                        <td style="padding-bottom: 10px;">
                                            <a href="{{ $waUrl }}" target="_blank" style="display: block; width: 100%; box-sizing: border-box; background-color: #25d366; color: #ffffff; text-decoration: none; padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 800; text-align: center;">
                                                💬 {{ __('Chat with Operator on WhatsApp') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>
                                        <a href="{{ $receiptUrl }}" target="_blank" style="display: block; width: 100%; box-sizing: border-box; background-color: #0f172a; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 14px; font-size: 12px; font-weight: 700; text-align: center;">
                                            🎟️ {{ __('View Digital E-Voucher & Receipt') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 28px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 11px; color: #94a3b8;">
                            <p style="margin: 0 0 6px 0;">
                                {{ __('Have questions or special requests? Reach out directly via WhatsApp.') }}
                            </p>
                            <p style="margin: 0; font-size: 10px; color: #cbd5e1;">
                                {{ __('Powered by :app', ['app' => config('app.name', 'Booking Engine')]) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
