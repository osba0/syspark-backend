<?php
namespace App\Notifications;

class ChauffeurCreerNotification extends BaseNotification
{
    public function __construct(
        private readonly array $chauffeur  // ['id', 'nom_complet', 'matricule_interne']
    ) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'chauffeur.cree',
            'titre'   => "Nouveau chauffeur enregistré",
            'message' => "{$this->chauffeur['nom_complet']} (matricule {$this->chauffeur['matricule_interne']}) a été ajouté au système.",
            'niveau'  => 'success',
            'lien'    => "/chauffeurs/{$this->chauffeur['id']}",
            'meta'    => $this->chauffeur,
        ];
    }
}
