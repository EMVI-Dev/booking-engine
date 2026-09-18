@php
    $operator = $reservation->operator;
    $brandColor = $operator->brand_color ?? '#FFEF4D';
    $brandForeground = $operator->brand_foreground_color ?? '#101730';
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $vendorUrl = route('storefront.reservation.vendor-view', ['reservation' => $reservation, 'token' => $reservation->public_token]);
    $activityList = ! empty($activities) ? implode(', ', $activities) : ($reservation->bookable?->name ?? ($reservation->bookable?->title ?? 'Activity'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Booking Cancelled - :operator', ['operator' => $operator->name]) }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                    <x-email.brand-header
                        :background="$brandColor"
                        :foreground="$brandForeground"
                        :logo-url="$operator->logo_url"
                        :logo-alt="$operator->name"
                        :eyebrow="__('Cancellation Notice')"
                        :title="__('Booking Cancelled #:code', ['code' => $code])"
                    />

                    <tr>
                        <td style="padding: 28px 28px 20px 28px;">
                            <div style="text-align: center; margin-bottom: 20px;">
                                <div style="display: inline-block; padding: 6px 14px; border-radius: 9999px; background-color: #fef2f2; color: #dc2626; font-size: 12px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;">
                                    {{ __('Cancelled') }}
                                </div>
                            </div>

                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569;">
                                {{ __('Hello :vendor, please be informed that booking #:code from :operator has been cancelled. Please release any slots or preparations.', [
                                    'vendor' => $vendor->name,
                                    'code' => $code,
                                    'operator' => $operator->name,
                                ]) }}
                            </p>

                            <!-- Cancelled Booking Details -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px; margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 4px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Booking Code') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 13px; font-weight: 800; color: #dc2626; font-family: monospace;">#{{ $code }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Activity') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $activityList }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Date') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 12px; font-weight: 800; color: #0f172a;">{{ $reservation->requested_date->format('l, M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Lead Guest') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->guest_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Total Pax') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $reservation->pax_count }} Pax</td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.5;">
                                @if ($operator->phone)
                                    @php
                                        $waDigits = preg_replace('/[^0-9]/', '', (string) $operator->phone);
                                        if (str_starts_with($waDigits, '0')) {
                                            $waDigits = '62' . substr($waDigits, 1);
                                        }
                                        $waUrl = 'https://wa.me/' . $waDigits;
                                    @endphp
                                    {{ __('For questions, adjustments, or coordination regarding this cancellation, please contact :operator directly on WhatsApp at', ['operator' => $operator->name]) }}
                                    <a href="{{ $waUrl }}" target="_blank" style="color: #059669; font-weight: 700; text-decoration: underline;">{{ $operator->phone }}</a>@if ($operator->email)
                                        {{ __('or via email at') }}
                                        <a href="mailto:{{ $operator->email }}" style="color: #0284c7; font-weight: 700; text-decoration: underline;">{{ $operator->email }}</a>
                                    @endif.
                                @elseif ($operator->email)
                                    {{ __('For questions or coordination, please contact :operator directly at', ['operator' => $operator->name]) }}
                                    <a href="mailto:{{ $operator->email }}" style="color: #0284c7; font-weight: 700; text-decoration: underline;">{{ $operator->email }}</a>.
                                @else
                                    {{ __('This is an automated cancellation notification from :operator.', ['operator' => $operator->name]) }}
                                @endif
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
