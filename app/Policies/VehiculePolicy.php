<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicule;

class VehiculePolicy
{
    /**
     * Super admin bypass total (déclaré dans AppServiceProvider)
     */

    public function viewAny(User $user): bool
    {
        return $user->can('vehicule.viewAny');
    }

    public function view(User $user, Vehicule $vehicule): bool
    {
        if (!$user->can('vehicule.view')) return false;

        // Chauffeur/attributaire : uniquement son véhicule en cours
        if ($user->hasAnyRole(['chauffeur', 'attributaire'])) {
            $affectation = $vehicule->affectationActive;
            if (!$affectation) return false;
            return $affectation->chauffeur?->user_id === $user->id
                || $affectation->attributaire_id === $user->id;
        }

        // Resp agence : uniquement son agence
        if ($user->hasRole('resp_agence')) {
            return $vehicule->agence_id === $user->agence_id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('vehicule.create');
    }

    public function update(User $user, Vehicule $vehicule): bool
    {
        if (!$user->can('vehicule.update')) return false;

        if ($user->hasRole('resp_agence')) {
            return $vehicule->agence_id === $user->agence_id;
        }

        return true;
    }

    public function delete(User $user, Vehicule $vehicule): bool
    {
        if (!$user->can('vehicule.delete')) return false;

        // Seul resp_parc et super_admin peuvent supprimer
        return $user->hasAnyRole(['resp_parc', 'super_admin']);
    }

    public function updateKm(User $user, Vehicule $vehicule): bool
    {
        if (!$user->can('vehicule.updateKm')) return false;

        // Chauffeur : seulement son véhicule
        if ($user->hasRole('chauffeur')) {
            $affectation = $vehicule->affectationActive;
            return $affectation?->chauffeur?->user_id === $user->id;
        }

        return true;
    }

    public function viewTco(User $user, Vehicule $vehicule): bool
    {
        return $user->can('vehicule.viewTco');
    }
}
