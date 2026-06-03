<?php

namespace App\Console\Commands;

use App\Jobs\ScanAlertesJob;
use App\Services\AlerteService;
use Illuminate\Console\Command;

class ScanAlertesCommand extends Command
{
    protected $signature   = 'parc:scan-alertes {--async : Lancer en arrière-plan via queue}';
    protected $description = 'Scanner et générer toutes les alertes automatiques du parc automobile';

    public function handle(AlerteService $alerteService): int
    {
        if ($this->option('async')) {
            ScanAlertesJob::dispatch();
            $this->info('✅ Job de scan des alertes dispatché en queue.');
            return self::SUCCESS;
        }

        $this->info('🔍 Scan des alertes du parc en cours...');
        $this->newLine();

        $bar = $this->output->createProgressBar(11);
        $bar->start();

        $stats = [];

        $this->info(' Visites techniques...');
        $stats['vt']                   = $alerteService->alertesVisiteTechnique();          $bar->advance();
        $stats['assurance']            = $alerteService->alertesAssurance();                  $bar->advance();
        $stats['permis']               = $alerteService->alertesPermisChaufeur();             $bar->advance();
        $stats['entretien_km']         = $alerteService->alertesEntretienKilometrique();      $bar->advance();
        $stats['entretien_periodique'] = $alerteService->alertesEntretienPeriodique();        $bar->advance();
        $stats['carburant']            = $alerteService->alertesDepassementCarburant();       $bar->advance();
        $stats['signalement']          = $alerteService->alertesSignalementsNonTraites();     $bar->advance();
        $stats['checklist']            = $alerteService->alertesChecklistsManquantes();       $bar->advance();
        $stats['immobilise']           = $alerteService->alertesVehiculesImmobilises();       $bar->advance();
        $stats['bc']                   = $alerteService->alertesBonsCommandeEnAttente();      $bar->advance();
        $stats['doc_manquant']         = $alerteService->alertesDocumentsManquants();         $bar->advance();

        $bar->finish();
        $this->newLine(2);

        $total = array_sum($stats);

        $this->table(
            ['Type d\'alerte', 'Nouvelles alertes'],
            [
                ['Visites techniques',       $stats['vt']],
                ['Assurances',               $stats['assurance']],
                ['Permis chauffeurs',        $stats['permis']],
                ['Entretien kilométrique',   $stats['entretien_km']],
                ['Entretien périodique',     $stats['entretien_periodique']],
                ['Carburant dépassement',    $stats['carburant']],
                ['Signalements non traités', $stats['signalement']],
                ['Checklists manquantes',    $stats['checklist']],
                ['Véhicules immobilisés',    $stats['immobilise']],
                ['BC en attente',            $stats['bc']],
                ['Documents manquants',      $stats['doc_manquant']],
                ['─────────────────────────', '───────'],
                ['TOTAL', $total],
            ]
        );

        $this->newLine();
        $color = $total > 0 ? 'yellow' : 'green';
        $this->line("<fg={$color}>" . ($total > 0 ? "⚠️  {$total} nouvelle(s) alerte(s) générée(s)." : "✅  Aucune nouvelle alerte.") . "</>");

        return self::SUCCESS;
    }
}
