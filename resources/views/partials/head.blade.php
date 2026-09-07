<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $isPlatformMarketing = request()->routeIs('home');
    $portalUser = auth()->user();
    $portalOperator = $isPlatformMarketing ? null : $portalUser?->currentOperator();
    $portalFavicon = $portalOperator?->logo_url;
    $faviconVersion = file_exists(public_path('favicon.svg')) ? filemtime(public_path('favicon.svg')) : time();
    $blockIndexing = $portalOperator?->isDemo()
        && $portalUser
        && (! $portalUser->isAdmin() || session()->has('admin_impersonated_operator_id'));
@endphp
@if ($isPlatformMarketing)
    @php
        $platformSeo = app(\App\Services\PlatformSeoService::class);
    @endphp
    @include('partials.platform-seo', [
        'title' => $platformSeo->homeTitle(),
        'description' => $platformSeo->homeDescription(),
        'url' => url('/'),
        'schema' => $platformSeo->homeGraph(),
    ])
@else
@php
    $fallbackSeo = app(\App\Services\PlatformSeoService::class);
    $fallbackTitle = filled($title ?? null)
        ? $title.' - '.$fallbackSeo->platformName()
        : $fallbackSeo->homeTitle();
@endphp
<title>{{ $fallbackTitle }}</title>
<meta name="description" content="{{ $fallbackSeo->homeDescription() }}" />
@if ($blockIndexing)
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />
<meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet" />
@endif

<!-- Open Graph / WhatsApp / Facebook Sharing Tags -->
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="{{ $fallbackTitle }}" />
<meta property="og:description" content="{{ $fallbackSeo->homeDescription() }}" />
<meta property="og:image" content="{{ url('/images/hero-cover.jpg') }}" />

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $fallbackTitle }}" />
<meta name="twitter:description" content="{{ $fallbackSeo->homeDescription() }}" />
<meta name="twitter:image" content="{{ url('/images/hero-cover.jpg') }}" />

@if ($portalFavicon)
    <link rel="icon" href="{{ $portalFavicon }}">
    <link rel="apple-touch-icon" href="{{ $portalFavicon }}">
@else
    <link rel="icon" href="/favicon.svg?v={{ $faviconVersion }}" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico?v={{ $faviconVersion }}" sizes="any">
    <link rel="icon" href="/favicon.png?v={{ $faviconVersion }}" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $faviconVersion }}">
@endif
@endif

<!-- Automated System Dark / Light Theme Sync -->
<script>
    (function () {
        function applyTheme() {
            var stored = localStorage.getItem('theme');
            if (stored === 'light') {
                document.documentElement.classList.remove('dark');
            } else if (stored === 'system') {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } else {
                // Default to dark mode when no explicit preference is set
                document.documentElement.classList.add('dark');
            }
        }

        // Apply immediately to prevent flash
        applyTheme();

        // Listen for OS/System theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                var stored = localStorage.getItem('theme');
                if (!stored || stored === 'system') {
                    applyTheme();
                }
            });
        }

        // Re-verify on Livewire navigation
        document.addEventListener('livewire:navigated', applyTheme);
    })();
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
