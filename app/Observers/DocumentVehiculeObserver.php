<?php

namespace App\Observers;

use App\Models\Alerte;
use App\Models\DocumentVehicule;

/**
 * Observer sur DocumentVehicule.
 *
 * Résout automatiquement les alertes "document_manquant" dès qu'un
 * document obligatoire est créé ou renouvelé pour un véhicule.
 * Cela évite d'attendre le prochain scan planifié.
 */
class DocumentVehiculeObserver
{
    /** Types de documents qui déclenchent des alertes "document_manquant" */
    private const TYPES_OBLIGATOIRES = ['carte_grise', 'assurance', 'visite_technique'];

    public function created(DocumentVehicule $document): void
    {
        $this->resoudreAlerte($document);
    }

    public function updated(DocumentVehicule $document): void
    {
        // Résoudre si le document passe à un statut actif
        if ($document->isDirty('statut') || $document->isDirty('est_actif')) {
            $this->resoudreAlerte($document);
        }
    }

    private function resoudreAlerte(DocumentVehicule $document): void
    {
        // Seuls les types obligatoires génèrent des alertes document_manquant
        if (! in_array($document->type_document, self::TYPES_OBLIGATOIRES)) {
            return;
        }

        if (! $document->vehicule_id) {
            return;
        }

        $typeLabel = ucfirst(str_replace('_', ' ', $document->type_document));

        // Résoudre toutes les alertes actives correspondantes pour ce véhicule
        $nb = Alerte::where('vehicule_id', $document->vehicule_id)
            ->where('type_alerte', 'document_manquant')
            ->where('statut',      'active')
            ->where('message',     'like', "%{$typeLabel}%")
            ->update([
                'statut'     => 'traitee',
                'updated_at' => now(),
            ]);

        if ($nb > 0) {
            \Illuminate\Support\Facades\Log::info(
                "[DocumentObserver] {$nb} alerte(s) document_manquant résolue(s) — " .
                "Véhicule #{$document->vehicule_id} / {$typeLabel}"
            );
        }
    }
}