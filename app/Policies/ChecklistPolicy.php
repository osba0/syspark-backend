<?php

namespace App\Policies;

use App\Models\Checklist;
use App\Models\User;

class ChecklistPolicy
{
    public function viewAny(User $user): bool   { return $user->can('checklist.viewAny'); }

    public function view(User $user, Checklist $checklist): bool
    {
        if (!$user->can('checklist.view')) return false;
        if ($user->hasRole('resp_agence')) return $checklist->agence_id === $user->agence_id;
        if ($user->hasRole('chauffeur'))   return $checklist->chauffeur?->user_id === $user->id;
        return true;
    }

    public function create(User $user): bool    { return $user->can('checklist.create'); }

    public function update(User $user, Checklist $checklist): bool
    {
        if (!$user->can('checklist.create')) return false;
        // Seul l'auteur ou un responsable peut modifier
        if ($user->hasRole('chauffeur')) {
            return $checklist->chauffeur?->user_id === $user->id && $checklist->statut === 'brouillon';
        }
        return true;
    }

    public function submit(User $user, Checklist $checklist): bool
    {
        if (!$user->can('checklist.submit')) return false;
        if ($user->hasRole('chauffeur')) {
            return $checklist->chauffeur?->user_id === $user->id;
        }
        return true;
    }

    public function validate(User $user, Checklist $checklist): bool
    {
        return $user->can('checklist.validate')
            && $user->hasAnyRole(['resp_parc', 'resp_agence', 'super_admin']);
    }

    public function reject(User $user, Checklist $checklist): bool
    {
        return $user->can('checklist.reject')
            && $user->hasAnyRole(['resp_parc', 'resp_agence', 'super_admin']);
    }
}
