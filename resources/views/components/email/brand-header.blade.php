@props([
    'background',
    'foreground',
    'title',
    'logoUrl' => null,
    'logoAlt' => '',
    'subtitle' => null,
    'eyebrow' => null,
])

@php
    $absoluteLogoUrl = filled($logoUrl) ? (string) $logoUrl : null;

    if (is_string($absoluteLogoUrl) && ! str_starts_with($absoluteLogoUrl, 'http://') && ! str_starts_with($absoluteLogoUrl, 'https://')) {
        $absoluteLogoUrl = url($absoluteLogoUrl);
    }

    $usesInk = strcasecmp(ltrim((string) $foreground, '#'), 'ffffff') !== 0;
    $muted = $usesInk ? 'rgba(16, 23, 48, 0.72)' : 'rgba(255, 255, 255, 0.85)';
    $eyebrowBackground = $usesInk ? 'rgba(16, 23, 48, 0.08)' : 'rgba(255, 255, 255, 0.18)';
@endphp
<tr>
    <td style="background-color: {{ $background }}; padding: 32px 24px; text-align: center;">
        @if ($absoluteLogoUrl)
            <img src="{{ $absoluteLogoUrl }}" alt="{{ $logoAlt }}" height="48" style="display: block; margin: 0 auto 14px auto; max-height: 48px; width: auto; max-width: 160px; border: 0; border-radius: 10px; background-color: #ffffff;">
        @endif
        @if (filled($eyebrow))
            <span style="display: inline-block; padding: 4px 12px; border-radius: 9999px; background-color: {{ $eyebrowBackground }}; color: {{ $foreground }}; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                {{ $eyebrow }}
            </span>
        @endif
        <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: {{ $foreground }}; letter-spacing: -0.5px;">
            {{ $title }}
        </h1>
        @if (filled($subtitle))
            <p style="margin: 6px 0 0 0; font-size: 13px; color: {{ $muted }}; font-weight: 500;">
                {{ $subtitle }}
            </p>
        @endif
    </td>
</tr>
