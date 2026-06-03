<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_vehicule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();

            $table->enum('type_document', [
                'carte_grise',
                'assurance',
                'visite_technique',
                'autorisation_circulation',
                'carte_carburant',
                'autre',
            ]);

            $table->string('intitule', 150)->nullable();   // Nom libre du document
            $table->string('numero', 100)->nullable();     // N° de document
            $table->date('date_emission')->nullable();
            $table->date('date_expiration')->nullable();
            $table->string('organisme_emetteur', 150)->nullable(); // Ex: SONAC, compagnie assurance

            // Statut calculé automatiquement
            $table->enum('statut', ['valide', 'expire', 'a_renouveler', 'manquant'])->default('valide');

            // Fichier scanné
            $table->string('fichier_path', 255)->nullable();
            $table->string('fichier_nom', 255)->nullable();

            // Historique : l'ancien document est conservé (est_actif = false)
            $table->boolean('est_actif')->default(true);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index
            $table->index('vehicule_id');
            $table->index('type_document');
            $table->index('date_expiration');
            $table->index('statut');
            $table->index('est_actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_vehicule');
    }
};
