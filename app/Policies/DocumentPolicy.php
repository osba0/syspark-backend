<?php

namespace App\Policies;

use App\Models\DocumentVehicule;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool  { return $user->can('document.viewAny'); }

    public function view(User $user, DocumentVehicule $document): bool
    {
        if (!$user->can('document.view')) return false;
        if ($user->hasRole('resp_agence')) {
            return $document->vehicule?->agence_id === $user->agence_id;
        }
        return true;
    }

    public function create(User $user): bool   { return $user->can('document.create'); }

    public function update(User $user, DocumentVehicule $document): bool
    {
        if (!$user->can('document.update')) return false;
        if ($user->hasRole('resp_agence')) {
            return $document->vehicule?->agence_id === $user->agence_id;
        }
        return true;
    }

    public function delete(User $user, DocumentVehicule $document): bool
    {
        return $user->can('document.delete') && $user->hasAnyRole(['resp_parc', 'super_admin']);
    }

    public function renouveler(User $user, DocumentVehicule $document): bool
    {
        return $user->can('document.renouveler');
    }
}
