<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Ordre strict : dépendances d'abord
            AgenceSeeder::class,
            AxeLivraisonSeeder::class,
            FournisseurSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
        ]);
    }
}