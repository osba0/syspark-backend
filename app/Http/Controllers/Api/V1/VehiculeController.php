<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreVehiculeRequest;
use App\Http\Requests\UpdateVehiculeRequest;
use App\Http\Resources\VehiculeResource;
use App\Http\Resources\VehiculeCollection;
use App\Models\Vehicule;
use App\Services\TcoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VehiculeController extends BaseApiController
{
    public function __construct(private TcoService $tcoService) {}

    /**
     * GET /api/v1/vehicules
     * Liste avec filtres, recherche, tri et pagination
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicule::class);

        $query = QueryBuilder::for(Vehicule::class)
            ->allowedFilters([
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('type_vehicule'),
                AllowedFilter::exact('statut'),
                AllowedFilter::partial('immatriculation'),
                AllowedFilter::partial('marque'),
                AllowedFilter::partial('modele'),
                AllowedFilter::scope('vt_prochaine', 'vtProchaine'),
                AllowedFilter::scope('assurance_prochaine', 'assuranceProchaine'),
                AllowedFilter::scope('entretien_du', 'entretienDu'),
            ])
            ->allowedSorts([
                'immatriculation', 'marque', 'statut',
                'kilometrage_actuel', 'date_prochaine_visite_tech',
                'date_expiration_assurance', 'created_at',
            ])
            ->allowedIncludes(['agence', 'affectationActive.chauffeur'])
            ->defaultSort('immatriculation')
            ->with(['agence']);

        // Scope agence automatique
        $this->applyAgenceScope($query, $request);

        $vehicules = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => VehiculeResource::collection($vehicules),
            'meta' => [
                'total'        => $vehicules->total(),
                'per_page'     => $vehicules->perPage(),
                'current_page' => $vehicules->currentPage(),
                'last_page'    => $vehicules->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/vehicules
     */
    public function store(StoreVehiculeRequest $request): JsonResponse
    {
        $this->authorize('create', Vehicule::class);

        $vehicule = Vehicule::create($request->validated());

        // Calculer le prochain entretien
        $vehicule->update([
            'prochain_entretien_km' =>
                $vehicule->kilometrage_actuel + $vehicule->intervalle_entretien_km,
        ]);

        return $this->created(new VehiculeResource($vehicule->load('agence')));
    }

    /**
     * GET /api/v1/vehicules/{vehicule}
     */
    public function show(Request $request, Vehicule $vehicule): JsonResponse
    {
        // Charger affectationActive.chauffeur AVANT authorize()
        // car la Policy vérifie affectation->chauffeur->user_id pour le rôle chauffeur
        $vehicule->loadMissing([
            'affectationActive.chauffeur',
            'affectationActive.axeLivraison',
        ]);

        $this->authorize('view', $vehicule);

        $vehicule->load([
            'agence',
            'affectationActive.chauffeur',
            'affectationActive.axeLivraison',
        ]);

        return $this->success(new VehiculeResource($vehicule));
    }

    /**
     * PUT /api/v1/vehicules/{vehicule}
     */
    public function update(UpdateVehiculeRequest $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('update', $vehicule);

        $vehicule->update($request->validated());

        return $this->success(new VehiculeResource($vehicule->fresh('agence')));
    }

    /**
     * DELETE /api/v1/vehicules/{vehicule}
     */
    public function destroy(Vehicule $vehicule): JsonResponse
    {
        $this->authorize('delete', $vehicule);

        // Vérifier qu'aucune affectation active n'est en cours
        if ($vehicule->affectationActive) {
            return $this->error(
                'Impossible de supprimer un véhicule avec une affectation active.',
                422
            );
        }

        $vehicule->delete();

        return $this->noContent('Véhicule supprimé.');
    }

    /**
     * PUT /api/v1/vehicules/{vehicule}/kilometrage
     */
    public function updateKilometrage(Request $request, Vehicule $vehicule): JsonResponse
    {
        // Charger la relation avant authorize() — Policy vérifie affectation->chauffeur
        $vehicule->loadMissing('affectationActive.chauffeur');

        $this->authorize('updateKm', $vehicule);

        $request->validate([
            'kilometrage' => ['required', 'integer', 'min:' . $vehicule->kilometrage_actuel],
        ], [
            'kilometrage.min' => 'Le kilométrage ne peut pas être inférieur au kilométrage actuel.',
        ]);

        $ancienKm = $vehicule->kilometrage_actuel;

        $vehicule->update([
            'kilometrage_actuel' => $request->kilometrage,
        ]);

        // Vérifier si un entretien est dû
        $alerteEntretien = null;
        if ($vehicule->prochain_entretien_km
            && $request->kilometrage >= $vehicule->prochain_entretien_km - config('parc.maintenance.alerte_km_avant')) {
            $alerteEntretien = [
                'entretien_du'   => $request->kilometrage >= $vehicule->prochain_entretien_km,
                'km_restants'    => max(0, $vehicule->prochain_entretien_km - $request->kilometrage),
            ];
        }

        return $this->success([
            'vehicule'         => new VehiculeResource($vehicule->fresh()),
            'km_precedent'     => $ancienKm,
            'km_nouveau'       => $request->kilometrage,
            'alerte_entretien' => $alerteEntretien,
        ], 'Kilométrage mis à jour.');
    }

    /**
     * GET /api/v1/vehicules/{vehicule}/tco
     * Coût Total de Possession
     */
    public function tco(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('viewTco', $vehicule);

        $request->validate([
            'annee' => ['nullable', 'integer', 'min:2020', 'max:' . date('Y')],
        ]);

        $tco = $this->tcoService->calculer($vehicule, $request->input('annee'));

        return $this->success($tco);
    }

    // ============================================================
    // Sous-ressources
    // ============================================================

    public function maintenances(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('view', $vehicule);

        $maintenances = $vehicule->maintenances()
            ->with(['fournisseur', 'chauffeur'])
            ->orderBy('date_travaux', 'desc')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $maintenances->items(),
            'meta' => [
                'total'        => $maintenances->total(),
                'current_page' => $maintenances->currentPage(),
            ],
        ]);
    }

    public function carburant(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('view', $vehicule);

        $carburants = $vehicule->carburants()
            ->with('chauffeur')
            ->orderBy('date', 'desc')
            ->paginate($this->perPage($request));

        return response()->json(['data' => $carburants->items()]);
    }

    public function documents(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('view', $vehicule);

        $documents = $vehicule->documentsVehicule()
            ->actifs()
            ->orderBy('type_document')
            ->get();

        // Utiliser DocumentResource pour que fichier_url soit calculé
        // (Storage::url sur fichier_path) — sans ça le frontend reçoit fichier_path brut
        return $this->success(\App\Http\Resources\DocumentResource::collection($documents));
    }

    public function checklists(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('view', $vehicule);

        $checklists = $vehicule->checklists()
            ->with('chauffeur')
            ->orderBy('date', 'desc')
            ->paginate($this->perPage($request));

        return response()->json(['data' => $checklists->items()]);
    }

    public function signalements(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('view', $vehicule);

        $signalements = $vehicule->signalements()
            ->with('chauffeur')
            ->orderBy('date_signalement', 'desc')
            ->paginate($this->perPage($request));

        return response()->json(['data' => $signalements->items()]);
    }

    public function affectations(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('view', $vehicule);

        $affectations = $vehicule->affectations()
            ->with(['chauffeur', 'axeLivraison', 'validePar'])
            ->orderBy('date_debut', 'desc')
            ->paginate($this->perPage($request));

        return response()->json(['data' => $affectations->items()]);
    }

    public function alertes(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->authorize('view', $vehicule);

        $alertes = $vehicule->alertes()
            ->actives()
            ->orderByDesc('created_at')
            ->get();

        return $this->success($alertes);
    }
}