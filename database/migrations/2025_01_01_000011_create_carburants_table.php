<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table des consommations individuelles
        Schema::create('carburants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->nullOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('axe_livraison_id')->nullable()->constrained('axes_livraison')->nullOnDelete();

            $table->date('date');
            $table->decimal('litres', 8, 2)->default(0);
            $table->decimal('montant', 12, 2)->default(0);       // FCFA
            $table->decimal('prix_unitaire', 8, 2)->nullable();  // Prix/litre
            $table->string('type_carburant', 30)->nullable();    // Diesel, Essence, SP95

            // Kilométrages pour calcul conso/100km
            $table->unsignedInteger('kilometrage')->nullable();   // Km au moment du plein
            $table->unsignedInteger('km_precedent')->nullable();  // Km au plein précédent

            $table->string('numero_transaction', 100)->nullable();
            $table->string('station', 100)->nullable();          // Nom de la station

            $table->boolean('est_complet')->default(true);        // Plein complet ?
            $table->text('notes')->nullable();

            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index
            $table->index('vehicule_id');
            $table->index('chauffeur_id');
            $table->index('agence_id');
            $table->index('date');
            $table->index(['vehicule_id', 'date']);
        });

        // Table des dotations mensuelles par véhicule
        Schema::create('dotations_carburant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->unsignedTinyInteger('mois');                 // 1-12
            $table->unsignedSmallInteger('annee');               // Ex: 2024
            $table->decimal('montant_dote', 12, 2)->default(0); // Budget alloué (FCFA)
            $table->decimal('montant_consomme', 12, 2)->default(0); // Consommé réel
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vehicule_id', 'mois', 'annee']);
            $table->index(['vehicule_id', 'annee']);
            $table->index(['agence_id', 'annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotations_carburant');
        Schema::dropIfExists('carburants');
    }
};
