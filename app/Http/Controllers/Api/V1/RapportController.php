<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Affectation;
use App\Models\BonCommande;
use App\Models\Checklist;
use App\Models\Signalement;
use App\Models\Vehicule;
use App\Services\ExcelExportService;
use App\Services\PdfService;
use App\Services\TcoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RapportController extends BaseApiController
{
    public function __construct(
        private PdfService          $pdfService,
        private ExcelExportService  $excelService,
        private TcoService          $tcoService
    ) {}

    // ============================================================
    // Données des rapports (JSON — pour Recharts frontend)
    // ============================================================

    /**
     * GET /api/v1/rapports/maintenance
     */
    public function maintenance(Request $request): JsonResponse
    {
        $request->validate([
            'annee'     => ['nullable', 'integer'],
            'agence_id' => ['nullable', 'exists:agences,id'],
        ]);

        $annee    = $request->input('annee', date('Y'));
        $agenceId = $request->input('agence_id') ?? $this->getAgenceScopeId($request);

        $base = DB::table('maintenances')
            ->whereYear('date_travaux', $annee)
            ->where('maintenances.statut', 'termine')
            ->when($agenceId, fn ($q) => $q->where('maintenances.agence_id', $agenceId));

        $totaux = (clone $base)->selectRaw(
            'SUM(montant_ttc) as total, COUNT(*) as nb,
            SUM(CASE WHEN type_operation="entretien" THEN montant_ttc ELSE 0 END) as entretien,
            SUM(CASE WHEN type_operation="reparation" THEN montant_ttc ELSE 0 END) as reparation,
            SUM(CASE WHEN type_operation="pneu" THEN montant_ttc ELSE 0 END) as pneu,
            SUM(CASE WHEN type_operation="equipement" THEN montant_ttc ELSE 0 END) as equipement,
            SUM(CASE WHEN type_operation="contravention" THEN montant_ttc ELSE 0 END) as contravention'
        )->first();

        $parMois = (clone $base)->selectRaw(
            'MONTH(date_travaux) as mois,
            SUM(montant_ttc) as total,
            SUM(CASE WHEN type_operation="entretien" THEN montant_ttc ELSE 0 END) as entretien,
            SUM(CASE WHEN type_operation="reparation" THEN montant_ttc ELSE 0 END) as reparation,
            SUM(CASE WHEN type_operation="pneu" THEN montant_ttc ELSE 0 END) as pneus,
            COUNT(*) as nb'
        )->groupBy('mois')->orderBy('mois')->get();

        $parVehicule = (clone $base)
            ->join('vehicules as v', 'maintenances.vehicule_id', '=', 'v.id')
            ->selectRaw('maintenances.vehicule_id, v.immatriculation, v.marque, v.modele, SUM(maintenances.montant_ttc) as total, COUNT(*) as nb')
            ->groupBy('maintenances.vehicule_id', 'v.immatriculation', 'v.marque', 'v.modele')
            ->orderByDesc('total')->get();

        $parFournisseur = (clone $base)
            ->join('fournisseurs as f', 'maintenances.fournisseur_id', '=', 'f.id')
            ->selectRaw('maintenances.fournisseur_id, f.nom, SUM(maintenances.montant_ttc) as total, COUNT(*) as nb')
            ->whereNotNull('maintenances.fournisseur_id')
            ->groupBy('maintenances.fournisseur_id', 'f.nom')
            ->orderByDesc('total')->get();

        $parAxe = (clone $base)
            ->join('axes_livraison as ax', 'maintenances.axe_livraison_id', '=', 'ax.id')
            ->selectRaw('maintenances.axe_livraison_id, ax.nom, SUM(maintenances.montant_ttc) as total, COUNT(*) as nb')
            ->whereNotNull('maintenances.axe_livraison_id')
            ->groupBy('maintenances.axe_livraison_id', 'ax.nom')
            ->orderByDesc('total')->get();

        $parChauffeur = (clone $base)
            ->join('chauffeurs as ch', 'maintenances.chauffeur_id', '=', 'ch.id')
            ->selectRaw('maintenances.chauffeur_id, ch.nom, ch.prenom, SUM(maintenances.montant_ttc) as total, COUNT(*) as nb')
            ->whereNotNull('maintenances.chauffeur_id')
            ->groupBy('maintenances.chauffeur_id', 'ch.nom', 'ch.prenom')
            ->orderByDesc('total')->limit(20)->get();

        return $this->success([
            'annee'   => $annee,
            'totaux'  => [
                'global'          => round((float)($totaux->total ?? 0), 2),
                'entretien'       => round((float)($totaux->entretien ?? 0), 2),
                'reparation'      => round((float)($totaux->reparation ?? 0), 2),
                'pneu'            => round((float)($totaux->pneu ?? 0), 2),
                'equipement'      => round((float)($totaux->equipement ?? 0), 2),
                'contravention'   => round((float)($totaux->contravention ?? 0), 2),
                'nb_interventions'=> (int)($totaux->nb ?? 0),
            ],
            'par_mois'       => $parMois->map(fn ($m) => ['mois' => (int)$m->mois, 'total' => round((float)$m->total, 2), 'entretien' => round((float)$m->entretien, 2), 'reparation' => round((float)$m->reparation, 2), 'pneus' => round((float)$m->pneus, 2), 'nb' => (int)$m->nb]),
            'par_vehicule'   => $parVehicule->map(fn ($v) => ['vehicule_id' => $v->vehicule_id, 'immatriculation' => $v->immatriculation, 'marque_modele' => $v->marque . ' ' . $v->modele, 'total' => round((float)$v->total, 2), 'nb' => (int)$v->nb]),
            'par_fournisseur'=> $parFournisseur->map(fn ($f) => ['fournisseur_id' => $f->fournisseur_id, 'nom' => $f->nom, 'total' => round((float)$f->total, 2), 'nb' => (int)$f->nb]),
            'par_axe'        => $parAxe->map(fn ($a) => ['axe_id' => $a->axe_livraison_id, 'nom' => $a->nom, 'total' => round((float)$a->total, 2), 'nb' => (int)$a->nb]),
            'par_chauffeur'  => $parChauffeur->map(fn ($c) => ['chauffeur_id' => $c->chauffeur_id, 'nom' => $c->prenom . ' ' . $c->nom, 'total' => round((float)$c->total, 2), 'nb' => (int)$c->nb]),
        ]);
    }

    /**
     * GET /api/v1/rapports/carburant
     */
    public function carburant(Request $request): JsonResponse
    {
        return app(CarburantController::class)->stats($request);
    }

    /**
     * GET /api/v1/rapports/tco
     */
    public function tco(Request $request): JsonResponse
    {
        $request->validate(['annee' => ['nullable', 'integer'], 'agence_id' => ['nullable', 'exists:agences,id']]);

        $annee    = $request->input('annee', date('Y'));
        $agenceId = $request->input('agence_id') ?? $this->getAgenceScopeId($request);

        if ($request->has('vehicule_id')) {
            $vehicule = Vehicule::findOrFail($request->vehicule_id);
            return $this->success($this->tcoService->calculer($vehicule, $annee));
        }

        return $this->success($this->tcoService->consolideParcParAgence((int)($agenceId ?? 0), (int)$annee));
    }

    /**

    /**
     * GET /api/v1/rapports/axes
     * Rapport coût par axe de livraison — maintenance + carburant
     */
    public function axes(Request $request): JsonResponse
    {
        $request->validate([
            'annee'     => ['nullable', 'integer'],
            'agence_id' => ['nullable', 'exists:agences,id'],
        ]);

        $annee    = $request->input('annee', date('Y'));
        $agenceId = $request->input('agence_id') ?? $this->getAgenceScopeId($request);

        // ── Maintenance par axe ────────────────────────────────
        $maintenanceParAxe = DB::table('maintenances')
            ->join('axes_livraison', 'maintenances.axe_livraison_id', '=', 'axes_livraison.id')
            ->whereYear('maintenances.date_travaux', $annee)
            ->where('maintenances.statut', 'termine')
            ->whereNotNull('maintenances.axe_livraison_id')
            ->when($agenceId, fn ($q) => $q->where('maintenances.agence_id', $agenceId))
            ->selectRaw('
                axes_livraison.id,
                axes_livraison.nom,
                axes_livraison.code,
                axes_livraison.zone,
                COUNT(*) as nb_maintenances,
                SUM(maintenances.montant_ttc) as cout_maintenance,
                SUM(CASE WHEN maintenances.type_operation = "entretien" THEN maintenances.montant_ttc ELSE 0 END) as entretien,
                SUM(CASE WHEN maintenances.type_operation = "reparation" THEN maintenances.montant_ttc ELSE 0 END) as reparation,
                SUM(CASE WHEN maintenances.type_operation = "pneu" THEN maintenances.montant_ttc ELSE 0 END) as pneu
            ')
            ->groupBy('axes_livraison.id', 'axes_livraison.nom', 'axes_livraison.code', 'axes_livraison.zone')
            ->orderByDesc('cout_maintenance')
            ->get()
            ->keyBy('id');

        // ── Carburant par axe ──────────────────────────────────
        $carburantParAxe = DB::table('carburants')
            ->join('axes_livraison', 'carburants.axe_livraison_id', '=', 'axes_livraison.id')
            ->whereYear('carburants.date', $annee)
            ->whereNotNull('carburants.axe_livraison_id')
            ->when($agenceId, fn ($q) => $q->where('carburants.agence_id', $agenceId))
            ->selectRaw('
                axes_livraison.id,
                COUNT(*) as nb_pleins,
                SUM(carburants.litres) as litres_total,
                SUM(carburants.montant) as cout_carburant
            ')
            ->groupBy('axes_livraison.id')
            ->get()
            ->keyBy('id');

        // ── Affectations par axe ───────────────────────────────
        $affectationsParAxe = DB::table('affectations')
            ->join('axes_livraison', 'affectations.axe_livraison_id', '=', 'axes_livraison.id')
            ->whereYear('affectations.date_debut', $annee)
            ->whereNotNull('affectations.axe_livraison_id')
            ->when($agenceId, fn ($q) => $q->where('affectations.agence_id', $agenceId))
            ->selectRaw('axes_livraison.id, COUNT(*) as nb_affectations')
            ->groupBy('axes_livraison.id')
            ->get()
            ->keyBy('id');

        // ── Liste de tous les axes concernés ───────────────────
        $axeIds = $maintenanceParAxe->keys()
            ->merge($carburantParAxe->keys())
            ->merge($affectationsParAxe->keys())
            ->unique();

        $axes = \App\Models\AxeLivraison::whereIn('id', $axeIds)
            ->with('agence:id,nom,code')
            ->get()
            ->keyBy('id');

        // ── Fusionner les données ──────────────────────────────
        $data = $axeIds->map(function ($id) use ($maintenanceParAxe, $carburantParAxe, $affectationsParAxe, $axes) {
            $m    = $maintenanceParAxe->get($id);
            $c    = $carburantParAxe->get($id);
            $a    = $affectationsParAxe->get($id);
            $axe  = $axes->get($id);

            $coutMaint   = (float) ($m->cout_maintenance ?? 0);
            $coutCarb    = (float) ($c->cout_carburant   ?? 0);
            $coutTotal   = $coutMaint + $coutCarb;

            return [
                'axe_id'          => $id,
                'axe_nom'         => $axe?->nom       ?? 'Axe #' . $id,
                'axe_code'        => $axe?->code       ?? '',
                'axe_zone'        => $axe?->zone       ?? null,
                'agence'          => $axe?->agence?->nom ?? null,
                'nb_affectations' => (int) ($a->nb_affectations ?? 0),
                'nb_maintenances' => (int) ($m->nb_maintenances ?? 0),
                'cout_maintenance'=> $coutMaint,
                'cout_entretien'  => (float) ($m->entretien ?? 0),
                'cout_reparation' => (float) ($m->reparation ?? 0),
                'cout_pneu'       => (float) ($m->pneu ?? 0),
                'nb_pleins'       => (int) ($c->nb_pleins ?? 0),
                'litres_total'    => (float) ($c->litres_total ?? 0),
                'cout_carburant'  => $coutCarb,
                'cout_total'      => $coutTotal,
            ];
        })->sortByDesc('cout_total')->values();

        // ── Totaux globaux ─────────────────────────────────────
        $totaux = [
            'nb_axes'          => $data->count(),
            'cout_maintenance' => $data->sum('cout_maintenance'),
            'cout_carburant'   => $data->sum('cout_carburant'),
            'cout_total'       => $data->sum('cout_total'),
            'nb_affectations'  => $data->sum('nb_affectations'),
            'litres_total'     => $data->sum('litres_total'),
        ];

        return $this->success([
            'annee'   => $annee,
            'totaux'  => $totaux,
            'par_axe' => $data,
        ]);
    }

    /**
     * GET /api/v1/rapports/parc-global
     */
    public function parcGlobal(Request $request): JsonResponse
    {
        $agenceId = $this->getAgenceScopeId($request);
        $vehicules = DB::table('vehicules')
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->whereNull('deleted_at')
            ->select('statut', 'type_vehicule', DB::raw('COUNT(*) as total'))
            ->groupBy('statut', 'type_vehicule')
            ->get();

        return $this->success([
            'vehicules_par_statut' => $vehicules->groupBy('statut'),
            'vehicules_par_type'   => $vehicules->groupBy('type_vehicule'),
            'total'                => $vehicules->sum('total'),
        ]);
    }

    // ============================================================
    // Génération PDF (téléchargement direct)
    // ============================================================

    /**
     * GET /api/v1/rapports/pdf/signalement/{id}
     */
    public function pdfSignalement(Signalement $signalement)
    {
        $this->authorize('view', $signalement);
        return $this->pdfService->ficheSignalement($signalement);
    }

    /**
     * GET /api/v1/rapports/pdf/checklist/{id}
     */
    public function pdfChecklist(Checklist $checklist)
    {
        $this->authorize('view', $checklist);
        return $this->pdfService->checklist($checklist);
    }

    /**
     * GET /api/v1/rapports/pdf/affectation/{id}
     */
    public function pdfAffectation(Affectation $affectation)
    {
        $this->authorize('view', $affectation);
        return $this->pdfService->ficheAttribution($affectation);
    }

    /**
     * GET /api/v1/rapports/pdf/bon-commande/{id}
     */
    public function pdfBonCommande(BonCommande $bonCommande)
    {
        $this->authorize('view', $bonCommande);
        return $this->pdfService->bonCommande($bonCommande);
    }

    /**
     * GET /api/v1/rapports/pdf/vehicule/{id}
     */
    public function pdfVehicule(Request $request, Vehicule $vehicule)
    {
        $this->authorize('view', $vehicule);
        $annee = (int)$request->input('annee', date('Y'));
        return $this->pdfService->ficheVehicule($vehicule, $annee);
    }

    /**
     * POST /api/v1/rapports/pdf/maintenance
     */
    public function pdfMaintenance(Request $request)
    {
        Gate::authorize('exportRapport', 'rapport');
        $request->validate(['annee' => ['nullable', 'integer'], 'agence_id' => ['nullable', 'exists:agences,id']]);

        $annee    = $request->input('annee', date('Y'));
        $agenceId = $request->input('agence_id') ?? $this->getAgenceScopeId($request);

        $data = json_decode($this->maintenance($request)->getContent(), true)['data'];
        $data['annee'] = $annee;

        return $this->pdfService->rapportMaintenance($data);
    }

    // ============================================================
    // Exports Excel (téléchargement direct)
    // ============================================================

    /**
     * GET /api/v1/rapports/excel/maintenance
     */
    public function excelMaintenance(Request $request)
    {
        $request->validate(['annee' => ['nullable', 'integer'], 'type' => ['nullable', 'in:livraison,administratif']]);
        $annee    = (int)$request->input('annee', date('Y'));
        $type     = $request->input('type', 'livraison');
        $agenceId = $this->getAgenceScopeId($request);

        return $this->excelService->exportMaintenance($annee, $type, $agenceId);
    }

    /**
     * GET /api/v1/rapports/excel/carburant
     */
    public function excelCarburant(Request $request)
    {
        $annee    = (int)$request->input('annee', date('Y'));
        $agenceId = $this->getAgenceScopeId($request);

        return $this->excelService->exportCarburant($annee, $agenceId);
    }

    /**
     * GET /api/v1/rapports/excel/parc-global
     */
    public function excelParcGlobal(Request $request)
    {
        $agenceId = $this->getAgenceScopeId($request);
        return $this->excelService->exportParcGlobal($agenceId);
    }

    /**
     * GET /api/v1/rapports/excel/bons-commande
     */
    public function excelBonsCommande(Request $request)
    {
        $this->authorize('viewAny', BonCommande::class);

        $request->validate([
            'annee'     => ['nullable', 'integer'],
            'agence_id' => ['nullable', 'exists:agences,id'],
        ]);

        $agenceId = (int) ($request->input('agence_id') ?? $this->getAgenceScopeId($request) ?? 0) ?: null;
        $annee    = (int) $request->input('annee', date('Y'));

        return $this->excelService->exportBonsCommande($annee, $agenceId);
    }
}