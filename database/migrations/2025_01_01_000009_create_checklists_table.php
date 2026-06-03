<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->nullOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();

            // Type de checklist (correspond aux 3 formulaires existants)
            $table->enum('type_checklist', [
                'hebdomadaire_vehicule',    // Checklist hebdo véhicules utilitaires
                'hebdomadaire_scooter',     // Checklist hebdo scooters
                'visite_technique',         // Préparation visite technique
                'passation',                // Passation entre chauffeurs
                'attribution',              // Attribution initiale du véhicule
            ]);

            $table->date('date');
            $table->unsignedInteger('kilometrage')->nullable();

            // Résultats sous forme JSON (flexible pour les différents types)
            // Structure: { "section": { "item": "ok|moyen|mauvais|oui|non|na" } }
            $table->json('data_json');

            // Résumé des non-conformités détectées
            $table->json('non_conformites')->nullable(); // Items en état "mauvais"

            $table->enum('statut', [
                'brouillon',    // En cours de saisie
                'soumis',       // Soumis par le chauffeur, en attente validation
                'valide',       // Validé par le responsable
                'rejete',       // Rejeté (manques, erreurs)
            ])->default('brouillon');

            // Résultat global
            $table->enum('resultat_global', ['conforme', 'non_conforme', 'en_attente'])->default('en_attente');

            // Validation
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->text('commentaire_validation')->nullable();

            // Signalement auto-généré en cas de non-conformité
            $table->unsignedBigInteger('signalement_genere_id')->nullable();

            $table->text('observations')->nullable();
            $table->timestamps();

            // Index
            $table->index('vehicule_id');
            $table->index('chauffeur_id');
            $table->index('agence_id');
            $table->index('type_checklist');
            $table->index('statut');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklists');
    }
};
