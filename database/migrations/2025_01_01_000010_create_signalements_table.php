<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->nullOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();

            // Origine du signalement
            $table->enum('origine', [
                'chauffeur',        // Signalé par le chauffeur
                'checklist',        // Généré automatiquement depuis une checklist
                'responsable',      // Signalé par le responsable
                'visite_technique', // Découvert à la VT
            ])->default('chauffeur');

            $table->date('date_signalement');
            $table->unsignedInteger('kilometrage')->nullable();

            // Classification de la panne/anomalie
            $table->enum('type_defaut', [
                'panne_moteur',
                'probleme_freins',
                'probleme_pneu',
                'probleme_electrique',
                'probleme_carrosserie',
                'probleme_eclairage',
                'fuite',
                'surchauffe',
                'bruit_anormal',
                'probleme_document',
                'autre',
            ])->default('autre');

            $table->enum('gravite', ['faible', 'moyenne', 'haute', 'critique'])->default('moyenne');
            $table->string('titre', 200);
            $table->text('description');

            // État des éléments (correspond à la fiche de signalement papier)
            $table->json('etat_elements')->nullable(); // pneu_av, pneu_ar, eclairage, etc.

            // Médias
            $table->json('photos')->nullable();        // URLs des photos uploadées

            // Workflow
            $table->enum('statut', [
                'nouveau',          // Vient d'être créé
                'en_cours',         // Pris en charge
                'maintenance_creee',// Ordre de maintenance créé
                'resolu',           // Résolu
                'ferme',            // Fermé sans suite
            ])->default('nouveau');

            // Lien vers la maintenance corrective créée
            $table->unsignedBigInteger('maintenance_id')->nullable();

            // Lien vers la checklist source
            $table->unsignedBigInteger('checklist_id')->nullable();

            $table->foreignId('pris_en_charge_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pris_en_charge_le')->nullable();
            $table->foreignId('resolu_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolu_le')->nullable();
            $table->text('commentaire_resolution')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index
            $table->index('vehicule_id');
            $table->index('chauffeur_id');
            $table->index('agence_id');
            $table->index('statut');
            $table->index('gravite');
            $table->index('date_signalement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};
