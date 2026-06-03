<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * S'assure que toutes les permissions maintenance/signalement/checklist
 * sont correctement assignées à tous les rôles gestionnaires.
 *
 * Résout le 403 quand le seeder a été modifié mais pas relancé.
 * Idempotente — peut être rejouée sans effet de bord.
 */
return new class extends Migration
{
    // Permissions par rôle — alignées avec RolePermissionSeeder
    private array $matrix = [
        'super_admin' => [
            'maintenance.viewAny', 'maintenance.view', 'maintenance.create',
            'maintenance.update',  'maintenance.delete', 'maintenance.approuver',
            'maintenance.cloturer',
        ],
        'directeur' => [
            'maintenance.viewAny', 'maintenance.view', 'maintenance.create',
            'maintenance.update',  'maintenance.approuver', 'maintenance.cloturer',
        ],
        'resp_parc' => [
            'maintenance.viewAny', 'maintenance.view', 'maintenance.create',
            'maintenance.update',  'maintenance.cloturer',
        ],
        'resp_agence' => [
            'maintenance.viewAny', 'maintenance.view', 'maintenance.create',
            'maintenance.update',  'maintenance.cloturer',
        ],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Collecter toutes les permissions uniques
        $allPerms = collect($this->matrix)->flatten()->unique()->values();

        // Créer les permissions si absentes
        foreach ($allPerms as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Assigner aux rôles (sans syncPermissions — givePermissionTo est non-destructif)
        foreach ($this->matrix as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            foreach ($permissions as $perm) {
                if (! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Pas de rollback sur les permissions
    }
};
