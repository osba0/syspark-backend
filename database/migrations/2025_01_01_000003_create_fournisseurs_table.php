<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('type', 50)->nullable();        // garage, concessionnaire, pneu, carburant
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('specialite', 150)->nullable(); // Ex: Mécanique, Électricité, Carrosserie
            $table->string('ninea', 50)->nullable();       // Numéro fiscal sénégalais
            $table->boolean('est_actif')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('est_actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};
