<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\ConfigEntreprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\Permission\Models\Role;

/**
 * AdminController
 * Gestion des utilisateurs, des rôles et des logs.
 * Accès : super_admin, directeur (middleware check.role dans les routes)
 */
class AdminController extends BaseApiController
{
    // ============================================================
    // Utilisateurs
    // ============================================================

    public function index(Request $request): JsonResponse
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters([
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('est_actif'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
            ])
            ->allowedSorts(['name', 'email', 'created_at', 'last_login_at'])
            ->allowedIncludes(['agence'])
            ->defaultSort('name')
            ->with(['agence:id,nom,code', 'roles:name'])
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => collect($users->items())->map(fn ($u) => $this->formatUser($u)),
            'meta' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'agence_id' => ['nullable', 'exists:agences,id'],
            'name'      => ['required', 'string', 'max:100'],
            'prenom'    => ['nullable', 'string', 'max:100'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'fonction'  => ['nullable', 'string', 'max:100'],
            'role'      => ['required', 'string', 'exists:roles,name'],
            'est_actif' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'agence_id' => $data['agence_id'] ?? null,
            'name'      => $data['name'],
            'prenom'    => $data['prenom'] ?? null,
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'telephone' => $data['telephone'] ?? null,
            'fonction'  => $data['fonction'] ?? null,
            'est_actif' => $data['est_actif'] ?? true,
        ]);

        // Assigner le rôle (guard 'web' — convention du projet)
        $user->assignRole($data['role']);

        return $this->created(
            $this->formatUser($user->load(['agence:id,nom,code', 'roles:name'])),
            "Utilisateur {$user->name} créé avec le rôle « {$data['role']} »."
        );
    }

    public function show(User $user): JsonResponse
    {
        return $this->success(
            $this->formatUser($user->load(['agence:id,nom,code', 'roles:name', 'permissions:name']))
        );
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'agence_id' => ['nullable', 'exists:agences,id'],
            'name'      => ['sometimes', 'string', 'max:100'],
            'prenom'    => ['nullable', 'string', 'max:100'],
            'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'telephone' => ['nullable', 'string', 'max:30'],
            'fonction'  => ['nullable', 'string', 'max:100'],
            'est_actif' => ['nullable', 'boolean'],
            'role'      => ['nullable', 'string', 'exists:roles,name'],
        ]);

        $user->update(collect($data)->except('role')->toArray());

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $this->success(
            $this->formatUser($user->fresh(['agence:id,nom,code', 'roles:name'])),
            'Utilisateur mis à jour.'
        );
    }

    public function destroy(User $user): JsonResponse
    {
        // Empêcher la suppression du compte courant
        if ($user->id === auth()->id()) {
            return $this->error('Vous ne pouvez pas supprimer votre propre compte.', 422);
        }

        $user->delete(); // SoftDelete

        return $this->noContent("Compte « {$user->name} » supprimé.");
    }

    // ============================================================
    // Actions spéciales
    // ============================================================

    public function toggleActif(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return $this->error('Vous ne pouvez pas désactiver votre propre compte.', 422);
        }

        $user->update(['est_actif' => !$user->est_actif]);

        $statut = $user->est_actif ? 'activé' : 'désactivé';

        return $this->success(
            $this->formatUser($user->fresh()),
            "Compte {$statut} avec succès."
        );
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        // Révoquer tous les tokens existants
        $user->tokens()->delete();

        return $this->success(null, "Mot de passe de « {$user->name} » réinitialisé. Tous les tokens révoqués.");
    }

    // ============================================================
    // Logs d'activité (spatie/activitylog)
    // ============================================================
    // Tâches système — déclenchement manuel
    // ============================================================

    private const ALLOWED_COMMANDS = [
        'parc:scan-alertes'             => 'Scan des alertes automatiques',
        'parc:update-documents'         => 'Mise à jour des statuts de documents',
        'parc:stats'                    => 'Recalcul des statistiques',
        'media-library:regenerate'      => 'Régénérer les miniatures des photos',
        'permission:cache-reset'        => 'Vider le cache des permissions',
        'cache:clear'                   => 'Vider le cache application',
        'email-queue:process'           => 'Envoyer les emails de notification en attente',
    ];

    public function runCommand(Request $request): JsonResponse
    {
        $request->validate([
            'command' => ['required', 'string', Rule::in(array_keys(self::ALLOWED_COMMANDS))],
        ]);

        $command = $request->input('command');
        $start   = microtime(true);

        try {
            \Illuminate\Support\Facades\Artisan::call($command);
            $output  = \Illuminate\Support\Facades\Artisan::output();
            $elapsed = round((microtime(true) - $start) * 1000);

            \Illuminate\Support\Facades\Log::info("[Admin] Commande manuelle : {$command}", [
                'user_id' => auth()->id(),
                'elapsed' => "{$elapsed}ms",
            ]);

            return $this->success([
                'command' => $command,
                'output'  => $output ?: 'Commande exécutée avec succès.',
                'elapsed' => $elapsed,
            ], self::ALLOWED_COMMANDS[$command] . ' — terminé.');

        } catch (\Exception $e) {
            return $this->error('Erreur : ' . $e->getMessage(), 500);
        }
    }

    // ============================================================

    /**
     * GET /api/v1/admin/config
     * Lisible par tous les utilisateurs authentifiés
     * (pour afficher le logo/nom dans le Topbar)
     */
    public function getConfig(): JsonResponse
    {
        $config = ConfigEntreprise::instance();
        return $this->success([
            'nom'         => $config->nom,
            'ninea'       => $config->ninea,
            'rc'          => $config->rc,
            'adresse'     => $config->adresse,
            'telephone'   => $config->telephone,
            'email'       => $config->email,
            'site_web'    => $config->site_web,
            'logo_url'    => $config->logo_url,
            'logo_app_url'=> $config->logo_app_url,
            'couleur_1'   => $config->couleur_1,
            'couleur_2'   => $config->couleur_2,
            'couleur_3'   => $config->couleur_3,
            'notes'       => $config->notes,
        ]);
    }

    /**
     * POST /api/v1/admin/config
     * Réservé super_admin — supporte upload logo multipart
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $request->validate([
            'nom'       => ['sometimes', 'string', 'max:150'],
            'ninea'     => ['nullable', 'string', 'max:50'],
            'rc'        => ['nullable', 'string', 'max:50'],
            'adresse'   => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:150'],
            'site_web'  => ['nullable', 'string', 'max:150'],
            'couleur_1' => ['nullable', 'string', 'max:20'],
            'couleur_2' => ['nullable', 'string', 'max:20'],
            'couleur_3' => ['nullable', 'string', 'max:20'],
            'notes'     => ['nullable', 'string'],
            'logo'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'logo_app'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ]);

        $config = ConfigEntreprise::instance();
        // Exclure les fichiers uploadés — ils sont traités séparément
        $data   = $request->except(['logo', 'logo_app']);

        // Upload logo entreprise (Topbar, PDF)
        if ($request->hasFile('logo')) {
            if ($config->logo) {
                Storage::disk('public')->delete($config->logo);
            }
            $data['logo'] = $request->file('logo')->store('entreprise', 'public');
        }

        // Upload logo application (Sidebar)
        if ($request->hasFile('logo_app')) {
            if ($config->logo_app) {
                Storage::disk('public')->delete($config->logo_app);
            }
            $data['logo_app'] = $request->file('logo_app')->store('entreprise', 'public');
        }

        $config->update($data);
        $config = $config->fresh(); // Recharger pour avoir les bons accesseurs

        return $this->success([
            'nom'          => $config->nom,
            'ninea'        => $config->ninea,
            'rc'           => $config->rc,
            'adresse'      => $config->adresse,
            'telephone'    => $config->telephone,
            'email'        => $config->email,
            'site_web'     => $config->site_web,
            'logo_url'     => $config->logo_url,
            'logo_app_url' => $config->logo_app_url,
            'couleur_1'    => $config->couleur_1,
            'couleur_2'    => $config->couleur_2,
            'couleur_3'    => $config->couleur_3,
            'notes'        => $config->notes,
        ], 'Configuration mise à jour.');
    }

    // ============================================================

    public function logs(Request $request): JsonResponse
    {
        $query = \Spatie\Activitylog\Models\Activity::with('causer:id,name,prenom,email')
            ->latest()
            ->when($request->user_id, fn ($q) => $q->where('causer_id', $request->user_id))
            ->when($request->subject_type, fn ($q) => $q->where('subject_type', $request->subject_type))
            ->when($request->date_debut, fn ($q) => $q->where('created_at', '>=', $request->date_debut))
            ->when($request->date_fin,   fn ($q) => $q->where('created_at', '<=', $request->date_fin));

        $logs = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => $logs->items(),
            'meta' => ['total' => $logs->total(), 'current_page' => $logs->currentPage()],
        ]);
    }

    // ============================================================
    // Helper privé
    // ============================================================

    private function formatUser(User $user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'prenom'       => $user->prenom,
            'nom_complet'  => trim(($user->prenom ?? '') . ' ' . $user->name),
            'email'        => $user->email,
            'telephone'    => $user->telephone,
            'fonction'     => $user->fonction,
            'est_actif'    => $user->est_actif,
            'last_login_at'=> $user->last_login_at?->toISOString(),
            'agence_id'    => $user->agence_id,
            'agence'       => $user->agence ? [
                'id'   => $user->agence->id,
                'nom'  => $user->agence->nom,
                'code' => $user->agence->code,
            ] : null,
            'role'         => $user->roles->first()?->name,
            'roles'        => $user->roles->pluck('name'),
            'created_at'   => $user->created_at?->toISOString(),
        ];
    }
}