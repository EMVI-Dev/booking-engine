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
    <title>{{ __('Booking Notification from :operator', ['operator' => $operator->name]) }}</title>
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
                        :eyebrow="__('Booking Notification')"
                        :title="__('New Booking from :operator', ['operator' => $operator->name])"
                    />

                    <tr>
                        <td style="padding: 28px 28px 20px 28px;">
                            @if (! empty($isTest))
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 14px; padding: 12px 16px; margin-bottom: 20px;">
                                    <tr>
                                        <td align="center">
                                            <div style="font-size: 12px; font-weight: 800; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">
                                                ⚠️ {{ __('Test Email Dispatch') }}
                                            </div>
                                            <div style="font-size: 11px; color: #b45309; margin-top: 2px;">
                                                {{ __('This is a test notification sent to verify vendor reservation email connectivity.') }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569;">
                                {{ __('Hello :vendor, you have received a new booking originated from :operator.', ['vendor' => $vendor->name, 'operator' => $operator->name]) }}
                            </p>

                            <!-- Booker / Operator Card -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px; margin-bottom: 20px;">
                                <tr>
                                    <td colspan="2" style="padding-bottom: 8px; font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Booker Information') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Business / Operator') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $operator->name }}</td>
                                </tr>
                                @if ($operator->email)
                                    <tr>
                                        <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Operator Email') }}</td>
                                        <td align="right" style="padding: 4px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $operator->email }}</td>
                                    </tr>
                                @endif
                                @if ($operator->phone)
                                    @php
                                        $waDigits = preg_replace('/[^0-9]/', '', (string) $operator->phone);
                                        if (str_starts_with($waDigits, '0')) {
                                            $waDigits = '62' . substr($waDigits, 1);
                                        }
                                        $waUrl = 'https://wa.me/' . $waDigits;
                                    @endphp
                                    <tr>
                                        <td style="padding: 4px 0; font-size: 12px; color: #64748b;">{{ __('Phone / WhatsApp') }}</td>
                                        <td align="right" style="padding: 4px 0; font-size: 12px; font-weight: 700;">
                                            <a href="{{ $waUrl }}" target="_blank" style="color: #059669; text-decoration: underline; font-weight: 700;">
                                                {{ $operator->phone }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            <!-- Booking Details Card -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px; margin-bottom: 24px;">
                                <tr>
                                    <td colspan="2" style="padding-bottom: 8px; font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ __('Booking Details') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Booking Code') }}</td>
                                    <td align="right" style="padding: 4px 0; font-size: 13px; font-weight: 800; color: #0f172a; font-family: monospace;">#{{ $code }}</td>
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
                                @if ($reservation->notes)
                                    <tr>
                                        <td colspan="2" style="padding: 10px 0 4px 0; font-size: 11px; color: #64748b; font-style: italic;">
                                            &ldquo;{{ $reservation->notes }}&rdquo;
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            <!-- CTA Button -->
                            <div style="text-align: center; margin-bottom: 24px;">
                                <a href="{{ $vendorUrl }}" style="display: inline-block; background-color: #0f172a; color: #ffffff; font-size: 14px; font-weight: 800; text-decoration: none; padding: 14px 28px; border-radius: 14px; letter-spacing: 0.3px;">
                                    {{ __('View Full Booking Details →') }}
                                </a>
                            </div>

                            <p style="margin: 0; font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.5;">
                                @if ($operator->phone)
                                    @php
                                        $waDigits = preg_replace('/[^0-9]/', '', (string) $operator->phone);
                                        if (str_starts_with($waDigits, '0')) {
                                            $waDigits = '62' . substr($waDigits, 1);
                                        }
                                        $waUrl = 'https://wa.me/' . $waDigits;
                                    @endphp
                                    {{ __('This is an automated notification. For questions, adjustments, or coordination regarding this booking, please contact :operator directly on WhatsApp at', ['operator' => $operator->name]) }}
                                    <a href="{{ $waUrl }}" target="_blank" style="color: #059669; font-weight: 700; text-decoration: underline;">{{ $operator->phone }}</a>@if ($operator->email)
                                        {{ __('or via email at') }}
                                        <a href="mailto:{{ $operator->email }}" style="color: #0284c7; font-weight: 700; text-decoration: underline;">{{ $operator->email }}</a>
                                    @endif.
                                @elseif ($operator->email)
                                    {{ __('This is an automated notification. For questions, adjustments, or coordination regarding this booking, please contact :operator directly at', ['operator' => $operator->name]) }}
                                    <a href="mailto:{{ $operator->email }}" style="color: #0284c7; font-weight: 700; text-decoration: underline;">{{ $operator->email }}</a>.
                                @else
                                    {{ __('This is an automated notification from :operator.', ['operator' => $operator->name]) }}
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
