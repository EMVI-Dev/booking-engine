<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Booking Engine') : config('app.name', 'Booking Engine') }}
</title>

@php
    $portalAgent = auth()->user()?->currentAgent();
    $portalFavicon = $portalAgent?->logo_url;
@endphp
@if ($portalFavicon)
    <link rel="icon" href="{{ $portalFavicon }}">
    <link rel="apple-touch-icon" href="{{ $portalFavicon }}">
@else
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif

<!-- Automated System Dark / Light Theme Sync -->
<script>
    (function () {
        function applySystemTheme() {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        // Apply immediately to prevent flash
        applySystemTheme();

        // Listen for OS/System theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applySystemTheme);
        }

        // Re-verify on Livewire navigation
        document.addEventListener('livewire:navigated', applySystemTheme);
    })();
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
