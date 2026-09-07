<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public demo operator
    |--------------------------------------------------------------------------
    |
    | One operator at demo.{platform-domain} for prospects to look around.
    | Checkout is disabled. The catalog is wiped and re-seeded daily.
    | Search engines and AI crawlers must never index this host.
    |
    */

    'email' => env('DEMO_OPERATOR_EMAIL', 'demo@travelengine.online'),
    'password' => env('DEMO_OPERATOR_PASSWORD'),
    'name' => env('DEMO_OPERATOR_NAME', 'Demo Tours'),
    'slug' => 'demo',

];
