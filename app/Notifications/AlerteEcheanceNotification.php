<?php

namespace App\Notifications;

use App\Models\Alerte;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlerteEcheanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alerte $alerte) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alerte = $this->alerte;

        // Couleur du sujet selon la criticité
        $prefix = match($alerte->niveau) {
            'danger'  => '🔴 URGENT',
            'warning' => '🟡 Attention',
            default   => '🔵 Info',
        };

        return (new MailMessage)
            ->subject("{$prefix} — {$alerte->titre}")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line($alerte->message)
            ->when($alerte->echeance, fn ($m) =>
                $m->line("**Échéance :** " . $alerte->echeance->format('d/m/Y'))
            )
            ->when($alerte->jours_restants !== null, fn ($m) =>
                $m->line($alerte->jours_restants < 0
                    ? "⚠️ Dépassé depuis " . abs($alerte->jours_restants) . " jour(s)."
                    : "⏱️ Il reste " . $alerte->jours_restants . " jour(s).")
            )
            ->action('Voir dans l\'application', url('/alertes/' . $alerte->id))
            ->line('---')
            ->line('Cet email a été envoyé automatiquement par le système de gestion du parc automobile.')
            ->salutation('Cordialement, le Système de Gestion du Parc');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'alerte_id'   => $this->alerte->id,
            'type'        => $this->alerte->type_alerte,
            'titre'       => $this->alerte->titre,
            'message'     => $this->alerte->message,
            'niveau'      => $this->alerte->niveau,
            'echeance'    => $this->alerte->echeance?->toDateString(),
            'vehicule_id' => $this->alerte->vehicule_id,
        ];
    }
}
