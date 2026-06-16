<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gestion des permissions par rôle.
 *
 * Permet à l'administrateur d'attribuer / retirer une ou plusieurs
 * permissions à un rôle existant, sans toucher au seeder.
 *
 * Le rôle 'super_admin' est protégé : il garde toujours toutes
 * les permissions (accès total garanti par Gate::before).
 */
class RolePermissionController extends BaseApiController
{
    /**
     * GET /api/v1/admin/roles
     * Liste des rôles avec leur nombre de permissions et d'utilisateurs.
     */
    public function index(): JsonResponse
    {
        $roles = Role::where('guard_name', 'web')
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn ($role) => [
                'id'                => $role->id,
                'name'              => $role->name,
                'label'             => $this->roleLabel($role->name),
                'permissions_count' => $role->permissions_count,
                'users_count'       => $role->users_count,
                'protege'           => $role->name === 'super_admin',
            ]);

        return $this->success($roles);
    }

    /**
     * GET /api/v1/admin/roles/{role}/permissions
     * Détail d'un rôle : toutes les permissions groupées par module,
     * avec indication de celles attribuées au rôle.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');
        $permissionsAttribuees = $role->permissions->pluck('name')->toArray();

        $toutes = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'module'   => $this->module($p->name),
                'action'   => $this->action($p->name),
                'attribue' => in_array($p->name, $permissionsAttribuees),
            ]);

        // Grouper par module pour l'affichage
        $groupes = $toutes->groupBy('module')->map(fn ($perms, $module) => [
            'module'      => $module,
            'label'       => $this->moduleLabel($module),
            'permissions' => $perms->values(),
        ])->values();

        return $this->success([
            'role' => [
                'id'      => $role->id,
                'name'    => $role->name,
                'label'   => $this->roleLabel($role->name),
                'protege' => $role->name === 'super_admin',
            ],
            'modules' => $groupes,
            'total_attribuees' => count($permissionsAttribuees),
            'total_disponibles' => $toutes->count(),
        ]);
    }

    /**
     * PUT /api/v1/admin/roles/{role}/permissions
     * Remplace l'ensemble des permissions du rôle (sync complet).
     * Body: { "permissions": ["vehicule.view", "vehicule.create", ...] }
     */
    public function sync(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'super_admin') {
            return $this->error('Le rôle Super Admin conserve toujours toutes les permissions.', 422);
        }

        $request->validate([
            'permissions'   => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($request->input('permissions'));

        $this->viderCachePermissions();

        return $this->success(
            ['total' => count($request->input('permissions'))],
            "Permissions du rôle « {$this->roleLabel($role->name)} » mises à jour."
        );
    }

    /**
     * POST /api/v1/admin/roles/{role}/permissions/attribuer
     * Ajoute une ou plusieurs permissions sans retirer les existantes.
     * Body: { "permissions": ["maintenance.approve"] }
     */
    public function attribuer(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'super_admin') {
            return $this->error('Le rôle Super Admin possède déjà toutes les permissions.', 422);
        }

        $request->validate([
            'permissions'   => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->givePermissionTo($request->input('permissions'));

        $this->viderCachePermissions();

        $nb = count($request->input('permissions'));
        return $this->success(null, "{$nb} permission(s) attribuée(s) au rôle « {$this->roleLabel($role->name)} ».");
    }

    /**
     * POST /api/v1/admin/roles/{role}/permissions/retirer
     * Retire une ou plusieurs permissions du rôle.
     * Body: { "permissions": ["maintenance.approve"] }
     */
    public function retirer(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'super_admin') {
            return $this->error('Impossible de retirer des permissions au Super Admin.', 422);
        }

        $request->validate([
            'permissions'   => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->revokePermissionTo($request->input('permissions'));

        $this->viderCachePermissions();

        $nb = count($request->input('permissions'));
        return $this->success(null, "{$nb} permission(s) retirée(s) du rôle « {$this->roleLabel($role->name)} ».");
    }

    // ── Helpers ───────────────────────────────────────────────

    private function viderCachePermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Extrait le module depuis le nom de permission: 'vehicule.view' → 'vehicule' */
    private function module(string $permission): string
    {
        return str_contains($permission, '.')
            ? explode('.', $permission)[0]
            : 'autre';
    }

    /** Extrait l'action: 'vehicule.view' → 'view' */
    private function action(string $permission): string
    {
        $parts = explode('.', $permission);
        return $parts[1] ?? $permission;
    }

    private function moduleLabel(string $module): string
    {
        return match($module) {
            'vehicule'      => 'Véhicules',
            'chauffeur'     => 'Chauffeurs',
            'affectation'   => 'Affectations',
            'checklist'     => 'Checklists',
            'signalement'   => 'Signalements',
            'maintenance'   => 'Maintenance',
            'carburant'     => 'Carburant',
            'pneumatique'   => 'Pneumatiques',
            'document'      => 'Documents',
            'bonCommande'   => 'Bons de commande',
            'fournisseur'   => 'Fournisseurs',
            'alerte'        => 'Alertes',
            'rapport'       => 'Rapports',
            'dashboard'     => 'Tableau de bord',
            'admin'         => 'Administration',
            default         => ucfirst($module),
        };
    }

    private function roleLabel(string $role): string
    {
        return match($role) {
            'super_admin'  => 'Super Admin',
            'directeur'    => 'Directeur',
            'resp_parc'    => 'Resp. Parc',
            'resp_agence'  => 'Resp. Agence',
            'comptable'    => 'Comptable',
            'chauffeur'    => 'Chauffeur',
            'attributaire' => 'Attributaire',
            default        => ucfirst(str_replace('_', ' ', $role)),
        };
    }
}