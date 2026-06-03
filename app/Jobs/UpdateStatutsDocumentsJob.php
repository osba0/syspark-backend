<?php

namespace App\Jobs;

use App\Models\DocumentVehicule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Met à jour automatiquement les statuts des documents selon leur date d'expiration.
 * Dispatché chaque nuit à 01h00 par le Scheduler.
 */
class UpdateStatutsDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function handle(): void
    {
        $updated = 0;

        // Documents expirés → statut 'expire'
        $nb = DocumentVehicule::actifs()
            ->whereNotNull('date_expiration')
            ->where('date_expiration', '<', now())
            ->where('statut', '!=', 'expire')
            ->update(['statut' => 'expire']);
        $updated += $nb;

        // Documents expirant dans 30 jours → 'a_renouveler'
        $nb = DocumentVehicule::actifs()
            ->whereNotNull('date_expiration')
            ->whereBetween('date_expiration', [now(), now()->addDays(30)])
            ->where('statut', '!=', 'a_renouveler')
            ->update(['statut' => 'a_renouveler']);
        $updated += $nb;

        // Documents valides (> 30 jours) → 'valide'
        $nb = DocumentVehicule::actifs()
            ->whereNotNull('date_expiration')
            ->where('date_expiration', '>', now()->addDays(30))
            ->where('statut', '!=', 'valide')
            ->update(['statut' => 'valide']);
        $updated += $nb;

        Log::info("[UpdateStatutsDocumentsJob] {$updated} documents mis à jour.");
    }
}
