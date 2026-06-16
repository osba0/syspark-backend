<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();

            // Contenu
            $table->string('titre', 200);
            $table->text('contenu');

            // Type : 'annonce' (info générale) ou 'note_service' (instruction officielle)
            $table->enum('type', ['annonce', 'note_service'])->default('annonce');

            // Gravité — pilote l'affichage (bandeau couleur, modal obligatoire)
            $table->enum('gravite', ['info', 'importante', 'critique'])->default('info');

            // Ciblage par rôles — null = tous les rôles
            // Stocké en JSON: ["chauffeur", "attributaire", ...]
            $table->json('roles_cibles')->nullable();

            // Ciblage par agences — null = toutes agences
            $table->json('agences_cibles')->nullable();

            // Période d'affichage
            $table->timestamp('date_publication')->nullable();
            $table->timestamp('date_expiration')->nullable();

            // Si true (gravite=critique recommandé) : modal bloquante à la
            // connexion tant que l'utilisateur n'a pas accusé lecture
            $table->boolean('accuse_lecture_requis')->default(false);

            // Statut éditorial
            $table->enum('statut', ['brouillon', 'publie', 'archive'])->default('brouillon');

            // Auteur
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();

            // Pièce jointe optionnelle (PDF, image) — gérée via Media Library
            $table->softDeletes();
            $table->timestamps();

            $table->index(['statut', 'date_publication', 'date_expiration'], 'communications_actives_idx');
            $table->index('type');
            $table->index('gravite');
        });

        // Accusés de lecture — un enregistrement par utilisateur/communication
        Schema::create('communication_lectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_id')->constrained('communications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('lu_at')->nullable();
            $table->timestamps();

            $table->unique(['communication_id', 'user_id'], 'communication_lectures_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_lectures');
        Schema::dropIfExists('communications');
    }
};
