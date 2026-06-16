<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Communication;
use App\Models\CommunicationLecture;
use App\Models\User;
use App\Http\Resources\CommunicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Module Communication — Annonces & Notes de service.
 *
 * Endpoints :
 *  - index / show / store / update / destroy : CRUD (gestionnaires)
 *  - publier / archiver                       : transitions de statut
 *  - actives                                   : pour le bandeau dashboard
 *  - critiques-non-lues                       : pour la modal obligatoire
 *  - marquerLue                                : accusé de lecture
 *  - historique                                : communications archivées
 */
class CommunicationController extends BaseApiController
{
    /**
     * GET /api/v1/communications
     * Liste de gestion (gestionnaires uniquement) — toutes communications,
     * avec compteurs de lecture pour suivi des accusés.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Communication::class);

        $query = QueryBuilder::for(Communication::class)
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::exact('gravite'),
                AllowedFilter::exact('statut'),
                AllowedFilter::partial('titre'),
            ])
            ->allowedSorts(['created_at', 'date_publication', 'titre'])
            ->defaultSort('-created_at')
            ->with('auteur:id,name,prenom')
            ->withCount([
                'lectures',
                'lectures as lectures_lues_count' => fn ($q) => $q->whereNotNull('lu_at'),
            ]);

        if (!$request->user()->hasAnyRole(['super_admin', 'directeur'])) {
            $query->where(function ($q) use ($request) {
                $q->where('auteur_id', $request->user()->id);
                if ($request->user()->agence_id) {
                    $q->orWhereJsonContains('agences_cibles', $request->user()->agence_id)
                      ->orWhereNull('agences_cibles');
                }
            });
        }

        $communications = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => CommunicationResource::collection($communications),
            'meta' => [
                'total'        => $communications->total(),
                'per_page'     => $communications->perPage(),
                'current_page' => $communications->currentPage(),
                'last_page'    => $communications->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/communications/{communication}
     */
    public function show(Request $request, Communication $communication): JsonResponse
    {
        $this->authorize('view', $communication);

        $communication->load(['auteur:id,name,prenom', 'lectures']);
        $communication->loadCount([
            'lectures',
            'lectures as lectures_lues_count' => fn ($q) => $q->whereNotNull('lu_at'),
        ]);

        return $this->success(new CommunicationResource($communication));
    }

    /**
     * POST /api/v1/communications
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Communication::class);

        $data = $request->validate([
            'titre'                 => ['required', 'string', 'max:200'],
            'contenu'               => ['required', 'string'],
            'type'                  => ['required', 'in:annonce,note_service'],
            'gravite'               => ['required', 'in:info,importante,critique'],
            'roles_cibles'          => ['nullable', 'array'],
            'roles_cibles.*'        => ['string', 'in:super_admin,directeur,resp_parc,resp_agence,comptable,chauffeur,attributaire'],
            'agences_cibles'        => ['nullable', 'array'],
            'agences_cibles.*'      => ['integer', 'exists:agences,id'],
            'date_publication'      => ['nullable', 'date'],
            'date_expiration'       => ['nullable', 'date', 'after_or_equal:date_publication'],
            'accuse_lecture_requis' => ['nullable', 'boolean'],
            'statut'                => ['nullable', 'in:brouillon,publie'],
            'piece_jointe'          => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (($data['statut'] ?? 'brouillon') === 'publie' && !$request->user()->can('communication.publish')) {
            $data['statut'] = 'brouillon';
        }

        $data['auteur_id'] = $request->user()->id;
        $data['statut']    = $data['statut'] ?? 'brouillon';

        DB::beginTransaction();
        try {
            $communication = Communication::create($data);

            if ($request->hasFile('piece_jointe')) {
                $communication->addMediaFromRequest('piece_jointe')->toMediaCollection('piece_jointe');
            }

            if ($communication->statut === 'publie') {
                $this->genererLecturesPour($communication);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Erreur lors de la création : ' . $e->getMessage(), 500);
        }

        return $this->created(
            new CommunicationResource($communication->load('auteur:id,name,prenom')),
            $communication->statut === 'publie' ? 'Communication publiée.' : 'Brouillon enregistré.'
        );
    }

    /**
     * PUT /api/v1/communications/{communication}
     */
    public function update(Request $request, Communication $communication): JsonResponse
    {
        $this->authorize('update', $communication);

        $data = $request->validate([
            'titre'                 => ['sometimes', 'string', 'max:200'],
            'contenu'               => ['sometimes', 'string'],
            'type'                  => ['sometimes', 'in:annonce,note_service'],
            'gravite'               => ['sometimes', 'in:info,importante,critique'],
            'roles_cibles'          => ['nullable', 'array'],
            'roles_cibles.*'        => ['string'],
            'agences_cibles'        => ['nullable', 'array'],
            'agences_cibles.*'      => ['integer', 'exists:agences,id'],
            'date_publication'      => ['nullable', 'date'],
            'date_expiration'       => ['nullable', 'date', 'after_or_equal:date_publication'],
            'accuse_lecture_requis' => ['nullable', 'boolean'],
            'piece_jointe'          => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $communication->update($data);

        if ($request->hasFile('piece_jointe')) {
            $communication->addMediaFromRequest('piece_jointe')->toMediaCollection('piece_jointe');
        }

        return $this->success(new CommunicationResource($communication->fresh('auteur:id,name,prenom')), 'Communication mise à jour.');
    }

    /**
     * DELETE /api/v1/communications/{communication}
     */
    public function destroy(Communication $communication): JsonResponse
    {
        $this->authorize('delete', $communication);
        $communication->delete();
        return $this->noContent('Communication supprimée.');
    }

    /**
     * POST /api/v1/communications/{communication}/publier
     */
    public function publier(Request $request, Communication $communication): JsonResponse
    {
        $this->authorize('publish', $communication);

        if ($communication->statut === 'publie') {
            return $this->error('Cette communication est déjà publiée.', 422);
        }

        $communication->update([
            'statut'           => 'publie',
            'date_publication' => $communication->date_publication ?? now(),
        ]);

        $this->genererLecturesPour($communication);

        return $this->success(new CommunicationResource($communication->fresh()), 'Communication publiée.');
    }

    /**
     * POST /api/v1/communications/{communication}/archiver
     */
    public function archiver(Communication $communication): JsonResponse
    {
        $this->authorize('publish', $communication);

        $communication->update(['statut' => 'archive']);

        return $this->success(new CommunicationResource($communication->fresh()), 'Communication archivée.');
    }

    /**
     * GET /api/v1/communications/actives
     * Pour le bandeau dashboard.
     */
    public function actives(Request $request): JsonResponse
    {
        $user = $request->user();

        $communications = Communication::query()
            ->actives()
            ->visiblePour($user)
            ->with('lectures')
            ->orderByRaw("CASE gravite WHEN 'critique' THEN 0 WHEN 'importante' THEN 1 ELSE 2 END")
            ->orderByDesc('date_publication')
            ->get();

        return $this->success(CommunicationResource::collection($communications));
    }

    /**
     * GET /api/v1/communications/critiques-non-lues
     * Pour la modal obligatoire à la connexion.
     */
    public function critiquesNonLues(Request $request): JsonResponse
    {
        $user = $request->user();

        $communications = Communication::query()
            ->actives()
            ->visiblePour($user)
            ->where('accuse_lecture_requis', true)
            ->where(function ($q) use ($user) {
                $q->whereHas('lectures', function ($lq) use ($user) {
                    $lq->where('user_id', $user->id)->whereNull('lu_at');
                })->orWhereDoesntHave('lectures', function ($lq) use ($user) {
                    $lq->where('user_id', $user->id);
                });
            })
            ->orderByDesc('date_publication')
            ->get();

        return $this->success(CommunicationResource::collection($communications));
    }

    /**
     * POST /api/v1/communications/{communication}/lire
     */
    public function marquerLue(Request $request, Communication $communication): JsonResponse
    {
        $user = $request->user();

        CommunicationLecture::updateOrCreate(
            ['communication_id' => $communication->id, 'user_id' => $user->id],
            ['lu_at' => now()]
        );

        return $this->success(null, 'Communication marquée comme lue.');
    }

    /**
     * GET /api/v1/communications/historique
     */
    public function historique(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Communication::query()
            ->where('statut', 'archive')
            ->where(function ($q) use ($user) {
                $q->visiblePour($user);

                // Les gestionnaires voient aussi l'historique complet de
                // leurs propres publications, même hors ciblage actuel
                // (ex: ciblage modifié après publication).
                if ($user->can('communication.viewAny')) {
                    $q->orWhere('auteur_id', $user->id);
                }
            })
            ->with('auteur:id,name,prenom')
            ->orderByDesc('date_publication');

        $communications = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => CommunicationResource::collection($communications),
            'meta' => [
                'total'        => $communications->total(),
                'current_page' => $communications->currentPage(),
                'last_page'    => $communications->lastPage(),
            ],
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Génère un enregistrement CommunicationLecture (lu_at = null) pour
     * chaque utilisateur ciblé par la communication.
     */
    private function genererLecturesPour(Communication $communication): void
    {
        $query = User::query()->where('est_actif', true);

        if (!empty($communication->roles_cibles)) {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', $communication->roles_cibles));
        }

        if (!empty($communication->agences_cibles)) {
            $query->whereIn('agence_id', $communication->agences_cibles);
        }

        $userIds = $query->pluck('id');

        $rows = $userIds->map(fn ($userId) => [
            'communication_id' => $communication->id,
            'user_id'          => $userId,
            'lu_at'            => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ])->toArray();

        if (!empty($rows)) {
            CommunicationLecture::upsert(
                $rows,
                ['communication_id', 'user_id'],
                ['updated_at']
            );
        }
    }
}
