<?php
namespace App\Notifications;

class SignalementCreerNotification extends BaseNotification
{
    public function __construct(private readonly array $signalement) {}

    public function toDatabase(object $notifiable): array
    {
        $emoji = match($this->signalement['gravite']) {
            'urgent'  => '🔴',
            'normale' => '🟡',
            default   => '🟢',
        };
        return [
            'event'   => 'signalement.cree',
            'titre'   => "{$emoji} Nouveau signalement",
            'message' => "Signalement {$this->signalement['gravite']} sur {$this->signalement['immatriculation']} : {$this->signalement['titre']}.",
            'niveau'  => match($this->signalement['gravite']) {
                'urgent' => 'danger', 'normale' => 'warning', default => 'info'
            },
            'lien'    => "/signalements/{$this->signalement['id']}",
            'meta'    => $this->signalement,
        ];
    }
}
