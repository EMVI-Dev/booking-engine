<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First platform admin (AdminUserSeeder)
    |--------------------------------------------------------------------------
    |
    | Read through config so seeding works on Laravel Cloud, where config is
    | cached and env() returns nothing. Production refuses to seed without a
    | password.
    |
    */

    'admin_email' => env('ADMIN_EMAIL', 'admin@travelengine.id'),
    'admin_password' => env('ADMIN_PASSWORD'),

];
