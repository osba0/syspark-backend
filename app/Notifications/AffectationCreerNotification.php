<?php
namespace App\Notifications;

class AffectationCreerNotification extends BaseNotification
{
    public function __construct(private readonly array $affectation) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'affectation.creee',
            'titre'   => "Nouvelle affectation",
            'message' => "{$this->affectation['chauffeur']} a été affecté au véhicule {$this->affectation['immatriculation']}.",
            'niveau'  => 'info',
            'lien'    => "/affectations/{$this->affectation['id']}",
            'meta'    => $this->affectation,
        ];
    }

    // Chauffeur reçoit aussi son email d'affectation
    protected function sendEmail(object $notifiable): bool { return true; }
}
