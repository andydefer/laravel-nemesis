<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Token Length
    |--------------------------------------------------------------------------
    */
    'token_length' => 64,

    /*
    |--------------------------------------------------------------------------
    | Hash Algorithm
    |--------------------------------------------------------------------------
    */
    'hash_algorithm' => 'sha256',

    /*
    |--------------------------------------------------------------------------
    | Token Expiration (in minutes)
    |--------------------------------------------------------------------------
    */
    'expiration' => 60,

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'parameter_name' => 'nemesis_auth',
        'token_header' => 'Authorization',
        'security_headers' => true,
        'validate_origin' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'allow_credentials' => true,
        'max_age' => 86400,
        'expose_token_info' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'auto_cleanup' => true,
        'frequency' => 60,
        'keep_expired_for_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Configuration
    |--------------------------------------------------------------------------
    | Configuration for web routes protection with cookie-based tokens.
    */
    'web' => [
        /*
        | Route to redirect unauthenticated users when accessing protected web routes.
        | Used by the NemesisWebMiddleware.
        */
        'login_route' => '/login',

        /*
        | Route to redirect authenticated users when accessing guest-only routes.
        | Used by the NemesisGuestMiddleware.
        */
        'dashboard_route' => '/dashboard',

        /*
        | Name of the cookie where the token is stored for web authentication.
        */
        'cookie_name' => 'nemesis_token',

        /*
        | Cookie security settings.
        */
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'lax',
    ],
];
