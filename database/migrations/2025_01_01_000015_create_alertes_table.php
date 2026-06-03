<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->nullable()->constrained('vehicules')->cascadeOnDelete();
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->cascadeOnDelete();
            $table->foreignId('agence_id')->nullable()->constrained('agences')->cascadeOnDelete();

            $table->enum('type_alerte', [
                'visite_technique',         // VT à renouveler
                'assurance',                // Assurance à renouveler
                'permis_chauffeur',         // Permis chauffeur expirant
                'entretien_km',             // Entretien kilométrique dû
                'entretien_periodique',     // Entretien périodique (date)
                'carburant_depassement',    // Dépassement dotation carburant
                'signalement_ouvert',       // Signalement non traité
                'checklist_manquante',      // Checklist hebdo absente
                'vehicule_immobilise',      // Véhicule immobilisé trop longtemps
                'bon_commande_en_attente',  // BC en attente d'approbation
                'document_manquant',        // Document obligatoire absent
            ]);

            $table->string('titre', 200);
            $table->text('message');
            $table->date('echeance')->nullable();          // Date de l'échéance concernée
            $table->integer('jours_restants')->nullable(); // Jours avant échéance (négatif si dépassé)

            $table->enum('niveau', ['info', 'warning', 'danger'])->default('warning');

            $table->enum('statut', [
                'active',   // Non lue, active
                'lue',      // Lue par un utilisateur
                'traitee',  // Traitée / résolue
                'ignoree',  // Ignorée volontairement
            ])->default('active');

            // Destinataires JSON : [{ "user_id": 1, "lu_le": null }]
            $table->json('destinataires')->nullable();

            // Référence à l'objet source
            $table->string('modele_source', 100)->nullable(); // Ex: "Vehicule", "Chauffeur"
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamp('envoyee_le')->nullable();    // Date d'envoi email
            $table->timestamps();

            // Index
            $table->index('vehicule_id');
            $table->index('agence_id');
            $table->index('type_alerte');
            $table->index('statut');
            $table->index('niveau');
            $table->index('echeance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};
