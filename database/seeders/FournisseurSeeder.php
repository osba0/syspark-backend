<?php

namespace Database\Seeders;

use App\Models\Fournisseur;
use Illuminate\Database\Seeder;

class FournisseurSeeder extends Seeder
{
    public function run(): void
    {
        // 16 fournisseurs identifiés dans RECUEIL_TRAVAUX_ET_STAT_DU_PARC.xlsx
        $fournisseurs = [
            // Concessionnaires / distributeurs
            [
                'nom'        => 'TATA Motors Sénégal',
                'type'       => 'concessionnaire',
                'specialite' => 'Véhicules TATA, Mécanique, Pièces détachées',
                'ville'      => 'Dakar',
                'telephone'  => '+221 33 800 00 10',
            ],
            [
                'nom'        => 'CFAO Motors',
                'type'       => 'concessionnaire',
                'specialite' => 'Toyota, Peugeot, Citroën, Mécanique',
                'ville'      => 'Dakar',
                'telephone'  => '+221 33 849 70 00',
            ],
            [
                'nom'        => 'Tractafric Motors',
                'type'       => 'concessionnaire',
                'specialite' => 'Ford, Mécanique générale',
                'ville'      => 'Dakar',
                'telephone'  => '+221 33 800 00 11',
            ],
            [
                'nom'        => 'SENCAR',
                'type'       => 'concessionnaire',
                'specialite' => 'Renault, Mécanique',
                'ville'      => 'Dakar',
                'telephone'  => '+221 33 800 00 12',
            ],
            // Garages mécaniques
            [
                'nom'        => 'Garage Central Dakar',
                'type'       => 'garage',
                'specialite' => 'Mécanique générale, Électricité automobile',
                'ville'      => 'Dakar',
                'telephone'  => '+221 77 500 00 01',
            ],
            [
                'nom'        => 'Auto Service Sodida',
                'type'       => 'garage',
                'specialite' => 'Vidange, Freinage, Suspension',
                'ville'      => 'Dakar',
                'telephone'  => '+221 77 500 00 02',
            ],
            [
                'nom'        => 'Garage Mécauto',
                'type'       => 'garage',
                'specialite' => 'Mécanique lourde, Carrosserie',
                'ville'      => 'Dakar',
                'telephone'  => '+221 77 500 00 03',
            ],
            [
                'nom'        => 'Atelier Diallo & Fils',
                'type'       => 'garage',
                'specialite' => 'Soudure, Tôlerie, Peinture',
                'ville'      => 'Dakar',
                'telephone'  => '+221 77 500 00 04',
            ],
            // Spécialistes pneus
            [
                'nom'        => 'Pneus Services Sénégal',
                'type'       => 'pneu',
                'specialite' => 'Vente et réparation pneus, Équilibrage',
                'ville'      => 'Dakar',
                'telephone'  => '+221 77 600 00 01',
            ],
            [
                'nom'        => 'Vulcanisation Express',
                'type'       => 'pneu',
                'specialite' => 'Vulcanisation, Réparation pneus',
                'ville'      => 'Dakar',
                'telephone'  => '+221 77 600 00 02',
            ],
            [
                'nom'        => 'Sénégal Pneus',
                'type'       => 'pneu',
                'specialite' => 'Michelin, Bridgestone, Goodyear',
                'ville'      => 'Dakar',
                'telephone'  => '+221 33 800 00 20',
            ],
            // Carburant
            [
                'nom'        => 'Total Energies Sénégal',
                'type'       => 'carburant',
                'specialite' => 'Carburant, Lubrifiants',
                'ville'      => 'Dakar',
                'telephone'  => '+221 33 849 72 00',
            ],
            [
                'nom'        => 'SAR (Société Africaine de Raffinage)',
                'type'       => 'carburant',
                'specialite' => 'Carburant en gros',
                'ville'      => 'Dakar',
                'telephone'  => '+221 33 800 00 30',
            ],
            // Électricité / Divers
            [
                'nom'        => 'Auto Élec Sénégal',
                'type'       => 'electricite',
                'specialite' => 'Électricité automobile, Diagnostic',
                'ville'      => 'Dakar',
                'telephone'  => '+221 77 700 00 01',
            ],
            [
                'nom'        => 'Vitrage Auto Plus',
                'type'       => 'carrosserie',
                'specialite' => 'Vitrage, Carrosserie, Peinture',
                'ville'      => 'Dakar',
                'telephone'  => '+221 77 700 00 02',
            ],
            [
                'nom'        => 'Garage Tambacounda',
                'type'       => 'garage',
                'specialite' => 'Mécanique générale, Dépannage',
                'ville'      => 'Tambacounda',
                'telephone'  => '+221 77 800 00 01',
            ],
        ];

        foreach ($fournisseurs as $f) {
            Fournisseur::updateOrCreate(['nom' => $f['nom']], $f);
        }

        $this->command->info('✅  ' . count($fournisseurs) . ' fournisseurs créés.');
    }
}
