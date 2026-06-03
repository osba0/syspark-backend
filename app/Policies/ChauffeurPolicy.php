<?php

namespace App\Policies;

use App\Models\Chauffeur;
use App\Models\User;

class ChauffeurPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chauffeur.viewAny');
    }

    public function view(User $user, Chauffeur $chauffeur): bool
    {
        if (!$user->can('chauffeur.view')) return false;

        if ($user->hasRole('resp_agence')) {
            return $chauffeur->agence_id === $user->agence_id;
        }
        if ($user->hasRole('chauffeur')) {
            return $chauffeur->user_id === $user->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('chauffeur.create');
    }

    public function update(User $user, Chauffeur $chauffeur): bool
    {
        if (!$user->can('chauffeur.update')) return false;
        if ($user->hasRole('resp_agence')) {
            return $chauffeur->agence_id === $user->agence_id;
        }
        return true;
    }

    public function delete(User $user, Chauffeur $chauffeur): bool
    {
        if (!$user->can('chauffeur.delete')) return false;
        return $user->hasAnyRole(['resp_parc', 'super_admin']);
    }
}
