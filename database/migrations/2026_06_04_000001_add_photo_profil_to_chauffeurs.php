<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chauffeurs', function (Blueprint $table) {
            $table->string('photo_profil', 255)
                ->nullable()
                ->after('photo')
                ->comment('Photo de profil du chauffeur (avatar)');
        });
    }

    public function down(): void
    {
        Schema::table('chauffeurs', function (Blueprint $table) {
            $table->dropColumn('photo_profil');
        });
    }
};
