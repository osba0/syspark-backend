<?php

use App\Jobs\PrecalculerStatsJob;
use App\Jobs\ScanAlertesJob;
use App\Jobs\UpdateStatutsDocumentsJob;
use Illuminate\Support\Facades\Schedule;

// ============================================================
// Scheduler du Parc Automobile
// Toutes les tâches planifiées de l'application.
//
// Pour activer sur le serveur, ajouter dans crontab :
//   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
// ============================================================

// ── TOUTES LES MINUTES ───────────────────────────────────────

/**
 * Traitement de la file d'attente d'emails de notification
 * Table : notification_emails (statuts pending → sent / failed)
 * Inclut la relance automatique des échecs (backoff exponentiel)
 */
Schedule::command('email-queue:process')
    ->everyMinute()
    ->name('process-email-queue')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Scheduler] email-queue:process a échoué');
    });

// ── QUOTIDIEN ────────────────────────────────────────────────

/**
 * Scan complet des alertes — chaque matin à 06h00
 * Vérifie : VT, assurances, permis, entretiens, carburant,
 * signalements ouverts, checklists, véhicules immobilisés, BC en attente
 */
Schedule::job(new ScanAlertesJob())
    ->dailyAt(config('parc.notifications.heure_scan_alertes', '06') . ':00')
    ->name('scan-alertes')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Scheduler] ScanAlertesJob a échoué');
    });

/**
 * Mise à jour des statuts de documents — chaque nuit à 01h00
 * Recalcule : expire / a_renouveler / valide
 */
Schedule::job(new UpdateStatutsDocumentsJob())
    ->dailyAt('01:00')
    ->name('update-statuts-documents')
    ->withoutOverlapping();

// ── HEBDOMADAIRE ─────────────────────────────────────────────

/**
 * Scan des checklists manquantes — chaque vendredi à 17h00
 * Alerte si aucune checklist hebdomadaire n'a été soumise cette semaine
 */
Schedule::call(function () {
    app(\App\Services\AlerteService::class)->alertesChecklistsManquantes();
})
    ->weeklyOn(5, '17:00') // 5 = vendredi
    ->name('scan-checklists-manquantes');

// ── MENSUEL ──────────────────────────────────────────────────

/**
 * Précalcul des statistiques mensuelles — le 1er de chaque mois à 05h30
 * Calcule les agrégats et remplit le cache pour le dashboard
 */
Schedule::job(new PrecalculerStatsJob())
    ->monthlyOn(1, '05:30')
    ->name('precalculer-stats-mensuelles')
    ->withoutOverlapping();

/**
 * Purge des anciennes alertes traitées — le 1er de chaque mois
 * Supprime les alertes résolues/ignorées vieilles de plus de 3 mois
 */
Schedule::call(function () {
    $nb = \App\Models\Alerte::whereIn('statut', ['traitee', 'ignoree'])
        ->where('created_at', '<', now()->subMonths(3))
        ->delete();
    \Illuminate\Support\Facades\Log::info("[Scheduler] {$nb} alertes anciennes purgées.");
})
    ->monthlyOn(1, '04:00')
    ->name('purge-alertes-anciennes');

/**
 * Purge des logs d'activité anciens — le 1er de chaque mois
 * Supprime les entrées spatie/activitylog vieilles de plus de 6 mois
 */
Schedule::call(function () {
    \Spatie\Activitylog\Models\Activity::where('created_at', '<', now()->subMonths(6))->delete();
    \Illuminate\Support\Facades\Log::info("[Scheduler] Purge logs activité terminée.");
})
    ->monthlyOn(1, '03:00')
    ->name('purge-activity-logs');