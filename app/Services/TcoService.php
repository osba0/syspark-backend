<?php

namespace App\Services;

use App\Models\Vehicule;
use Illuminate\Support\Facades\DB;

class TcoService
{
    /**
     * Calcule le TCO (Total Cost of Ownership) d'un véhicule pour une année donnée.
     * Inclut : maintenance, réparations, carburant, pneumatiques.
     */
    public function calculer(Vehicule $vehicule, ?int $annee = null): array
    {
        $annee = $annee ?? date('Y');

        // --- Maintenance & Réparations ---
        $maintenance = DB::table('maintenances')
            ->where('vehicule_id', $vehicule->id)
            ->whereYear('date_travaux', $annee)
            ->where('statut', 'termine')
            ->selectRaw("
                SUM(CASE WHEN type_operation = 'entretien'        THEN montant_ttc ELSE 0 END) as entretien,
                SUM(CASE WHEN type_operation = 'reparation'       THEN montant_ttc ELSE 0 END) as reparation,
                SUM(CASE WHEN type_operation = 'carrosserie'      THEN montant_ttc ELSE 0 END) as carrosserie,
                SUM(CASE WHEN type_operation = 'equipement'       THEN montant_ttc ELSE 0 END) as equipement,
                SUM(CASE WHEN type_operation = 'visite_technique' THEN montant_ttc ELSE 0 END) as visite_technique,
                SUM(CASE WHEN type_operation = 'contravention'    THEN montant_ttc ELSE 0 END) as contravention,
                SUM(CASE WHEN type_operation = 'pneu'             THEN montant_ttc ELSE 0 END) as pneu_maintenance,
                SUM(montant_ttc) as total_maintenance,
                COUNT(*) as nb_interventions
            ")
            ->first();

        // --- Pneumatiques ---
        $pneus = DB::table('pneumatiques')
            ->where('vehicule_id', $vehicule->id)
            ->whereYear('date', $annee)
            ->selectRaw('SUM(montant_total) as total_pneus, COUNT(*) as nb_operations')
            ->first();

        // --- Carburant ---
        $carburant = DB::table('carburants')
            ->where('vehicule_id', $vehicule->id)
            ->whereYear('date', $annee)
            ->selectRaw('
                SUM(montant) as total_carburant,
                SUM(litres)  as total_litres,
                COUNT(*)     as nb_pleins
            ')
            ->first();

        // --- Bons de commande (approuvés ou exécutés) ---
        $bonsCommande = DB::table('bons_commande')
            ->where('vehicule_id', $vehicule->id)
            ->whereYear('date_commande', $annee)
            ->whereIn('statut', ['approuve', 'execute'])
            ->selectRaw('SUM(montant_ttc) as total_bc, COUNT(*) as nb_bc')
            ->first();

        // --- Kilométrage sur la période ---
        $kmDebut = DB::table('carburants')
            ->where('vehicule_id', $vehicule->id)
            ->whereYear('date', $annee)
            ->whereNotNull('kilometrage')
            ->min('kilometrage');

        $kmFin = DB::table('carburants')
            ->where('vehicule_id', $vehicule->id)
            ->whereYear('date', $annee)
            ->whereNotNull('kilometrage')
            ->max('kilometrage');

        $kmParcourus = ($kmDebut && $kmFin) ? ($kmFin - $kmDebut) : null;

        // --- Totaux ---
        $totalMaintenance = (float)($maintenance->total_maintenance ?? 0);
        $totalPneus       = (float)($pneus->total_pneus ?? 0);
        $totalCarburant   = (float)($carburant->total_carburant ?? 0);
        $totalBonsCommande = (float)($bonsCommande->total_bc ?? 0);

        // Postes détaillés depuis la table maintenances
        $posteEntretien       = (float)($maintenance->entretien        ?? 0);
        $posteReparation      = (float)($maintenance->reparation       ?? 0);
        $posteCarrosserie     = (float)($maintenance->carrosserie      ?? 0);
        $posteEquipement      = (float)($maintenance->equipement       ?? 0);
        $posteVisiteTech      = (float)($maintenance->visite_technique ?? 0);
        $posteContravention   = (float)($maintenance->contravention    ?? 0);
        // Pneus = maintenances type "pneu" (legacy) + table pneumatiques dédiée
        $postePneuMaint       = (float)($maintenance->pneu_maintenance ?? 0);

        // ⚠️ ÉVITER LE DOUBLE COMPTAGE
        // Depuis l'intégration du module Pneumatiques (table dédiée), c'est la
        // SOURCE DE VÉRITÉ pour les coûts pneus. Le type 'pneu' dans Maintenance
        // est conservé pour compatibilité avec l'historique, mais EXCLU du total
        // afin d'éviter qu'une même opération soit comptée deux fois (une fois
        // comme maintenance type=pneu, une fois dans la table pneumatiques).
        //
        // postePneuMaint reste affiché séparément (legacy_pneu_maintenance) pour
        // permettre à l'admin d'identifier et nettoyer les doublons existants.
        $postePneuTotal       = $totalPneus;

        // Total global = somme réelle de TOUS les postes
        $totalGlobal = $posteEntretien
                     + $posteReparation
                     + $posteCarrosserie
                     + $posteEquipement
                     + $posteVisiteTech
                     + $posteContravention
                     + $postePneuTotal
                     + $totalCarburant
                     + $totalBonsCommande;

        // Coût au km
        $coutParKm = ($kmParcourus && $kmParcourus > 0)
            ? round($totalGlobal / $kmParcourus, 2)
            : null;

        // Conso moyenne au 100km
        $conso100km = ($kmParcourus && $kmParcourus > 0 && ($carburant->total_litres ?? 0) > 0)
            ? round(((float)$carburant->total_litres / $kmParcourus) * 100, 2)
            : null;

        return [
            'vehicule_id'     => $vehicule->id,
            'immatriculation' => $vehicule->immatriculation,
            'annee'           => $annee,

            // Détail par poste — chaque valeur est incluse dans total_global
            'postes' => [
                'entretien'        => round($posteEntretien,      2),
                'reparation'       => round($posteReparation,     2),
                'carrosserie'      => round($posteCarrosserie,    2),
                'equipement'       => round($posteEquipement,     2),
                'visite_technique' => round($posteVisiteTech,     2),
                'contravention'    => round($posteContravention,  2),
                'pneus'            => round($postePneuTotal,      2),
                'carburant'        => round($totalCarburant,      2),
                'bons_commande'    => round($totalBonsCommande,   2),
            ],

            // Audit — montant des maintenances type='pneu' (legacy, NON inclus dans total_global)
            // Si > 0, des opérations pneus existent encore dans le module Maintenance
            // et devraient être migrées/saisies via le module Pneumatiques dédié.
            'legacy_pneu_maintenance'      => round($postePneuMaint, 2),
            'alerte_doublon_pneus_possible'=> $postePneuMaint > 0 && $totalPneus > 0,

            // Totaux intermédiaires
            'total_maintenance'   => round($totalMaintenance,   2),
            'total_pneus'         => round($totalPneus,         2),
            'total_carburant'     => round($totalCarburant,     2),
            'total_bons_commande' => round($totalBonsCommande,  2),
            'total_global'        => round($totalGlobal,        2),

            // KPIs
            'nb_interventions'  => (int)($maintenance->nb_interventions ?? 0),
            'nb_pleins'         => (int)($carburant->nb_pleins          ?? 0),
            'total_litres'      => round((float)($carburant->total_litres  ?? 0), 2),
            'km_parcourus'      => $kmParcourus,
            'cout_par_km'       => $coutParKm,
            'conso_100km'       => $conso100km,
        ];
    }

    /**
     * TCO consolidé pour tous les véhicules d'une agence.
     */
    public function consolideParcParAgence(int $agenceId, int $annee): array
    {
        // Si agenceId = 0 → tous les véhicules du parc
        $vehicules = $agenceId > 0
            ? Vehicule::where('agence_id', $agenceId)->get()
            : Vehicule::all();

        $tcos = $vehicules->map(fn($v) => $this->calculer($v, $annee));

        return [
            'agence_id'           => $agenceId ?: null,
            'annee'               => $annee,
            'nb_vehicules'        => $vehicules->count(),
            'total_global'        => $tcos->sum('total_global'),
            'total_maintenance'   => $tcos->sum('total_maintenance'),
            'total_carburant'     => $tcos->sum('total_carburant'),
            'total_pneus'         => $tcos->sum('total_pneus'),
            'total_bons_commande' => $tcos->sum('total_bons_commande'),
            'vehicules'           => $tcos->values(),
        ];
    }
}