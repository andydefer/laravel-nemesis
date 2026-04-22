<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Token Length
    |--------------------------------------------------------------------------
    | This option controls the length of the generated tokens.
    | Longer tokens are more secure but larger in size.
    | Recommended: 64 or higher for production.
    */
    'token_length' => 64,

    /*
    |--------------------------------------------------------------------------
    | Hash Algorithm
    |--------------------------------------------------------------------------
    | The algorithm used to hash tokens before storing them in the database.
    | Supported: 'sha256', 'sha512', 'md5' (not recommended), etc.
    | Recommended: 'sha256' or 'sha512' for production.
    */
    'hash_algorithm' => 'sha256',

    /*
    |--------------------------------------------------------------------------
    | Token Expiration (in minutes)
    |--------------------------------------------------------------------------
    | The number of minutes after which tokens expire.
    | Set to null for tokens that never expire (not recommended for production).
    | Default: 60 minutes (1 hour)
    */
    'expiration' => 60,

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    | Configuration options for the Nemesis authentication middleware.
    */
    'middleware' => [
        /*
         * The parameter name used to inject the authenticated model into the route.
         * Access via: $request->nemesisAuth
         */
        'parameter_name' => 'nemesisAuth',

        /*
         * The header name that contains the bearer token.
         * Standard is 'Authorization' with 'Bearer ' prefix.
         */
        'token_header' => 'Authorization',

        /*
         * Enable security headers on successful responses.
         * Adds X-Frame-Options, X-XSS-Protection, X-Content-Type-Options, Referrer-Policy.
         */
        'security_headers' => true,

        /*
         * Enable CORS origin validation.
         * When enabled, tokens can restrict which origins can use them.
         */
        'validate_origin' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing settings for token validation.
    */
    'cors' => [
        /*
         * Whether to allow credentials (cookies, authorization headers) in CORS requests.
         */
        'allow_credentials' => true,

        /*
         * Maximum age (in seconds) for preflight requests caching.
         */
        'max_age' => 86400, // 24 hours

        /*
         * Whether to expose token information in CORS responses.
         */
        'expose_token_info' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    | Automatic cleanup of expired tokens.
    */
    'cleanup' => [
        /*
         * Whether to automatically clean expired tokens.
         * Recommended: true for production to keep database size manageable.
         */
        'auto_cleanup' => true,

        /*
         * Frequency of cleanup (in minutes).
         * Uses Laravel's scheduling system.
         */
        'frequency' => 60, // Run every hour

        /*
         * Delete tokens older than (in days) after expiration.
         * Keep for audit purposes before permanent deletion.
         */
        'keep_expired_for_days' => 30,
    ],
];
