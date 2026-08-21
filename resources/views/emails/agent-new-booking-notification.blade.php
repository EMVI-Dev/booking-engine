@php
    $agent = $reservation->agent;
    $brandColor = $agent->brand_color ?? '#4f46e5';
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $payment = $reservation->latestPayment;
    $bookableTitle = $reservation->bookable?->name ?? ($reservation->bookable?->title ?? 'Direct Booking');
    $reservationUrl = route('reservations.index');

    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $waGuestUrl = $reservation->guest_contact
        ? $waService->buildWhatsAppUrl($reservation->guest_contact, "Hello {$reservation->guest_name}, this is {$agent->name} regarding your booking #{$code} for {$bookableTitle}.")
        : null;

    $split = $payment?->split_details ?? [];
    $agentNet = $split['agent_amount'] ?? ($payment ? round($payment->amount * 0.9, 2) : 0);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('New Booking Alert') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                    <!-- Top Header -->
                    <tr>
                        <td style="background-color: #1e1b4b; padding: 28px 24px; text-align: center;">
                            <span style="display: inline-block; padding: 4px 12px; border-radius: 9999px; background-color: rgba(99, 102, 241, 0.2); color: #a5b4fc; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                                ⚡ {{ __('Operator Notification') }}
                            </span>
                            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                🎉 {{ __('New Booking Received!') }}
                            </h1>
                        </td>
                    </tr>

                    <!-- Booking Summary Hero -->
                    <tr>
                        <td style="padding: 28px 28px 16px 28px;">
                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569;">
                                {{ __('A guest has completed checkout and locked a reservation for your experience.') }}
                            </p>

                            <!-- Breakdown Card -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Booking Code') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: #4f46e5; font-family: monospace;">#{{ $code }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Lead Guest') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->guest_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('WhatsApp / Phone') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->guest_contact }}</td>
                                </tr>
                                @if ($reservation->guest_email)
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Guest Email') }}</td>
                                        <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->guest_email }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Booked Item') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $bookableTitle }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Trip Date') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 800; color: #4f46e5;">{{ $reservation->requested_date->format('l, M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Party Size') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->pax_count }} Pax</td>
                                </tr>
                                @if ($reservation->notes)
                                    <tr>
                                        <td colspan="2" style="padding: 10px 0 4px 0; font-size: 11px; color: #64748b; font-style: italic;">
                                            &ldquo;{{ $reservation->notes }}&rdquo;
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td colspan="2" style="padding-top: 10px; border-top: 1px solid #e2e8f0;"></td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Gross Total') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 12px; font-weight: 700; color: #0f172a;">
                                        Rp {{ number_format((float) ($payment?->amount ?? 0), 0, ',', '.') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 13px; font-weight: 800; color: #059669;">{{ __('Your Net Earnings') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 15px; font-weight: 900; color: #059669;">
                                        Rp {{ number_format((float) $agentNet, 0, ',', '.') }}
                                    </td>
                                </tr>
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
                                                💬 {{ __('Message Guest on WhatsApp') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>
                                        <a href="{{ $reservationUrl }}" target="_blank" style="display: block; width: 100%; box-sizing: border-box; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 14px; font-size: 12px; font-weight: 700; text-align: center;">
                                            📂 {{ __('Open in Agent Portal') }}
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
                                {{ __('Earnings have been credited to your Wallet Ledger in escrow.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
