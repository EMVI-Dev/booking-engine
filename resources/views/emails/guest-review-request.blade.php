@php
    $agent = $reservation->agent;
    $brandColor = $agent->brand_color ?? '#4f46e5';
    $code = $reservation->code ?: strtoupper(substr($reservation->id, -8));
    $bookableTitle = $reservation->bookable?->name ?? ($reservation->bookable?->title ?? 'Tour Experience');
    $tripDate = \Illuminate\Support\Carbon::parse($reservation->requested_date)->translatedFormat('d F Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('How was your experience?') }}</title>
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
                                {{ __('We hope you loved your adventure!') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Review Hero -->
                    <tr>
                        <td style="padding: 32px 28px 20px 28px; text-align: center;">
                            <div style="display: inline-block; padding: 8px 16px; border-radius: 9999px; background-color: #fef3c7; color: #b45309; font-size: 12px; font-weight: 800; margin-bottom: 16px; letter-spacing: 0.08em; text-transform: uppercase;">
                                {{ __('Review request') }}
                            </div>
                            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">
                                {{ __('Thank you for joining us, :name!', ['name' => $reservation->guest_name]) }}
                            </h2>
                            <p style="margin: 10px 0 0 0; font-size: 14px; color: #64748b; line-height: 1.6;">
                                {{ __('We hope you had an unforgettable experience on your :tour on :date.', ['tour' => $bookableTitle, 'date' => $tripDate]) }}
                            </p>
                            <p style="margin: 10px 0 0 0; font-size: 13px; color: #64748b;">
                                {{ __('Your feedback helps other travelers find amazing local adventures and means the world to our team & guides.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Review CTA Button -->
                    <tr>
                        <td style="padding: 10px 28px 32px 28px; text-align: center;">
                            <a href="{{ $reviewUrl }}" target="_blank" style="display: inline-block; background-color: {{ $brandColor }}; color: #ffffff; font-size: 15px; font-weight: 800; text-decoration: none; padding: 14px 32px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.25);">
                                {{ __('Leave a Review on Google / TripAdvisor') }}
                            </a>
                        </td>
                    </tr>

                    <!-- Booking Reference Box -->
                    <tr>
                        <td style="padding: 0 28px 24px 28px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 14px 18px;">
                                <tr>
                                    <td style="font-size: 12px; color: #64748b;">
                                        <strong>{{ __('Booking Reference:') }}</strong> #{{ $code }}
                                    </td>
                                    <td align="right" style="font-size: 12px; color: #64748b;">
                                        {{ $reservation->pax_count }} {{ __('Guests') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 28px; text-align: center; font-size: 11px; color: #94a3b8;">
                            <p style="margin: 0;">
                                @if ($agent->showsPlatformBranding())
                                    {{ __('Sent by :agent via :platform', ['agent' => $agent->name ?? 'Tour Operator', 'platform' => config('app.name')]) }}
                                @else
                                    {{ __('Sent by :agent', ['agent' => $agent->name ?? 'Tour Operator']) }}
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
