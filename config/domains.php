<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public server addresses
    |--------------------------------------------------------------------------
    |
    | Production origin is AWS Lightsail. The apex travelengine.online is on
    | Cloudflare; slugs and custom domains hit this box directly. These
    | addresses are what Agency apex names A / AAAA to. Prefer a CNAME to a
    | grey hostname (cname.travelengine.online). Comma-separate more than
    | one address if needed.
    |
    */

    'public_ipv4' => env('PLATFORM_PUBLIC_IPV4'),
    'public_ipv6' => env('PLATFORM_PUBLIC_IPV6'),

    /*
    |--------------------------------------------------------------------------
    | Caddy on-demand TLS
    |--------------------------------------------------------------------------
    |
    | Caddy calls GET /internal/caddy/ask?token=…&domain=yourname.com before
    | it asks Let's Encrypt for a padlock. An empty token fails closed.
    | Point Caddy at: http://127.0.0.1/internal/caddy/ask?token=…
    |
    */

    'caddy_ask_token' => env('CADDY_ASK_TOKEN'),

];
