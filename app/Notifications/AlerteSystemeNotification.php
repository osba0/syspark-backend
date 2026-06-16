<?php
namespace App\Notifications;

class AlerteSystemeNotification extends BaseNotification
{
    public function __construct(
        private readonly string $titre,
        private readonly string $message,
        private readonly string $niveau = 'warning',
        private readonly ?string $lien  = null,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'   => 'systeme.alerte',
            'titre'   => $this->titre,
            'message' => $this->message,
            'niveau'  => $this->niveau,
            'lien'    => $this->lien,
            'meta'    => [],
        ];
    }

    // Toujours envoyer l'email pour les alertes système
    protected function sendEmail(object $notifiable): bool { return true; }
}
