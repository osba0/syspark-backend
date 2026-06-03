<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AxeLivraison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * AxeLivraisonController
 * GET/POST/PUT/DELETE /api/v1/admin/axes-livraison
 */
class AxeLivraisonController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = QueryBuilder::for(AxeLivraison::class)
            ->allowedFilters([
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('est_actif'),
                AllowedFilter::partial('nom'),
            ])
            ->allowedSorts(['nom', 'code', 'agence_id', 'created_at'])
            ->allowedIncludes(['agence'])
            ->defaultSort('nom')
            ->with('agence:id,nom,code');

        // Scope agence si applicable
        $this->applyAgenceScope($query, $request);

        $axes = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => $axes->items(),
            'meta' => [
                'total'        => $axes->total(),
                'per_page'     => $axes->perPage(),
                'current_page' => $axes->currentPage(),
                'last_page'    => $axes->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'agence_id'   => ['required', 'exists:agences,id'],
            'nom'         => ['required', 'string', 'max:100'],
            'code'        => ['required', 'string', 'max:30'],
            'zone'        => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'est_actif'   => ['nullable', 'boolean'],
        ]);

        $axe = AxeLivraison::create($data);

        return $this->created(
            $axe->load('agence:id,nom,code'),
            "Axe de livraison « {$axe->nom} » créé."
        );
    }

    public function show(AxeLivraison $axeLivraison): JsonResponse
    {
        return $this->success($axeLivraison->load('agence:id,nom,code'));
    }

    public function update(Request $request, AxeLivraison $axeLivraison): JsonResponse
    {
        $data = $request->validate([
            'agence_id'   => ['sometimes', 'exists:agences,id'],
            'nom'         => ['sometimes', 'string', 'max:100'],
            'code'        => ['sometimes', 'string', 'max:30'],
            'zone'        => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'est_actif'   => ['nullable', 'boolean'],
        ]);

        $axeLivraison->update($data);

        return $this->success(
            $axeLivraison->fresh('agence:id,nom,code'),
            'Axe de livraison mis à jour.'
        );
    }

    public function destroy(AxeLivraison $axeLivraison): JsonResponse
    {
        if ($axeLivraison->affectations()->exists()) {
            return $this->error(
                "Impossible de supprimer l'axe « {$axeLivraison->nom} » : des affectations actives y sont liées.",
                422
            );
        }

        $axeLivraison->delete();

        return $this->noContent("Axe « {$axeLivraison->nom} » supprimé.");
    }
}
