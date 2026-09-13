{{--
    Per-operator storefront theme variables.

    Desk chrome stays on platform yellow. The shop maps the full brand-* scale from
    the operator's Brand Accent Color so fills, hovers, and accents stay on-brand.

    Light mode: brand-700/800 are darkened mixes so text accents stay readable on
    white/slate surfaces. Filled CTAs use brand-600 + brand-foreground.

    Dark mode is class-based (`html.dark`). Follow the device setting so guest pages
    stay consistent when navigating between trips and Find your booking. brand-400/700/800
    stay lightened mixes so text and icons remain readable when the Brand Accent is a
    dark ink (e.g. navy). Never map brand-400 to the raw brand color in dark mode.
--}}
<style>
    :root {
        --brand-color: {{ $agent->brand_color }};
        --brand-foreground: {{ $agent->brand_foreground_color }};

        --color-brand-50: color-mix(in srgb, var(--brand-color) 12%, white);
        --color-brand-100: color-mix(in srgb, var(--brand-color) 22%, white);
        --color-brand-200: color-mix(in srgb, var(--brand-color) 35%, white);
        --color-brand-300: color-mix(in srgb, var(--brand-color) 50%, white);
        --color-brand-400: color-mix(in srgb, var(--brand-color) 78%, white);
        --color-brand-500: var(--brand-color);
        --color-brand-600: var(--brand-color);
        --color-brand-700: color-mix(in srgb, var(--brand-color) 72%, #0f172a);
        --color-brand-800: color-mix(in srgb, var(--brand-color) 52%, #0f172a);
        --color-brand-900: color-mix(in srgb, var(--brand-color) 38%, #0f172a);
        --color-brand-950: color-mix(in srgb, var(--brand-color) 22%, #0f172a);
        --color-brand-foreground: var(--brand-foreground);
        --color-accent: var(--brand-color);
        --color-accent-foreground: var(--brand-foreground);
        --sf-canvas: color-mix(in srgb, var(--brand-color) 5%, #f8fafc);
        --sf-canvas-dark: color-mix(in srgb, var(--brand-color) 7%, #09090b);
    }

    html.dark {
        --color-brand-400: color-mix(in srgb, var(--brand-color) 62%, white);
        --color-brand-500: color-mix(in srgb, var(--brand-color) 78%, white);
        --color-brand-700: color-mix(in srgb, var(--brand-color) 82%, white);
        --color-brand-800: color-mix(in srgb, var(--brand-color) 70%, white);
        --color-brand-900: color-mix(in srgb, var(--brand-color) 55%, white);
        --color-brand-950: color-mix(in srgb, var(--brand-color) 28%, #020617);
        --sf-canvas: var(--sf-canvas-dark);
    }

    .sf-canvas {
        background-color: var(--sf-canvas);
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
