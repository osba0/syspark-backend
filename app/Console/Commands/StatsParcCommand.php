<?php

namespace App\Console\Commands;

use App\Services\StatistiqueService;
use Illuminate\Console\Command;

class StatsParcCommand extends Command
{
    protected $signature   = 'parc:stats {--annee= : Année (défaut: année courante)} {--agence= : ID agence}';
    protected $description = 'Afficher les statistiques globales du parc automobile';

    public function handle(StatistiqueService $statsService): int
    {
        $annee    = (int)($this->option('annee') ?? date('Y'));
        $agenceId = $this->option('agence') ? (int)$this->option('agence') : null;

        $this->info("📊 Statistiques du Parc Automobile — {$annee}");
        $this->newLine();

        $kpi = $statsService->kpiGlobaux($agenceId);

        // KPIs véhicules
        $this->line('<fg=cyan>🚗 Véhicules</>');
        $this->table(
            ['Statut', 'Nombre'],
            [
                ['Total',          $kpi['vehicules']['total']],
                ['Actifs',         $kpi['vehicules']['actifs']],
                ['En panne',       $kpi['vehicules']['en_panne']],
                ['En maintenance', $kpi['vehicules']['en_maintenance']],
                ['Hors service',   $kpi['vehicules']['hors_service']],
            ]
        );

        // Finances du mois
        $this->line('<fg=cyan>💰 Finances — ' . now()->format('F Y') . '</>');
        $this->table(
            ['Poste', 'Montant (FCFA)'],
            [
                ['Maintenance mois',  number_format($kpi['maintenance']['depenses_mois'], 0, ',', ' ')],
                ['Carburant mois',    number_format($kpi['carburant']['depenses_mois'], 0, ',', ' ')],
                ['Dotation carburant',number_format($kpi['carburant']['dotation_mois'], 0, ',', ' ')],
                ['Taux conso carbu.', ($kpi['carburant']['taux_conso'] ?? 'N/A') . '%'],
            ]
        );

        // Alertes
        $this->line('<fg=cyan>🔔 Alertes actives</>');
        $this->table(
            ['Niveau', 'Nombre'],
            [
                ['🔴 Danger',  $kpi['alertes']['danger']],
                ['🟡 Warning', $kpi['alertes']['warning']],
                ['🔵 Info',    $kpi['alertes']['info']],
                ['Total',      $kpi['alertes']['total']],
            ]
        );

        // Signalements
        $this->line('<fg=cyan>⚠️  Signalements ouverts</>');
        $this->table(
            ['Type', 'Nombre'],
            [
                ['Total ouverts', $kpi['signalements']['total_ouverts']],
                ['Critiques',     $kpi['signalements']['critiques']],
                ['Hauts',         $kpi['signalements']['hauts']],
            ]
        );

        return self::SUCCESS;
    }
}
