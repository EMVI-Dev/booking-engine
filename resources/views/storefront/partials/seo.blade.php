@php
    $seoService = app(\App\Services\StorefrontSeoService::class);
    $share = $share ?? $seoService->shareImage($agent, $coverPath ?? null);
    $favicon = $seoService->absoluteMediaUrl($agent->logo) ?? url(asset('favicon.png'));
@endphp
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}" />
<link rel="canonical" href="{{ $url }}" />

@if ($agent->isDemo())
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />
<meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet" />
@else
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
<meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
<meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
<link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}" />
<link rel="alternate" type="text/plain" href="{{ url('/llms.txt') }}" title="LLMs Text Summary" />
@endif

<link rel="icon" href="{{ $favicon }}" />
<link rel="apple-touch-icon" href="{{ $favicon }}" />

<meta property="og:locale" content="en_US" />
<meta property="og:type" content="{{ $type ?? 'website' }}" />
<meta property="og:url" content="{{ $url }}" />
<meta property="og:title" content="{{ $title }}" />
<meta property="og:description" content="{{ $description }}" />
<meta property="og:site_name" content="{{ $agent->storefrontSiteName() }}" />
<meta property="og:image" content="{{ $share['url'] }}" />
<meta property="og:image:secure_url" content="{{ $share['url'] }}" />
<meta property="og:image:alt" content="{{ $share['alt'] }}" />
<meta property="og:image:width" content="{{ $share['width'] }}" />
<meta property="og:image:height" content="{{ $share['height'] }}" />

<meta name="twitter:card" content="{{ $share['card'] }}" />
<meta name="twitter:title" content="{{ $title }}" />
<meta name="twitter:description" content="{{ $description }}" />
<meta name="twitter:image" content="{{ $share['url'] }}" />
<meta name="twitter:image:alt" content="{{ $share['alt'] }}" />

@if (! empty($schema) && ! $agent->isDemo())
    <script type="application/ld+json">
        {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif
