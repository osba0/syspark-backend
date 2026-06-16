<?php
namespace App\Notifications;

class ChauffeurSupprimeNotification extends BaseNotification
{
    public function __construct(private readonly array $chauffeur) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'chauffeur.supprime',
            'titre'   => "Chauffeur supprimé",
            'message' => "{$this->chauffeur['nom_complet']} (matricule {$this->chauffeur['matricule_interne']}) a été retiré du système par {$this->chauffeur['supprime_par']}.",
            'niveau'  => 'warning',
            'lien'    => null,
            'meta'    => $this->chauffeur,
        ];
    }

    protected function sendEmail(object $notifiable): bool { return true; }
}
