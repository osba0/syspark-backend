<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bons_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs')->nullOnDelete();
            $table->foreignId('vehicule_id')->nullable()->constrained('vehicules')->nullOnDelete();

            $table->string('numero_bc', 50)->unique();     // Ex: BC-2024-001
            $table->date('date_commande');
            $table->date('date_livraison_prevue')->nullable();
            $table->date('date_livraison_reelle')->nullable();

            // Lignes de commande en JSON
            // [{ "description": "...", "quantite": 2, "prix_unitaire": 15000, "total": 30000 }]
            $table->json('lignes');

            // Financier (FCFA)
            $table->decimal('montant_ht', 12, 2)->default(0);
            $table->decimal('tva', 5, 2)->default(18);     // TVA Sénégal : 18%
            $table->decimal('montant_ttc', 12, 2)->default(0);

            $table->enum('statut', [
                'brouillon',    // En cours de rédaction
                'soumis',       // Soumis pour approbation
                'approuve',     // Approuvé
                'rejete',       // Rejeté
                'execute',      // Commande exécutée / livrée
                'annule',       // Annulé
            ])->default('brouillon');

            // Workflow approbation
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approuve_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approuve_le')->nullable();
            $table->text('motif_rejet')->nullable();

            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('agence_id');
            $table->index('fournisseur_id');
            $table->index('statut');
            $table->index('date_commande');
        });

        // Ajout des FK différées (évite les références circulaires)
        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreign('bon_commande_id')->references('id')->on('bons_commande')->nullOnDelete();
            $table->foreign('signalement_id')->references('id')->on('signalements')->nullOnDelete();
        });

        Schema::table('signalements', function (Blueprint $table) {
            $table->foreign('maintenance_id')->references('id')->on('maintenances')->nullOnDelete();
            $table->foreign('checklist_id')->references('id')->on('checklists')->nullOnDelete();
        });

        Schema::table('checklists', function (Blueprint $table) {
            $table->foreign('signalement_genere_id')->references('id')->on('signalements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->dropForeign(['signalement_genere_id']);
        });
        Schema::table('signalements', function (Blueprint $table) {
            $table->dropForeign(['maintenance_id']);
            $table->dropForeign(['checklist_id']);
        });
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropForeign(['bon_commande_id']);
            $table->dropForeign(['signalement_id']);
        });
        Schema::dropIfExists('bons_commande');
    }
};
