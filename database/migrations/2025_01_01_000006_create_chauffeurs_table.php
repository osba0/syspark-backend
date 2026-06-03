<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chauffeurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Identité
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('adresse', 255)->nullable();
            $table->string('cni', 50)->nullable();          // Carte Nationale d'Identité

            // Permis
            $table->string('numero_permis', 50)->nullable();
            $table->string('categorie_permis', 20)->nullable(); // B, D, BE, etc.
            $table->date('date_delivrance_permis')->nullable();
            $table->date('date_expiration_permis')->nullable();

            // Emploi
            $table->date('date_embauche')->nullable();
            $table->string('matricule_interne', 50)->nullable();
            $table->enum('statut', ['actif', 'suspendu', 'quitte', 'conge'])->default('actif');

            $table->string('photo', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('agence_id');
            $table->index('user_id');
            $table->index('statut');
            $table->index('date_expiration_permis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chauffeurs');
    }
};
