<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Journal d'audit — accès adapté au rôle de l'utilisateur connecté.
 *
 * Règles de scoping (appliquées automatiquement, non contournables par
 * les paramètres de requête) :
 *
 *  - super_admin, directeur          : voient TOUT (toutes agences, tous utilisateurs)
 *  - resp_parc                       : voient TOUT (multi-agences) — pas de filtre agence forcé
 *  - resp_agence, comptable          : scopés à leur propre agence (agence_id)
 *  - chauffeur, attributaire         : voient uniquement les actions liées
 *                                       à eux-mêmes, à leur véhicule affecté
 *                                       ou à leurs propres signalements/checklists
 *
 * Les filtres explicites (utilisateur, module, type d'action, période, agence)
 * restent disponibles mais sont toujours INTERSECTÉS avec le scope du rôle —
 * un resp_agence ne peut donc jamais voir une autre agence même en forçant
 * le paramètre agence_id.
 */
class AuditLogController extends BaseApiController
{
    /** Rôles voyant l'ensemble du journal, toutes agences confondues */
    private const ACCES_GLOBAL = ['super_admin', 'directeur', 'resp_parc'];

    /** Rôles scopés à leur propre agence */
    private const ACCES_AGENCE = ['resp_agence', 'comptable'];

    /** Rôles ne voyant que ce qui les concerne personnellement */
    private const ACCES_PERSONNEL = ['chauffeur', 'attributaire'];

    /**
     * GET /api/v1/audit-logs
     *
     * Filtres disponibles (tous optionnels) :
     *  - user_id      : filtrer par auteur de l'action (causer_id)
     *  - module       : 'vehicule', 'chauffeur', 'maintenance', 'document', ...
     *  - event        : 'created', 'updated', 'deleted'
     *  - agence_id    : filtrer par agence (ignoré si hors du scope du rôle)
     *  - date_debut / date_fin : période (YYYY-MM-DD)
     *  - search       : recherche texte dans la description
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Activity::query()
            ->with(['causer:id,name,prenom,email'])
            ->latest('created_at');

        $this->appliquerScopeRole($query, $user);
        $this->appliquerFiltres($query, $request, $user);

        $logs = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
            ],
            // Informe le frontend des filtres pertinents pour ce rôle
            // (ex: ne pas afficher le sélecteur "Agence" pour resp_agence)
            'scope' => $this->decrireScopeRole($user),
        ]);
    }

    /**
     * GET /api/v1/audit-logs/modules
     * Liste des modules disponibles pour le filtre, avec libellés FR.
     */
    public function modules(): JsonResponse
    {
        return $this->success([
            ['value' => 'vehicule',     'label' => 'Véhicules'],
            ['value' => 'chauffeur',    'label' => 'Chauffeurs'],
            ['value' => 'affectation',  'label' => 'Affectations'],
            ['value' => 'checklist',    'label' => 'Checklists'],
            ['value' => 'signalement',  'label' => 'Signalements'],
            ['value' => 'maintenance',  'label' => 'Maintenance'],
            ['value' => 'carburant',    'label' => 'Carburant'],
            ['value' => 'pneumatique',  'label' => 'Pneumatiques'],
            ['value' => 'document',     'label' => 'Documents'],
            ['value' => 'bonCommande',  'label' => 'Bons de commande'],
            ['value' => 'fournisseur',  'label' => 'Fournisseurs'],
            ['value' => 'agence',       'label' => 'Agences'],
            ['value' => 'axeLivraison', 'label' => 'Axes de livraison'],
            ['value' => 'utilisateur',  'label' => 'Utilisateurs'],
        ]);
    }

    /**
     * GET /api/v1/audit-logs/utilisateurs
     * Liste légère des utilisateurs ayant un log visible dans le scope du
     * rôle courant — alimente le filtre "Utilisateur" sans exposer la
     * gestion complète des comptes (réservée à /admin/users).
     */
    public function utilisateurs(Request $request): JsonResponse
    {
        $user = $request->user();

        // Le filtre utilisateur n'a pas de sens en scope personnel
        if ($user->hasAnyRole(self::ACCES_PERSONNEL)) {
            return $this->success([]);
        }

        $query = Activity::query()
            ->where('causer_type', \App\Models\User::class)
            ->whereNotNull('causer_id');

        $this->appliquerScopeRole($query, $user);

        $causerIds = $query->distinct()->pluck('causer_id');

        $utilisateurs = \App\Models\User::whereIn('id', $causerIds)
            ->orderBy('name')
            ->get(['id', 'name', 'prenom', 'email'])
            ->map(fn ($u) => [
                'id'    => $u->id,
                'label' => trim("{$u->prenom} {$u->name}") . " ({$u->email})",
            ]);

        return $this->success($utilisateurs);
    }

    // ============================================================
    // Scoping par rôle
    // ============================================================

    /**
     * Applique le scope obligatoire selon le rôle de l'utilisateur.
     * Cette méthode est la SEULE source de vérité pour l'accès aux données —
     * les filtres optionnels ne peuvent qu'affiner, jamais élargir ce scope.
     */
    private function appliquerScopeRole($query, $user): void
    {
        // Accès global : aucune restriction supplémentaire
        if ($user->hasAnyRole(self::ACCES_GLOBAL)) {
            return;
        }

        // Scope agence : uniquement les logs de son agence
        if ($user->hasAnyRole(self::ACCES_AGENCE)) {
            if ($user->agence_id) {
                $query->where('agence_id', $user->agence_id);
            } else {
                // Pas d'agence assignée → aucun log visible (fail-safe)
                $query->whereRaw('1 = 0');
            }
            return;
        }

        // Scope personnel : chauffeur / attributaire
        if ($user->hasAnyRole(self::ACCES_PERSONNEL)) {
            $chauffeur   = $user->chauffeur()->first(); // relation hasMany(Chauffeur) sur User
            $chauffeurId = $chauffeur?->id;
            $vehiculeId  = $chauffeur?->affectationActive?->vehicule_id;

            $query->where(function ($q) use ($user, $chauffeurId, $vehiculeId) {
                // Actions qu'il a lui-même effectuées
                $q->where('causer_id', $user->id)
                  ->where('causer_type', \App\Models\User::class);

                // Actions concernant son propre profil chauffeur
                if ($chauffeurId) {
                    $q->orWhere('chauffeur_id', $chauffeurId);
                }

                // Actions concernant son véhicule actuellement affecté
                if ($vehiculeId) {
                    $q->orWhere('vehicule_id', $vehiculeId);
                }
            });
            return;
        }

        // Tout autre rôle non prévu → aucun accès (fail-safe par défaut)
        $query->whereRaw('1 = 0');
    }

    /**
     * Décrit le scope appliqué pour informer le frontend
     * (quels filtres proposer, quel message d'en-tête afficher).
     */
    private function decrireScopeRole($user): array
    {
        if ($user->hasAnyRole(self::ACCES_GLOBAL)) {
            return [
                'type'                => 'global',
                'peut_filtrer_agence' => true,
                'peut_filtrer_user'   => true,
                'label'               => 'Journal complet — toutes agences',
            ];
        }

        if ($user->hasAnyRole(self::ACCES_AGENCE)) {
            return [
                'type'                => 'agence',
                'peut_filtrer_agence' => false,
                'peut_filtrer_user'   => true,
                'agence_id'           => $user->agence_id,
                'label'               => 'Journal de votre agence',
            ];
        }

        return [
            'type'                => 'personnel',
            'peut_filtrer_agence' => false,
            'peut_filtrer_user'   => false,
            'label'               => 'Actions vous concernant',
        ];
    }

    // ============================================================
    // Filtres optionnels
    // ============================================================

    private function appliquerFiltres($query, Request $request, $user): void
    {
        // Utilisateur (causer) — uniquement pertinent pour scope global/agence
        if ($request->filled('user_id') && !$user->hasAnyRole(self::ACCES_PERSONNEL)) {
            $query->where('causer_id', $request->input('user_id'))
                  ->where('causer_type', \App\Models\User::class);
        }

        // Module
        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        // Type d'action (created/updated/deleted)
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        // Agence — uniquement applicable si le rôle a un accès global
        // (sinon le scope agence est déjà imposé par appliquerScopeRole)
        if ($request->filled('agence_id') && $user->hasAnyRole(self::ACCES_GLOBAL)) {
            $query->where('agence_id', $request->input('agence_id'));
        }

        // Période
        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->input('date_debut'));
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->input('date_fin'));
        }

        // Recherche texte dans la description
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->input('search') . '%');
        }
    }
}