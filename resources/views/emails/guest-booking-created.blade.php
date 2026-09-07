@php
    $agent = $reservation->agent;
    $brandColor = $agent->brand_color ?? '#4f46e5';
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $payment = $reservation->latestPayment;
    $bookableTitle = $reservation->bookable?->name ?? ($reservation->bookable?->title ?? 'Direct Booking');
    $payUrl = route('storefront.reservation.pay', $reservation);

    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $waUrl = $agent->contact_whatsapp ? $waService->getPaymentHoldLinkUrl($reservation) : null;
    $amountDue = $payment ? (float) $payment->amount : ($reservation->pax_count * (float) ($reservation->bookable?->price ?? 0));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Complete Your Booking Payment') }}</title>
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
                                {{ __('Reservation Placed • 30-Minute Hold') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Hold Notice Hero -->
                    <tr>
                        <td style="padding: 32px 28px 20px 28px; text-align: center;">
                            <div style="display: inline-block; width: 56px; height: 56px; line-height: 56px; border-radius: 18px; background-color: #fef3c7; color: #d97706; font-size: 12px; font-weight: 800; margin-bottom: 16px; letter-spacing: 0.06em;">
                                {{ __('HOLD') }}
                            </div>
                            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">
                                {{ __('Reservation Held for :name', ['name' => $reservation->guest_name]) }}
                            </h2>
                            <p style="margin: 8px 0 0 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                {{ __('Your booking has been received and your spots are reserved on a 30-minute hold. Please complete payment to secure and confirm your trip.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Primary Pay CTA Button -->
                    <tr>
                        <td style="padding: 0 28px 24px 28px; text-align: center;">
                            <a href="{{ $payUrl }}" target="_blank" style="display: block; background-color: #d97706; color: #ffffff; font-size: 15px; font-weight: 800; text-decoration: none; padding: 14px 24px; border-radius: 14px; box-shadow: 0 10px 15px -3px rgba(217, 119, 6, 0.3);">
                                {{ __('Complete Payment (Pay Now)') }}
                            </a>
                            <p style="margin: 10px 0 0 0; font-size: 11px; color: #94a3b8;">
                                {{ __('If your payment tab was closed or you need to retry with a different method, click above.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Booking Summary Card -->
                    <tr>
                        <td style="padding: 0 28px 24px 28px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Booking Reference') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: #d97706; font-family: monospace;">
                                        #{{ $code }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Experience') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #0f172a;">
                                        {{ $bookableTitle }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Trip Date') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #0f172a;">
                                        {{ $reservation->requested_date->format('l, d F Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Guests') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #0f172a;">
                                        {{ __(':count guests', ['count' => $reservation->pax_count]) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0 4px 0; font-size: 12px; font-weight: 800; color: #0f172a; border-top: 1px dashed #cbd5e1;">
                                        {{ __('Amount Due') }}
                                    </td>
                                    <td align="right" style="padding: 10px 0 4px 0; font-size: 16px; font-weight: 900; color: #d97706; border-top: 1px dashed #cbd5e1;">
                                        Rp {{ number_format($amountDue, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Assistance Section -->
                    @if ($waUrl)
                        <tr>
                            <td style="padding: 0 28px 28px 28px; text-align: center;">
                                <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 14px; padding: 14px 18px;">
                                    <p style="margin: 0 0 8px 0; font-size: 12px; color: #065f46; font-weight: 700;">
                                        {{ __('Need help with your reservation or payment?') }}
                                    </p>
                                    <a href="{{ $waUrl }}" target="_blank" style="display: inline-block; background-color: #059669; color: #ffffff; font-size: 12px; font-weight: 700; text-decoration: none; padding: 8px 16px; border-radius: 10px;">
                                        {{ __('Chat with Operator on WhatsApp') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 24px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0; font-size: 11px; color: #94a3b8;">
                                {{ __('Automated booking message from :agent. All rights reserved.', ['agent' => $agent->name ?? config('app.name')]) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
