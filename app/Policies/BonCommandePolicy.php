<?php

namespace App\Policies;

use App\Models\BonCommande;
use App\Models\User;

class BonCommandePolicy
{
    public function viewAny(User $user): bool { return $user->can('bonCommande.viewAny'); }

    public function view(User $user, BonCommande $bc): bool
    {
        if (!$user->can('bonCommande.view')) return false;
        if ($user->hasRole('resp_agence')) return $bc->agence_id === $user->agence_id;
        return true;
    }

    public function create(User $user): bool  { return $user->can('bonCommande.create'); }

    public function update(User $user, BonCommande $bc): bool
    {
        if (!$user->can('bonCommande.update')) return false;
        if (!in_array($bc->statut, ['brouillon', 'rejete'])) return false;
        if ($user->hasRole('resp_agence')) return $bc->agence_id === $user->agence_id;
        return true;
    }

    public function delete(User $user, BonCommande $bc): bool
    {
        return $user->can('bonCommande.delete')
            && $bc->statut === 'brouillon'
            && ($bc->cree_par === $user->id || $user->hasAnyRole(['resp_parc', 'super_admin']));
    }

    public function submit(User $user, BonCommande $bc): bool
    {
        return $user->can('bonCommande.submit')
            && ($bc->cree_par === $user->id || $user->hasAnyRole(['resp_parc', 'resp_agence', 'super_admin']));
    }

    public function approve(User $user, BonCommande $bc): bool
    {
        return $user->can('bonCommande.approve')
            && $user->hasAnyRole(['directeur', 'resp_parc', 'comptable', 'super_admin']);
    }

    public function reject(User $user, BonCommande $bc): bool
    {
        return $user->can('bonCommande.reject')
            && $user->hasAnyRole(['directeur', 'resp_parc', 'comptable', 'super_admin']);
    }
}
