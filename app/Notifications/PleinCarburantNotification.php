<?php
namespace App\Notifications;

class PleinCarburantNotification extends BaseNotification
{
    public function __construct(private readonly array $plein) {}

    public function toDatabase(object $notifiable): array
    {
        $montant = number_format($this->plein['montant'] ?? 0, 0, ',', ' ');
        return [
            'event'   => 'carburant.plein',
            'titre'   => "Plein carburant enregistré",
            'message' => "{$this->plein['litres']} L ({$montant} FCFA) enregistrés pour {$this->plein['immatriculation']} par {$this->plein['chauffeur']}.",
            'niveau'  => 'info',
            'lien'    => "/vehicules/{$this->plein['vehicule_id']}",
            'meta'    => $this->plein,
        ];
    }

    // Pas d'email pour les pleins (trop fréquent)
    protected function sendEmail(object $notifiable): bool { return false; }
}
