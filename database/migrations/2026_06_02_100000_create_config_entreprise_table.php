<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_entreprise', function (Blueprint $table) {
            $table->id();
            $table->string('nom',          150)->default('Parc Auto');
            $table->string('ninea',         50)->nullable();
            $table->string('rc',            50)->nullable();
            $table->string('adresse',      255)->nullable();
            $table->string('telephone',     50)->nullable();
            $table->string('email',        150)->nullable();
            $table->string('site_web',     150)->nullable();
            $table->string('logo',         255)->nullable()->comment('Chemin du logo uploadé');
            $table->string('couleur_1',     20)->default('#1E3A5F')->comment('Couleur primaire');
            $table->string('couleur_2',     20)->default('#2E86C1')->comment('Couleur secondaire');
            $table->string('couleur_3',     20)->default('#1ABC9C')->comment('Couleur accent');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Insérer la configuration par défaut
        DB::table('config_entreprise')->insert([
            'nom'        => 'Gestion Parc Auto',
            'couleur_1'  => '#1E3A5F',
            'couleur_2'  => '#2E86C1',
            'couleur_3'  => '#1ABC9C',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('config_entreprise');
    }
};
