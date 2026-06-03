<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $matrix = [
        'super_admin' => [
            'carburant.viewAny', 'carburant.view', 'carburant.create',
            'carburant.update',  'carburant.delete', 'carburant.gererDotations',
        ],
        'directeur' => [
            'carburant.viewAny', 'carburant.view', 'carburant.create',
            'carburant.update',  'carburant.gererDotations',
        ],
        'resp_parc' => [
            'carburant.viewAny', 'carburant.view', 'carburant.create',
            'carburant.update',  'carburant.gererDotations',
        ],
        'resp_agence' => [
            'carburant.viewAny', 'carburant.view', 'carburant.create',
            'carburant.update',
        ],
        'comptable' => [
            'carburant.viewAny', 'carburant.view', 'carburant.create',
            'carburant.update',
        ],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPerms = collect($this->matrix)->flatten()->unique()->values();
        foreach ($allPerms as $name) {
            Permission::findOrCreate($name, 'web');
        }

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

    public function down(): void {}
};
