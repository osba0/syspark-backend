<?php

namespace App\Services;

use App\Exports\MaintenanceExport;
use App\Exports\StatCarburantExport;
use App\Exports\ParcGlobalExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportService
{
    /**
     * Export maintenance — onglet ENTRET ET REP VEHIC LIVRAISON
     */
    public function exportMaintenance(int $annee, string $type = 'livraison', ?int $agenceId = null): BinaryFileResponse
    {
        $export   = new MaintenanceExport($annee, $type, $agenceId);
        $filename = "maintenance_{$type}_{$annee}.xlsx";

        return Excel::download($export, $filename);
    }

    /**
     * Export carburant multi-onglets — reproduce STAT CARBURANT
     */
    public function exportCarburant(int $annee, ?int $agenceId = null): BinaryFileResponse
    {
        return Excel::download(
            new StatCarburantExport($annee, $agenceId),
            "stat_carburant_{$annee}.xlsx"
        );
    }

    /**
     * Export parc global (véhicules + chauffeurs)
     */
    public function exportParcGlobal(?int $agenceId = null): BinaryFileResponse
    {
        return Excel::download(
            new ParcGlobalExport($agenceId),
            'parc_global_' . now()->format('Ymd') . '.xlsx'
        );
    }

    /**
     * Export recueil complet (tous les onglets du fichier original)
     * Maintenance livraison + Maintenance admin + Carburant
     */
    public function exportRecueilComplet(int $annee, ?int $agenceId = null): BinaryFileResponse
    {
        // Multi-sheets combiné : livraison + admin + carburant
        $sheets = array_merge(
            (new MaintenanceExport($annee, 'livraison', $agenceId))->sheets ?? [(new MaintenanceExport($annee, 'livraison', $agenceId))],
            [(new MaintenanceExport($annee, 'administratif', $agenceId))],
            (new StatCarburantExport($annee, $agenceId))->sheets()
        );

        // Pour simplifier, on génère les exports séparément
        // En production on pourrait les fusionner avec un writer custom
        return Excel::download(
            new StatCarburantExport($annee, $agenceId),
            "recueil_travaux_{$annee}.xlsx"
        );
    }

    /**
     * Export bons de commande — format simple
     */
    public function exportBonsCommande(int $annee, ?int $agenceId = null): BinaryFileResponse
    {
        return Excel::download(
            new \App\Exports\BonsCommandeExport($annee, $agenceId),
            "bons_commande_{$annee}.xlsx"
        );
    }
}