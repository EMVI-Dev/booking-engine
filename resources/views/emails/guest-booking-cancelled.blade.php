@php
    $agent = $reservation->operator;
    $brandColor = $agent->brand_color ?? '#FFEF4D';
    $brandForeground = $agent->brand_foreground_color ?? '#101730';
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $bookableTitle = $reservation->bookable?->name ?? ($reservation->bookable?->title ?? 'Direct Booking');
    $receiptUrl = route('storefront.reservation.receipt', $reservation);

    $waService = app(\App\Services\WhatsAppDispatchService::class);
    $waUrl = $agent->contact_whatsapp ? $waService->getGuestEnquiryUrl($reservation) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Your booking is cancelled') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                    <x-email.brand-header
                        :background="$brandColor"
                        :foreground="$brandForeground"
                        :logo-url="$agent->logo_url"
                        :logo-alt="$agent->name ?? config('app.name')"
                        :title="$agent->name ?? config('app.name')"
                        :subtitle="__('Booking Cancelled')"
                    />

                    <!-- Hero -->
                    <tr>
                        <td style="padding: 32px 28px 20px 28px; text-align: center;">
                            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">
                                {{ __('Your booking is cancelled') }}
                            </h2>
                            <p style="margin: 8px 0 0 0; font-size: 13px; color: #64748b;">
                                @if ($cancelledByGuest)
                                    {{ __('Hi :name, we have cancelled this booking as you asked.', ['name' => $reservation->guest_name]) }}
                                @else
                                    {{ __('Hi :name, :operator cancelled this booking. We are sorry for the change of plans.', ['name' => $reservation->guest_name, 'operator' => $agent->name]) }}
                                @endif
                            </p>
                        </td>
                    </tr>

                    <!-- Booking Card -->
                    <tr>
                        <td style="padding: 0 28px 24px 28px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Booking Reference') }}
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: #0f172a; font-family: monospace;">
                                        #{{ $code }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Experience') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $bookableTitle }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Trip Date') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a; text-decoration: line-through;">{{ $reservation->requested_date->format('l, M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Guests') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->pax_count }} {{ __('guests') }}</td>
                                </tr>
                                @if ($refundAmount > 0)
                                    <tr>
                                        <td colspan="2" style="padding-top: 10px; border-top: 1px solid #e2e8f0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; font-size: 13px; font-weight: 800; color: #0f172a;">{{ __('Refund') }}</td>
                                        <td align="right" style="padding: 4px 0; font-size: 15px; font-weight: 900; color: #059669;">
                                            Rp {{ number_format($refundAmount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding: 6px 0 0 0; font-size: 12px; color: #64748b;">
                                            {{ __('The money goes back to your original payment method, usually within a few working days. You do not need to do anything.') }}
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
                                                {{ __('Chat with Operator on WhatsApp') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>
                                        <a href="{{ $receiptUrl }}" target="_blank" style="display: block; width: 100%; box-sizing: border-box; background-color: #0f172a; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 14px; font-size: 12px; font-weight: 700; text-align: center;">
                                            {{ __('View the booking') }}
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
                                {{ __('Want to pick another date? Message us on WhatsApp and we will help.') }}
                            </p>
                            @if ($agent->showsPlatformBranding())
                            <p style="margin: 0; font-size: 10px; color: #cbd5e1;">
                                {{ __('Powered by :app', ['app' => config('app.name', 'Booking Engine')]) }}
                            </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
