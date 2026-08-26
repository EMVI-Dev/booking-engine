<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Subscription Renewal Reminder') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                    <!-- Top Header -->
                    <tr>
                        <td style="background-color: #1e1b4b; padding: 28px 24px; text-align: center;">
                            <span style="display: inline-block; padding: 4px 12px; border-radius: 9999px; background-color: rgba(147, 51, 234, 0.2); color: #d8b4fe; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                                👑 {{ config('app.name', 'TravelEngine') }} {{ __('Subscription') }}
                            </span>
                            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                @if ($daysRemaining <= 0)
                                    ⚠️ {{ __('Subscription Expired') }}
                                @else
                                    ⏰ {{ __('Upcoming Plan Renewal') }}
                                @endif
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 28px 28px 16px 28px;">
                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569;">
                                {{ __('Hello :name,', ['name' => $operator->name]) }}
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569;">
                                @if ($daysRemaining <= 0)
                                    {{ __('Your **:plan** subscription has expired. Renew now to maintain your custom branding, reduced platform take-rate, and active marketing tools.', ['plan' => $plan->name]) }}
                                @else
                                    {{ __('This is a friendly reminder that your **:plan** subscription will expire in **:days days**.', ['plan' => $plan->name, 'days' => $daysRemaining]) }}
                                @endif
                            </p>

                            <!-- Breakdown Card -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Current Plan') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: #7e22ce;">{{ $plan->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Take Rate') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $plan->commission_rate * 100 }}%</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Rate') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">
                                        Rp {{ number_format((float) $plan->price_monthly, 0, ',', '.') }}/{{ __('month') }}
                                    </td>
                                </tr>
                                @if ($operator->plan_expires_at)
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ __('Expiry Date') }}</td>
                                        <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #e11d48;">
                                            {{ $operator->plan_expires_at->format('d M Y') }}
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            <!-- CTA Button -->
                            <div style="margin-top: 24px; text-align: center;">
                                <a href="{{ route('settings.plan') }}" style="display: inline-block; padding: 14px 28px; background-color: #7e22ce; color: #ffffff; text-decoration: none; border-radius: 14px; font-weight: 700; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(126, 34, 206, 0.3);">
                                    {{ __('Manage & Renew Subscription') }} &rarr;
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 28px; background-color: #f1f5f9; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
                            {{ __('Sent by :app Platform Team', ['app' => config('app.name', 'TravelEngine')]) }} &bull; {{ $operator->billing_email ?: $operator->booking_notification_email }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
