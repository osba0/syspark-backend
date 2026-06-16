<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Service centralisé de distribution des notifications.
 *
 * Définit qui reçoit quoi (matrice rôles ↔ événements).
 * Point d'entrée unique — les controllers/observers appellent ici.
 *
 * Extensibilité : ajouter un événement = ajouter une méthode.
 */
class NotificationService
{
    // ── Rôles ─────────────────────────────────────────────────

    private const GESTIONNAIRES  = ['super_admin', 'directeur', 'resp_parc', 'resp_agence'];
    private const DIRECTION      = ['super_admin', 'directeur'];
    private const ADMIN          = ['super_admin'];
    private const FINANCES       = ['super_admin', 'directeur', 'comptable'];

    // ── Helpers ───────────────────────────────────────────────

    private function usersAvecRoles(array $roles, ?int $agenceId = null): Collection
    {
        return User::role($roles)
            ->where('est_actif', true)
            ->when($agenceId, fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->whereNull('agence_id')
                       ->orWhere('agence_id', $agenceId)
                )
            )
            ->get();
    }

    private function notifier(Collection $users, Notification $notification): void
    {
        \Illuminate\Support\Facades\Notification::send($users, $notification);
    }

    // ── Véhicules ─────────────────────────────────────────────

    public function vehiculeCree(array $vehicule): void
    {
        $this->notifier(
            $this->usersAvecRoles(self::GESTIONNAIRES, $vehicule['agence_id'] ?? null),
            new \App\Notifications\VehiculeCreerNotification($vehicule)
        );
    }

    public function vehiculeSupprime(array $vehicule): void
    {
        // Notifier direction + gestionnaires de l'agence — action sensible
        $this->notifier(
            $this->usersAvecRoles(self::GESTIONNAIRES, $vehicule['agence_id'] ?? null),
            new \App\Notifications\VehiculeSupprimeNotification($vehicule)
        );
    }

    // ── Chauffeurs ────────────────────────────────────────────

    public function chauffeurCree(array $chauffeur): void
    {
        $this->notifier(
            $this->usersAvecRoles(self::GESTIONNAIRES, $chauffeur['agence_id'] ?? null),
            new \App\Notifications\ChauffeurCreerNotification($chauffeur)
        );
    }

    public function chauffeurSupprime(array $chauffeur): void
    {
        $this->notifier(
            $this->usersAvecRoles(self::GESTIONNAIRES, $chauffeur['agence_id'] ?? null),
            new \App\Notifications\ChauffeurSupprimeNotification($chauffeur)
        );
    }

    // ── Documents ─────────────────────────────────────────────

    public function documentAjoute(array $doc): void
    {
        $this->notifier(
            $this->usersAvecRoles(self::GESTIONNAIRES, $doc['agence_id'] ?? null),
            new \App\Notifications\DocumentAjouterNotification($doc)
        );
    }

    public function documentSupprime(array $doc): void
    {
        $this->notifier(
            $this->usersAvecRoles(self::GESTIONNAIRES, $doc['agence_id'] ?? null),
            new \App\Notifications\DocumentSupprimeNotification($doc)
        );
    }

    public function documentExpiration(array $doc): void
    {
        // Gestionnaires + chauffeur affecté
        $users = $this->usersAvecRoles(self::GESTIONNAIRES, $doc['agence_id'] ?? null);

        if (!empty($doc['chauffeur_user_id'])) {
            $chauffeur = User::find($doc['chauffeur_user_id']);
            if ($chauffeur) $users = $users->push($chauffeur)->unique('id');
        }

        $this->notifier($users, new \App\Notifications\DocumentExpirationNotification($doc));
    }

    // ── Checklists ────────────────────────────────────────────

    public function checklistSoumise(array $checklist): void
    {
        // Responsables agence uniquement
        $this->notifier(
            $this->usersAvecRoles(['resp_parc', 'resp_agence', 'super_admin'], $checklist['agence_id'] ?? null),
            new \App\Notifications\ChecklistSoumiseNotification($checklist)
        );
    }

    // ── Signalements ──────────────────────────────────────────

    public function signalementCree(array $signalement, ?int $createurUserId = null): void
    {
        // Gestionnaires + créateur si chauffeur
        $users = $this->usersAvecRoles(self::GESTIONNAIRES, $signalement['agence_id'] ?? null);
        $this->notifier($users, new \App\Notifications\SignalementCreerNotification($signalement));
    }

    public function signalementStatutChange(array $signalement, ?int $chauffeurUserId = null): void
    {
        $users = $this->usersAvecRoles(self::GESTIONNAIRES, $signalement['agence_id'] ?? null);

        // Notifier le chauffeur créateur
        if ($chauffeurUserId) {
            $chauffeur = User::find($chauffeurUserId);
            if ($chauffeur) $users = $users->push($chauffeur)->unique('id');
        }

        $this->notifier($users, new \App\Notifications\SignalementStatutNotification($signalement));
    }

    // ── Maintenances ──────────────────────────────────────────

    public function maintenanceCreee(array $maintenance): void
    {
        $this->notifier(
            $this->usersAvecRoles(self::GESTIONNAIRES, $maintenance['agence_id'] ?? null),
            new \App\Notifications\MaintenanceCreerNotification($maintenance)
        );
    }

    public function maintenanceCloturee(array $maintenance): void
    {
        $users = $this->usersAvecRoles(self::GESTIONNAIRES, $maintenance['agence_id'] ?? null);

        // Notifier le chauffeur affecté si existant
        if (!empty($maintenance['chauffeur_user_id'])) {
            $chauffeur = User::find($maintenance['chauffeur_user_id']);
            if ($chauffeur) $users = $users->push($chauffeur)->unique('id');
        }

        $this->notifier($users, new \App\Notifications\MaintenanceCloturerNotification($maintenance));
    }

    // ── Carburant ─────────────────────────────────────────────

    public function pleinCarburant(array $plein): void
    {
        // Seulement gestionnaires finances (pas d'email, pas les chauffeurs)
        $this->notifier(
            $this->usersAvecRoles(['resp_parc', 'resp_agence', 'comptable', 'super_admin'], $plein['agence_id'] ?? null),
            new \App\Notifications\PleinCarburantNotification($plein)
        );
    }

    // ── Affectations ──────────────────────────────────────────

    public function affectationCreee(array $affectation, ?int $chauffeurUserId = null): void
    {
        $users = $this->usersAvecRoles(self::GESTIONNAIRES, $affectation['agence_id'] ?? null);

        // Notifier le chauffeur affecté
        if ($chauffeurUserId) {
            $chauffeur = User::find($chauffeurUserId);
            if ($chauffeur) $users = $users->push($chauffeur)->unique('id');
        }

        $this->notifier($users, new \App\Notifications\AffectationCreerNotification($affectation));
    }

    public function affectationCloturee(array $affectation, ?int $chauffeurUserId = null): void
    {
        $users = $this->usersAvecRoles(self::GESTIONNAIRES, $affectation['agence_id'] ?? null);

        // Notifier le chauffeur désaffecté
        if ($chauffeurUserId) {
            $chauffeur = User::find($chauffeurUserId);
            if ($chauffeur) $users = $users->push($chauffeur)->unique('id');
        }

        $this->notifier($users, new \App\Notifications\AffectationClotureeNotification($affectation));
    }

    // ── Alertes système ───────────────────────────────────────

    public function alerteSysteme(string $titre, string $message, string $niveau = 'warning', ?string $lien = null): void
    {
        $this->notifier(
            $this->usersAvecRoles(self::DIRECTION),
            new \App\Notifications\AlerteSystemeNotification($titre, $message, $niveau, $lien)
        );
    }
}