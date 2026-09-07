@php
    $seo = app(\App\Services\PlatformSeoService::class);
    $share = $share ?? $seo->shareImage();
    $faviconVersion = file_exists(public_path('favicon.svg')) ? filemtime(public_path('favicon.svg')) : time();
@endphp
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}" />
<link rel="canonical" href="{{ $url }}" />

<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
<meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
<link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}" />

<link rel="icon" href="/favicon.svg?v={{ $faviconVersion }}" type="image/svg+xml">
<link rel="icon" href="/favicon.ico?v={{ $faviconVersion }}" sizes="any">
<link rel="icon" href="/favicon.png?v={{ $faviconVersion }}" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $faviconVersion }}">

<meta property="og:locale" content="en_US" />
<meta property="og:type" content="{{ $type ?? 'website' }}" />
<meta property="og:url" content="{{ $url }}" />
<meta property="og:title" content="{{ $title }}" />
<meta property="og:description" content="{{ $description }}" />
<meta property="og:site_name" content="{{ $seo->platformName() }}" />
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

@if (! empty($schema))
    <script type="application/ld+json">
        {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif
