<?php

namespace App\Services;

use App\Models\Checklist;
use App\Models\Signalement;
use App\Models\User;

class ChecklistService
{
    /**
     * Items considérés comme non-conformes selon leur valeur.
     * Structure data_json : { "section": { "item": "valeur" } }
     * Valeurs non-conformes : "mauvais", "non", "defaillant", "absent"
     */
    private const VALEURS_NON_CONFORMES = ['mauvais', 'non', 'defaillant', 'absent', 'ko', '0'];

    /**
     * Items critiques qui génèrent un signalement de gravité "haute" ou "critique"
     */
    private const ITEMS_CRITIQUES = [
        'frein_av', 'frein_ar', 'frein_service', 'frein_stationnement',
        'pneu_av', 'pneu_ar',
        'phare_av_g', 'phare_av_d', 'feu_stop',
        'moteur', 'temperature', 'huile_moteur',
        'disque_frein_av', 'disque_frein_ar',
    ];

    /**
     * Analyse le data_json et retourne la liste des non-conformités.
     */
    public function detecterNonConformites(array $dataJson, string $typeChecklist): array
    {
        $nonConformites = [];

        foreach ($dataJson as $section => $items) {
            if (!is_array($items)) continue;

            foreach ($items as $item => $valeur) {
                if (in_array(strtolower((string)$valeur), self::VALEURS_NON_CONFORMES)) {
                    $nonConformites[] = [
                        'section' => $section,
                        'item'    => $item,
                        'valeur'  => $valeur,
                        'critique'=> in_array($item, self::ITEMS_CRITIQUES),
                    ];
                }
            }
        }

        return $nonConformites;
    }

    /**
     * Génère automatiquement un signalement depuis une checklist non-conforme.
     */
    public function genererSignalement(Checklist $checklist, User $validePar): Signalement
    {
        $nonConformites = $checklist->non_conformites ?? [];

        // Déterminer la gravité selon les items en défaut
        $aCritique = collect($nonConformites)->contains('critique', true);
        $gravite   = $aCritique ? 'haute' : 'moyenne';

        // Construire le titre
        $nbDefauts = count($nonConformites);
        $titre     = "Checklist {$checklist->type_checklist} — {$nbDefauts} non-conformité(s) détectée(s)";

        // Construire la description
        $lignes = ["Non-conformités issues de la checklist du {$checklist->date->format('d/m/Y')} :"];
        foreach ($nonConformites as $nc) {
            $critTag = $nc['critique'] ? ' ⚠ CRITIQUE' : '';
            $lignes[] = "- [{$nc['section']}] {$nc['item']} : {$nc['valeur']}{$critTag}";
        }
        $description = implode("\n", $lignes);

        return Signalement::create([
            'vehicule_id'       => $checklist->vehicule_id,
            'chauffeur_id'      => $checklist->chauffeur_id,
            'agence_id'         => $checklist->agence_id,
            'origine'           => 'checklist',
            'date_signalement'  => $checklist->date,
            'kilometrage'       => $checklist->kilometrage,
            'type_defaut'       => $this->determinerTypeDefaut($nonConformites),
            'gravite'           => $gravite,
            'titre'             => $titre,
            'description'       => $description,
            'etat_elements'     => $this->buildEtatElements($nonConformites),
            'statut'            => 'nouveau',
            'checklist_id'      => $checklist->id,
            'created_by'        => $validePar->id,
        ]);
    }

    /**
     * Structure de l'état des éléments pour la fiche de signalement.
     */
    private function buildEtatElements(array $nonConformites): array
    {
        $etat = [];
        foreach ($nonConformites as $nc) {
            $etat[$nc['item']] = $nc['valeur'];
        }
        return $etat;
    }

    /**
     * Détermine le type de défaut principal selon les non-conformités.
     */
    private function determinerTypeDefaut(array $nonConformites): string
    {
        $items = array_column($nonConformites, 'item');

        if (array_intersect($items, ['frein_av', 'frein_ar', 'disque_frein_av', 'disque_frein_ar'])) {
            return 'probleme_freins';
        }
        if (array_intersect($items, ['pneu_av', 'pneu_ar'])) {
            return 'probleme_pneu';
        }
        if (array_intersect($items, ['phare_av_g', 'phare_av_d', 'feu_stop', 'clignotant_avg', 'clignotant_avd'])) {
            return 'probleme_eclairage';
        }
        if (array_intersect($items, ['moteur', 'temperature', 'huile_moteur'])) {
            return 'panne_moteur';
        }

        return 'autre';
    }

    /**
     * Retourne la structure JSON attendue pour chaque type de checklist.
     * Utilisée par le frontend pour afficher le bon formulaire.
     */
    public function getTemplate(string $typeChecklist): array
    {
        return match($typeChecklist) {
            'hebdomadaire_vehicule' => $this->templateVehicule(),
            'hebdomadaire_scooter'  => $this->templateScooter(),
            'visite_technique'      => $this->templateVisiteTechnique(),
            'passation'             => $this->templatePassation(),
            'attribution'           => $this->templateAttribution(),
            default                 => [],
        };
    }

    private function templateVehicule(): array
    {
        return [
            'niveaux' => [
                'huile_moteur'        => null,
                'liquide_refroidissement' => null,
                'liquide_frein'       => null,
                'carburant'           => null,
            ],
            'pneumatiques' => [
                'pression_av'  => null,
                'pression_ar'  => null,
                'etat_pneu_av' => null,
                'etat_pneu_ar' => null,
            ],
            'visibilite' => [
                'retroviseur_g'    => null,
                'retroviseur_d'    => null,
                'vitres'           => null,
                'essuie_glaces'    => null,
                'plaque_imat'      => null,
            ],
            'documents' => [
                'carte_grise'  => null,
                'assurance'    => null,
                'permis'       => null,
            ],
            'materiels' => [
                'cric'         => null,
                'roue_secours' => null,
                'triangle'     => null,
                'extincteur'   => null,
                'cle_roue'     => null,
            ],
            'eclairage' => [
                'phare_av_g'      => null,
                'phare_av_d'      => null,
                'feu_stop'        => null,
                'clignotant_avg'  => null,
                'clignotant_avd'  => null,
                'clignotant_arg'  => null,
                'clignotant_ard'  => null,
                'feux_recul'      => null,
            ],
            'proprete' => [
                'interieur'    => null,
                'exterieur'    => null,
            ],
        ];
    }

    private function templateScooter(): array
    {
        return [
            'niveaux' => [
                'huile_moteur'            => null,
                'liquide_refroidissement' => null,
                'carburant'               => null,
            ],
            'pneumatiques' => [
                'pression_av'  => null,
                'pression_ar'  => null,
                'etat_pneu_av' => null,
                'etat_pneu_ar' => null,
            ],
            'visibilite' => [
                'retroviseur_g' => null,
                'retroviseur_d' => null,
                'plaque_imat'   => null,
            ],
            'documents' => [
                'carte_grise' => null,
                'assurance'   => null,
                'permis'      => null,
            ],
            'equipements_epi' => [
                'casque'              => null,
                'masque'              => null,
                'chaussures_securite' => null,
                'sac_isotherme'       => null,
            ],
            'eclairage' => [
                'phare_av'        => null,
                'feu_stop'        => null,
                'clignotant_avg'  => null,
                'clignotant_avd'  => null,
                'clignotant_arg'  => null,
                'clignotant_ard'  => null,
            ],
            'carrosserie' => [
                'carenage_av_g' => null,
                'carenage_av_d' => null,
                'carenage_ar_g' => null,
                'carenage_ar_d' => null,
                'garde_boue_av' => null,
                'garde_boue_ar' => null,
            ],
        ];
    }

    private function templateVisiteTechnique(): array
    {
        return [
            'identification' => [
                'date_mise_circulation'   => null,
                'date_derniere_visite_tech'=> null,
                'kilometrage'             => null,
            ],
            'freinage' => [
                'frein_service'       => null,
                'frein_stationnement' => null,
                'disque_frein_av'     => null,
                'disque_frein_ar'     => null,
                'plaquettes_av'       => null,
            ],
            'direction_suspension' => [
                'parallelisme'         => null,
                'amortisseurs_av'      => null,
                'amortisseurs_ar'      => null,
                'rotules_direction'    => null,
            ],
            'eclairage_signalisation' => [
                'phare_av_g'      => null,
                'phare_av_d'      => null,
                'feux_position'   => null,
                'feu_stop'        => null,
                'clignotants'     => null,
                'feux_recul'      => null,
                'feux_brouillard' => null,
            ],
            'pneumatiques' => [
                'pneu_av_g'    => null,
                'pneu_av_d'    => null,
                'pneu_ar_g'    => null,
                'pneu_ar_d'    => null,
                'roue_secours' => null,
            ],
            'carrosserie_vitrage' => [
                'pare_brise'   => null,
                'vitres'       => null,
                'carrosserie'  => null,
                'portes'       => null,
            ],
            'moteur_echappement' => [
                'niveaux'       => null,
                'echappement'   => null,
                'courroie'      => null,
            ],
        ];
    }

    private function templatePassation(): array
    {
        // Même structure que véhicule + état général
        $template = $this->templateVehicule();
        $template['etat_general'] = [
            'etat_interieur'   => null,
            'etat_exterieur'   => null,
            'remarques'        => null,
        ];
        return $template;
    }

    private function templateAttribution(): array
    {
        return $this->templatePassation();
    }
}
