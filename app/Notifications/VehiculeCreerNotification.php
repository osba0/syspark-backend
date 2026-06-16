<?php
namespace App\Notifications;

class VehiculeCreerNotification extends BaseNotification
{
    public function __construct(
        private readonly array $vehicule  // ['id', 'immatriculation', 'marque', 'modele']
    ) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'vehicule.cree',
            'titre'   => "Nouveau véhicule ajouté",
            'message' => "Le véhicule {$this->vehicule['marque']} {$this->vehicule['modele']} ({$this->vehicule['immatriculation']}) a été ajouté à la flotte.",
            'niveau'  => 'success',
            'lien'    => "/vehicules/{$this->vehicule['id']}",
            'meta'    => $this->vehicule,
        ];
    }
}
