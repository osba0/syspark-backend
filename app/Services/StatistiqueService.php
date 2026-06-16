<?php

namespace App\Services;

use App\Models\Alerte;
use App\Models\Carburant;
use App\Models\DotationCarburant;
use App\Models\Maintenance;
use App\Models\Pneumatique;
use App\Models\Signalement;
use App\Models\Vehicule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatistiqueService
{
    // Durée du cache des statistiques (1 heure en secondes)
    private const CACHE_TTL = 3600;

    // ============================================================
    // KPIs Dashboard — avec cache Redis/file
    // ============================================================

    /**
     * KPIs globaux du parc — retournés en < 50ms grâce au cache.
     */
    public function kpiGlobaux(?int $agenceId = null): array
    {
        $cacheKey = "kpi_globaux_" . ($agenceId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($agenceId) {
            return $this->calculerKpiGlobaux($agenceId);
        });
    }

    /**
     * Invalide le cache des KPIs — appelé après chaque mutation importante.
     */
    public function invaliderCache(?int $agenceId = null): void
    {
        $key = "kpi_globaux_" . ($agenceId ?? 'all');
        Cache::forget($key);
        Cache::forget('kpi_globaux_all');
    }

    private function calculerKpiGlobaux(?int $agenceId): array
    {
        $mois  = now()->month;
        $annee = now()->year;

        // --- Véhicules ---
        $vBase = Vehicule::whereNull('deleted_at')->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId));

        $statuts = (clone $vBase)->select('statut', DB::raw('COUNT(*) as n'))->groupBy('statut')->get()->keyBy('statut');

        // --- Maintenances du mois ---
        $mBase = Maintenance::where('statut', 'termine')
            ->whereYear('date_travaux', $annee)->whereMonth('date_travaux', $mois)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId));

        $maintenance = (clone $mBase)->selectRaw('SUM(montant_ttc) as total, COUNT(*) as nb')->first();

        // --- Carburant du mois ---
        $cBase = Carburant::whereYear('date', $annee)->whereMonth('date', $mois)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId));

        $carburant = (clone $cBase)->selectRaw('SUM(montant) as total, SUM(litres) as litres')->first();

        // --- Pneumatiques du mois (module dédié, hors Maintenance) ---
        $pBase = Pneumatique::whereYear('date', $annee)->whereMonth('date', $mois)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId));

        $pneus = (clone $pBase)->selectRaw('SUM(montant_total) as total, COUNT(*) as nb')->first();

        // --- Dotation carburant du mois ---
        $dotation = DotationCarburant::where('mois', $mois)->where('annee', $annee)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->sum('montant_dote');

        // --- Signalements ouverts ---
        $signalBase = Signalement::whereIn('statut', ['nouveau', 'en_cours'])
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId));

        $signalements = (clone $signalBase)->select('gravite', DB::raw('COUNT(*) as n'))->groupBy('gravite')->get()->keyBy('gravite');

        // --- Alertes actives ---
        $alertes = Alerte::actives()
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->select('niveau', DB::raw('COUNT(*) as n'))
            ->groupBy('niveau')->get()->keyBy('niveau');

        // --- Chauffeurs ---
        $chauffeurBase = \App\Models\Chauffeur::actifs()
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId));

        $permisExpirant = (clone $chauffeurBase)
            ->permisExpirantDans(30)
            ->count();

        // --- Véhicules — alertes documentaires ---
        $vtProchaine    = (clone $vBase)->whereNotNull('date_prochaine_visite_tech')
            ->where('date_prochaine_visite_tech', '<=', now()->addDays(30))->count();

        $entretiensEchus = (clone $vBase)->whereNotNull('prochain_entretien_km')
            ->whereRaw('kilometrage_actuel >= prochain_entretien_km')->count();

        return [
            'periode'     => ['mois' => $mois, 'annee' => $annee],
            'vehicules'   => [
                'total'               => (clone $vBase)->count(),
                'actifs'              => (int)($statuts['actif']?->n ?? 0),
                'en_panne'            => (int)($statuts['en_panne']?->n ?? 0),
                'en_maintenance'      => (int)($statuts['en_maintenance']?->n ?? 0),
                'hors_service'        => (int)($statuts['hors_service']?->n ?? 0),
                'vt_prochaine_30j'    => $vtProchaine,
                'assurance_prochaine_30j' => (clone $vBase)->whereNotNull('date_expiration_assurance')
                    ->where('date_expiration_assurance', '<=', now()->addDays(30))->count(),
                'entretien_echu'      => $entretiensEchus,
            ],
            'maintenance' => [
                'depenses_mois'          => round((float)($maintenance->total ?? 0), 2),
                'nb_interventions'       => (int)($maintenance->nb ?? 0),
                'en_attente_approbation' => Maintenance::where('necessite_approbation', true)->whereNull('approuve_par')
                    ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))->count(),
            ],
            'carburant'   => [
                'depenses_mois' => round((float)($carburant->total ?? 0), 2),
                'litres_mois'   => round((float)($carburant->litres ?? 0), 2),
                'dotation_mois' => round((float)$dotation, 2),
                'taux_conso'    => $dotation > 0
                    ? round(((float)($carburant->total ?? 0) / (float)$dotation) * 100, 1)
                    : null,
            ],
            'pneus'       => [
                'depenses_mois'    => round((float)($pneus->total ?? 0), 2),
                'nb_operations'    => (int)($pneus->nb ?? 0),
            ],
            'signalements' => [
                'total_ouverts' => (clone $signalBase)->count(),
                'critiques'     => (int)($signalements['critique']?->n ?? 0),
                'hauts'         => (int)($signalements['haute']?->n ?? 0),
            ],
            'alertes'     => [
                'danger'  => (int)($alertes['danger']?->n ?? 0),
                'warning' => (int)($alertes['warning']?->n ?? 0),
                'info'    => (int)($alertes['info']?->n ?? 0),
                'total'   => (int)$alertes->sum('n'),
            ],
            // Clé manquante — ajoutée ici
            'chauffeurs'  => [
                'total'               => (clone $chauffeurBase)->count(),
                'permis_expirant_30j' => $permisExpirant,
            ],
        ];
    }

    // ============================================================
    // Statistiques mensuelles pour graphiques Recharts
    // ============================================================

    /**
     * Évolution sur 12 mois — pour les graphiques en barres/courbes.
     */
    public function evolutionMensuelle(int $annee, ?int $agenceId = null): array
    {
        $cacheKey = "evolution_{$annee}_" . ($agenceId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL * 6, function () use ($annee, $agenceId) {
            return $this->calculerEvolutionMensuelle($annee, $agenceId);
        });
    }

    private function calculerEvolutionMensuelle(int $annee, ?int $agenceId): array
    {
        $moisLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

        // Maintenance par mois
        $maintenance = DB::table('maintenances')
            ->whereYear('date_travaux', $annee)->where('statut', 'termine')
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->selectRaw('MONTH(date_travaux) as mois,
                SUM(montant_ttc) as total,
                SUM(CASE WHEN type_operation="entretien" THEN montant_ttc ELSE 0 END) as entretien,
                SUM(CASE WHEN type_operation="reparation" THEN montant_ttc ELSE 0 END) as reparation,
                SUM(CASE WHEN type_operation="pneu" THEN montant_ttc ELSE 0 END) as pneu,
                COUNT(*) as nb')
            ->groupBy('mois')->get()->keyBy('mois');

        // Carburant par mois
        $carburant = DB::table('carburants')
            ->whereYear('date', $annee)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->selectRaw('MONTH(date) as mois, SUM(montant) as total, SUM(litres) as litres')
            ->groupBy('mois')->get()->keyBy('mois');

        // Dotations par mois
        $dotations = DB::table('dotations_carburant')
            ->where('annee', $annee)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->selectRaw('mois, SUM(montant_dote) as dote')
            ->groupBy('mois')->get()->keyBy('mois');

        // Signalements par mois
        $signalements = DB::table('signalements')
            ->whereYear('date_signalement', $annee)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->selectRaw('MONTH(date_signalement) as mois, COUNT(*) as nb')
            ->groupBy('mois')->get()->keyBy('mois');

        // Pneumatiques par mois (module dédié — source de vérité, distinct
        // du champ legacy "pneu" sur maintenances pour éviter le double comptage)
        $pneumatiques = DB::table('pneumatiques')
            ->whereYear('date', $annee)
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->selectRaw('MONTH(date) as mois, SUM(montant_total) as total, COUNT(*) as nb')
            ->groupBy('mois')->get()->keyBy('mois');

        $series = collect(range(1, 12))->map(fn ($m) => [
            'mois'             => $moisLabels[$m - 1],
            'mois_num'         => $m,
            'maintenance'      => round((float)($maintenance[$m]?->total ?? 0), 2),
            'entretien'        => round((float)($maintenance[$m]?->entretien ?? 0), 2),
            'reparation'       => round((float)($maintenance[$m]?->reparation ?? 0), 2),
            'pneus'            => round((float)($maintenance[$m]?->pneu ?? 0), 2),
            'pneumatiques_module' => round((float)($pneumatiques[$m]?->total ?? 0), 2),
            'nb_operations_pneus' => (int)($pneumatiques[$m]?->nb ?? 0),
            'nb_interventions' => (int)($maintenance[$m]?->nb ?? 0),
            'carburant'        => round((float)($carburant[$m]?->total ?? 0), 2),
            'litres'           => round((float)($carburant[$m]?->litres ?? 0), 2),
            'dotation'         => round((float)($dotations[$m]?->dote ?? 0), 2),
            'total_depenses'   => round(
                (float)($maintenance[$m]?->total ?? 0)
                + (float)($carburant[$m]?->total ?? 0)
                + (float)($pneumatiques[$m]?->total ?? 0),
                2
            ),
            'nb_signalements'  => (int)($signalements[$m]?->nb ?? 0),
        ]);

        return [
            'annee'   => $annee,
            'series'  => $series,
            'totaux'  => [
                'maintenance'  => round($series->sum('maintenance'), 2),
                'carburant'    => round($series->sum('carburant'), 2),
                'pneus'        => round($series->sum('pneus'), 2),
                'pneumatiques_module' => round($series->sum('pneumatiques_module'), 2),
                'total'        => round($series->sum('total_depenses'), 2),
                'nb_interventions' => $series->sum('nb_interventions'),
            ],
        ];
    }

    // ============================================================
    // Classements (Top véhicules coûteux, top axes, etc.)
    // ============================================================

    public function topVehiculesCouteux(int $annee, int $limite = 10, ?int $agenceId = null): array
    {
        return Cache::remember(
            "top_vehicules_{$annee}_{$limite}_" . ($agenceId ?? 'all'),
            self::CACHE_TTL * 6,
            function () use ($annee, $limite, $agenceId) {
                return DB::table('maintenances as m')
                    ->join('vehicules as v', 'm.vehicule_id', '=', 'v.id')
                    ->whereYear('m.date_travaux', $annee)
                    ->where('m.statut', 'termine')
                    ->when($agenceId, fn ($q) => $q->where('m.agence_id', $agenceId))
                    ->select(
                        'v.id', 'v.immatriculation', 'v.marque', 'v.modele', 'v.type_vehicule',
                        DB::raw('SUM(m.montant_ttc) as total_maintenance'),
                        DB::raw('COUNT(m.id) as nb_interventions')
                    )
                    ->groupBy('v.id', 'v.immatriculation', 'v.marque', 'v.modele', 'v.type_vehicule')
                    ->orderByDesc('total_maintenance')
                    ->limit($limite)
                    ->get()
                    ->map(fn ($v) => [
                        'vehicule_id'     => $v->id,
                        'immatriculation' => $v->immatriculation,
                        'marque_modele'   => $v->marque . ' ' . $v->modele,
                        'type'            => $v->type_vehicule,
                        'total'           => round((float)$v->total_maintenance, 2),
                        'nb'              => (int)$v->nb_interventions,
                    ])
                    ->toArray();
            }
        );
    }

    // ============================================================
    // Génération des stats mensuelles (appelé par le scheduler)
    // ============================================================

    /**
     * Précalcule et met en cache les statistiques mensuelles.
     * Appelé le 1er de chaque mois par le scheduler.
     */
    public function precalculerStatsMensuelles(): void
    {
        Log::info('[StatistiqueService] Précalcul des stats mensuelles');

        // Invalider tous les caches
        Cache::flush();

        // Précalculer pour l'année en cours et N-1
        foreach ([date('Y'), date('Y') - 1] as $annee) {
            $this->evolutionMensuelle($annee);
            $this->topVehiculesCouteux($annee);
        }

        $this->kpiGlobaux();

        Log::info('[StatistiqueService] Précalcul terminé');
    }
}