<?php
namespace App\Notifications;

class MaintenanceCreerNotification extends BaseNotification
{
    public function __construct(private readonly array $maintenance) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'maintenance.creee',
            'titre'   => "Nouvelle maintenance",
            'message' => "Maintenance « {$this->maintenance['titre']} » créée pour {$this->maintenance['immatriculation']} ({$this->maintenance['type_operation']}).",
            'niveau'  => 'info',
            'lien'    => "/maintenances/{$this->maintenance['id']}",
            'meta'    => $this->maintenance,
        ];
    }
}
