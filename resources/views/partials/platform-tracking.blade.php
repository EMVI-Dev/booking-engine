@php
    $platformGaId = config('services.google.platform_analytics_id');
@endphp

@if ($platformGaId)
    <!-- Google Analytics 4 - Platform (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $platformGaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $platformGaId }}');

        // Track virtual pageviews on Livewire SPA navigation
        (function() {
            var isInitial = true;
            document.addEventListener('livewire:navigated', function () {
                if (isInitial) {
                    isInitial = false;
                    return;
                }
                if (typeof gtag === 'function') {
                    gtag('event', 'page_view', {
                        page_title: document.title,
                        page_location: window.location.href,
                        page_path: window.location.pathname
                    });
                }
            });
        })();
    </script>
@endif
