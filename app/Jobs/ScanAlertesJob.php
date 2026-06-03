<?php

namespace App\Jobs;

use App\Services\AlerteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job principal de scan des alertes.
 * Dispatché chaque matin à 06h00 par le Scheduler.
 * Peut aussi être déclenché manuellement :
 *   php artisan parc:scan-alertes
 */
class ScanAlertesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Nombre de tentatives max en cas d'échec
    public int $tries = 3;

    // Timeout en secondes
    public int $timeout = 300;

    public function handle(AlerteService $alerteService): void
    {
        Log::channel('daily')->info('[ScanAlertesJob] Démarrage');

        $stats = $alerteService->scannerToutesLesAlertes();

        Log::channel('daily')->info('[ScanAlertesJob] Terminé', $stats);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('daily')->error('[ScanAlertesJob] Échec', [
            'message' => $exception->getMessage(),
        ]);
    }
}
