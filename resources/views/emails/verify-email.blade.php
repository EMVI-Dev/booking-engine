@php
    $platformColor = '#FFEF4D';
    $platformInk = '#101730';
    $operator = $operator ?? ($user instanceof \App\Models\User ? $user->currentOperator() : null);
    $supportEmail = \App\Models\PlatformSetting::current()->getOperatorSupportEmail();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Verify Email Address - :app', ['app' => config('app.name', 'TravelEngine')]) }}</title>
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
                        :logo-alt="config('app.name', 'TravelEngine')"
                        :eyebrow="config('app.name', 'TravelEngine').' '.__('Platform')"
                        :title="__('Verify Your Email Address')"
                    />

                    <!-- Body -->
                    <tr>
                        <td style="padding: 28px 28px 16px 28px;">
                            <p style="margin: 0 0 16px 0; font-size: 15px; font-weight: 600; color: #0f172a;">
                                {{ __('Hello :name,', ['name' => $user->name ?: 'there']) }}
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                {{ __('Please confirm your email address to complete your registration and secure access to your :app workspace.', ['app' => config('app.name', 'TravelEngine')]) }}
                            </p>

                            <!-- Account Details Card -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px; margin-bottom: 24px;">
                                @if ($operator)
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Workspace') }}</td>
                                        <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 800; color: {{ $platformInk }};">{{ $operator->name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Page Address (Slug)') }}</td>
                                        <td align="right" style="padding: 6px 0; font-size: 13px; font-family: monospace; font-weight: 700; color: #0f172a;">{{ $operator->slug }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Login Email') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 600; color: #475569;">{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Link Expiration') }}</td>
                                    <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ __(':minutes minutes', ['minutes' => config('auth.verification.expire', 60)]) }}</td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <div style="margin-bottom: 28px; text-align: center;">
                                <a href="{{ $url }}" style="display: inline-block; padding: 14px 32px; background-color: {{ $platformColor }}; color: {{ $platformInk }}; text-decoration: none; border-radius: 14px; font-weight: 800; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(16, 23, 48, 0.12);">
                                    {{ __('Verify Email Address') }} &rarr;
                                </a>
                            </div>

                            <!-- Security Notice -->
                            <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; margin-bottom: 20px;">
                                <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">
                                    {{ __('Security Notice') }}
                                </div>
                                <p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                    {{ __('If you did not create an account on :app, no further action is required. You can safely disregard this email.', ['app' => config('app.name', 'TravelEngine')]) }}
                                </p>
                            </div>

                            <!-- Fallback Link -->
                            <div style="background-color: #f8fafc; border-radius: 12px; padding: 14px; margin-bottom: 20px; font-size: 12px; color: #64748b; line-height: 1.6;">
                                <p style="margin: 0 0 6px 0;">
                                    {{ __('If you are having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:') }}
                                </p>
                                <a href="{{ $url }}" style="font-size: 12px; color: #0284c7; text-decoration: underline; word-break: break-all;">
                                    {{ $url }}
                                </a>
                            </div>

                            <p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b;">
                                {{ __('Need help getting started? Feel free to reach our support team at :email.', ['email' => $supportEmail]) }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 28px; background-color: #f1f5f9; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
                            {{ __('Sent by :app Platform Team', ['app' => config('app.name', 'TravelEngine')]) }} &bull; {{ $user->email }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
