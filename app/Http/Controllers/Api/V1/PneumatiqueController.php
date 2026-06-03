<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Pneumatique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * PneumatiqueController
 * Gestion des opérations pneumatiques (achat neuf, rechapé, rotation, réforme).
 * GET/POST/PUT/DELETE /api/v1/pneumatiques
 */
class PneumatiqueController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = QueryBuilder::for(Pneumatique::class)
            ->allowedFilters([
                AllowedFilter::exact('vehicule_id'),
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('fournisseur_id'),
                AllowedFilter::exact('type_operation'),
                AllowedFilter::callback('annee', fn ($q, $v) => $q->whereYear('date', $v)),
                AllowedFilter::callback('mois',  fn ($q, $v) => $q->whereMonth('date', $v)),
            ])
            ->allowedSorts(['-date', 'montant_total', 'type_operation'])
            ->allowedIncludes(['vehicule', 'fournisseur', 'chauffeur'])
            ->defaultSort('-date')
            ->with(['vehicule:id,immatriculation,marque,modele', 'fournisseur:id,nom']);

        $this->applyAgenceScope($query, $request);

        $pneumatiques = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => $pneumatiques->items(),
            'meta' => [
                'total'        => $pneumatiques->total(),
                'per_page'     => $pneumatiques->perPage(),
                'current_page' => $pneumatiques->currentPage(),
                'last_page'    => $pneumatiques->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicule_id'    => ['required', 'exists:vehicules,id'],
            'agence_id'      => ['required', 'exists:agences,id'],
            'chauffeur_id'   => ['nullable', 'exists:chauffeurs,id'],
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
            'date'           => ['required', 'date', 'before_or_equal:today'],
            'type_operation' => ['required', Rule::in(['achat_neuf', 'rechape', 'rotation', 'reforme', 'reparation'])],
            'position'       => ['nullable', 'string', 'max:50'],
            'marque_pneu'    => ['nullable', 'string', 'max:100'],
            'dimension'      => ['nullable', 'string', 'max:50'],
            'quantite'       => ['required', 'integer', 'min:1', 'max:20'],
            'prix_unitaire'  => ['nullable', 'numeric', 'min:0'],
            'montant_total'  => ['required', 'numeric', 'min:0'],
            'kilometrage'    => ['nullable', 'integer', 'min:0'],
            'commentaire'    => ['nullable', 'string', 'max:500'],
        ]);

        $data['saisi_par'] = $request->user()->id;

        $pneumatique = Pneumatique::create($data);

        return $this->created(
            $pneumatique->load(['vehicule:id,immatriculation', 'fournisseur:id,nom']),
            'Opération pneumatique enregistrée.'
        );
    }

    public function show(Pneumatique $pneumatique): JsonResponse
    {
        return $this->success(
            $pneumatique->load(['vehicule', 'fournisseur', 'chauffeur', 'saisiPar'])
        );
    }

    public function update(Request $request, Pneumatique $pneumatique): JsonResponse
    {
        $data = $request->validate([
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
            'date'           => ['sometimes', 'date'],
            'type_operation' => ['sometimes', Rule::in(['achat_neuf', 'rechape', 'rotation', 'reforme', 'reparation'])],
            'position'       => ['nullable', 'string', 'max:50'],
            'marque_pneu'    => ['nullable', 'string', 'max:100'],
            'dimension'      => ['nullable', 'string', 'max:50'],
            'quantite'       => ['sometimes', 'integer', 'min:1'],
            'prix_unitaire'  => ['nullable', 'numeric', 'min:0'],
            'montant_total'  => ['sometimes', 'numeric', 'min:0'],
            'kilometrage'    => ['nullable', 'integer', 'min:0'],
            'commentaire'    => ['nullable', 'string', 'max:500'],
        ]);

        $pneumatique->update($data);

        return $this->success($pneumatique->fresh(), 'Opération mise à jour.');
    }

    public function destroy(Pneumatique $pneumatique): JsonResponse
    {
        $pneumatique->delete();
        return $this->noContent('Opération pneumatique supprimée.');
    }

    // ============================================================
    // Statistiques pneumatiques
    // ============================================================

    public function stats(Request $request): JsonResponse
    {
        $annee    = $request->input('annee', date('Y'));
        $agenceId = $this->getAgenceScopeId($request);

        $base = Pneumatique::whereYear('date', $annee)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId));

        $parType = (clone $base)
            ->select('type_operation', DB::raw('SUM(montant_total) as total, COUNT(*) as nb, SUM(quantite) as qte'))
            ->groupBy('type_operation')
            ->get();

        $parMois = (clone $base)
            ->selectRaw('MONTH(date) as mois, SUM(montant_total) as total, COUNT(*) as nb')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        $parVehicule = (clone $base)
            ->join('vehicules as v', 'pneumatiques.vehicule_id', '=', 'v.id')
            ->select('pneumatiques.vehicule_id', 'v.immatriculation', 'v.marque', 'v.modele',
                DB::raw('SUM(pneumatiques.montant_total) as total, COUNT(*) as nb'))
            ->groupBy('pneumatiques.vehicule_id', 'v.immatriculation', 'v.marque', 'v.modele')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return $this->success([
            'annee'         => $annee,
            'total_global'  => round((float)(clone $base)->sum('montant_total'), 2),
            'par_type'      => $parType->map(fn ($t) => [
                'type'  => $t->type_operation,
                'total' => round((float)$t->total, 2),
                'nb'    => (int)$t->nb,
                'qte'   => (int)$t->qte,
            ]),
            'par_mois'      => $parMois->map(fn ($m) => [
                'mois'  => (int)$m->mois,
                'total' => round((float)$m->total, 2),
                'nb'    => (int)$m->nb,
            ]),
            'par_vehicule'  => $parVehicule->map(fn ($v) => [
                'vehicule_id'    => $v->vehicule_id,
                'immatriculation'=> $v->immatriculation,
                'marque_modele'  => $v->marque . ' ' . $v->modele,
                'total'          => round((float)$v->total, 2),
                'nb'             => (int)$v->nb,
            ]),
        ]);
    }
}
