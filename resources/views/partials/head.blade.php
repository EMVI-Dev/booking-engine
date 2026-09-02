<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'TravelEngine') : config('app.name', 'TravelEngine').' - Online Booking System for Tour Operators' }}
</title>
<meta name="description" content="The simple way to sell your tours online with 0% platform commission. Get your tour website, accept QRIS and bank payments, and manage reservations." />

<!-- Open Graph / WhatsApp / Facebook Sharing Tags -->
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="{{ filled($title ?? null) ? $title.' - '.config('app.name', 'TravelEngine') : config('app.name', 'TravelEngine').' - Direct Tour Booking System' }}" />
<meta property="og:description" content="The simple way to sell your tours online with 0% platform commission. Get your tour website, accept QRIS and bank payments, and manage reservations." />
<meta property="og:image" content="{{ asset('images/hero-cover.jpg') }}" />

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ filled($title ?? null) ? $title.' - '.config('app.name', 'TravelEngine') : config('app.name', 'TravelEngine') }}" />
<meta name="twitter:description" content="The simple way to sell your tours online with 0% platform commission." />
<meta name="twitter:image" content="{{ asset('images/hero-cover.jpg') }}" />

@php
    $isMarketing = request()->routeIs('home');
    $portalOperator = $isMarketing ? null : auth()->user()?->currentOperator();
    $portalFavicon = $portalOperator?->logo_url;
    $faviconVersion = file_exists(public_path('favicon.svg')) ? filemtime(public_path('favicon.svg')) : time();
@endphp
@if ($portalFavicon)
    <link rel="icon" href="{{ $portalFavicon }}">
    <link rel="apple-touch-icon" href="{{ $portalFavicon }}">
@else
    <link rel="icon" href="/favicon.svg?v={{ $faviconVersion }}" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico?v={{ $faviconVersion }}" sizes="any">
    <link rel="icon" href="/favicon.png?v={{ $faviconVersion }}" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $faviconVersion }}">
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
