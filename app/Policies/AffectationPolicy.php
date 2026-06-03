<?php

namespace App\Policies;

use App\Models\Affectation;
use App\Models\User;

class AffectationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('affectation.viewAny');
    }

    public function view(User $user, Affectation $affectation): bool
    {
        if (!$user->can('affectation.view')) return false;
        if ($user->hasRole('resp_agence')) {
            return $affectation->agence_id === $user->agence_id;
        }
        if ($user->hasRole('chauffeur')) {
            return $affectation->chauffeur?->user_id === $user->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('affectation.create');
    }

    public function update(User $user, Affectation $affectation): bool
    {
        if (!$user->can('affectation.update')) return false;
        if ($user->hasRole('resp_agence')) {
            return $affectation->agence_id === $user->agence_id;
        }
        return true;
    }

    public function cloturer(User $user, Affectation $affectation): bool
    {
        if (!$user->can('affectation.cloturer')) return false;
        if ($user->hasRole('resp_agence')) {
            return $affectation->agence_id === $user->agence_id;
        }
        return true;
    }
}
