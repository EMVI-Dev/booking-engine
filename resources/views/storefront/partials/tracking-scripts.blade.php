@php
    /** @var \App\Models\Operator|null $agent */
    $agent = $agent ?? null;
    $gaId = $agent?->getGoogleAnalyticsId();
    $metaPixelId = $agent?->getMetaPixelId();
    $gtmId = $agent?->getGoogleTagManagerId();
    $isConversion = $isConversion ?? false;
    $conversionAmount = $conversionAmount ?? 0;
    $conversionCurrency = $conversionCurrency ?? 'IDR';
    $conversionTransactionId = $conversionTransactionId ?? '';
    // Receipt URLs carry the guest's private link token; analytics gets a neutral URL instead.
    $redactedPageLocation = $redactedPageLocation ?? null;
@endphp

@if ($agent)
    {{-- Google Search Console Site Verification --}}
    @if (!empty($agent->google_site_verification))
        <meta name="google-site-verification" content="{{ $agent->google_site_verification }}" />
    @endif

    {{-- Tracking scripts are inert until the guest accepts cookies (partials.cookie-consent). --}}

    {{-- Google Tag Manager Container --}}
    @if ($gtmId)
        <script type="text/plain" data-consent="analytics">
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer',@js($gtmId));
        </script>
    @endif

    {{-- Google Analytics 4 (gtag.js) --}}
    @if ($gaId)
        <script type="text/plain" data-consent="analytics" data-src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script type="text/plain" data-consent="analytics">
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @js($gaId){!! $redactedPageLocation ? ', '.\Illuminate\Support\Js::from(['page_location' => $redactedPageLocation, 'page_referrer' => '']) : '' !!});

            @if ($isConversion && $conversionAmount > 0)
                gtag('event', 'purchase', {
                    transaction_id: @js($conversionTransactionId),
                    value: @js((float) $conversionAmount),
                    currency: @js($conversionCurrency)
                });
            @endif
        </script>
    @endif

    {{-- Meta / Facebook Pixel --}}
    @if ($metaPixelId)
        <script type="text/plain" data-consent="analytics">
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', @js($metaPixelId));
            fbq('track', 'PageView');

            @if ($isConversion && $conversionAmount > 0)
                fbq('track', 'Purchase', {
                    value: @js((float) $conversionAmount),
                    currency: @js($conversionCurrency)
                });
            @endif
        </script>
    @endif

    @if ($gtmId || $gaId || $metaPixelId)
        @include('partials.cookie-consent')
    @endif
@endif
