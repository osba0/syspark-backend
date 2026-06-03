<?php

namespace App\Policies;

use App\Models\Carburant;
use App\Models\User;

class CarburantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('carburant.viewAny');
    }

    public function view(User $user, Carburant $carburant): bool
    {
        return $user->can('carburant.view');
    }

    public function create(User $user): bool
    {
        return $user->can('carburant.create');
    }

    public function update(User $user, Carburant $carburant): bool
    {
        return $user->can('carburant.update');
    }

    public function delete(User $user, Carburant $carburant): bool
    {
        return $user->can('carburant.delete');
    }

    public function gererDotations(User $user): bool
    {
        return $user->can('carburant.gererDotations');
    }
}
