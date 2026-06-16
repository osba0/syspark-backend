<?php
namespace App\Notifications;

class VehiculeSupprimeNotification extends BaseNotification
{
    public function __construct(private readonly array $vehicule) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'vehicule.supprime',
            'titre'   => "Véhicule supprimé",
            'message' => "Le véhicule {$this->vehicule['marque']} {$this->vehicule['modele']} ({$this->vehicule['immatriculation']}) a été retiré de la flotte par {$this->vehicule['supprime_par']}.",
            'niveau'  => 'warning',
            'lien'    => null, // la fiche n'existe plus
            'meta'    => $this->vehicule,
        ];
    }

    // Action sensible — toujours notifier par email la direction
    protected function sendEmail(object $notifiable): bool { return true; }
}
