<?php

namespace App\Policies;

use App\Models\Signalement;
use App\Models\User;

class SignalementPolicy
{
    public function viewAny(User $user): bool { return $user->can('signalement.viewAny'); }

    public function view(User $user, Signalement $signalement): bool
    {
        if (!$user->can('signalement.view')) return false;
        if ($user->hasRole('resp_agence')) return $signalement->agence_id === $user->agence_id;
        if ($user->hasRole('chauffeur')) {
            return $signalement->chauffeur?->user_id === $user->id
                || $signalement->created_by === $user->id;
        }
        return true;
    }

    public function create(User $user): bool     { return $user->can('signalement.create'); }
    public function update(User $user, Signalement $signalement): bool { return $user->can('signalement.update'); }

    public function prendreEnCharge(User $user, Signalement $signalement): bool
    {
        return $user->can('signalement.prendreEnCharge')
            && $user->hasAnyRole(['resp_parc', 'resp_agence', 'super_admin']);
    }

    public function resoudre(User $user, Signalement $signalement): bool
    {
        return $user->can('signalement.resoudre')
            && $user->hasAnyRole(['resp_parc', 'resp_agence', 'super_admin']);
    }

    public function uploadPhoto(User $user, Signalement $signalement): bool
    {
        if (!$user->can('signalement.uploadPhoto')) return false;
        if ($user->hasRole('chauffeur')) {
            return $signalement->created_by === $user->id;
        }
        return true;
    }
}
