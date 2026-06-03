<?php

/**
 * Configuration métier de l'application Parc Auto.
 * Toutes les constantes métier sont ici — pas dans le code.
 * Accès : config('parc.seuil_approbation')
 */

return [

    // ============================================================
    // MAINTENANCE
    // ============================================================
    'maintenance' => [
        // Montant TTC (FCFA) au-dessus duquel une maintenance nécessite approbation directeur
        'seuil_approbation'     => env('PARC_SEUIL_APPROBATION', 500_000),

        // Intervalle d'entretien kilométrique par défaut (km)
        'intervalle_km_defaut'  => env('PARC_INTERVALLE_KM', 10_000),

        // Alerte entretien kilométrique : combien de km avant l'échéance
        'alerte_km_avant'       => 500,
    ],

    // ============================================================
    // ALERTES (délais en jours)
    // ============================================================
    'alertes' => [
        'visite_technique' => [
            'jours'     => [60, 30, 15, 7, 0],
            'niveaux'   => ['info', 'warning', 'warning', 'danger', 'danger'],
        ],
        'assurance' => [
            'jours'     => [60, 30, 15, 7, 0],
            'niveaux'   => ['info', 'warning', 'warning', 'danger', 'danger'],
        ],
        'permis_chauffeur' => [
            'jours'     => [90, 30, 7],
            'niveaux'   => ['info', 'warning', 'danger'],
        ],
        // Signalement non traité depuis N jours
        'signalement_ouvert'    => env('PARC_ALERTE_SIGNALEMENT_JOURS', 3),
        // Véhicule immobilisé depuis N jours
        'vehicule_immobilise'   => env('PARC_ALERTE_IMMOBILISE_JOURS', 7),
        // BC en attente depuis N jours
        'bc_en_attente'         => env('PARC_ALERTE_BC_JOURS', 2),
    ],

    // ============================================================
    // CARBURANT
    // ============================================================
    'carburant' => [
        // Seuils d'alerte dépassement dotation (en %)
        'seuils_alerte' => [80, 90, 100],

        // TVA Sénégal
        'tva_taux' => env('PARC_TVA', 18),
    ],

    // ============================================================
    // BONS DE COMMANDE
    // ============================================================
    'bon_commande' => [
        // Montant TTC (FCFA) au-dessus duquel un BC nécessite approbation directeur
        'seuil_approbation' => env('PARC_BC_SEUIL', 200_000),
        // Format du numéro BC : BC-YYYY-XXXX
        'format_numero'     => 'BC-%s-%04d',
    ],

    // ============================================================
    // PAGINATION
    // ============================================================
    'pagination' => [
        'per_page_defaut' => 25,
        'per_page_max'    => 100,
    ],

    // ============================================================
    // UPLOADS
    // ============================================================
    'uploads' => [
        'taille_max_mo'     => 10,                           // Taille max fichier en Mo
        'types_images'      => ['jpg', 'jpeg', 'png', 'webp'],
        'types_documents'   => ['pdf', 'jpg', 'jpeg', 'png'],
        'disque'            => env('PARC_STORAGE_DISK', 'local'), // local ou s3
    ],

    // ============================================================
    // NOTIFICATIONS
    // ============================================================
    'notifications' => [
        // Canal par défaut pour les alertes
        'canal_defaut' => env('PARC_NOTIF_CANAL', 'mail'),  // mail, database, slack

        // Heure d'envoi des alertes quotidiennes (format H)
        'heure_scan_alertes' => env('PARC_ALERTE_HEURE', 6), // 06h00
    ],

];
