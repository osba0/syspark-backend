<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Réinitialiser le cache des permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ============================================================
        // 1. DÉFINITION DE TOUTES LES PERMISSIONS
        // ============================================================
        $permissions = [

            // --- Véhicules ---
            'vehicule.viewAny',      // Lister tous les véhicules
            'vehicule.view',         // Voir un véhicule
            'vehicule.create',       // Créer un véhicule
            'vehicule.update',       // Modifier un véhicule
            'vehicule.delete',       // Supprimer (soft delete)
            'vehicule.updateKm',     // Mettre à jour le kilométrage
            'vehicule.viewTco',      // Voir le TCO (coût total possession)

            // --- Chauffeurs ---
            'chauffeur.viewAny',
            'chauffeur.view',
            'chauffeur.create',
            'chauffeur.update',
            'chauffeur.delete',

            // --- Affectations ---
            'affectation.viewAny',
            'affectation.view',
            'affectation.create',    // Attribuer un véhicule
            'affectation.update',
            'affectation.cloturer',  // Clôturer une affectation

            // --- Checklists ---
            'checklist.viewAny',
            'checklist.view',
            'checklist.create',      // Saisir une checklist
            'checklist.submit',      // Soumettre pour validation
            'checklist.validate',    // Valider (responsable)
            'checklist.reject',      // Rejeter

            // --- Signalements ---
            'signalement.viewAny',
            'signalement.view',
            'signalement.create',    // Signaler un incident
            'signalement.update',
            'signalement.prendreEnCharge',
            'signalement.resoudre',
            'signalement.uploadPhoto',

            // --- Communication (Annonces / Notes de service) ---
            'communication.viewAny',  // Voir la liste de gestion (auteur/édition)
            'communication.create',   // Créer une annonce/note (brouillon)
            'communication.update',
            'communication.delete',
            'communication.publish',  // Publier / archiver

            // --- Maintenance ---
            'maintenance.viewAny',
            'maintenance.view',
            'maintenance.create',
            'maintenance.update',
            'maintenance.delete',
            'maintenance.approve',   // Approuver (montants importants)
            'maintenance.cloturer',

            // --- Carburant ---
            'carburant.viewAny',
            'carburant.view',
            'carburant.create',
            'carburant.update',
            'carburant.delete',
            'carburant.gererDotations', // Gérer les dotations mensuelles

            // --- Pneumatiques ---
            'pneumatique.viewAny',
            'pneumatique.view',
            'pneumatique.create',
            'pneumatique.update',
            'pneumatique.delete',

            // --- Documents véhicules ---
            'document.viewAny',
            'document.view',
            'document.create',
            'document.update',
            'document.delete',
            'document.renouveler',

            // --- Bons de commande ---
            'bonCommande.viewAny',
            'bonCommande.view',
            'bonCommande.create',
            'bonCommande.update',
            'bonCommande.delete',
            'bonCommande.submit',    // Soumettre pour approbation
            'bonCommande.approve',   // Approuver
            'bonCommande.reject',    // Rejeter

            // --- Fournisseurs ---
            'fournisseur.viewAny',
            'fournisseur.view',
            'fournisseur.create',
            'fournisseur.update',
            'fournisseur.delete',

            // --- Alertes ---
            'alerte.viewAny',
            'alerte.view',
            'alerte.create',
            'alerte.markRead',
            'alerte.manage',         // Configurer les alertes

            // --- Rapports ---
            'rapport.view',          // Voir les rapports
            'rapport.export',        // Exporter PDF/Excel
            'rapport.viewFinancier', // Voir les données financières

            // --- Dashboard ---
            'dashboard.view',
            'dashboard.viewStats',   // Stats globales

            // --- Administration ---
            'admin.users.viewAny',
            'admin.users.create',
            'admin.users.update',
            'admin.users.delete',
            'admin.agences.manage',
            'admin.config.manage',   // Configuration globale de l'app
            'admin.logs.view',       // Journal d'audit
        ];

        // Créer toutes les permissions
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->command->info('✅  ' . count($permissions) . ' permissions créées.');

        // ============================================================
        // 2. CRÉATION DES RÔLES ET ATTRIBUTION DES PERMISSIONS
        // ============================================================

        // ----------------------------------------------------------
        // SUPER ADMIN — accès total (bypass via Gate::before dans app)
        // ----------------------------------------------------------
        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        // ----------------------------------------------------------
        // DIRECTEUR / DG — lecture totale, approbation dépenses
        // ----------------------------------------------------------
        $directeur = Role::findOrCreate('directeur', 'web');
        $directeur->syncPermissions([
            'vehicule.viewAny', 'vehicule.view', 'vehicule.viewTco',
            'chauffeur.viewAny', 'chauffeur.view',
            'affectation.viewAny', 'affectation.view',
            'checklist.viewAny', 'checklist.view',
            'signalement.viewAny', 'signalement.view',
            'maintenance.viewAny', 'maintenance.view', 'maintenance.approve',
            'carburant.viewAny', 'carburant.view',
            'pneumatique.viewAny', 'pneumatique.view',
            'document.viewAny', 'document.view',
            'bonCommande.viewAny', 'bonCommande.view', 'bonCommande.approve', 'bonCommande.reject',
            'fournisseur.viewAny', 'fournisseur.view',
            'alerte.viewAny', 'alerte.view', 'alerte.markRead',
            'rapport.view', 'rapport.export', 'rapport.viewFinancier',
            'dashboard.view', 'dashboard.viewStats',
            'admin.logs.view',
            // Communication — direction valide/publie tout
            'communication.viewAny', 'communication.create', 'communication.update',
            'communication.delete', 'communication.publish',
        ]);

        // ----------------------------------------------------------
        // RESPONSABLE DE PARC — accès complet opérationnel
        // ----------------------------------------------------------
        $respParc = Role::findOrCreate('resp_parc', 'web');
        $respParc->syncPermissions([
            // Véhicules
            'vehicule.viewAny', 'vehicule.view', 'vehicule.create',
            'vehicule.update', 'vehicule.delete', 'vehicule.updateKm', 'vehicule.viewTco',
            // Chauffeurs
            'chauffeur.viewAny', 'chauffeur.view', 'chauffeur.create',
            'chauffeur.update', 'chauffeur.delete',
            // Affectations
            'affectation.viewAny', 'affectation.view', 'affectation.create',
            'affectation.update', 'affectation.cloturer',
            // Checklists
            'checklist.viewAny', 'checklist.view', 'checklist.create',
            'checklist.submit', 'checklist.validate', 'checklist.reject',
            // Signalements
            'signalement.viewAny', 'signalement.view', 'signalement.create',
            'signalement.update', 'signalement.prendreEnCharge',
            'signalement.resoudre', 'signalement.uploadPhoto',
            // Maintenance
            'maintenance.viewAny', 'maintenance.view', 'maintenance.create',
            'maintenance.update', 'maintenance.delete', 'maintenance.cloturer',
            // Carburant
            'carburant.viewAny', 'carburant.view', 'carburant.create',
            'carburant.update', 'carburant.delete', 'carburant.gererDotations',
            // Pneumatiques
            'pneumatique.viewAny', 'pneumatique.view', 'pneumatique.create',
            'pneumatique.update', 'pneumatique.delete',
            // Documents
            'document.viewAny', 'document.view', 'document.create',
            'document.update', 'document.delete', 'document.renouveler',
            // Bons de commande
            'bonCommande.viewAny', 'bonCommande.view', 'bonCommande.create',
            'bonCommande.update', 'bonCommande.delete', 'bonCommande.submit',
            // Fournisseurs
            'fournisseur.viewAny', 'fournisseur.view', 'fournisseur.create',
            'fournisseur.update',
            // Alertes
            'alerte.viewAny', 'alerte.view', 'alerte.create',
            'alerte.markRead', 'alerte.manage',
            // Rapports
            'rapport.view', 'rapport.export', 'rapport.viewFinancier',
            // Dashboard
            'dashboard.view', 'dashboard.viewStats',
            // Audit — journal complet (multi-agences)
            'admin.logs.view',
            // Communication
            'communication.viewAny', 'communication.create', 'communication.update',
            'communication.delete', 'communication.publish',
        ]);

        // ----------------------------------------------------------
        // RESPONSABLE D'AGENCE — même que resp_parc mais scoped à son agence
        // Le scope est géré par le middleware EnsureAgenceAccess
        // ----------------------------------------------------------
        $respAgence = Role::findOrCreate('resp_agence', 'web');
        $respAgence->syncPermissions([
            'vehicule.viewAny', 'vehicule.view', 'vehicule.create',
            'vehicule.update', 'vehicule.updateKm', 'vehicule.viewTco',
            'chauffeur.viewAny', 'chauffeur.view', 'chauffeur.create', 'chauffeur.update',
            'affectation.viewAny', 'affectation.view', 'affectation.create',
            'affectation.update', 'affectation.cloturer',
            'checklist.viewAny', 'checklist.view', 'checklist.create',
            'checklist.submit', 'checklist.validate',
            'signalement.viewAny', 'signalement.view', 'signalement.create',
            'signalement.update', 'signalement.prendreEnCharge', 'signalement.resoudre',
            'signalement.uploadPhoto',
            'maintenance.viewAny', 'maintenance.view', 'maintenance.create',
            'maintenance.update', 'maintenance.cloturer',
            'carburant.viewAny', 'carburant.view', 'carburant.create', 'carburant.update',
            'pneumatique.viewAny', 'pneumatique.view', 'pneumatique.create',
            'document.viewAny', 'document.view', 'document.create', 'document.update',
            'bonCommande.viewAny', 'bonCommande.view', 'bonCommande.create',
            'bonCommande.update', 'bonCommande.submit',
            'fournisseur.viewAny', 'fournisseur.view',
            'alerte.viewAny', 'alerte.view', 'alerte.markRead',
            'rapport.view', 'rapport.export',
            'dashboard.view', 'dashboard.viewStats',
            // Audit — journal de son agence uniquement
            'admin.logs.view',
            // Communication — peut publier pour son agence
            'communication.viewAny', 'communication.create', 'communication.update',
            'communication.delete', 'communication.publish',
        ]);

        // ----------------------------------------------------------
        // CHAUFFEUR — accès limité à son véhicule
        // ----------------------------------------------------------
        $chauffeur = Role::findOrCreate('chauffeur', 'web');
        $chauffeur->syncPermissions([
            'vehicule.view',            // Son véhicule uniquement (géré par Policy)
            'vehicule.updateKm',        // Déclarer son kilométrage
            'checklist.viewAny',        // Lister les checklists (scopées par Policy/Controller)
            'checklist.view',           // Voir ses checklists
            'checklist.create',         // Saisir une checklist
            'checklist.submit',         // Soumettre pour validation
            'signalement.view',         // Voir ses signalements
            'signalement.create',       // Signaler un incident
            'signalement.uploadPhoto',  // Ajouter des photos
            'carburant.view',           // Voir sa consommation
            'carburant.create',         // Saisir une consommation
            'alerte.view',              // Voir ses alertes
            'alerte.markRead',          // Marquer comme lue
            'dashboard.view',
            // Audit — uniquement les actions le concernant personnellement
            'admin.logs.view',
        ]);

        // ----------------------------------------------------------
        // ATTRIBUTAIRE ADMINISTRATIF — lecture son véhicule de fonction
        // ----------------------------------------------------------
        $attributaire = Role::findOrCreate('attributaire', 'web');
        $attributaire->syncPermissions([
            'vehicule.view',
            'signalement.view',
            'signalement.create',
            'signalement.uploadPhoto',
            'document.view',
            'alerte.view',
            'alerte.markRead',
            'dashboard.view',
            // Audit — uniquement les actions le concernant personnellement
            'admin.logs.view',
        ]);

        // ----------------------------------------------------------
        // COMPTABLE / DAF — lecture finances, export
        // ----------------------------------------------------------
        $comptable = Role::findOrCreate('comptable', 'web');
        $comptable->syncPermissions([
            'vehicule.viewAny', 'vehicule.view', 'vehicule.viewTco',
            'maintenance.viewAny', 'maintenance.view',
            'carburant.viewAny', 'carburant.view',
            'pneumatique.viewAny', 'pneumatique.view',
            'bonCommande.viewAny', 'bonCommande.view',
            'bonCommande.approve', 'bonCommande.reject',
            'fournisseur.viewAny', 'fournisseur.view',
            'rapport.view', 'rapport.export', 'rapport.viewFinancier',
            'dashboard.view', 'dashboard.viewStats',
            'alerte.view', 'alerte.markRead',
            // Audit — journal de son agence uniquement
            'admin.logs.view',
        ]);

        $this->command->info('✅  7 rôles créés avec permissions.');
    }
}