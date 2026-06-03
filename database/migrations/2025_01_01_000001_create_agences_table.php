<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agences', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('code', 20)->unique();          // Ex: ZI, SODIDA, TAMBA
            $table->string('ville', 100);
            $table->string('adresse', 255)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('est_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('est_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agences');
    }
};
