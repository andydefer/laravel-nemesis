<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default maximum requests
    |--------------------------------------------------------------------------
    */
    'default_max_requests' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Reset period
    |--------------------------------------------------------------------------
    */
    'reset_period' => 'daily',

    /*
    |--------------------------------------------------------------------------
    | Block response configuration
    |--------------------------------------------------------------------------
    */
    'block_response' => [
        'message' => 'Accès refusé : quota dépassé ou domaine non autorisé.',
        'status' => 429,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token extraction methods
    |--------------------------------------------------------------------------
    | Méthodes pour extraire le token de la requête
    */
    'token_sources' => [
        'bearer',
        'query:token',
        'query:api_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS settings
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'allow_credentials' => true,
        'max_age' => 86400,
        'expose_headers' => [
            'X-RateLimit-Limit',
            'X-RateLimit-Remaining',
            'X-RateLimit-Reset'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Command settings
    |--------------------------------------------------------------------------
    | Paramètres pour les commandes Artisan
    */
    'commands' => [
        'list_limit' => 10, // Nombre par défaut de tokens à afficher dans la liste
        'default_origins' => ['*'], // Origines par défaut pour les nouveaux tokens
    ],

    /*
    |--------------------------------------------------------------------------
    | Token generation settings
    |--------------------------------------------------------------------------
    */
    'token_generation' => [
        'length' => 40, // Longueur du token généré
        'characters' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic reset scheduling
    |--------------------------------------------------------------------------
    */
    'scheduling' => [
        'enabled' => true,
        'time' => '00:00', // Heure de réinitialisation quotidienne
    ],
];
