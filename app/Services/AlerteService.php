<?php

namespace App\Services;

use App\Models\Alerte;
use App\Models\Chauffeur;
use App\Models\DocumentVehicule;
use App\Models\Vehicule;
use App\Models\BonCommande;
use App\Models\Signalement;
use App\Models\Checklist;
use App\Models\DotationCarburant;
use App\Notifications\AlerteEcheanceNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AlerteService
{
    // ============================================================
    // Point d'entrée principal — appelé par le scheduler quotidien
    // ============================================================

    /**
     * Lance le scan complet de toutes les alertes.
     * Appelé chaque matin à 06h00 par le Scheduler.
     */
    public function scannerToutesLesAlertes(): array
    {
        $stats = [
            'vt'                  => 0,
            'assurance'           => 0,
            'permis'              => 0,
            'entretien_km'        => 0,
            'entretien_periodique'=> 0,
            'carburant'           => 0,
            'signalement_ouvert'  => 0,
            'checklist_manquante' => 0,
            'vehicule_immobilise' => 0,
            'bc_en_attente'       => 0,
            'document_manquant'   => 0,
        ];

        Log::channel('daily')->info('[AlerteService] Début du scan quotidien', ['date' => now()->toDateString()]);

        try {
            $stats['vt']                   = $this->alertesVisiteTechnique();
            $stats['assurance']            = $this->alertesAssurance();
            $stats['permis']               = $this->alertesPermisChaufeur();
            $stats['entretien_km']         = $this->alertesEntretienKilometrique();
            $stats['entretien_periodique'] = $this->alertesEntretienPeriodique();
            $stats['carburant']            = $this->alertesDepassementCarburant();
            $stats['signalement_ouvert']   = $this->alertesSignalementsNonTraites();
            $stats['checklist_manquante']  = $this->alertesChecklistsManquantes();
            $stats['vehicule_immobilise']  = $this->alertesVehiculesImmobilises();
            $stats['bc_en_attente']        = $this->alertesBonsCommandeEnAttente();
            $stats['document_manquant']    = $this->alertesDocumentsManquants();
        } catch (\Exception $e) {
            Log::channel('daily')->error('[AlerteService] Erreur pendant le scan', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }

        $total = array_sum($stats);
        Log::channel('daily')->info('[AlerteService] Scan terminé', array_merge(['total' => $total], $stats));

        return $stats;
    }

    // ============================================================
    // 1. Visites techniques
    // ============================================================

    public function alertesVisiteTechnique(): int
    {
        $seuils = config('parc.alertes.visite_technique.jours');   // [60, 30, 15, 7, 0]
        $niveaux = config('parc.alertes.visite_technique.niveaux'); // ['info', 'warning', 'warning', 'danger', 'danger']
        $count = 0;

        $vehicules = Vehicule::whereNotNull('date_prochaine_visite_tech')
            ->where('date_prochaine_visite_tech', '<=', now()->addDays(max($seuils)))
            ->where('statut', '!=', 'hors_service')
            ->with('agence')
            ->get();

        foreach ($vehicules as $vehicule) {
            $jours = now()->diffInDays($vehicule->date_prochaine_visite_tech, false);

            foreach ($seuils as $i => $seuil) {
                if ($jours <= $seuil) {
                    $niveau = $niveaux[$i];

                    // Ne pas dupliquer une alerte déjà active pour ce seuil
                    if ($this->alerteExiste($vehicule->id, 'visite_technique', $seuil)) {
                        continue;
                    }

                    $message = $jours < 0
                        ? "La visite technique du véhicule {$vehicule->immatriculation} a expiré il y a " . abs((int)$jours) . " jours."
                        : "La visite technique du véhicule {$vehicule->immatriculation} expire dans {$jours} jour(s) (le {$vehicule->date_prochaine_visite_tech->format('d/m/Y')}).";

                    $this->creerAlerte([
                        'vehicule_id'    => $vehicule->id,
                        'agence_id'      => $vehicule->agence_id,
                        'type_alerte'    => 'visite_technique',
                        'titre'          => "VT {$vehicule->immatriculation} — " . ($jours < 0 ? 'Expirée' : "{$jours}j restants"),
                        'message'        => $message,
                        'echeance'       => $vehicule->date_prochaine_visite_tech,
                        'jours_restants' => (int)$jours,
                        'niveau'         => $niveau,
                        'modele_source'  => 'Vehicule',
                        'source_id'      => $vehicule->id,
                    ], $vehicule);

                    $count++;
                    break; // Un seul seuil par véhicule à la fois
                }
            }
        }

        return $count;
    }

    // ============================================================
    // 2. Assurances
    // ============================================================

    public function alertesAssurance(): int
    {
        $seuils  = config('parc.alertes.assurance.jours');
        $niveaux = config('parc.alertes.assurance.niveaux');
        $count = 0;

        $vehicules = Vehicule::whereNotNull('date_expiration_assurance')
            ->where('date_expiration_assurance', '<=', now()->addDays(max($seuils)))
            ->where('statut', '!=', 'hors_service')
            ->get();

        foreach ($vehicules as $vehicule) {
            $jours = now()->diffInDays($vehicule->date_expiration_assurance, false);

            foreach ($seuils as $i => $seuil) {
                if ($jours <= $seuil) {
                    if ($this->alerteExiste($vehicule->id, 'assurance', $seuil)) continue;

                    $message = $jours < 0
                        ? "L'assurance du véhicule {$vehicule->immatriculation} a expiré il y a " . abs((int)$jours) . " jours."
                        : "L'assurance du véhicule {$vehicule->immatriculation} expire dans {$jours} jour(s).";

                    $this->creerAlerte([
                        'vehicule_id'    => $vehicule->id,
                        'agence_id'      => $vehicule->agence_id,
                        'type_alerte'    => 'assurance',
                        'titre'          => "Assurance {$vehicule->immatriculation} — " . ($jours < 0 ? 'Expirée' : "{$jours}j"),
                        'message'        => $message,
                        'echeance'       => $vehicule->date_expiration_assurance,
                        'jours_restants' => (int)$jours,
                        'niveau'         => $niveaux[$i],
                        'modele_source'  => 'Vehicule',
                        'source_id'      => $vehicule->id,
                    ], $vehicule);

                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    // ============================================================
    // 3. Permis de conduire des chauffeurs
    // ============================================================

    public function alertesPermisChaufeur(): int
    {
        $seuils  = config('parc.alertes.permis_chauffeur.jours');   // [90, 30, 7]
        $niveaux = config('parc.alertes.permis_chauffeur.niveaux'); // ['info', 'warning', 'danger']
        $count = 0;

        $chauffeurs = Chauffeur::actifs()
            ->whereNotNull('date_expiration_permis')
            ->where('date_expiration_permis', '<=', now()->addDays(max($seuils)))
            ->with('agence')
            ->get();

        foreach ($chauffeurs as $chauffeur) {
            $jours = now()->diffInDays($chauffeur->date_expiration_permis, false);

            foreach ($seuils as $i => $seuil) {
                if ($jours <= $seuil) {
                    if ($this->alerteExiste(null, 'permis_chauffeur', $seuil, $chauffeur->id)) continue;

                    $message = $jours < 0
                        ? "Le permis de {$chauffeur->nom_complet} a expiré il y a " . abs((int)$jours) . " jours."
                        : "Le permis de {$chauffeur->nom_complet} expire dans {$jours} jour(s) (le {$chauffeur->date_expiration_permis->format('d/m/Y')}).";

                    $this->creerAlerte([
                        'chauffeur_id'   => $chauffeur->id,
                        'agence_id'      => $chauffeur->agence_id,
                        'type_alerte'    => 'permis_chauffeur',
                        'titre'          => "Permis {$chauffeur->nom_complet} — " . ($jours < 0 ? 'Expiré' : "{$jours}j"),
                        'message'        => $message,
                        'echeance'       => $chauffeur->date_expiration_permis,
                        'jours_restants' => (int)$jours,
                        'niveau'         => $niveaux[$i],
                        'modele_source'  => 'Chauffeur',
                        'source_id'      => $chauffeur->id,
                    ]);

                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    // ============================================================
    // 4. Entretien kilométrique dû
    // ============================================================

    public function alertesEntretienKilometrique(): int
    {
        $kmAvant = config('parc.maintenance.alerte_km_avant', 500);
        $count = 0;

        // Véhicules dont le prochain entretien est dans moins de $kmAvant km ou déjà dépassé
        $vehicules = Vehicule::whereNotNull('prochain_entretien_km')
            ->whereRaw('kilometrage_actuel >= (prochain_entretien_km - ?)', [$kmAvant])
            ->where('statut', '!=', 'hors_service')
            ->get();

        foreach ($vehicules as $vehicule) {
            $kmRestants = $vehicule->prochain_entretien_km - $vehicule->kilometrage_actuel;
            $echu = $kmRestants <= 0;

            if ($this->alerteExiste($vehicule->id, 'entretien_km')) continue;

            $message = $echu
                ? "L'entretien du véhicule {$vehicule->immatriculation} est échu depuis " . abs((int)$kmRestants) . " km (actuel : " . number_format($vehicule->kilometrage_actuel) . " km)."
                : "L'entretien du véhicule {$vehicule->immatriculation} est dû dans {$kmRestants} km (actuel : " . number_format($vehicule->kilometrage_actuel) . " km).";

            $this->creerAlerte([
                'vehicule_id'    => $vehicule->id,
                'agence_id'      => $vehicule->agence_id,
                'type_alerte'    => 'entretien_km',
                'titre'          => "Entretien {$vehicule->immatriculation} — " . ($echu ? 'Échu' : "{$kmRestants} km restants"),
                'message'        => $message,
                'jours_restants' => null,
                'niveau'         => $echu ? 'danger' : 'warning',
                'modele_source'  => 'Vehicule',
                'source_id'      => $vehicule->id,
            ], $vehicule);

            $count++;
        }

        return $count;
    }

    // ============================================================
    // 5. Entretien périodique (date)
    // ============================================================

    public function alertesEntretienPeriodique(): int
    {
        $count = 0;

        $vehicules = Vehicule::whereNotNull('prochain_entretien_date')
            ->where('prochain_entretien_date', '<=', now()->addDays(7))
            ->where('statut', '!=', 'hors_service')
            ->get();

        foreach ($vehicules as $vehicule) {
            $jours = now()->diffInDays($vehicule->prochain_entretien_date, false);
            if ($this->alerteExiste($vehicule->id, 'entretien_periodique')) continue;

            $this->creerAlerte([
                'vehicule_id'    => $vehicule->id,
                'agence_id'      => $vehicule->agence_id,
                'type_alerte'    => 'entretien_periodique',
                'titre'          => "Entretien périodique {$vehicule->immatriculation} — " . ($jours < 0 ? 'Dépassé' : "{$jours}j"),
                'message'        => "L'entretien périodique du véhicule {$vehicule->immatriculation} est prévu le {$vehicule->prochain_entretien_date->format('d/m/Y')}.",
                'echeance'       => $vehicule->prochain_entretien_date,
                'jours_restants' => (int)$jours,
                'niveau'         => $jours <= 0 ? 'danger' : 'warning',
                'modele_source'  => 'Vehicule',
                'source_id'      => $vehicule->id,
            ], $vehicule);

            $count++;
        }

        return $count;
    }

    // ============================================================
    // 6. Dépassement dotation carburant
    // ============================================================

    public function alertesDepassementCarburant(): int
    {
        $seuils = config('parc.carburant.seuils_alerte', [80, 90, 100]); // %
        $count = 0;
        $mois  = now()->month;
        $annee = now()->year;

        $dotations = DotationCarburant::where('mois', $mois)
            ->where('annee', $annee)
            ->where('montant_dote', '>', 0)
            ->get();

        foreach ($dotations as $dotation) {
            $taux = $dotation->taux_consommation;

            foreach (array_reverse($seuils) as $seuil) {
                if ($taux >= $seuil) {
                    if ($this->alerteExiste($dotation->vehicule_id, 'carburant_depassement')) continue 2;

                    $vehicule = $dotation->vehicule;
                    $niveau = $seuil >= 100 ? 'danger' : ($seuil >= 90 ? 'warning' : 'info');

                    $this->creerAlerte([
                        'vehicule_id'    => $dotation->vehicule_id,
                        'agence_id'      => $dotation->agence_id,
                        'type_alerte'    => 'carburant_depassement',
                        'titre'          => "Carburant {$vehicule?->immatriculation} — {$taux}% de la dotation",
                        'message'        => "Le véhicule {$vehicule?->immatriculation} a consommé {$taux}% de sa dotation carburant de " . number_format($dotation->montant_dote) . " FCFA pour " . now()->format('m/Y') . ".",
                        'jours_restants' => null,
                        'niveau'         => $niveau,
                        'modele_source'  => 'DotationCarburant',
                        'source_id'      => $dotation->id,
                    ]);

                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    // ============================================================
    // 7. Signalements non traités depuis N jours
    // ============================================================

    public function alertesSignalementsNonTraites(): int
    {
        $joursMax = config('parc.alertes.signalement_ouvert', 3);
        $count = 0;

        $signalements = Signalement::where('statut', 'nouveau')
            ->where('date_signalement', '<=', now()->subDays($joursMax))
            ->with(['vehicule', 'agence'])
            ->get();

        foreach ($signalements as $signalement) {
            $jours = now()->diffInDays($signalement->date_signalement);

            if ($this->alerteExiste($signalement->vehicule_id, 'signalement_ouvert', null, null, $signalement->id)) continue;

            $this->creerAlerte([
                'vehicule_id'    => $signalement->vehicule_id,
                'agence_id'      => $signalement->agence_id,
                'type_alerte'    => 'signalement_ouvert',
                'titre'          => "Signalement non traité — {$signalement->vehicule?->immatriculation}",
                'message'        => "Le signalement \"{$signalement->titre}\" sur le véhicule {$signalement->vehicule?->immatriculation} n'a pas été pris en charge depuis {$jours} jours.",
                'jours_restants' => -(int)$jours,
                'niveau'         => $jours >= 7 ? 'danger' : 'warning',
                'modele_source'  => 'Signalement',
                'source_id'      => $signalement->id,
            ]);

            $count++;
        }

        return $count;
    }

    // ============================================================
    // 8. Checklists hebdomadaires manquantes (scan vendredi)
    // ============================================================

    public function alertesChecklistsManquantes(): int
    {
        // Seulement utile si on est vendredi ou qu'on force le scan
        $count = 0;
        $debutSemaine = now()->startOfWeek()->toDateString();
        $finSemaine   = now()->endOfWeek()->toDateString();

        // Véhicules actifs avec une affectation active
        $vehiculesActifs = Vehicule::where('statut', 'actif')
            ->whereHas('affectationActive')
            ->pluck('id');

        // Véhicules qui ont déjà eu une checklist cette semaine
        $avecChecklist = Checklist::whereBetween('date', [$debutSemaine, $finSemaine])
            ->whereIn('type_checklist', ['hebdomadaire_vehicule', 'hebdomadaire_scooter'])
            ->pluck('vehicule_id')
            ->unique();

        // Véhicules sans checklist cette semaine
        $sansCherklist = $vehiculesActifs->diff($avecChecklist);

        $vehicules = Vehicule::whereIn('id', $sansCherklist)->with('agence', 'affectationActive.chauffeur')->get();

        foreach ($vehicules as $vehicule) {
            if ($this->alerteExiste($vehicule->id, 'checklist_manquante')) continue;

            $chauffeur = $vehicule->affectationActive?->chauffeur;

            $this->creerAlerte([
                'vehicule_id'    => $vehicule->id,
                'agence_id'      => $vehicule->agence_id,
                'chauffeur_id'   => $chauffeur?->id,
                'type_alerte'    => 'checklist_manquante',
                'titre'          => "Checklist manquante — {$vehicule->immatriculation}",
                'message'        => "Aucune checklist hebdomadaire enregistrée pour le véhicule {$vehicule->immatriculation}" . ($chauffeur ? " (chauffeur : {$chauffeur->nom_complet})" : "") . " pour la semaine du {$debutSemaine}.",
                'jours_restants' => null,
                'niveau'         => 'warning',
                'modele_source'  => 'Vehicule',
                'source_id'      => $vehicule->id,
            ]);

            $count++;
        }

        return $count;
    }

    // ============================================================
    // 9. Véhicules immobilisés trop longtemps
    // ============================================================

    public function alertesVehiculesImmobilises(): int
    {
        $joursMax = config('parc.alertes.vehicule_immobilise', 7);
        $count = 0;

        $vehicules = Vehicule::whereIn('statut', ['en_panne', 'en_maintenance'])
            ->where('updated_at', '<=', now()->subDays($joursMax))
            ->with('agence')
            ->get();

        foreach ($vehicules as $vehicule) {
            $jours = now()->diffInDays($vehicule->updated_at);
            if ($this->alerteExiste($vehicule->id, 'vehicule_immobilise')) continue;

            $this->creerAlerte([
                'vehicule_id'    => $vehicule->id,
                'agence_id'      => $vehicule->agence_id,
                'type_alerte'    => 'vehicule_immobilise',
                'titre'          => "Véhicule immobilisé {$jours}j — {$vehicule->immatriculation}",
                'message'        => "Le véhicule {$vehicule->immatriculation} est en statut \"{$vehicule->statut}\" depuis {$jours} jours. Vérifiez l'état de la maintenance en cours.",
                'jours_restants' => -(int)$jours,
                'niveau'         => $jours >= 14 ? 'danger' : 'warning',
                'modele_source'  => 'Vehicule',
                'source_id'      => $vehicule->id,
            ], $vehicule);

            $count++;
        }

        return $count;
    }

    // ============================================================
    // 10. Bons de commande en attente d'approbation
    // ============================================================

    public function alertesBonsCommandeEnAttente(): int
    {
        $joursMax = config('parc.alertes.bc_en_attente', 2);
        $count = 0;

        $bons = BonCommande::where('statut', 'soumis')
            ->where('updated_at', '<=', now()->subDays($joursMax))
            ->with('agence', 'creePar')
            ->get();

        foreach ($bons as $bc) {
            $jours = now()->diffInDays($bc->updated_at);
            if ($this->alerteExiste(null, 'bon_commande_en_attente', null, null, $bc->id)) continue;

            $this->creerAlerte([
                'agence_id'      => $bc->agence_id,
                'type_alerte'    => 'bon_commande_en_attente',
                'titre'          => "BC {$bc->numero_bc} en attente depuis {$jours}j",
                'message'        => "Le bon de commande {$bc->numero_bc} d'un montant de " . number_format($bc->montant_ttc) . " FCFA attend une approbation depuis {$jours} jours.",
                'jours_restants' => -(int)$jours,
                'niveau'         => $jours >= 5 ? 'danger' : 'warning',
                'modele_source'  => 'BonCommande',
                'source_id'      => $bc->id,
            ]);

            $count++;
        }

        return $count;
    }

    // ============================================================
    // 11. Documents obligatoires manquants
    // ============================================================

    public function alertesDocumentsManquants(): int
    {
        $count = 0;
        $typesObligatoires = ['carte_grise', 'assurance', 'visite_technique'];

        $vehicules = Vehicule::where('statut', '!=', 'hors_service')
            ->with(['documentsVehicule' => fn ($q) => $q->actifs()])
            ->get();

        foreach ($vehicules as $vehicule) {
            $typesPresents = $vehicule->documentsVehicule->pluck('type_document')->toArray();
            $manquants     = array_diff($typesObligatoires, $typesPresents);
            $presents      = array_intersect($typesObligatoires, $typesPresents);

            // Résoudre les alertes dont le document a été ajouté depuis le dernier scan
            foreach ($presents as $type) {
                Alerte::where('vehicule_id',  $vehicule->id)
                    ->where('type_alerte',    'document_manquant')
                    ->where('statut',         'active')
                    ->where('message',        'like', '%' . ucfirst(str_replace('_', ' ', $type)) . '%')
                    ->update(['statut' => 'resolue', 'updated_at' => now()]);
            }

            // Créer les alertes pour les documents encore manquants
            foreach ($manquants as $type) {
                if ($this->alerteExiste($vehicule->id, 'document_manquant')) continue;

                $this->creerAlerte([
                    'vehicule_id'    => $vehicule->id,
                    'agence_id'      => $vehicule->agence_id,
                    'type_alerte'    => 'document_manquant',
                    'titre'          => "Document manquant — {$vehicule->immatriculation}",
                    'message'        => "Le document \"" . ucfirst(str_replace('_', ' ', $type)) . "\" est absent pour le véhicule {$vehicule->immatriculation}.",
                    'niveau'         => 'danger',
                    'modele_source'  => 'Vehicule',
                    'source_id'      => $vehicule->id,
                ]);

                $count++;
            }
        }

        return $count;
    }

    // ============================================================
    // Helpers privés
    // ============================================================

    /**
     * Crée une alerte en base et envoie les notifications aux destinataires.
     */
    private function creerAlerte(array $data, ?Vehicule $vehicule = null): Alerte
    {
        // Déterminer les destinataires selon le type d'alerte et l'agence
        $destinataires = $this->getDestinataires($data, $vehicule);

        $alerte = Alerte::create(array_merge($data, [
            'statut'        => 'active',
            'destinataires' => $destinataires->map(fn ($u) => [
                'user_id' => $u->id,
                'lu_le'   => null,
            ])->values()->toArray(),
            'envoyee_le'    => now(),
        ]));

        // Envoyer les notifications email en queue
        if ($destinataires->isNotEmpty()) {
            try {
                Notification::send($destinataires, new AlerteEcheanceNotification($alerte));
            } catch (\Exception $e) {
                Log::warning('[AlerteService] Erreur envoi notification', ['alerte_id' => $alerte->id, 'error' => $e->getMessage()]);
            }
        }

        return $alerte;
    }

    /**
     * Vérifie si une alerte similaire (active) existe déjà pour éviter les doublons.
     */
    private function alerteExiste(
        ?int $vehiculeId,
        string $typeAlerte,
        ?int $seuil = null,
        ?int $chauffeurId = null,
        ?int $sourceId = null
    ): bool {
        $query = Alerte::where('type_alerte', $typeAlerte)
            ->where('statut', 'active')
            ->where('created_at', '>=', now()->startOfDay()); // Pas de doublon le même jour

        if ($vehiculeId)  $query->where('vehicule_id', $vehiculeId);
        if ($chauffeurId) $query->where('chauffeur_id', $chauffeurId);
        if ($sourceId)    $query->where('source_id', $sourceId);

        // Pour les alertes à seuils multiples, on vérifie sur les 5 derniers jours
        if ($seuil !== null) {
            $query->where('created_at', '>=', now()->subDays(5));
        }

        return $query->exists();
    }

    /**
     * Détermine les utilisateurs à notifier selon le type d'alerte.
     */
    private function getDestinataires(array $data, ?Vehicule $vehicule = null): Collection
    {
        $agenceId   = $data['agence_id'] ?? null;
        $typeAlerte = $data['type_alerte'];

        // Rôles qui reçoivent toujours les alertes
        $rolesGlobaux = match($typeAlerte) {
            'visite_technique', 'assurance', 'document_manquant'
                => ['resp_parc', 'resp_agence', 'directeur'],
            'permis_chauffeur', 'checklist_manquante'
                => ['resp_parc', 'resp_agence'],
            'carburant_depassement', 'bon_commande_en_attente'
                => ['resp_parc', 'directeur', 'comptable'],
            'entretien_km', 'entretien_periodique', 'vehicule_immobilise', 'signalement_ouvert'
                => ['resp_parc', 'resp_agence'],
            default => ['resp_parc'],
        };

        return \App\Models\User::whereHas('roles', fn ($q) => $q->whereIn('name', $rolesGlobaux))
            ->where('est_actif', true)
            ->when($agenceId, fn ($q) => $q->where(fn ($q2) =>
                $q2->where('agence_id', $agenceId)
                   ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['resp_parc', 'directeur', 'comptable']))
            ))
            ->get();
    }
}