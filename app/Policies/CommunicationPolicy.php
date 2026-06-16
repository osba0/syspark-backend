<?php

namespace App\Policies;

use App\Models\Communication;
use App\Models\User;

class CommunicationPolicy
{
    /** Voir la liste de gestion (toutes communications créées) */
    public function viewAny(User $user): bool
    {
        return $user->can('communication.viewAny');
    }

    /** Voir une communication précise (gestion) */
    public function view(User $user, Communication $communication): bool
    {
        if (!$user->can('communication.viewAny')) return false;

        // super_admin / directeur voient tout
        if ($user->hasAnyRole(['super_admin', 'directeur'])) return true;

        // Sinon : auteur ou communication ciblant son agence / globale
        if ($communication->auteur_id === $user->id) return true;

        if ($communication->agences_cibles === null) return true;

        return $user->agence_id && in_array($user->agence_id, $communication->agences_cibles ?? []);
    }

    /** Créer une communication (en brouillon a minima) */
    public function create(User $user): bool
    {
        return $user->can('communication.create');
    }

    /** Modifier — uniquement l'auteur ou un rôle de direction */
    public function update(User $user, Communication $communication): bool
    {
        if (!$user->can('communication.update')) return false;

        return $communication->auteur_id === $user->id
            || $user->hasAnyRole(['super_admin', 'directeur']);
    }

    /** Supprimer — idem update */
    public function delete(User $user, Communication $communication): bool
    {
        if (!$user->can('communication.delete')) return false;

        return $communication->auteur_id === $user->id
            || $user->hasAnyRole(['super_admin', 'directeur']);
    }

    /** Publier / archiver — nécessite la permission dédiée */
    public function publish(User $user, Communication $communication): bool
    {
        if (!$user->can('communication.publish')) return false;

        return $communication->auteur_id === $user->id
            || $user->hasAnyRole(['super_admin', 'directeur']);
    }
}
