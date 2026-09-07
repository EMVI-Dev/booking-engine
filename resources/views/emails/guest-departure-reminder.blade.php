@php
    $agent = $reservation->agent;
    $brandColor = $agent->brand_color ?? '#4f46e5';
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $bookableTitle = $reservation->bookable?->name ?? ($reservation->bookable?->title ?? __('Tour Experience'));
    $receiptUrl = route('storefront.reservation.receipt', $reservation);

    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $waUrl = $agent->contact_whatsapp ? $waService->getConfirmationUrl($reservation) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Upcoming Trip Departure Reminder') }}</title>
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
                                {{ __('Upcoming Trip Reminder') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Reminder Hero -->
                    <tr>
                        <td style="padding: 32px 28px 20px 28px; text-align: center;">
                            <div style="display: inline-block; width: 56px; height: 56px; line-height: 56px; border-radius: 18px; background-color: #e0e7ff; color: #4338ca; font-size: 13px; font-weight: 800; margin-bottom: 16px;">
                                24h
                            </div>
                            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">
                                {{ __('Get ready for your trip, :name!', ['name' => $reservation->guest_name]) }}
                            </h2>
                            <p style="margin: 8px 0 0 0; font-size: 13px; color: #64748b;">
                                {{ __('Your scheduled departure is tomorrow. Here are your key trip details and pickup instructions.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Reservation Summary Card -->
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
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Experience') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #0f172a;">
                                        {{ $bookableTitle }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Departure Date') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #059669;">
                                        {{ $reservation->requested_date->format('l, d F Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Total Guests') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 700; color: #0f172a;">
                                        {{ $reservation->pax_count }} {{ __('person(s)') }}
                                    </td>
                                </tr>
                                @if (!empty($reservation->notes))
                                    <tr>
                                        <td colspan="2" style="padding-top: 12px; border-top: 1px dashed #e2e8f0; font-size: 12px; color: #64748b;">
                                            <strong>{{ __('Notes / Pickup details') }}:</strong> {{ $reservation->notes }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- Action Buttons -->
                    <tr>
                        <td style="padding: 0 28px 28px 28px; text-align: center;">
                            <a href="{{ $receiptUrl }}" style="display: block; width: 100%; box-sizing: border-box; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 14px; padding: 14px 20px; border-radius: 14px; margin-bottom: 10px;">
                                {{ __('View E-Voucher & Live Status') }} &rarr;
                            </a>

                            @if ($waUrl)
                                <a href="{{ $waUrl }}" style="display: block; width: 100%; box-sizing: border-box; background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px 20px; border-radius: 14px;">
                                    {{ __('Contact Operator on WhatsApp') }}
                                </a>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer Note -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 28px; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p style="margin: 0; font-size: 11px; color: #94a3b8;">
                                &copy; {{ date('Y') }} {{ $agent->name ?? config('app.name') }}. {{ __('All rights reserved.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
