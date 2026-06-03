<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();

            // Identification
            $table->string('immatriculation', 20)->unique(); // Ex: AA 111 KL, DK 7362 BL
            $table->string('marque', 100);                   // Toyota, TATA, Renault
            $table->string('modele', 100);                   // Hilux, LNT, Kangoo
            $table->enum('type_vehicule', ['livraison', 'administratif', 'scooter']);
            $table->string('categorie', 50)->nullable();     // Camionnette, Berline, Scooter
            $table->year('annee_fabrication')->nullable();
            $table->date('date_mise_circulation')->nullable();
            $table->string('couleur', 50)->nullable();
            $table->string('numero_chassis', 100)->unique()->nullable();
            $table->string('numero_moteur', 100)->nullable();
            $table->string('energie', 30)->nullable();       // Diesel, Essence, Hybride

            // Statut
            $table->enum('statut', [
                'actif',
                'en_panne',
                'en_maintenance',
                'en_mission',
                'cede',
                'hors_service'
            ])->default('actif');

            // Kilométrage
            $table->unsignedInteger('kilometrage_actuel')->default(0);
            $table->unsignedInteger('prochain_entretien_km')->nullable();
            $table->date('prochain_entretien_date')->nullable();
            $table->unsignedInteger('intervalle_entretien_km')->default(10000); // 10 000 km par défaut

            // Documents / assurances
            $table->date('date_mise_circulation_officielle')->nullable();
            $table->date('date_derniere_visite_tech')->nullable();
            $table->date('date_prochaine_visite_tech')->nullable();
            $table->date('date_expiration_assurance')->nullable();
            $table->string('numero_assurance', 100)->nullable();
            $table->string('compagnie_assurance', 100)->nullable();

            // Carburant
            $table->string('numero_carte_carburant', 50)->nullable();
            $table->string('type_carburant', 30)->nullable(); // Diesel, Essence, Sans plomb

            // Média
            $table->string('photo_principale', 255)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('agence_id');
            $table->index('type_vehicule');
            $table->index('statut');
            $table->index('immatriculation');
            $table->index('date_prochaine_visite_tech');
            $table->index('date_expiration_assurance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};
