<?php

namespace App\Jobs;

use App\Services\StatistiqueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job de précalcul des statistiques mensuelles.
 * Dispatché le 1er de chaque mois à 05h30 par le Scheduler.
 */
class PrecalculerStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 600;

    public function handle(StatistiqueService $statsService): void
    {
        Log::info('[PrecalculerStatsJob] Démarrage précalcul mensuel');
        $statsService->precalculerStatsMensuelles();
        Log::info('[PrecalculerStatsJob] Précalcul terminé');
    }
}
