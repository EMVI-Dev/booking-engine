<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DOKU Payment Gateway & SNAP Open API Config
    |--------------------------------------------------------------------------
    |
    | Configuration for DOKU Hosted Checkout, SNAP Open API (Bank Indonesia),
    | Virtual Accounts, QRIS, Credit Cards, E-Wallets, and automated Split Settlement.
    |
    */

    'default_mode' => env('DOKU_MODE', 'sandbox'), // sandbox or live

    'sandbox' => [
        'client_id' => env('DOKU_SANDBOX_CLIENT_ID', ''),
        'secret_key' => env('DOKU_SANDBOX_SECRET_KEY', ''),
        'doku_public_key' => env('DOKU_SANDBOX_DOKU_PUBLIC_KEY', ''),
        'merchant_public_key' => env('DOKU_SANDBOX_MERCHANT_PUBLIC_KEY', ''),
        'merchant_private_key' => env('DOKU_SANDBOX_MERCHANT_PRIVATE_KEY', ''),
        'snap_token_url' => env('DOKU_SANDBOX_SNAP_TOKEN_URL', 'https://api-sandbox.doku.com/authorization/v1/access-token/b2b'),
        'base_url' => env('DOKU_SANDBOX_BASE_URL', 'https://api-sandbox.doku.com'),
        'checkout_url' => env('DOKU_SANDBOX_CHECKOUT_URL', 'https://jokul-sandbox.doku.com/checkout'),
    ],

    'live' => [
        'client_id' => env('DOKU_LIVE_CLIENT_ID', ''),
        'secret_key' => env('DOKU_LIVE_SECRET_KEY', ''),
        'doku_public_key' => env('DOKU_LIVE_DOKU_PUBLIC_KEY', ''),
        'merchant_public_key' => env('DOKU_LIVE_MERCHANT_PUBLIC_KEY', ''),
        'merchant_private_key' => env('DOKU_LIVE_MERCHANT_PRIVATE_KEY', ''),
        'snap_token_url' => env('DOKU_LIVE_SNAP_TOKEN_URL', 'https://api.doku.com/authorization/v1/access-token/b2b'),
        'base_url' => env('DOKU_LIVE_BASE_URL', 'https://api.doku.com'),
        'checkout_url' => env('DOKU_LIVE_CHECKOUT_URL', 'https://jokul.doku.com/checkout'),
    ],

    'timeout' => 30, // Request timeout in seconds

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
