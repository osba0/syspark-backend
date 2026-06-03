<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('axe_livraison_id')->nullable()->constrained('axes_livraison')->nullOnDelete();

            // Acteur : chauffeur (livraison) OU attributaire admin
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->nullOnDelete();
            $table->foreignId('attributaire_id')->nullable()->constrained('users')->nullOnDelete(); // Véhicule admin

            // Période
            $table->date('date_debut');
            $table->date('date_fin')->nullable();           // NULL = en cours

            // Kilométrages
            $table->unsignedInteger('kilometrage_debut')->default(0);
            $table->unsignedInteger('kilometrage_fin')->nullable();

            $table->enum('type_affectation', ['livraison', 'administratif', 'mission'])->default('livraison');
            $table->enum('statut', ['active', 'terminee', 'suspendue'])->default('active');

            // Validation
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validee_le')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Index
            $table->index('vehicule_id');
            $table->index('chauffeur_id');
            $table->index('agence_id');
            $table->index('statut');
            $table->index('date_debut');
            $table->index('date_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affectations');
    }
};
