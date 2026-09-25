@php
    $platformColor = '#FFEF4D';
    $platformInk = '#101730';
    $storefrontUrl = $operator->getStorefrontUrl();
    $deskUrl = $operator->getDeskUrl();
    $supportEmail = \App\Models\PlatformSetting::current()->getOperatorSupportEmail();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Welcome to :app', ['app' => config('app.name', 'TravelEngine')]) }}</title>
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
                        :eyebrow="config('app.name', 'TravelEngine').' '.__('Platform')"
                        :title="__('Tour Operator Account Created')"
                    />

                    <!-- Body -->
                    <tr>
                        <td style="padding: 28px 28px 16px 28px;">
                            <p style="margin: 0 0 16px 0; font-size: 15px; font-weight: 600; color: #0f172a;">
                                {{ __('Hello :name,', ['name' => $user->name]) }}
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                {{ __('Welcome to :app! Your operator workspace for **:operator** has been successfully provisioned. You can now manage your catalog, customize your public storefront, and accept guest bookings online.', ['app' => config('app.name', 'TravelEngine'), 'operator' => $operator->name]) }}
                            </p>

                            <!-- Account Details Card -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Business') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: {{ $platformInk }};">{{ $operator->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Page Address (Slug)') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 13px; font-mono; font-weight: 700; color: #0f172a;">{{ $operator->slug }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Login Email') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #475569;">{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Subscription') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $operator->getPlan()->name }} (Free)</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-top: 10px; border-top: 1px dashed #e2e8f0;">
                                        <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">{{ __('Your Public Storefront') }}</div>
                                        <a href="{{ $storefrontUrl }}" style="font-size: 13px; font-weight: 700; color: #0284c7; text-decoration: none; word-break: break-all;">
                                            {{ $storefrontUrl }} &rarr;
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-top: 10px;">
                                        <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">{{ __('Operator Desk Login') }}</div>
                                        <a href="{{ $deskUrl }}" style="font-size: 13px; font-weight: 700; color: #0f172a; text-decoration: none; word-break: break-all;">
                                            {{ $deskUrl }} &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <div style="margin-bottom: 28px; text-align: center;">
                                <a href="{{ $deskUrl }}" style="display: inline-block; padding: 14px 32px; background-color: {{ $platformColor }}; color: {{ $platformInk }}; text-decoration: none; border-radius: 14px; font-weight: 800; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(16, 23, 48, 0.12);">
                                    {{ __('Open Operator Desk') }} &rarr;
                                </a>
                            </div>

                            <!-- Setup Checklist Tips -->
                            <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; margin-bottom: 20px;">
                                <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 10px;">
                                    {{ __('Next Steps to Start Taking Bookings') }}
                                </div>
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #475569; vertical-align: top; width: 24px;">1.</td>
                                        <td style="padding: 6px 0; font-size: 13px; color: #475569;">
                                            <strong>{{ __('Upload Brand Logo & Bio') }}:</strong> {{ __('Personalize your shop name, avatar, and contact line.') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #475569; vertical-align: top; width: 24px;">2.</td>
                                        <td style="padding: 6px 0; font-size: 13px; color: #475569;">
                                            <strong>{{ __('Create Your First Tour or Activity') }}:</strong> {{ __('Add photos, inclusions, departure capacity, and rates.') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #475569; vertical-align: top; width: 24px;">3.</td>
                                        <td style="padding: 6px 0; font-size: 13px; color: #475569;">
                                            <strong>{{ __('Configure Payout Bank Account') }}:</strong> {{ __('Ensure seamless disbursement of your guest payments.') }}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b;">
                                {{ __('Need help getting started? Feel free to reply to this email or reach our support team at :email.', ['email' => $supportEmail]) }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 28px; background-color: #f1f5f9; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
                            {{ __('Sent by :app Platform Team', ['app' => config('app.name', 'TravelEngine')]) }} &bull; {{ $operator->booking_notification_email ?: $user->email }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
