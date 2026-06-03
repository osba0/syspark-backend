<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pneumatiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->nullOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs')->nullOnDelete();

            $table->date('date');
            $table->enum('type_operation', [
                'achat_neuf',       // Achat pneu neuf
                'reparation',       // Réparation / vulcanisation
                'permutation',      // Rotation des pneus
                'recreusage',       // Recreusage
            ]);

            // Position(s) concernée(s)
            $table->string('position', 100)->nullable(); // AV_G, AV_D, AR_G, AR_D, Secours

            // Caractéristiques
            $table->string('marque_pneu', 100)->nullable();  // Michelin, Bridgestone, etc.
            $table->string('dimension', 50)->nullable();     // Ex: 185/65 R15
            $table->unsignedTinyInteger('quantite')->default(1);

            // Financier
            $table->decimal('prix_unitaire', 10, 2)->default(0);
            $table->decimal('montant_total', 12, 2)->default(0);

            // Kilométrage
            $table->unsignedInteger('kilometrage')->nullable();

            $table->text('commentaire')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index
            $table->index('vehicule_id');
            $table->index('agence_id');
            $table->index('date');
            $table->index('type_operation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pneumatiques');
    }
};
