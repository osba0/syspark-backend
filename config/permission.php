<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guard par défaut pour Spatie/Permission
    |--------------------------------------------------------------------------
    |
    | RÈGLE FONDAMENTALE de ce projet :
    |
    |   Tous les rôles et permissions sont créés avec guard_name = 'web'.
    |   L'API utilise Sanctum (auth:sanctum), mais HasRoles::getDefaultGuardName()
    |   retourne le guard par défaut ('web') pour résoudre les rôles.
    |
    |   → NE PAS créer de rôles avec guard_name = 'sanctum' ou 'api'.
    |   → Toujours utiliser 'web' dans les seeders.
    |   → $user->hasRole('directeur') fonctionne sans préciser le guard.
    */

    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role'       => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles'                  => 'roles',
        'permissions'            => 'permissions',
        'model_has_permissions'  => 'model_has_permissions',
        'model_has_roles'        => 'model_has_roles',
        'role_has_permissions'   => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key'        => null,
        'permission_pivot_key'  => null,
        'model_morph_key'       => 'model_id',
        'team_foreign_key'      => 'team_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guard par défaut pour la résolution des rôles
    |--------------------------------------------------------------------------
    |
    | Quand HasRoles recherche les rôles d'un utilisateur, il utilise
    | ce guard pour filtrer. Mettre 'web' = les rôles du seeder sont trouvés.
    */
    'guard_name'  => 'web',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'expiration_time'  => \DateInterval::createFromDateString('24 hours'),
        'key'              => 'spatie.permission.cache',
        'store'            => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Options avancées
    |--------------------------------------------------------------------------
    */

    // Désactiver les équipes (on n'utilise pas le mode multi-tenant de Spatie)
    'teams' => false,

    // Enregistrer les middlewares automatiquement (on les enregistre manuellement)
    'register_permission_check_method'              => true,
    'register_octane_reset_listener'                => false,
    'display_permission_in_exception'               => true,
    'display_role_in_exception'                     => true,
    'enable_wildcard_permission'                    => false,
    'use_passport_client_credentials'               => false,
    'use_default_permission_names_in_exception'     => true,

    // Classe du modèle User utilisé par Spatie
    'permission_model_class'   => Spatie\Permission\Models\Permission::class,
];