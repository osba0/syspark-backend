<?php
namespace App\Notifications;

class AffectationClotureeNotification extends BaseNotification
{
    public function __construct(private readonly array $affectation) {}

    public function toDatabase(object $notifiable): array
    {
        $km = number_format($this->affectation['kilometrage_fin'] ?? 0, 0, ',', ' ');
        return [
            'event'   => 'affectation.cloturee',
            'titre'   => "Désaffectation effectuée",
            'message' => "{$this->affectation['chauffeur']} n'est plus affecté au véhicule {$this->affectation['immatriculation']} (kilométrage retour : {$km} km).",
            'niveau'  => 'info',
            'lien'    => "/vehicules/{$this->affectation['vehicule_id']}",
            'meta'    => $this->affectation,
        ];
    }

    // Le chauffeur reçoit aussi l'email de fin d'affectation
    protected function sendEmail(object $notifiable): bool { return true; }
}
