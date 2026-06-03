<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreAffectationRequest;
use App\Http\Requests\CloturerAffectationRequest;
use App\Http\Resources\AffectationResource;
use App\Models\Affectation;
use App\Models\Vehicule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AffectationController extends BaseApiController
{
    /**
     * GET /api/v1/affectations
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Affectation::class);

        $query = QueryBuilder::for(Affectation::class)
            ->allowedFilters([
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('statut'),
                AllowedFilter::exact('vehicule_id'),
                AllowedFilter::exact('chauffeur_id'),
                AllowedFilter::exact('type_affectation'),
            ])
            ->allowedSorts(['date_debut', 'date_fin', 'created_at'])
            ->allowedIncludes(['vehicule', 'chauffeur', 'axeLivraison', 'agence', 'validePar'])
            ->defaultSort('-date_debut')
            ->with(['vehicule', 'chauffeur', 'axeLivraison']);

        $this->applyAgenceScope($query, $request);

        // Scope chauffeur — ne voit que ses propres affectations
        $user = $request->user();
        if ($user->hasRole('chauffeur')) {
            $chauffeurId = $user->chauffeur()->first()?->id;
            if ($chauffeurId) {
                $query->where('chauffeur_id', $chauffeurId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $affectations = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => AffectationResource::collection($affectations),
            'meta' => [
                'total'        => $affectations->total(),
                'per_page'     => $affectations->perPage(),
                'current_page' => $affectations->currentPage(),
                'last_page'    => $affectations->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/affectations/actives
     * Vue temps réel : qui conduit quoi sur quel axe
     */
    public function actives(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Affectation::class);

        $query = Affectation::actives()
            ->with(['vehicule.agence', 'chauffeur', 'axeLivraison', 'agence']);

        $agenceId = $this->getAgenceScopeId($request);
        if ($agenceId) {
            $query->where('agence_id', $agenceId);
        }

        $affectations = $query->get();

        return $this->success([
            'total'        => $affectations->count(),
            'affectations' => AffectationResource::collection($affectations),
        ]);
    }

    /**
     * POST /api/v1/affectations
     * Attribution d'un véhicule à un chauffeur (reproduit la FICHE ATTRIBUTION)
     */
    public function store(StoreAffectationRequest $request): JsonResponse
    {
        $this->authorize('create', Affectation::class);

        $data = $request->validated();
        $vehicule = Vehicule::findOrFail($data['vehicule_id']);

        // Vérifier qu'il n'y a pas déjà une affectation active sur ce véhicule
        $existante = $vehicule->affectationActive;
        if ($existante) {
            return $this->error(
                "Ce véhicule est déjà affecté à {$existante->acteur} depuis le {$existante->date_debut->format('d/m/Y')}. Clôturez l'affectation existante avant d'en créer une nouvelle.",
                422
            );
        }

        DB::beginTransaction();
        try {
            $affectation = Affectation::create([
                ...$data,
                'statut'      => 'active',
                'validee_par' => $request->user()->id,
                'validee_le'  => now(),
            ]);

            // Mettre à jour le kilométrage de début du véhicule
            if (isset($data['kilometrage_debut'])) {
                $vehicule->update(['kilometrage_actuel' => $data['kilometrage_debut']]);
            }

            // Mettre à jour le statut du véhicule
            $vehicule->update(['statut' => 'actif']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de la création de l\'affectation : ' . $e->getMessage(), 500);
        }

        return $this->created(
            new AffectationResource($affectation->load(['vehicule', 'chauffeur', 'axeLivraison'])),
            'Affectation créée avec succès.'
        );
    }

    /**
     * GET /api/v1/affectations/{affectation}
     */
    public function show(Affectation $affectation): JsonResponse
    {
        $this->authorize('view', $affectation);

        $affectation->load(['vehicule.agence', 'chauffeur', 'axeLivraison', 'agence', 'validePar']);

        return $this->success(new AffectationResource($affectation));
    }

    /**
     * PUT /api/v1/affectations/{affectation}
     */
    public function update(Request $request, Affectation $affectation): JsonResponse
    {
        $this->authorize('update', $affectation);

        $request->validate([
            'axe_livraison_id' => ['nullable', 'exists:axes_livraison,id'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $affectation->update($request->only(['axe_livraison_id', 'notes']));

        return $this->success(new AffectationResource($affectation->fresh()));
    }

    /**
     * POST /api/v1/affectations/{affectation}/cloturer
     * Clôture de l'affectation avec kilométrage final
     */
    public function cloturer(CloturerAffectationRequest $request, Affectation $affectation): JsonResponse
    {
        $this->authorize('cloturer', $affectation);

        if ($affectation->statut !== 'active') {
            return $this->error('Cette affectation n\'est pas active.', 422);
        }

        $data = $request->validated();

        DB::beginTransaction();
        try {
            $affectation->update([
                'statut'          => 'terminee',
                'date_fin'        => $data['date_fin'] ?? now()->toDateString(),
                'kilometrage_fin' => $data['kilometrage_fin'],
                'notes'           => $data['notes'] ?? $affectation->notes,
            ]);

            // Mettre à jour le kilométrage du véhicule
            $vehicule = $affectation->vehicule;
            if ($data['kilometrage_fin'] > $vehicule->kilometrage_actuel) {
                $vehicule->update(['kilometrage_actuel' => $data['kilometrage_fin']]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de la clôture : ' . $e->getMessage(), 500);
        }

        return $this->success(
            new AffectationResource($affectation->fresh(['vehicule', 'chauffeur'])),
            'Affectation clôturée. Kilométrage : ' . number_format($data['kilometrage_fin']) . ' km.'
        );
    }
}