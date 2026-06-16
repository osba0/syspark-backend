<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreCarburantRequest;
use App\Http\Requests\StoreDotationRequest;
use App\Http\Requests\UpdateDotationRequest;
use App\Http\Resources\CarburantResource;
use App\Models\Carburant;
use App\Models\DotationCarburant;
use App\Models\Vehicule;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CarburantController extends BaseApiController
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}
    /**
     * GET /api/v1/carburant
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Carburant::class);

        $query = QueryBuilder::for(Carburant::class)
            ->allowedFilters([
                AllowedFilter::exact('vehicule_id'),
                AllowedFilter::exact('chauffeur_id'),
                AllowedFilter::exact('agence_id'),
                AllowedFilter::exact('axe_livraison_id'),
                AllowedFilter::callback('annee', fn ($q, $v) => $q->whereYear('date', $v)),
                AllowedFilter::callback('mois',  fn ($q, $v) => $q->whereMonth('date', $v)),
                AllowedFilter::callback('periode_debut', fn ($q, $v) => $q->where('date', '>=', $v)),
                AllowedFilter::callback('periode_fin',   fn ($q, $v) => $q->where('date', '<=', $v)),
            ])
            ->allowedSorts(['-date', 'montant', 'litres'])
            ->allowedIncludes(['vehicule', 'chauffeur', 'axeLivraison'])
            ->defaultSort('-date')
            ->with(['vehicule', 'chauffeur']);

        $this->applyAgenceScope($query, $request);

        $carburants = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => CarburantResource::collection($carburants),
            'meta' => [
                'total'        => $carburants->total(),
                'per_page'     => $carburants->perPage(),
                'current_page' => $carburants->currentPage(),
                'last_page'    => $carburants->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/carburant
     */
    public function store(StoreCarburantRequest $request): JsonResponse
    {
        $this->authorize('create', Carburant::class);

        $data = $request->validated();

        // Récupérer le kilométrage du plein précédent pour calculer la conso
        $dernierPlein = Carburant::where('vehicule_id', $data['vehicule_id'])
            ->whereNotNull('kilometrage')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if ($dernierPlein?->kilometrage && isset($data['kilometrage'])) {
            $data['km_precedent'] = $dernierPlein->kilometrage;
        }

        // Calculer le prix unitaire si non fourni
        if (!isset($data['prix_unitaire']) && isset($data['montant'], $data['litres']) && $data['litres'] > 0) {
            $data['prix_unitaire'] = round($data['montant'] / $data['litres'], 2);
        }

        $data['saisi_par'] = $request->user()->id;

        DB::beginTransaction();
        try {
            $carburant = Carburant::create($data);

            // Mettre à jour le kilométrage du véhicule si fourni
            if (isset($data['kilometrage'])) {
                $vehicule = Vehicule::find($data['vehicule_id']);
                if ($vehicule && $data['kilometrage'] > $vehicule->kilometrage_actuel) {
                    $vehicule->update(['kilometrage_actuel' => $data['kilometrage']]);
                }
            }

            // Mettre à jour la dotation du mois
            $this->majDotationConsommee(
                $data['vehicule_id'],
                $data['agence_id'],
                $data['date'],
                $data['montant'],
                (float)($data['litres'] ?? 0)
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de l\'enregistrement : ' . $e->getMessage(), 500);
        }

        // Notification
        $carburant->loadMissing(['vehicule', 'chauffeur']);
        $this->notificationService->pleinCarburant([
            'id'              => $carburant->id,
            'litres'          => $carburant->litres,
            'montant'         => $carburant->montant,
            'immatriculation' => $carburant->vehicule?->immatriculation ?? '—',
            'chauffeur'       => $carburant->chauffeur?->nom_complet ?? '—',
            'vehicule_id'     => $carburant->vehicule_id,
            'agence_id'       => $carburant->agence_id,
        ]);

        return $this->created(
            new CarburantResource($carburant->load(['vehicule', 'chauffeur'])),
            'Consommation carburant enregistrée.'
        );
    }

    /**
     * GET /api/v1/carburant/{carburant}
     */
    public function show(Carburant $carburant): JsonResponse
    {
        $this->authorize('view', $carburant);

        return $this->success(
            new CarburantResource($carburant->load(['vehicule', 'chauffeur', 'axeLivraison']))
        );
    }

    /**
     * PUT /api/v1/carburant/{carburant}
     */
    public function update(Request $request, Carburant $carburant): JsonResponse
    {
        $this->authorize('update', $carburant);

        $request->validate([
            'litres'          => ['sometimes', 'numeric', 'min:0'],
            'montant'         => ['sometimes', 'numeric', 'min:0'],
            'prix_unitaire'   => ['nullable', 'numeric', 'min:0'],
            'kilometrage'     => ['nullable', 'integer', 'min:0'],
            'station'         => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $ancienMontant = (float)$carburant->montant;
        $anciensLitres = (float)$carburant->litres;
        $carburant->update($request->only(['litres', 'montant', 'prix_unitaire', 'kilometrage', 'station', 'notes']));

        // Recalculer la dotation si le montant ou les litres ont changé
        $diffMontant = $request->has('montant') ? (float)$request->montant - $ancienMontant : 0;
        $diffLitres  = $request->has('litres')  ? (float)$request->litres  - $anciensLitres  : 0;

        if ($diffMontant != 0 || $diffLitres != 0) {
            $this->ajusterDotationConsommee(
                $carburant->vehicule_id,
                $carburant->agence_id,
                $carburant->date->format('Y-m-d'),
                $diffMontant,
                $diffLitres
            );
        }

        return $this->success(new CarburantResource($carburant->fresh()));
    }

    /**
     * DELETE /api/v1/carburant/{carburant}
     */
    public function destroy(Carburant $carburant): JsonResponse
    {
        $this->authorize('delete', $carburant);

        // Déduire du montant ET des litres consommés avant suppression
        $this->ajusterDotationConsommee(
            $carburant->vehicule_id,
            $carburant->agence_id,
            $carburant->date->format('Y-m-d'),
            -(float)$carburant->montant,
            -(float)$carburant->litres
        );

        $carburant->delete();

        return $this->noContent('Enregistrement supprimé.');
    }

    /**
     * GET /api/v1/carburant/stats
     * Reproduce le tableau STAT CARBURANT du fichier Excel
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Carburant::class);

        $request->validate([
            'annee'     => ['nullable', 'integer', 'min:2020'],
            'agence_id' => ['nullable', 'exists:agences,id'],
        ]);

        $annee    = $request->input('annee', date('Y'));
        $agenceId = $request->input('agence_id') ?? $this->getAgenceScopeId($request);

        $base = Carburant::whereYear('date', $annee);
        if ($agenceId) {
            $base->where('carburants.agence_id', $agenceId);
        }

        // --- Totaux par mois ---
        $parMois = (clone $base)
            ->selectRaw('MONTH(date) as mois, SUM(montant) as total_montant, SUM(litres) as total_litres, COUNT(*) as nb_pleins')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        // --- Situation par véhicule (dotation vs consommation) ---
        $parVehicule = DB::table('carburants as c')
            ->join('vehicules as v', 'c.vehicule_id', '=', 'v.id')
            ->leftJoin(DB::raw('(SELECT vehicule_id, SUM(montant_dote) as dote, SUM(montant_consomme) as consomme
                FROM dotations_carburant WHERE annee = ' . $annee . '
                GROUP BY vehicule_id) as d'), 'd.vehicule_id', '=', 'c.vehicule_id')
            ->whereYear('c.date', $annee)
            ->when($agenceId, fn ($q) => $q->where('c.agence_id', $agenceId))
            ->select(
                'c.vehicule_id',
                'v.immatriculation',
                'v.marque',
                'v.modele',
                DB::raw('SUM(c.montant) as total_consomme'),
                DB::raw('SUM(c.litres) as total_litres'),
                DB::raw('COUNT(c.id) as nb_pleins'),
                DB::raw('MAX(d.dote) as total_dote')
            )
            ->groupBy('c.vehicule_id', 'v.immatriculation', 'v.marque', 'v.modele')
            ->orderByDesc('total_consomme')
            ->get()
            ->map(fn ($v) => [
                'vehicule_id'    => $v->vehicule_id,
                'immatriculation'=> $v->immatriculation,
                'marque_modele'  => $v->marque . ' ' . $v->modele,
                'total_consomme' => round((float)$v->total_consomme, 2),
                'total_dote'     => round((float)($v->total_dote ?? 0), 2),
                'ecart'          => round((float)($v->total_dote ?? 0) - (float)$v->total_consomme, 2),
                'taux_conso'     => $v->total_dote > 0
                    ? round(((float)$v->total_consomme / (float)$v->total_dote) * 100, 1)
                    : null,
                'total_litres'   => round((float)$v->total_litres, 2),
                'nb_pleins'      => (int)$v->nb_pleins,
            ]);

        // --- Par chauffeur ---
        $parChauffeur = (clone $base)
            ->join('chauffeurs as ch', 'carburants.chauffeur_id', '=', 'ch.id')
            ->select(
                'carburants.chauffeur_id',
                'ch.nom', 'ch.prenom',
                DB::raw('SUM(carburants.montant) as total, SUM(carburants.litres) as litres, COUNT(*) as nb')
            )
            ->whereNotNull('carburants.chauffeur_id')
            ->groupBy('carburants.chauffeur_id', 'ch.nom', 'ch.prenom')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'chauffeur_id' => $c->chauffeur_id,
                'nom'          => $c->prenom . ' ' . $c->nom,
                'total'        => round((float)$c->total, 2),
                'litres'       => round((float)$c->litres, 2),
                'nb'           => (int)$c->nb,
            ]);

        // --- Par axe ---
        $parAxe = (clone $base)
            ->join('axes_livraison as ax', 'carburants.axe_livraison_id', '=', 'ax.id')
            ->select(
                'carburants.axe_livraison_id',
                'ax.nom',
                DB::raw('SUM(carburants.montant) as total, SUM(carburants.litres) as litres')
            )
            ->whereNotNull('carburants.axe_livraison_id')
            ->groupBy('carburants.axe_livraison_id', 'ax.nom')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($a) => [
                'axe_id' => $a->axe_livraison_id,
                'nom'    => $a->nom,
                'total'  => round((float)$a->total, 2),
                'litres' => round((float)$a->litres, 2),
            ]);

        $totalGlobal     = (clone $base)->sum('montant');
        $totalLitres     = (clone $base)->sum('litres');
        $totalDote       = DotationCarburant::where('annee', $annee)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->sum('montant_dote');

        return $this->success([
            'annee'           => $annee,
            'total_consomme'  => round((float)$totalGlobal, 2),
            'total_dote'      => round((float)$totalDote, 2),
            'ecart_global'    => round((float)$totalDote - (float)$totalGlobal, 2),
            'total_litres'    => round((float)$totalLitres, 2),
            'par_mois'        => $parMois->map(fn ($m) => [
                'mois'          => (int)$m->mois,
                'total_montant' => round((float)$m->total_montant, 2),
                'total_litres'  => round((float)$m->total_litres, 2),
                'nb_pleins'     => (int)$m->nb_pleins,
            ]),
            'par_vehicule'    => $parVehicule,
            'par_chauffeur'   => $parChauffeur,
            'par_axe'         => $parAxe,
        ]);
    }

    // ============================================================
    // Dotations
    // ============================================================

    /**
     * GET /api/v1/carburant/dotations
     */
    public function dotations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Carburant::class);

        $request->validate([
            'annee'      => ['nullable', 'integer'],
            'mois'       => ['nullable', 'integer', 'min:1', 'max:12'],
            'agence_id'  => ['nullable', 'exists:agences,id'],
            'vehicule_id'=> ['nullable', 'exists:vehicules,id'],
        ]);

        $annee    = $request->input('annee', date('Y'));
        $agenceId = $request->input('agence_id') ?? $this->getAgenceScopeId($request);

        $query = DotationCarburant::with([
                'vehicule:id,immatriculation,marque,modele',
                'chauffeur:id,nom,prenom',
            ])
            ->where('annee', $annee)
            ->when($request->mois,       fn ($q) => $q->where('mois', $request->mois))
            ->when($agenceId,            fn ($q) => $q->where('agence_id', $agenceId))
            ->when($request->vehicule_id,fn ($q) => $q->where('vehicule_id', $request->vehicule_id))
            ->orderBy('mois')
            ->orderBy('vehicule_id');

        $dotations = $query->get()->map(fn ($d) => [
            'id'                       => $d->id,
            'vehicule_id'              => $d->vehicule_id,
            'immatriculation'          => $d->vehicule?->immatriculation,
            'marque_modele'            => $d->vehicule ? trim($d->vehicule->marque . ' ' . $d->vehicule->modele) : null,
            'chauffeur_id'             => $d->chauffeur_id,
            'chauffeur_nom'            => $d->chauffeur ? trim($d->chauffeur->prenom . ' ' . $d->chauffeur->nom) : null,
            'mois'                     => $d->mois,
            'annee'                    => $d->annee,
            'montant_dote'             => (float)$d->montant_dote,
            'montant_consomme'         => (float)$d->montant_consomme,
            'litres_dotes'             => (float)$d->litres_dotes,
            'litres_consommes'         => (float)$d->litres_consommes,
            'ecart'                    => $d->ecart,
            'taux_consommation'        => $d->taux_consommation,
            'ecart_litres'             => $d->ecart_litres,
            'taux_consommation_litres' => $d->taux_consommation_litres,
            'notes'                    => $d->notes,
        ]);

        return $this->success($dotations);
    }

    /**
     * POST /api/v1/carburant/dotations
     */
    public function storeDotation(StoreDotationRequest $request): JsonResponse
    {
        $this->authorize('gererDotations', Carburant::class);

        $data = $request->validated();

        $dotation = DotationCarburant::updateOrCreate(
            [
                'vehicule_id' => $data['vehicule_id'],
                'mois'        => $data['mois'],
                'annee'       => $data['annee'],
            ],
            [
                'chauffeur_id'  => $data['chauffeur_id'] ?? null,
                'agence_id'     => $data['agence_id'],
                'montant_dote'  => $data['montant_dote'],
                'litres_dotes'  => $data['litres_dotes'] ?? 0,
                'notes'         => $data['notes'] ?? null,
            ]
        );

        return $this->created($dotation->load(['vehicule', 'chauffeur']), 'Dotation enregistrée.');
    }

    /**
     * PUT /api/v1/carburant/dotations/{dotation}
     */
    public function updateDotation(UpdateDotationRequest $request, DotationCarburant $dotation): JsonResponse
    {
        $this->authorize('gererDotations', Carburant::class);

        $dotation->update($request->validated());

        return $this->success($dotation->fresh(), 'Dotation mise à jour.');
    }

    // ============================================================
    // Helpers privés
    // ============================================================

    private function majDotationConsommee(int $vehiculeId, int $agenceId, string $date, float $montant, float $litres = 0): void
    {
        $mois  = (int)date('n', strtotime($date));
        $annee = (int)date('Y', strtotime($date));

        // Créer la dotation si elle n'existe pas encore (montant_dote = 0)
        DotationCarburant::updateOrCreate(
            ['vehicule_id' => $vehiculeId, 'mois' => $mois, 'annee' => $annee],
            ['agence_id' => $agenceId]
        );

        $record = DotationCarburant::where([
            'vehicule_id' => $vehiculeId,
            'mois'        => $mois,
            'annee'       => $annee,
        ])->first();

        if ($record) {
            $record->increment('montant_consomme', $montant);
            if ($litres > 0) {
                $record->increment('litres_consommes', $litres);
            }
        }
    }

    private function ajusterDotationConsommee(int $vehiculeId, int $agenceId, string $date, float $delta, float $deltaLitres = 0): void
    {
        $mois  = (int)date('n', strtotime($date));
        $annee = (int)date('Y', strtotime($date));

        $dotation = DotationCarburant::where([
            'vehicule_id' => $vehiculeId,
            'mois'        => $mois,
            'annee'       => $annee,
        ])->first();

        if ($dotation) {
            $dotation->update([
                'montant_consomme'  => max(0, (float)$dotation->montant_consomme  + $delta),
                'litres_consommes'  => max(0, (float)$dotation->litres_consommes  + $deltaLitres),
            ]);
        }
    }
}