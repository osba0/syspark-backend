<?php

namespace Database\Seeders;

use App\Models\Agence;
use Illuminate\Database\Seeder;

class AgenceSeeder extends Seeder
{
    public function run(): void
    {
        // Agences identifiées dans les fichiers Excel du recueil de travaux
        $agences = [
            [
                'nom'       => 'Zone Industrielle',
                'code'      => 'ZI',
                'ville'     => 'Dakar',
                'adresse'   => 'Zone Industrielle, Dakar',
                'telephone' => '+221 33 800 00 01',
                'email'     => 'zi@parcauto.sn',
            ],
            [
                'nom'       => 'Sodida',
                'code'      => 'SODIDA',
                'ville'     => 'Dakar',
                'adresse'   => 'Sodida, Dakar',
                'telephone' => '+221 33 800 00 02',
                'email'     => 'sodida@parcauto.sn',
            ],
            [
                'nom'       => 'Tambacounda',
                'code'      => 'TAMBA',
                'ville'     => 'Tambacounda',
                'adresse'   => 'Tambacounda',
                'telephone' => '+221 33 981 00 01',
                'email'     => 'tamba@parcauto.sn',
            ],
            [
                'nom'       => 'Ziguinchor',
                'code'      => 'ZIG',
                'ville'     => 'Ziguinchor',
                'adresse'   => 'Ziguinchor',
                'telephone' => '+221 33 991 00 01',
                'email'     => 'zig@parcauto.sn',
            ],
            [
                'nom'       => 'Saint-Louis',
                'code'      => 'STL',
                'ville'     => 'Saint-Louis',
                'adresse'   => 'Saint-Louis',
                'telephone' => '+221 33 961 00 01',
                'email'     => 'stl@parcauto.sn',
            ],
            [
                'nom'       => 'Siège Social',
                'code'      => 'SIEGE',
                'ville'     => 'Dakar',
                'adresse'   => 'Dakar Plateau',
                'telephone' => '+221 33 800 00 00',
                'email'     => 'siege@parcauto.sn',
            ],
        ];

        foreach ($agences as $agence) {
            Agence::updateOrCreate(['code' => $agence['code']], $agence);
        }

        $this->command->info('✅  ' . count($agences) . ' agences créées.');
    }
}
