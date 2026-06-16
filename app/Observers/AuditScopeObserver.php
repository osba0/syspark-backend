<?php

namespace App\Observers;

use Spatie\Activitylog\Models\Activity;

/**
 * Remplit automatiquement les colonnes de scoping dénormalisées sur
 * activity_log (agence_id, vehicule_id, chauffeur_id, module) à partir
 * du modèle "subject" qui a déclenché le log.
 *
 * Objectif : permettre au AuditLogController de filtrer par agence/véhicule
 * sans JOIN sur les tables métier — essentiel pour rester performant quand
 * activity_log atteint plusieurs centaines de milliers de lignes.
 */
class AuditScopeObserver
{
    /**
     * Mapping classe de modèle → nom de module affiché dans les filtres UI.
     * Toute classe absente de cette liste reçoit module = null (catégorie "Autre").
     */
    private const MODULES = [
        \App\Models\Vehicule::class        => 'vehicule',
        \App\Models\Chauffeur::class       => 'chauffeur',
        \App\Models\Affectation::class     => 'affectation',
        \App\Models\Checklist::class       => 'checklist',
        \App\Models\Signalement::class     => 'signalement',
        \App\Models\Maintenance::class     => 'maintenance',
        \App\Models\Carburant::class       => 'carburant',
        \App\Models\Pneumatique::class     => 'pneumatique',
        \App\Models\DocumentVehicule::class=> 'document',
        \App\Models\BonCommande::class     => 'bonCommande',
        \App\Models\Fournisseur::class     => 'fournisseur',
        \App\Models\Agence::class          => 'agence',
        \App\Models\AxeLivraison::class    => 'axeLivraison',
        \App\Models\User::class            => 'utilisateur',
    ];

    public function creating(Activity $activity): void
    {
        $subject = $activity->subject;

        if (!$subject) {
            return;
        }

        // Module — pour le filtre par catégorie
        $activity->module = self::MODULES[get_class($subject)] ?? null;

        // agence_id — directement sur le modèle, ou via une relation connue
        $activity->agence_id = $this->resoudreAgenceId($subject);

        // vehicule_id — directement sur le modèle, ou si le sujet EST un véhicule
        $activity->vehicule_id = match (true) {
            $subject instanceof \App\Models\Vehicule => $subject->id,
            isset($subject->vehicule_id)              => $subject->vehicule_id,
            default                                    => null,
        };

        // chauffeur_id — idem
        $activity->chauffeur_id = match (true) {
            $subject instanceof \App\Models\Chauffeur => $subject->id,
            isset($subject->chauffeur_id)             => $subject->chauffeur_id,
            default                                     => null,
        };
    }

    /**
     * Résout agence_id pour un sujet donné, avec repli sur la relation
     * véhicule/chauffeur quand le modèle n'a pas directement agence_id
     * (ex: DocumentVehicule n'a pas agence_id mais son véhicule oui).
     */
    private function resoudreAgenceId(object $subject): ?int
    {
        if (isset($subject->agence_id)) {
            return $subject->agence_id;
        }

        // Repli : passer par le véhicule lié
        if (isset($subject->vehicule_id) && $subject->vehicule_id) {
            return \App\Models\Vehicule::where('id', $subject->vehicule_id)
                ->value('agence_id');
        }

        // Repli : passer par le chauffeur lié
        if (isset($subject->chauffeur_id) && $subject->chauffeur_id) {
            return \App\Models\Chauffeur::where('id', $subject->chauffeur_id)
                ->value('agence_id');
        }

        return null;
    }
}
