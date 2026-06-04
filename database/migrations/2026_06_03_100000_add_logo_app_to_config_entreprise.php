<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_entreprise', function (Blueprint $table) {
            $table->string('logo_app', 255)
                ->nullable()
                ->after('logo')
                ->comment('Logo de l\'application — affiché dans le sidebar');
        });
    }

    public function down(): void
    {
        Schema::table('config_entreprise', function (Blueprint $table) {
            $table->dropColumn('logo_app');
        });
    }
};
