<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute des colonnes de scoping dénormalisées à activity_log pour permettre
 * un filtrage par rôle (agence / véhicule / chauffeur) sans JOIN coûteux sur
 * subject_type + subject_id, ce qui reste performant même à fort volume.
 *
 * Ces colonnes sont remplies automatiquement par AuditScopeObserver
 * (voir app/Observers/AuditScopeObserver.php) au moment de la création
 * de chaque entrée d'activity log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('agence_id')->nullable()->after('causer_id');
            $table->unsignedBigInteger('vehicule_id')->nullable()->after('agence_id');
            $table->unsignedBigInteger('chauffeur_id')->nullable()->after('vehicule_id');
            // Catégorisation par module pour le filtre "Module" côté UI
            // ex: 'vehicule', 'chauffeur', 'maintenance', 'document', 'signalement'...
            $table->string('module', 50)->nullable()->after('chauffeur_id');

            $table->index('agence_id',   'activity_log_agence_idx');
            $table->index('vehicule_id', 'activity_log_vehicule_idx');
            $table->index('chauffeur_id','activity_log_chauffeur_idx');
            $table->index('module',      'activity_log_module_idx');
            // Index composite pour les requêtes scopées les plus fréquentes
            $table->index(['agence_id', 'created_at'], 'activity_log_agence_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_agence_idx');
            $table->dropIndex('activity_log_vehicule_idx');
            $table->dropIndex('activity_log_chauffeur_idx');
            $table->dropIndex('activity_log_module_idx');
            $table->dropIndex('activity_log_agence_date_idx');
            $table->dropColumn(['agence_id', 'vehicule_id', 'chauffeur_id', 'module']);
        });
    }
};
