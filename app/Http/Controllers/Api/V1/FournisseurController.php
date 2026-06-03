<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreFournisseurRequest;
use App\Http\Resources\FournisseurResource;
use App\Models\Fournisseur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FournisseurController extends BaseApiController
{
    /**
     * GET /api/v1/fournisseurs
     */
    public function index(Request $request): JsonResponse
    {
        $query = QueryBuilder::for(Fournisseur::class)
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::partial('nom'),
                AllowedFilter::partial('ville'),
                AllowedFilter::exact('est_actif'),
            ])
            ->allowedSorts(['nom', 'type', 'ville', 'created_at'])
            ->defaultSort('nom')
            ->where('est_actif', true);

        $fournisseurs = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => FournisseurResource::collection($fournisseurs),
            'meta' => ['total' => $fournisseurs->total(), 'per_page' => $fournisseurs->perPage(), 'current_page' => $fournisseurs->currentPage(), 'last_page' => $fournisseurs->lastPage()],
        ]);
    }

    /**
     * POST /api/v1/fournisseurs
     */
    public function store(StoreFournisseurRequest $request): JsonResponse
    {
        $this->authorize('create', Fournisseur::class);
        $fournisseur = Fournisseur::create($request->validated());
        return $this->created(new FournisseurResource($fournisseur), 'Fournisseur créé.');
    }

    /**
     * GET /api/v1/fournisseurs/{fournisseur}
     */
    public function show(Fournisseur $fournisseur): JsonResponse
    {
        return $this->success(new FournisseurResource($fournisseur));
    }

    /**
     * PUT /api/v1/fournisseurs/{fournisseur}
     */
    public function update(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        $this->authorize('update', Fournisseur::class);

        $request->validate([
            'nom'        => ['sometimes', 'string', 'max:150'],
            'type'       => ['nullable', 'string', 'max:50'],
            'telephone'  => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email'],
            'adresse'    => ['nullable', 'string', 'max:255'],
            'ville'      => ['nullable', 'string', 'max:100'],
            'specialite' => ['nullable', 'string', 'max:150'],
            'ninea'      => ['nullable', 'string', 'max:50'],
            'est_actif'  => ['nullable', 'boolean'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        $fournisseur->update($request->validated());
        return $this->success(new FournisseurResource($fournisseur->fresh()));
    }

    /**
     * DELETE /api/v1/fournisseurs/{fournisseur}
     */
    public function destroy(Fournisseur $fournisseur): JsonResponse
    {
        $this->authorize('delete', Fournisseur::class);

        if ($fournisseur->maintenances()->count() > 0) {
            return $this->error('Impossible de supprimer un fournisseur ayant des interventions enregistrées. Désactivez-le plutôt.', 422);
        }

        $fournisseur->delete();
        return $this->noContent('Fournisseur supprimé.');
    }

    /**
     * GET /api/v1/fournisseurs/{fournisseur}/interventions
     * Historique de toutes les maintenances pour ce fournisseur
     */
    public function interventions(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        $interventions = $fournisseur->maintenances()
            ->with('vehicule:id,immatriculation,marque,modele')
            ->orderByDesc('date_travaux')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $interventions->items(),
            'meta' => ['total' => $interventions->total(), 'current_page' => $interventions->currentPage()],
        ]);
    }

    /**
     * GET /api/v1/fournisseurs/{fournisseur}/stats
     * Statistiques par fournisseur (montants, types, véhicules)
     */
    public function stats(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        $annee = $request->input('annee', date('Y'));

        $global = DB::table('maintenances')
            ->where('fournisseur_id', $fournisseur->id)
            ->where('statut', 'termine')
            ->selectRaw('SUM(montant_ttc) as total, COUNT(*) as nb, AVG(montant_ttc) as moyenne')
            ->first();

        $parType = DB::table('maintenances')
            ->where('fournisseur_id', $fournisseur->id)
            ->where('statut', 'termine')
            ->whereYear('date_travaux', $annee)
            ->selectRaw('type_operation, SUM(montant_ttc) as total, COUNT(*) as nb')
            ->groupBy('type_operation')
            ->get();

        $parMois = DB::table('maintenances')
            ->where('fournisseur_id', $fournisseur->id)
            ->where('statut', 'termine')
            ->whereYear('date_travaux', $annee)
            ->selectRaw('MONTH(date_travaux) as mois, SUM(montant_ttc) as total, COUNT(*) as nb')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        return $this->success([
            'fournisseur_id' => $fournisseur->id,
            'nom'            => $fournisseur->nom,
            'annee'          => $annee,
            'global'         => [
                'total_all_time' => round((float)($global->total ?? 0), 2),
                'nb_all_time'    => (int)($global->nb ?? 0),
                'moyenne_facture'=> round((float)($global->moyenne ?? 0), 2),
            ],
            'par_type'  => $parType->map(fn ($t) => ['type' => $t->type_operation, 'total' => round((float)$t->total, 2), 'nb' => (int)$t->nb]),
            'par_mois'  => $parMois->map(fn ($m) => ['mois' => (int)$m->mois, 'total' => round((float)$m->total, 2), 'nb' => (int)$m->nb]),
        ]);
    }
}
