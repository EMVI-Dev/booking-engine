{{--
    Per-operator storefront theme variables.

    --brand-foreground is derived from the brand colour's luminance so text and icons
    placed on brand-coloured surfaces stay readable for both dark and light brands.

    Dark mode is class-based (`html.dark`). Follow the device setting so guest pages
    stay consistent when navigating between trips and Find your booking.
--}}
<style>
    :root {
        --brand-color: {{ $agent->brand_color }};
        --brand-foreground: {{ $agent->brand_foreground_color }};
    }
</style>
<script>
    (function () {
        function applySystemTheme() {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        applySystemTheme();
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applySystemTheme);
        }
    })();
</script>
