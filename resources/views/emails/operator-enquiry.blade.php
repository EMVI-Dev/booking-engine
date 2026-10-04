@php
    /** @var \App\Models\Enquiry $enquiry */
    $platformColor = '#FFEF4D';
    $platformInk = '#101730';
    $rows = array_filter([
        __('From') => $enquiry->name,
        __('WhatsApp') => $enquiry->whatsapp,
        __('Email') => $enquiry->email,
        __('Preferred date') => $enquiry->preferred_date?->format('j M Y'),
        __('Group size') => $enquiry->group_size ? (string) $enquiry->group_size : null,
    ], fn ($value) => filled($value));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $enquiry->typeLabel() }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; overflow: hidden;">
                    <x-email.brand-header
                        :background="$platformColor"
                        :foreground="$platformInk"
                        :logo-url="url('/favicon.png')"
                        :logo-alt="config('app.name')"
                        :eyebrow="$enquiry->operator->name"
                        :title="$enquiry->isPrivateGroup() ? __('New private trip request') : __('New question from your website')"
                    />
                    <tr>
                        <td style="padding: 28px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px;">
                                @foreach ($rows as $label => $value)
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 12px; color: #64748b;">{{ $label }}</td>
                                        <td align="right" style="padding: 6px 0; font-size: 12px; font-weight: 700; color: #0f172a;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>

                            <p style="margin: 20px 0 6px 0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">{{ __('Message') }}</p>
                            <p style="margin: 0; font-size: 14px; color: #0f172a; white-space: pre-line;">{{ $enquiry->message }}</p>

                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top: 24px;">
                                <tr>
                                    <td style="border-radius: 12px; background-color: #16a34a;">
                                        <a href="{{ $replyUrl }}" style="display: inline-block; padding: 12px 20px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none;">{{ __('Reply on WhatsApp') }}</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 16px 0 0 0; font-size: 12px; color: #64748b;">
                                {{ __('It is also saved under Enquiries in your desk.') }}
                                <a href="{{ route('enquiries.index') }}" style="color: {{ $platformInk }}; font-weight: 700;">{{ __('Open Enquiries') }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
