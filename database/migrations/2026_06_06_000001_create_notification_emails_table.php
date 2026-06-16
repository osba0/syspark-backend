<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_emails', function (Blueprint $table) {
            $table->id();

            // Destinataire
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();

            // Contenu de l'email (stocké complet — pas de dépendance à la notification d'origine)
            $table->string('subject');
            $table->longText('body_html');     // corps rendu (HTML)
            $table->longText('body_text')->nullable(); // version texte (fallback)

            // Traçabilité — lien vers la notification d'origine (optionnel)
            $table->string('notification_type')->nullable();  // ex: App\Notifications\VehiculeCreerNotification
            $table->uuid('notification_id')->nullable();       // id de la table notifications

            // Statut de la file
            $table->enum('statut', ['pending', 'sent', 'failed'])->default('pending');

            // Relance
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->unsignedTinyInteger('max_tentatives')->default(3);
            $table->timestamp('next_attempt_at')->nullable(); // backoff — null = dès que possible

            // Résultat
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // Index pour le traitement par le cron
            $table->index(['statut', 'next_attempt_at'], 'notif_emails_queue_idx');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_emails');
    }
};
