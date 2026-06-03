<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBonCommandeRequest;
use App\Http\Requests\UpdateBonCommandeRequest;
use App\Http\Resources\BonCommandeResource;
use App\Models\BonCommande;
use App\Notifications\BonCommandeSoumisNotification;
use App\Notifications\BonCommandeApprouveNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\Permission\Models\Role;

class BonCommandeController extends BaseApiController
{
    /**
     * GET /api/v1/bons-commande
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BonCommande::class);

        $query = QueryBuilder::for(BonCommande::class)
            ->allowedFilters([
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('fournisseur_id'),
                AllowedFilter::exact('vehicule_id'),
                AllowedFilter::exact('statut'),
                AllowedFilter::partial('numero_bc'),
                AllowedFilter::callback('annee', fn ($q, $v) => $q->whereYear('date_commande', $v)),
            ])
            ->allowedSorts(['-date_commande', 'montant_ttc', 'statut', 'numero_bc'])
            ->allowedIncludes(['fournisseur', 'vehicule', 'agence', 'creePar', 'approuvePar'])
            ->defaultSort('-date_commande')
            ->with(['fournisseur', 'vehicule', 'creePar']);

        $this->applyAgenceScope($query, $request);

        $bons = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => BonCommandeResource::collection($bons),
            'meta' => [
                'total'        => $bons->total(),
                'per_page'     => $bons->perPage(),
                'current_page' => $bons->currentPage(),
                'last_page'    => $bons->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/bons-commande
     * Création d'un bon de commande (brouillon par défaut)
     */
    public function store(StoreBonCommandeRequest $request): JsonResponse
    {
        $this->authorize('create', BonCommande::class);

        $data = $request->validated();

        // Calcul automatique des montants depuis les lignes
        $montants = $this->calculerMontants($data['lignes'], $data['tva'] ?? config('parc.carburant.tva_taux', 18));
        $data = array_merge($data, $montants);

        $data['numero_bc'] = BonCommande::genererNumeroBc();
        $data['cree_par']  = $request->user()->id;
        $data['statut']    = 'brouillon';

        $bc = BonCommande::create($data);

        return $this->created(
            new BonCommandeResource($bc->load(['fournisseur', 'vehicule', 'creePar'])),
            "Bon de commande {$bc->numero_bc} créé en brouillon."
        );
    }

    /**
     * GET /api/v1/bons-commande/{bonCommande}
     */
    public function show(BonCommande $bonCommande): JsonResponse
    {
        $this->authorize('view', $bonCommande);

        $bonCommande->load(['fournisseur', 'vehicule.agence', 'agence', 'creePar', 'approuvePar', 'maintenances']);

        return $this->success(new BonCommandeResource($bonCommande));
    }

    /**
     * PUT /api/v1/bons-commande/{bonCommande}
     * Modification uniquement en brouillon
     */
    public function update(UpdateBonCommandeRequest $request, BonCommande $bonCommande): JsonResponse
    {
        $this->authorize('update', $bonCommande);

        if (!in_array($bonCommande->statut, ['brouillon', 'rejete'])) {
            return $this->error('Seul un bon de commande en brouillon ou rejeté peut être modifié.', 422);
        }

        $data = $request->validated();

        if (isset($data['lignes'])) {
            $montants = $this->calculerMontants($data['lignes'], $data['tva'] ?? $bonCommande->tva);
            $data     = array_merge($data, $montants);
        }

        // Si rejeté et on modifie → repasse en brouillon
        if ($bonCommande->statut === 'rejete') {
            $data['statut']      = 'brouillon';
            $data['motif_rejet'] = null;
        }

        $bonCommande->update($data);

        return $this->success(
            new BonCommandeResource($bonCommande->fresh(['fournisseur', 'vehicule'])),
            'Bon de commande mis à jour.'
        );
    }

    /**
     * DELETE /api/v1/bons-commande/{bonCommande}
     * Suppression uniquement si brouillon
     */
    public function destroy(BonCommande $bonCommande): JsonResponse
    {
        $this->authorize('delete', $bonCommande);

        if ($bonCommande->statut !== 'brouillon') {
            return $this->error('Seul un bon de commande en brouillon peut être supprimé.', 422);
        }

        $bonCommande->delete();

        return $this->noContent("Bon de commande {$bonCommande->numero_bc} supprimé.");
    }

    /**
     * POST /api/v1/bons-commande/{bonCommande}/soumettre
     * Soumettre pour approbation
     */
    public function soumettre(BonCommande $bonCommande): JsonResponse
    {
        $this->authorize('submit', $bonCommande);

        if ($bonCommande->statut !== 'brouillon') {
            return $this->error('Seul un brouillon peut être soumis.', 422);
        }

        if (empty($bonCommande->lignes)) {
            return $this->error('Le bon de commande doit avoir au moins une ligne.', 422);
        }

        $bonCommande->update(['statut' => 'soumis']);

        // Notifier les approbateurs (directeur, resp_parc)
        try {
            $approb = Role::findByName('directeur')->users()
                ->union(Role::findByName('resp_parc')->users())
                ->get();
            Notification::send($approb, new BonCommandeSoumisNotification($bonCommande));
        } catch (\Exception) {
            // Les notifications ne bloquent pas le workflow
        }

        return $this->success(
            new BonCommandeResource($bonCommande->fresh()),
            "Bon de commande {$bonCommande->numero_bc} soumis pour approbation."
        );
    }

    /**
     * POST /api/v1/bons-commande/{bonCommande}/approuver
     */
    public function approuver(Request $request, BonCommande $bonCommande): JsonResponse
    {
        $this->authorize('approve', $bonCommande);

        if ($bonCommande->statut !== 'soumis') {
            return $this->error('Seul un bon de commande soumis peut être approuvé.', 422);
        }

        $bonCommande->update([
            'statut'      => 'approuve',
            'approuve_par'=> $request->user()->id,
            'approuve_le' => now(),
        ]);

        // Notifier le créateur
        try {
            $bonCommande->creePar?->notify(new BonCommandeApprouveNotification($bonCommande, 'approuve'));
        } catch (\Exception) {}

        return $this->success(
            new BonCommandeResource($bonCommande->fresh(['approuvePar'])),
            "Bon de commande {$bonCommande->numero_bc} approuvé."
        );
    }

    /**
     * POST /api/v1/bons-commande/{bonCommande}/rejeter
     */
    public function rejeter(Request $request, BonCommande $bonCommande): JsonResponse
    {
        $this->authorize('reject', $bonCommande);

        if ($bonCommande->statut !== 'soumis') {
            return $this->error('Seul un bon de commande soumis peut être rejeté.', 422);
        }

        $request->validate([
            'motif' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $bonCommande->update([
            'statut'       => 'rejete',
            'motif_rejet'  => $request->motif,
            'approuve_par' => $request->user()->id,
            'approuve_le'  => now(),
        ]);

        // Notifier le créateur
        try {
            $bonCommande->creePar?->notify(new BonCommandeApprouveNotification($bonCommande, 'rejete'));
        } catch (\Exception) {}

        return $this->success(
            new BonCommandeResource($bonCommande->fresh()),
            "Bon de commande {$bonCommande->numero_bc} rejeté."
        );
    }

    /**
     * POST /api/v1/bons-commande/{bonCommande}/executer
     * Marque le BC comme exécuté (livraison reçue).
     */
    public function executer(Request $request, BonCommande $bonCommande): JsonResponse
    {
        $this->authorize('update', $bonCommande);

        if ($bonCommande->statut !== 'approuve') {
            return $this->error('Seul un bon de commande approuvé peut être marqué exécuté.', 422);
        }

        $request->validate([
            'date_livraison_reelle' => ['nullable', 'date'],
        ]);

        $bonCommande->update([
            'statut'               => 'execute',
            'date_livraison_reelle'=> $request->input('date_livraison_reelle', now()->toDateString()),
        ]);

        return $this->success(
            new BonCommandeResource($bonCommande->fresh()),
            "Bon de commande {$bonCommande->numero_bc} marqué comme exécuté."
        );
    }

    // ============================================================
    // Helpers privés
    // ============================================================

    private function calculerMontants(array $lignes, float $tauxTva): array
    {
        $montantHt = 0;
        foreach ($lignes as $ligne) {
            $montantHt += (float)($ligne['quantite'] ?? 1) * (float)($ligne['prix_unitaire'] ?? 0);
        }
        $montantTtc = $montantHt * (1 + $tauxTva / 100);

        return [
            'montant_ht'  => round($montantHt, 2),
            'tva'         => $tauxTva,
            'montant_ttc' => round($montantTtc, 2),
        ];
    }
}