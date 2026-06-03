<?php

namespace App\Services;

use App\Models\BonCommande;
use App\Models\Checklist;
use App\Models\Maintenance;
use App\Models\Vehicule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PdfService
{
    // ============================================================
    // Options DomPDF communes
    // ============================================================

    private function optionsPortrait(): array
    {
        return [
            'defaultFont'       => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'   => false,
            'dpi'               => 150,
            'defaultPaperSize'  => 'A4',
            'defaultPaperOrientation' => 'portrait',
        ];
    }

    private function optionsPaysage(): array
    {
        return array_merge($this->optionsPortrait(), [
            'defaultPaperOrientation' => 'landscape',
        ]);
    }

    // ============================================================
    // 1. Fiche de Signalement
    // ============================================================

    /**
     * Génère la FICHE DE SIGNALEMENT (PANNE, DÉFAUT, ANOMALIES)
     * Reproduit fidèlement le formulaire DOCX existant
     */
    public function ficheSignalement(\App\Models\Signalement $signalement): \Illuminate\Http\Response
    {
        $signalement->load([
            'vehicule.agence',
            'chauffeur',
            'createdBy',
        ]);

        $pdf = Pdf::loadView('pdf.fiche-signalement', [
            'signalement' => $signalement,
        ])->setOptions($this->optionsPortrait());

        $nom = "signalement_{$signalement->vehicule?->immatriculation}_{$signalement->date_signalement->format('Ymd')}.pdf";

        return $pdf->download($nom);
    }

    // ============================================================
    // 2. Checklist (3 types)
    // ============================================================

    /**
     * Génère la checklist en PDF (hebdomadaire véhicule, scooter ou VT)
     */
    public function checklist(Checklist $checklist): \Illuminate\Http\Response
    {
        $checklist->load(['vehicule.agence', 'chauffeur', 'validePar']);

        $view = match($checklist->type_checklist) {
            'hebdomadaire_vehicule' => 'pdf.checklist-vehicule',
            'hebdomadaire_scooter'  => 'pdf.checklist-scooter',
            'visite_technique'      => 'pdf.checklist-vt',
            'passation', 'attribution' => 'pdf.checklist-passation',
            default                 => 'pdf.checklist-vehicule',
        };

        $pdf = Pdf::loadView($view, [
            'checklist' => $checklist,
        ])->setOptions($this->optionsPortrait());

        $nom = "checklist_{$checklist->type_checklist}_{$checklist->vehicule?->immatriculation}_{$checklist->date->format('Ymd')}.pdf";

        return $pdf->download($nom);
    }

    // ============================================================
    // 3. Fiche attribution / réception véhicule
    // ============================================================

    public function ficheAttribution(\App\Models\Affectation $affectation): \Illuminate\Http\Response
    {
        $affectation->load(['vehicule.agence', 'chauffeur', 'axeLivraison', 'validePar']);

        $pdf = Pdf::loadView('pdf.fiche-attribution', [
            'affectation' => $affectation,
        ])->setOptions($this->optionsPortrait());

        $nom = "attribution_{$affectation->vehicule?->immatriculation}_{$affectation->date_debut->format('Ymd')}.pdf";

        return $pdf->download($nom);
    }

    // ============================================================
    // 4. Bon de Commande
    // ============================================================

    public function bonCommande(BonCommande $bc): \Illuminate\Http\Response
    {
        $bc->load(['fournisseur', 'vehicule', 'agence', 'creePar', 'approuvePar']);

        $pdf = Pdf::loadView('pdf.bon-commande', [
            'bc' => $bc,
        ])->setOptions($this->optionsPortrait());

        $nom = "bon_commande_{$bc->numero_bc}.pdf";

        return $pdf->download($nom);
    }

    // ============================================================
    // 5. Rapport maintenance (reproduce ENTRET ET REP VEHIC)
    // ============================================================

    public function rapportMaintenance(array $data): \Illuminate\Http\Response
    {
        $pdf = Pdf::loadView('pdf.rapport-maintenance', $data)
            ->setOptions($this->optionsPaysage());

        $nom = "rapport_maintenance_{$data['annee']}.pdf";

        return $pdf->download($nom);
    }

    // ============================================================
    // 6. Rapport carburant (reproduce STAT CARBURANT)
    // ============================================================

    public function rapportCarburant(array $data): \Illuminate\Http\Response
    {
        $pdf = Pdf::loadView('pdf.rapport-carburant', $data)
            ->setOptions($this->optionsPaysage());

        $nom = "rapport_carburant_{$data['annee']}.pdf";

        return $pdf->download($nom);
    }

    // ============================================================
    // 7. Fiche véhicule complète (historique)
    // ============================================================

    public function ficheVehicule(Vehicule $vehicule, int $annee): \Illuminate\Http\Response
    {
        $vehicule->load([
            'agence',
            'affectationActive.chauffeur',
            'documentsVehicule' => fn ($q) => $q->actifs(),
            'maintenances' => fn ($q) => $q->whereYear('date_travaux', $annee)
                ->where('statut', 'termine')->orderBy('date_travaux'),
            'maintenances.fournisseur',
            'carburants' => fn ($q) => $q->whereYear('date', $annee)->orderBy('date'),
        ]);

        $pdf = Pdf::loadView('pdf.fiche-vehicule', [
            'vehicule' => $vehicule,
            'annee'    => $annee,
        ])->setOptions($this->optionsPaysage());

        $nom = "vehicule_{$vehicule->immatriculation}_{$annee}.pdf";

        return $pdf->download($nom);
    }

    // ============================================================
    // Helper : stream (afficher dans le navigateur)
    // ============================================================

    public function streamBonCommande(BonCommande $bc): \Illuminate\Http\Response
    {
        $bc->load(['fournisseur', 'vehicule', 'agence', 'creePar', 'approuvePar']);

        return Pdf::loadView('pdf.bon-commande', ['bc' => $bc])
            ->setOptions($this->optionsPortrait())
            ->stream("bon_commande_{$bc->numero_bc}.pdf");
    }
}
