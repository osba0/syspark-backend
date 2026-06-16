<?php
namespace App\Notifications;

class SignalementStatutNotification extends BaseNotification
{
    public function __construct(private readonly array $signalement) {}

    public function toDatabase(object $notifiable): array
    {
        $labels = ['en_cours' => 'pris en charge', 'resolu' => 'résolu', 'ferme' => 'fermé'];
        $label  = $labels[$this->signalement['statut']] ?? $this->signalement['statut'];
        return [
            'event'   => 'signalement.statut',
            'titre'   => "Signalement {$label}",
            'message' => "Le signalement sur {$this->signalement['immatriculation']} a été marqué comme {$label}.",
            'niveau'  => $this->signalement['statut'] === 'resolu' ? 'success' : 'info',
            'lien'    => "/signalements/{$this->signalement['id']}",
            'meta'    => $this->signalement,
        ];
    }

    // Chauffeur reçoit aussi l'email quand son signalement change de statut
    protected function sendEmail(object $notifiable): bool { return true; }
}
