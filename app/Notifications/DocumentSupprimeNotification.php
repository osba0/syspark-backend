<?php
namespace App\Notifications;

class DocumentSupprimeNotification extends BaseNotification
{
    public function __construct(private readonly array $doc) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'document.supprime',
            'titre'   => "Document supprimé",
            'message' => "Le document « {$this->doc['type']} » du véhicule {$this->doc['immatriculation']} a été supprimé par {$this->doc['supprime_par']}.",
            'niveau'  => 'warning',
            'lien'    => "/vehicules/{$this->doc['vehicule_id']}",
            'meta'    => $this->doc,
        ];
    }

    protected function sendEmail(object $notifiable): bool { return true; }
}
