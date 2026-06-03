<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $agenceZI    = Agence::where('code', 'ZI')->first();
        $agenceSodida = Agence::where('code', 'SODIDA')->first();
        $agenceTamba = Agence::where('code', 'TAMBA')->first();
        $agenceSiege  = Agence::where('code', 'SIEGE')->first();

        $users = [
            // Super Admin (aucune agence)
            [
                'agence_id' => null,
                'name'      => 'Admin',
                'prenom'    => 'Super',
                'email'     => 'admin@parcauto.sn',
                'password'  => Hash::make('Admin@2025!'),
                'fonction'  => 'Administrateur Système',
                'est_actif' => true,
                'role'      => 'super_admin',
            ],
            // Directeur Général (siège, toutes agences)
            [
                'agence_id' => $agenceSiege?->id,
                'name'      => 'Ndiaye',
                'prenom'    => 'Moussa',
                'email'     => 'dg@parcauto.sn',
                'password'  => Hash::make('DG@2025!'),
                'fonction'  => 'Directeur Général',
                'est_actif' => true,
                'role'      => 'directeur',
            ],
            // Responsable de Parc (siège)
            [
                'agence_id' => $agenceSiege?->id,
                'name'      => 'Diallo',
                'prenom'    => 'Ibrahima',
                'email'     => 'resp.parc@parcauto.sn',
                'password'  => Hash::make('RespParc@2025!'),
                'fonction'  => 'Responsable de Parc',
                'est_actif' => true,
                'role'      => 'resp_parc',
            ],
            // Responsable Agence ZI
            [
                'agence_id' => $agenceZI?->id,
                'name'      => 'Sow',
                'prenom'    => 'Fatou',
                'email'     => 'resp.zi@parcauto.sn',
                'password'  => Hash::make('RespZI@2025!'),
                'fonction'  => 'Responsable Agence ZI',
                'est_actif' => true,
                'role'      => 'resp_agence',
            ],
            // Responsable Agence Tambacounda
            [
                'agence_id' => $agenceTamba?->id,
                'name'      => 'Balde',
                'prenom'    => 'Mamadou',
                'email'     => 'resp.tamba@parcauto.sn',
                'password'  => Hash::make('RespTamba@2025!'),
                'fonction'  => 'Responsable Agence Tamba',
                'est_actif' => true,
                'role'      => 'resp_agence',
            ],
            // Chauffeur de test
            [
                'agence_id' => $agenceZI?->id,
                'name'      => 'Diop',
                'prenom'    => 'Oumar',
                'email'     => 'chauffeur1@parcauto.sn',
                'password'  => Hash::make('Chauffeur@2025!'),
                'fonction'  => 'Chauffeur Livreur',
                'est_actif' => true,
                'role'      => 'chauffeur',
            ],
            // Comptable / DAF
            [
                'agence_id' => $agenceSiege?->id,
                'name'      => 'Fall',
                'prenom'    => 'Aissatou',
                'email'     => 'comptable@parcauto.sn',
                'password'  => Hash::make('Compta@2025!'),
                'fonction'  => 'Directrice Administrative et Financière',
                'est_actif' => true,
                'role'      => 'comptable',
            ],
            // Attributaire administratif
            [
                'agence_id' => $agenceSiege?->id,
                'name'      => 'Kane',
                'prenom'    => 'Souleymane',
                'email'     => 'attributaire@parcauto.sn',
                'password'  => Hash::make('Attrib@2025!'),
                'fonction'  => 'Directeur Commercial',
                'est_actif' => true,
                'role'      => 'attributaire',
            ],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );

            // Assigner le rôle (spatie/permission)
            $user->syncRoles([$role]);
        }

        $this->command->info('✅  ' . count($users) . ' utilisateurs créés.');
        $this->command->newLine();
        $this->command->table(
            ['Rôle', 'Email', 'Mot de passe'],
            [
                ['super_admin',  'admin@parcauto.sn',        'Admin@2025!'],
                ['directeur',    'dg@parcauto.sn',           'DG@2025!'],
                ['resp_parc',    'resp.parc@parcauto.sn',    'RespParc@2025!'],
                ['resp_agence',  'resp.zi@parcauto.sn',      'RespZI@2025!'],
                ['resp_agence',  'resp.tamba@parcauto.sn',   'RespTamba@2025!'],
                ['chauffeur',    'chauffeur1@parcauto.sn',   'Chauffeur@2025!'],
                ['comptable',    'comptable@parcauto.sn',    'Compta@2025!'],
                ['attributaire', 'attributaire@parcauto.sn', 'Attrib@2025!'],
            ]
        );
    }
}
