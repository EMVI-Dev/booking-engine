<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom domain provider
    |--------------------------------------------------------------------------
    |
    | Where Agency operators' own website addresses are served. "laravel_cloud"
    | adds each one to the Cloud environment through the API (token and
    | environment id in config/services.php) and shows the DNS records Cloud
    | returns. "local" is for development and tests only and refuses to run in
    | production. Empty: laravel_cloud in production, local everywhere else.
    | Operator slugs ({slug}.travelengine.id) use the wildcard domain either way.
    |
    */

    'provider' => env('CUSTOM_DOMAIN_PROVIDER'),

];
