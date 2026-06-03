<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\User;

class MaintenancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.viewAny');
    }

    public function view(User $user, Maintenance $maintenance): bool
    {
        if (!$user->can('maintenance.view')) return false;
        if ($user->hasRole('resp_agence')) {
            return $maintenance->agence_id === $user->agence_id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.create');
    }

    public function update(User $user, Maintenance $maintenance): bool
    {
        if (!$user->can('maintenance.update')) return false;
        if ($user->hasRole('resp_agence')) {
            return $maintenance->agence_id === $user->agence_id;
        }
        return true;
    }

    public function delete(User $user, Maintenance $maintenance): bool
    {
        return $user->can('maintenance.delete')
            && $user->hasAnyRole(['resp_parc', 'super_admin']);
    }

    public function approve(User $user, Maintenance $maintenance): bool
    {
        return $user->can('maintenance.approve')
            && $user->hasAnyRole(['directeur', 'resp_parc', 'super_admin']);
    }

    public function cloturer(User $user, Maintenance $maintenance): bool
    {
        if (!$user->can('maintenance.cloturer')) return false;
        if ($user->hasRole('resp_agence')) {
            return $maintenance->agence_id === $user->agence_id;
        }
        return true;
    }
}
