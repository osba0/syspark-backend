<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Force la synchronisation des permissions chauffeur en base.
 * Résout le 403 quand le seeder a été modifié mais pas relancé.
 * Idempotente — peut être rejouée sans effet de bord.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Vider le cache AVANT toute opération
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. S'assurer que toutes les permissions chauffeur existent
        $permissionsRequises = [
            'checklist.viewAny',
            'checklist.view',
            'checklist.create',
            'checklist.submit',
            'signalement.view',
            'signalement.create',
            'signalement.uploadPhoto',
            'vehicule.view',
            'vehicule.updateKm',
            'carburant.view',
            'carburant.create',
            'alerte.view',
            'alerte.markRead',
            'dashboard.view',
        ];

        foreach ($permissionsRequises as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // 3. Récupérer le rôle chauffeur
        $chauffeur = Role::findOrCreate('chauffeur', 'web');

        // 4. Synchroniser — syncPermissions remplace entièrement les permissions du rôle
        $chauffeur->syncPermissions($permissionsRequises);

        // 5. Vider le cache APRÈS modification
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Pas de rollback sur les permissions — trop risqué
    }
};