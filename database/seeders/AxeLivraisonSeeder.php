<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\AxeLivraison;
use Illuminate\Database\Seeder;

class AxeLivraisonSeeder extends Seeder
{
    public function run(): void
    {
        // Les 23 axes de livraison identifiés dans RECUEIL_TRAVAUX_ET_STAT_DU_PARC.xlsx
        $axes = [
            // Axes depuis ZI Dakar
            ['agence' => 'ZI',     'nom' => 'Dakar Banlieue',    'code' => 'DKR-BNL',  'zone' => 'Grand Dakar'],
            ['agence' => 'ZI',     'nom' => 'Thiès',             'code' => 'THS',       'zone' => 'Ouest'],
            ['agence' => 'ZI',     'nom' => 'Mbour',             'code' => 'MBR',       'zone' => 'Petite Côte'],
            ['agence' => 'ZI',     'nom' => 'Kaolack',           'code' => 'KLC',       'zone' => 'Centre'],
            ['agence' => 'ZI',     'nom' => 'Touba',             'code' => 'TBA',       'zone' => 'Centre'],
            ['agence' => 'ZI',     'nom' => 'Diourbel',          'code' => 'DRB',       'zone' => 'Centre'],
            ['agence' => 'ZI',     'nom' => 'Louga',             'code' => 'LGA',       'zone' => 'Nord'],
            ['agence' => 'ZI',     'nom' => 'Saint-Louis',       'code' => 'STL',       'zone' => 'Nord'],
            ['agence' => 'ZI',     'nom' => 'Richard Toll',      'code' => 'RTL',       'zone' => 'Nord'],
            ['agence' => 'ZI',     'nom' => 'Matam',             'code' => 'MTM',       'zone' => 'Nord-Est'],
            ['agence' => 'SODIDA', 'nom' => 'Pikine / Guédiawaye', 'code' => 'PKN',     'zone' => 'Grand Dakar'],
            ['agence' => 'SODIDA', 'nom' => 'Rufisque',          'code' => 'RFQ',       'zone' => 'Grand Dakar'],
            ['agence' => 'SODIDA', 'nom' => 'Fatick',            'code' => 'FTK',       'zone' => 'Centre'],
            ['agence' => 'TAMBA',  'nom' => 'Tambacounda Ville', 'code' => 'TBA-VL',    'zone' => 'Est'],
            ['agence' => 'TAMBA',  'nom' => 'Bakel',             'code' => 'BKL',       'zone' => 'Est'],
            ['agence' => 'TAMBA',  'nom' => 'Kédougou',          'code' => 'KDG',       'zone' => 'Est'],
            ['agence' => 'TAMBA',  'nom' => 'Vélingara',         'code' => 'VLG',       'zone' => 'Sud-Est'],
            ['agence' => 'ZIG',    'nom' => 'Ziguinchor Ville',  'code' => 'ZIG-VL',    'zone' => 'Sud'],
            ['agence' => 'ZIG',    'nom' => 'Kolda',             'code' => 'KLD',       'zone' => 'Sud'],
            ['agence' => 'ZIG',    'nom' => 'Sédhiou',           'code' => 'SDH',       'zone' => 'Sud'],
            ['agence' => 'ZIG',    'nom' => 'Bignona',           'code' => 'BGN',       'zone' => 'Sud'],
            ['agence' => 'STL',    'nom' => 'Podor',             'code' => 'PDR',       'zone' => 'Nord'],
            ['agence' => 'SIEGE',  'nom' => 'Dakar Plateau',     'code' => 'DKR-PLT',   'zone' => 'Grand Dakar'],
        ];

        $agencesMap = Agence::pluck('id', 'code');

        foreach ($axes as $axe) {
            $agenceId = $agencesMap[$axe['agence']] ?? null;
            if (!$agenceId) continue;

            AxeLivraison::updateOrCreate(
                ['code' => $axe['code']],
                [
                    'agence_id' => $agenceId,
                    'nom'       => $axe['nom'],
                    'code'      => $axe['code'],
                    'zone'      => $axe['zone'],
                ]
            );
        }

        $this->command->info('✅  ' . count($axes) . ' axes de livraison créés.');
    }
}
