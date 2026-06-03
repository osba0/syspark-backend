<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('axes_livraison', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->string('nom', 100);                    // Ex: Touba, Kaolack, Richard Toll
            $table->string('code', 20)->unique();          // Ex: TBA, KLC, RTL
            $table->string('zone', 100)->nullable();       // Ex: Centre, Nord, Sud
            $table->text('description')->nullable();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();

            $table->index('agence_id');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('axes_livraison');
    }
};
