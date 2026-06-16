<?php
namespace App\Notifications;

class MaintenanceCloturerNotification extends BaseNotification
{
    public function __construct(private readonly array $maintenance) {}

    public function toDatabase(object $notifiable): array
    {
        $montant = number_format($this->maintenance['montant_ttc'] ?? 0, 0, ',', ' ');
        return [
            'event'   => 'maintenance.cloturee',
            'titre'   => "Maintenance clôturée",
            'message' => "La maintenance de {$this->maintenance['immatriculation']} est clôturée. Montant : {$montant} FCFA.",
            'niveau'  => 'success',
            'lien'    => "/maintenances/{$this->maintenance['id']}",
            'meta'    => $this->maintenance,
        ];
    }
}
