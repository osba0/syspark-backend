<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit dotations_carburant :
 * - chauffeur_id : dotation optionnellement liée à un chauffeur spécifique
 * - litres_dotes : volume alloué en litres (en plus du montant FCFA)
 * - litres_consommes : réel consommé en litres (calculé automatiquement)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dotations_carburant', function (Blueprint $table) {
            $table->foreignId('chauffeur_id')
                ->nullable()
                ->after('vehicule_id')
                ->constrained('chauffeurs')
                ->nullOnDelete();

            $table->decimal('litres_dotes', 8, 2)
                ->default(0)
                ->after('montant_dote')
                ->comment('Volume alloué en litres');

            $table->decimal('litres_consommes', 8, 2)
                ->default(0)
                ->after('montant_consomme')
                ->comment('Volume réellement consommé en litres');
        });

        // Mettre à jour l'index unique pour inclure chauffeur_id optionnel
        // On supprime l'ancien index unique vehicule+mois+annee
        // et on crée un partiel : un véhicule ne peut avoir qu'une dotation
        // par mois/année (chauffeur_id est optionnel et ne change pas l'unicité)
    }

    public function down(): void
    {
        Schema::table('dotations_carburant', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chauffeur_id');
            $table->dropColumn(['litres_dotes', 'litres_consommes']);
        });
    }
};
