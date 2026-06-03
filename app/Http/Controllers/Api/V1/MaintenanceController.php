<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreMaintenanceRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Models\Maintenance;
use App\Models\Signalement;
use App\Models\Vehicule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MaintenanceController extends BaseApiController
{
    /**
     * GET /api/v1/maintenances
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Maintenance::class);

        $query = QueryBuilder::for(Maintenance::class)
            ->allowedFilters([
                AllowedFilter::exact('vehicule_id'),
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('fournisseur_id'),
                AllowedFilter::exact('chauffeur_id'),
                AllowedFilter::exact('type_operation'),
                AllowedFilter::exact('statut'),
                AllowedFilter::partial('titre'),
                AllowedFilter::callback('annee', fn ($q, $v) => $q->whereYear('date_travaux', $v)),
                AllowedFilter::callback('mois',  fn ($q, $v) => $q->whereMonth('date_travaux', $v)),
                AllowedFilter::callback('periode_debut', fn ($q, $v) => $q->where('date_travaux', '>=', $v)),
                AllowedFilter::callback('periode_fin',   fn ($q, $v) => $q->where('date_travaux', '<=', $v)),
            ])
            ->allowedSorts(['-date_travaux', 'montant_ttc', 'statut', 'type_operation'])
            ->allowedIncludes(['vehicule', 'fournisseur', 'chauffeur', 'agence', 'bonCommande'])
            ->defaultSort('-date_travaux')
            ->with(['vehicule', 'fournisseur', 'chauffeur']);

        $this->applyAgenceScope($query, $request);

        $maintenances = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => MaintenanceResource::collection($maintenances),
            'meta' => [
                'total'        => $maintenances->total(),
                'per_page'     => $maintenances->perPage(),
                'current_page' => $maintenances->currentPage(),
                'last_page'    => $maintenances->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/maintenances/stats
     * Statistiques agrégées — reproduce STAT EXP du fichier Excel
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Maintenance::class);

        $request->validate([
            'annee'     => ['nullable', 'integer', 'min:2020'],
            'agence_id' => ['nullable', 'exists:agences,id'],
        ]);

        $annee    = $request->input('annee', date('Y'));
        $agenceId = $request->input('agence_id') ?? $this->getAgenceScopeId($request);

        $base = Maintenance::whereYear('date_travaux', $annee)
            ->where('statut', 'termine');

        if ($agenceId) {
            $base->where('agence_id', $agenceId);
        }

        // Totaux par type d'opération
        $parType = (clone $base)
            ->select('type_operation', DB::raw('SUM(montant_ttc) as total, COUNT(*) as nb'))
            ->groupBy('type_operation')
            ->get()
            ->keyBy('type_operation');

        // Totaux par mois (pour courbe)
        $parMois = (clone $base)
            ->select(
                DB::raw('MONTH(date_travaux) as mois'),
                DB::raw('SUM(montant_ttc) as total'),
                DB::raw('COUNT(*) as nb_interventions')
            )
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        // Top 10 véhicules les plus coûteux
        $parVehicule = (clone $base)
            ->select('vehicule_id', DB::raw('SUM(montant_ttc) as total, COUNT(*) as nb'))
            ->groupBy('vehicule_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('vehicule:id,immatriculation,marque,modele')
            ->get();

        // Top 10 fournisseurs
        $parFournisseur = (clone $base)
            ->select('fournisseur_id', DB::raw('SUM(montant_ttc) as total, COUNT(*) as nb'))
            ->groupBy('fournisseur_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('fournisseur:id,nom')
            ->get();

        // Top chauffeurs
        $parChauffeur = (clone $base)
            ->select('chauffeur_id', DB::raw('SUM(montant_ttc) as total, COUNT(*) as nb'))
            ->whereNotNull('chauffeur_id')
            ->groupBy('chauffeur_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('chauffeur:id,nom,prenom')
            ->get();

        $totalGlobal = (clone $base)->sum('montant_ttc');

        return $this->success([
            'annee'         => $annee,
            'total_global'  => round($totalGlobal, 2),
            'par_type'      => [
                'entretien'   => ['total' => round((float)($parType['entretien']?->total ?? 0), 2), 'nb' => (int)($parType['entretien']?->nb ?? 0)],
                'reparation'  => ['total' => round((float)($parType['reparation']?->total ?? 0), 2), 'nb' => (int)($parType['reparation']?->nb ?? 0)],
                'pneu'        => ['total' => round((float)($parType['pneu']?->total ?? 0), 2), 'nb' => (int)($parType['pneu']?->nb ?? 0)],
                'equipement'  => ['total' => round((float)($parType['equipement']?->total ?? 0), 2), 'nb' => (int)($parType['equipement']?->nb ?? 0)],
                'carrosserie' => ['total' => round((float)($parType['carrosserie']?->total ?? 0), 2), 'nb' => (int)($parType['carrosserie']?->nb ?? 0)],
                'contravention'=> ['total' => round((float)($parType['contravention']?->total ?? 0), 2), 'nb' => (int)($parType['contravention']?->nb ?? 0)],
            ],
            'par_mois'      => $parMois->map(fn ($m) => [
                'mois'  => (int)$m->mois,
                'total' => round((float)$m->total, 2),
                'nb'    => (int)$m->nb_interventions,
            ]),
            'top_vehicules' => $parVehicule->map(fn ($v) => [
                'vehicule_id'    => $v->vehicule_id,
                'immatriculation'=> $v->vehicule?->immatriculation,
                'marque_modele'  => $v->vehicule ? $v->vehicule->marque . ' ' . $v->vehicule->modele : null,
                'total'          => round((float)$v->total, 2),
                'nb'             => (int)$v->nb,
            ]),
            'top_fournisseurs' => $parFournisseur->map(fn ($f) => [
                'fournisseur_id' => $f->fournisseur_id,
                'nom'            => $f->fournisseur?->nom,
                'total'          => round((float)$f->total, 2),
                'nb'             => (int)$f->nb,
            ]),
            'top_chauffeurs' => $parChauffeur->map(fn ($c) => [
                'chauffeur_id' => $c->chauffeur_id,
                'nom'          => $c->chauffeur?->nom_complet,
                'total'        => round((float)$c->total, 2),
                'nb'           => (int)$c->nb,
            ]),
        ]);
    }

    /**
     * GET /api/v1/maintenances/planifiees
     */
    public function planifiees(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Maintenance::class);

        $query = Maintenance::whereIn('statut', ['planifie', 'en_cours'])
            ->with(['vehicule', 'fournisseur', 'chauffeur'])
            ->orderBy('date_travaux');

        $agenceId = $this->getAgenceScopeId($request);
        if ($agenceId) {
            $query->where('agence_id', $agenceId);
        }

        return $this->success(
            MaintenanceResource::collection($query->get())
        );
    }

    /**
     * POST /api/v1/maintenances
     */
    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        $this->authorize('create', Maintenance::class);

        $data = $request->validated();

        // Calculer si approbation requise
        $montant = (float)($data['montant_ttc'] ?? 0);
        $data['necessite_approbation'] = $montant >= config('parc.maintenance.seuil_approbation');
        $data['created_by'] = $request->user()->id;

        // Calculer montant TTC si non fourni
        if (!isset($data['montant_ttc']) && isset($data['montant_ht'], $data['tva'])) {
            $data['montant_ttc'] = $data['montant_ht'] * (1 + $data['tva'] / 100);
        }

        $maintenance = Maintenance::create($data);

        return $this->created(
            new MaintenanceResource($maintenance->load(['vehicule', 'fournisseur'])),
            $data['necessite_approbation']
                ? 'Maintenance créée. En attente d\'approbation (montant > ' . number_format(config('parc.maintenance.seuil_approbation')) . ' FCFA).'
                : 'Maintenance créée avec succès.'
        );
    }

    /**
     * GET /api/v1/maintenances/{maintenance}
     */
    public function show(Maintenance $maintenance): JsonResponse
    {
        $this->authorize('view', $maintenance);

        $maintenance->load([
            'vehicule.agence',
            'fournisseur',
            'chauffeur',
            'axeLivraison',
            'bonCommande',
            'signalement',
            'approuvePar',
            'createdBy',
        ]);

        return $this->success(new MaintenanceResource($maintenance));
    }

    /**
     * PUT /api/v1/maintenances/{maintenance}
     */
    public function update(UpdateMaintenanceRequest $request, Maintenance $maintenance): JsonResponse
    {
        $this->authorize('update', $maintenance);

        if ($maintenance->statut === 'termine') {
            return $this->error('Une maintenance terminée ne peut pas être modifiée.', 422);
        }

        $maintenance->update($request->validated());

        return $this->success(
            new MaintenanceResource($maintenance->fresh(['vehicule', 'fournisseur'])),
            'Maintenance mise à jour.'
        );
    }

    /**
     * DELETE /api/v1/maintenances/{maintenance}
     */
    public function destroy(Maintenance $maintenance): JsonResponse
    {
        $this->authorize('delete', $maintenance);

        if ($maintenance->statut === 'termine') {
            return $this->error('Impossible de supprimer une maintenance terminée.', 422);
        }

        $maintenance->delete();

        return $this->noContent('Maintenance supprimée.');
    }

    /**
     * POST /api/v1/maintenances/{maintenance}/approuver
     * Directeur / resp_parc approuve une maintenance à montant élevé
     */
    public function approuver(Request $request, Maintenance $maintenance): JsonResponse
    {
        $this->authorize('approve', $maintenance);

        if (!$maintenance->necessite_approbation) {
            return $this->error('Cette maintenance ne nécessite pas d\'approbation.', 422);
        }

        if ($maintenance->approuve_par) {
            return $this->error('Cette maintenance est déjà approuvée.', 422);
        }

        $maintenance->update([
            'approuve_par' => $request->user()->id,
            'approuve_le'  => now(),
        ]);

        return $this->success(
            new MaintenanceResource($maintenance->fresh()),
            'Maintenance approuvée.'
        );
    }

    /**
     * POST /api/v1/maintenances/{maintenance}/cloturer
     * Clôture la maintenance et met à jour le véhicule
     */
    public function cloturer(Request $request, Maintenance $maintenance): JsonResponse
    {
        $this->authorize('cloturer', $maintenance);

        if ($maintenance->statut === 'termine') {
            return $this->error('Cette maintenance est déjà clôturée.', 422);
        }

        if ($maintenance->necessite_approbation && !$maintenance->approuve_par) {
            return $this->error('Cette maintenance nécessite une approbation avant clôture.', 422);
        }

        $request->validate([
            'montant_ttc'   => ['nullable', 'numeric', 'min:0'],
            'numero_facture'=> ['nullable', 'string', 'max:100'],
            'date_sortie'   => ['nullable', 'date'],
            'kilometrage'   => ['nullable', 'integer', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $updateData = ['statut' => 'termine'];
            if ($request->montant_ttc)    $updateData['montant_ttc']    = $request->montant_ttc;
            if ($request->numero_facture) $updateData['numero_facture'] = $request->numero_facture;
            if ($request->date_sortie)    $updateData['date_sortie']    = $request->date_sortie;
            if ($request->kilometrage)    $updateData['kilometrage']    = $request->kilometrage;

            $maintenance->update($updateData);

            // Mettre à jour le kilométrage et le prochain entretien du véhicule
            $vehicule = $maintenance->vehicule;
            if ($vehicule && $request->kilometrage > $vehicule->kilometrage_actuel) {
                $vehicule->update([
                    'kilometrage_actuel'     => $request->kilometrage,
                    'prochain_entretien_km'  => $request->kilometrage + $vehicule->intervalle_entretien_km,
                ]);

                // Si c'est un entretien, mettre à jour la date du dernier entretien
                if ($maintenance->type_operation === 'entretien') {
                    $vehicule->update([
                        'prochain_entretien_date' => now()->addMonths(6)->toDateString(),
                    ]);
                }
            }

            // Remettre le véhicule en état actif après maintenance
            if ($vehicule?->statut === 'en_maintenance') {
                $vehicule->update(['statut' => 'actif']);
            }

            // Résoudre automatiquement le signalement lié si présent
            if ($maintenance->signalement_id) {
                $signalement = Signalement::find($maintenance->signalement_id);
                if ($signalement && $signalement->statut === 'maintenance_creee') {
                    $signalement->update([
                        'statut'                 => 'resolu',
                        'resolu_par'             => auth()->id(),
                        'resolu_le'              => now(),
                        'commentaire_resolution' => 'Résolu automatiquement suite à la clôture de la maintenance #' . $maintenance->id . '.',
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de la clôture : ' . $e->getMessage(), 500);
        }

        return $this->success(
            new MaintenanceResource($maintenance->fresh(['vehicule', 'fournisseur'])),
            'Maintenance clôturée. Véhicule remis en service.'
        );
    }
}