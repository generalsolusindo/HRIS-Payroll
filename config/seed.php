<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Account Credentials
    |--------------------------------------------------------------------------
    |
    | Only used the first time each seeded account is created — a re-run
    | never resets a password the account holder has since changed
    | themselves. Override these in .env for a real deployment instead of
    | relying on the defaults below.
    |
    */

    'user_password' => env('SEED_USER_PASSWORD', 'password'),

    'hrd' => [
        'email' => env('HRD_EMAIL', 'hrd@generalsolusindo.com'),
        'name' => env('HRD_NAME', 'HRD General Solusindo'),
    ],

    'manager' => [
        'email' => env('ADMIN_EMAIL', 'manager@example.com'),
        'name' => env('ADMIN_NAME', 'Manager'),
    ],

];
