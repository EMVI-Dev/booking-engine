<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public server addresses
    |--------------------------------------------------------------------------
    |
    | Freelance guides often connect the root name (yourname.com). That apex
    | cannot use a CNAME at most domain shops, so they point an A / AAAA
    | record at this Lightsail (or other) public address instead. Comma-
    | separate more than one address if needed.
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
