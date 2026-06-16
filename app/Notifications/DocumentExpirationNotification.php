<?php
namespace App\Notifications;

class DocumentExpirationNotification extends BaseNotification
{
    public function __construct(private readonly array $doc) {}

    public function toDatabase(object $notifiable): array
    {
        $jours = $this->doc['jours_restants'];
        $urgent = $jours <= 0;

        return [
            'event'   => 'document.expiration',
            'titre'   => $urgent ? "Document expiré !" : "Document bientôt expiré",
            'message' => $urgent
                ? "Le {$this->doc['type']} du véhicule {$this->doc['immatriculation']} a expiré depuis " . abs($jours) . " jour(s)."
                : "Le {$this->doc['type']} du véhicule {$this->doc['immatriculation']} expire dans {$jours} jour(s).",
            'niveau'  => $urgent ? 'danger' : ($jours <= 7 ? 'warning' : 'info'),
            'lien'    => "/vehicules/{$this->doc['vehicule_id']}",
            'meta'    => $this->doc,
        ];
    }

    // Les chauffeurs aussi reçoivent l'email pour ce type
    protected function sendEmail(object $notifiable): bool { return true; }
}
