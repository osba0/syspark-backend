<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['affectation.viewAny', 'affectation.view'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $chauffeur = Role::findOrCreate('chauffeur', 'web');
        foreach (['affectation.viewAny', 'affectation.view'] as $perm) {
            if (! $chauffeur->hasPermissionTo($perm)) {
                $chauffeur->givePermissionTo($perm);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void {}
};
