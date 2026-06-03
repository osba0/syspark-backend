<?php

namespace App\Console\Commands;

use App\Jobs\UpdateStatutsDocumentsJob;
use App\Models\DocumentVehicule;
use Illuminate\Console\Command;

class UpdateStatutsDocumentsCommand extends Command
{
    protected $signature   = 'parc:update-documents';
    protected $description = 'Mettre à jour les statuts des documents véhicules selon leurs dates d\'expiration';

    public function handle(): int
    {
        $this->info('📄 Mise à jour des statuts de documents...');

        // Expiré
        $nb1 = DocumentVehicule::actifs()
            ->whereNotNull('date_expiration')
            ->where('date_expiration', '<', now())
            ->where('statut', '!=', 'expire')
            ->update(['statut' => 'expire']);

        // À renouveler
        $nb2 = DocumentVehicule::actifs()
            ->whereNotNull('date_expiration')
            ->whereBetween('date_expiration', [now(), now()->addDays(30)])
            ->where('statut', '!=', 'a_renouveler')
            ->update(['statut' => 'a_renouveler']);

        // Valide
        $nb3 = DocumentVehicule::actifs()
            ->whereNotNull('date_expiration')
            ->where('date_expiration', '>', now()->addDays(30))
            ->where('statut', '!=', 'valide')
            ->update(['statut' => 'valide']);

        $this->table(
            ['Statut', 'Documents mis à jour'],
            [
                ['Expirés',       $nb1],
                ['À renouveler',  $nb2],
                ['Valides',       $nb3],
            ]
        );

        $this->info('✅ ' . ($nb1 + $nb2 + $nb3) . ' document(s) mis à jour.');

        return self::SUCCESS;
    }
}
