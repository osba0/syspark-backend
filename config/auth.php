<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    | Guard par défaut pour les requêtes web → 'web'
    | Les routes API utilisent 'sanctum' via auth:sanctum
    */

    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | IMPORTANT Spatie/Permission :
    |   Les rôles et permissions sont créés avec guard_name = 'web' (seeder).
    |   Sanctum n'est PAS un guard séparé — il réutilise le guard 'web'
    |   pour authentifier l'utilisateur via token.
    |   → $user->hasRole('directeur') fonctionne car guard_name = 'web'
    |     correspond au guard par défaut de HasRoles.
    */

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Guard Sanctum pour l'API — réutilise le provider 'users'
        // Sanctum injecte l'utilisateur via token, puis HasRoles
        // utilise le guard 'web' (default) pour résoudre les rôles.
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];