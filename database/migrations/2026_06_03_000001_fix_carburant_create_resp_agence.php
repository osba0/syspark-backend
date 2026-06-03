<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Force la réassignation de carburant.create à resp_agence.
 * Corrige le 403 sur POST /api/v1/carburant pour les resp_agence.
 *
 * CarburantPolicy était absente — maintenant enregistrée dans AppServiceProvider.
 * Cette migration s'assure que la permission existe ET est assignée.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $perms = [
            'carburant.viewAny', 'carburant.view',
            'carburant.create',  'carburant.update',
        ];

        // Créer les permissions si absentes
        foreach ($perms as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Assigner à resp_agence
        $role = Role::findOrCreate('resp_agence', 'web');
        foreach ($perms as $perm) {
            if (! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        // Assigner gererDotations à resp_parc
        $permDotation = Permission::findOrCreate('carburant.gererDotations', 'web');
        $respParc     = Role::findOrCreate('resp_parc', 'web');
        if (! $respParc->hasPermissionTo('carburant.gererDotations')) {
            $respParc->givePermissionTo('carburant.gererDotations');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void {}
};
