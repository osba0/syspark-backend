<?php

namespace App\Observers;

use App\Services\StatistiqueService;

/**
 * Observer générique qui invalide le cache des statistiques
 * après chaque création/modification/suppression sur les models critiques.
 *
 * Enregistrer dans AppServiceProvider :
 *   Maintenance::observe(InvaliderCacheObserver::class);
 *   Carburant::observe(InvaliderCacheObserver::class);
 */
class InvaliderCacheObserver
{
    public function __construct(private StatistiqueService $statsService) {}

    public function created($model): void   { $this->invalider($model); }
    public function updated($model): void   { $this->invalider($model); }
    public function deleted($model): void   { $this->invalider($model); }

    private function invalider($model): void
    {
        $agenceId = $model->agence_id ?? null;
        $this->statsService->invaliderCache($agenceId);
    }
}
