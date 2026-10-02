<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DOKU Jokul (hosted checkout, status, refunds, BI-FAST payouts)
    |--------------------------------------------------------------------------
    |
    | Every plan checks out through EMVI's single DOKU merchant account. Only
    | DokuClient reads this file. Client ids and secret keys are secrets: set
    | them in Laravel Cloud (environment variables / Secrets Manager), never in
    | a committed file. Endpoints are fixed per mode and are not secrets.
    |
    */

    'mode' => env('DOKU_MODE', 'sandbox'), // sandbox or live

    'sandbox' => [
        'client_id' => env('DOKU_SANDBOX_CLIENT_ID'),
        'secret_key' => env('DOKU_SANDBOX_SECRET_KEY'),
        'base_url' => 'https://api-sandbox.doku.com',
    ],

    'live' => [
        'client_id' => env('DOKU_LIVE_CLIENT_ID'),
        'secret_key' => env('DOKU_LIVE_SECRET_KEY'),
        'base_url' => 'https://api.doku.com',
    ],

    'notification_path' => '/api/v1/payments/doku/notify',

    /*
    |--------------------------------------------------------------------------
    | Offline Payment Simulator
    |--------------------------------------------------------------------------
    |
    | The simulator marks reservations as paid without money changing hands. It
    | is therefore restricted to local/testing unless explicitly switched on for
    | an offline demo deployment. Never enable this alongside live credentials.
    |
    */

    'simulator_enabled' => env('DOKU_SIMULATOR_ENABLED'),

];
