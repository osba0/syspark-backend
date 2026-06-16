<?php
namespace App\Notifications;

class DocumentAjouterNotification extends BaseNotification
{
    public function __construct(private readonly array $doc) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'document.ajoute',
            'titre'   => "Document ajouté",
            'message' => "Un document « {$this->doc['type']} » a été ajouté pour le véhicule {$this->doc['immatriculation']}.",
            'niveau'  => 'info',
            'lien'    => "/vehicules/{$this->doc['vehicule_id']}",
            'meta'    => $this->doc,
        ];
    }
}
