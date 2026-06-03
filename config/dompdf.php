<?php

/**
 * Configuration DomPDF pour le projet Parc Auto
 * Publié par : php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
 */
return [

    // ============================================================
    // Dossier temporaire pour DomPDF (doit être accessible en écriture)
    // ============================================================
    'temporary_path' => storage_path('app/dompdf'),

    // ============================================================
    // Options de rendu
    // ============================================================
    'options' => [

        // Police par défaut — DejaVu Sans supporte les caractères UTF-8 (é, è, ê, etc.)
        'defaultFont' => 'DejaVu Sans',

        // Activer le parser HTML5 (nécessaire pour les templates Blade)
        'isHtml5ParserEnabled' => true,

        // Désactiver le chargement de ressources distantes (images HTTP externes)
        // Mettre à true uniquement si vous avez des logos en URL externe
        'isRemoteEnabled' => false,

        // DPI : 150 = bon compromis qualité / performance
        'dpi' => 150,

        // Format par défaut
        'defaultPaperSize'        => 'A4',
        'defaultPaperOrientation' => 'portrait',

        // Activer les polices embarquées (important pour les caractères spéciaux)
        'enable_font_subsetting' => true,

        // Encodage par défaut
        'defaultCharset' => 'UTF-8',

        // Mémoire PHP allouée pour la génération
        'memory_limit' => '256M',

        // Cache des polices (accélère la génération)
        'fontCache' => storage_path('fonts'),

        // Dossier des polices personnalisées (si besoin)
        'fontDir' => storage_path('fonts'),

        // Activer le débogage CSS (false en production)
        'debugCss'       => false,
        'debugLayout'    => false,
        'debugLayoutLines'  => false,
        'debugLayoutBlocks' => false,
        'debugLayoutInline' => false,
        'debugLayoutPaddingBox' => false,
    ],

];
