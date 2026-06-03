<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs')->nullOnDelete();
            $table->foreignId('axe_livraison_id')->nullable()->constrained('axes_livraison')->nullOnDelete();
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->nullOnDelete();

            // Dates
            $table->date('date_travaux');
            $table->date('date_entree')->nullable();        // Entrée chez le fournisseur
            $table->date('date_sortie')->nullable();        // Sortie chez le fournisseur

            // Kilométrage
            $table->unsignedInteger('kilometrage')->default(0);

            // Classification (correspond aux onglets du fichier Excel)
            $table->enum('type_operation', [
                'entretien',        // Vidange, filtres, révision planifiée
                'reparation',       // Panne, casse
                'pneu',             // Achat ou réparation pneu
                'equipement',       // Accessoires, équipements
                'contravention',    // Amendes
                'carrosserie',      // Peinture, soudure, tôlerie
                'visite_technique', // VT
            ]);
            $table->string('categorie_travaux', 100)->nullable(); // Ex: Vidange, Électricité, Mécanique

            // Description
            $table->string('titre', 200);                   // Résumé court
            $table->text('description_travaux');
            $table->text('fournitures_mo')->nullable();     // Détail pièces + main d'œuvre

            // Financier (FCFA)
            $table->decimal('montant_ht', 12, 2)->default(0);
            $table->decimal('tva', 5, 2)->default(0);       // Taux TVA en %
            $table->decimal('montant_ttc', 12, 2)->default(0);

            // Facturation
            $table->string('numero_facture', 100)->nullable();
            $table->date('date_facture')->nullable();

            // Statut
            $table->enum('statut', ['planifie', 'en_cours', 'termine', 'annule'])->default('planifie');

            // Lien bon de commande (FK ajoutée après création de la table bons_commande)
            $table->unsignedBigInteger('bon_commande_id')->nullable();

            // Lien signalement déclencheur
            $table->unsignedBigInteger('signalement_id')->nullable();

            // Validation pour montants importants
            $table->boolean('necessite_approbation')->default(false);
            $table->foreignId('approuve_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approuve_le')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('vehicule_id');
            $table->index('agence_id');
            $table->index('fournisseur_id');
            $table->index('type_operation');
            $table->index('statut');
            $table->index('date_travaux');
            $table->index('chauffeur_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
