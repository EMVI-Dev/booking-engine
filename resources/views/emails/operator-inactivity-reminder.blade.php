@php
    $platformColor = '#FFEF4D';
    $platformInk = '#101730';
    $deskUrl = $operator->getDeskUrl();
    $supportEmail = \App\Models\PlatformSetting::current()->getOperatorSupportEmail();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Account Inactivity Notice') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                    <x-email.brand-header
                        :background="$platformColor"
                        :foreground="$platformInk"
                        :logo-url="url('/favicon.png')"
                        :logo-alt="config('app.name')"
                        :eyebrow="config('app.name', 'TravelEngine').' '.__('Account')"
                        :title="__('Account Inactivity Notice')"
                    />

                    <!-- Body -->
                    <tr>
                        <td style="padding: 28px 28px 16px 28px;">
                            <p style="margin: 0 0 16px 0; font-size: 15px; font-weight: 600; color: #0f172a;">
                                {{ __('Hello :name,', ['name' => $operator->name]) }}
                            </p>
                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                {{ __('We noticed that your tour operator workspace has had no recorded activity for over :days days.', ['days' => $inactiveDays]) }}
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                {{ __('To ensure high availability and clean catalog listings for travelers, accounts that remain inactive for 90 days (3 months) are automatically suspended. Simply logging into your desk keeps your account active and ready for reservations.', ['app' => config('app.name', 'TravelEngine')]) }}
                            </p>

                            <!-- Account Details Box -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Operator') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: {{ $platformInk }};">{{ $operator->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Slug') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-mono; font-weight: 700; color: #0f172a;">{{ $operator->slug }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Inactive Period') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #d97706;">{{ $inactiveDays }} {{ __('days') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Suspension Policy') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #e11d48;">{{ __('90 days without activity') }}</td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <div style="margin-bottom: 24px; text-align: center;">
                                <a href="{{ $deskUrl }}" style="display: inline-block; padding: 14px 32px; background-color: {{ $platformColor }}; color: {{ $platformInk }}; text-decoration: none; border-radius: 14px; font-weight: 800; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(16, 23, 48, 0.12);">
                                    {{ __('Log In to Operator Desk') }} &rarr;
                                </a>
                            </div>

                            <p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b;">
                                {{ __('Have questions or need assistance setting up your tours? Contact our platform team at :email.', ['email' => $supportEmail]) }}
                            </p>
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
