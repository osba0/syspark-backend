<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Agence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * AgenceController
 * GET/POST/PUT/DELETE /api/v1/admin/agences
 * Accès réservé : super_admin, directeur (middleware check.role)
 */
class AgenceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $agences = QueryBuilder::for(Agence::class)
            ->allowedFilters([
                AllowedFilter::partial('nom'),
                AllowedFilter::partial('ville'),
                AllowedFilter::exact('est_active'),
            ])
            ->allowedSorts(['nom', 'ville', 'code', 'created_at'])
            ->defaultSort('nom')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $agences->items(),
            'meta' => [
                'total'        => $agences->total(),
                'per_page'     => $agences->perPage(),
                'current_page' => $agences->currentPage(),
                'last_page'    => $agences->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'       => ['required', 'string', 'max:100'],
            'code'      => ['required', 'string', 'max:20', 'unique:agences,code'],
            'ville'     => ['required', 'string', 'max:100'],
            'adresse'   => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:150'],
        ]);

        $agence = Agence::create($data);

        return $this->created($agence, "Agence {$agence->nom} créée.");
    }

    public function show(Agence $agence): JsonResponse
    {
        return $this->success($agence);
    }

    public function update(Request $request, Agence $agence): JsonResponse
    {
        $data = $request->validate([
            'nom'       => ['sometimes', 'string', 'max:100'],
            'code'      => ['sometimes', 'string', 'max:20', "unique:agences,code,{$agence->id}"],
            'ville'     => ['sometimes', 'string', 'max:100'],
            'adresse'   => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:150'],
            'est_active'=> ['nullable', 'boolean'],
        ]);

        $agence->update($data);

        return $this->success($agence->fresh(), 'Agence mise à jour.');
    }

    public function destroy(Agence $agence): JsonResponse
    {
        // Empêcher la suppression si des véhicules ou utilisateurs y sont rattachés
        if ($agence->vehicules()->exists()) {
            return $this->error(
                "Impossible de supprimer l'agence « {$agence->nom} » : des véhicules y sont rattachés.",
                422
            );
        }

        $agence->delete();

        return $this->noContent("Agence « {$agence->nom} » supprimée.");
    }
}
