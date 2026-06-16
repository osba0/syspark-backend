<?php

namespace App\Notifications;

use App\Notifications\Channels\QueuedEmailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification de base — héritée par toutes les notifications SysPark.
 *
 * Canaux :
 *  - database     : centre de notifications in-app
 *  - queued_email : enregistre l'email dans notification_emails (table de file)
 *                    → envoyé plus tard par le cron email-queue:process
 *
 * IMPORTANT : les emails ne sont JAMAIS envoyés directement par Laravel ici.
 * Ils sont seulement ENREGISTRÉS pour traitement asynchrone par cron.
 *
 * Structure data() standardisée :
 * {
 *   event    : string        — identifiant de l'événement
 *   titre    : string        — titre court affiché in-app
 *   message  : string        — description longue
 *   niveau   : info|success|warning|danger
 *   lien     : string|null   — URL de la ressource concernée
 *   meta     : object        — données supplémentaires (id, type, etc.)
 * }
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Canaux actifs — surcharger dans les sous-classes si besoin */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        // File d'attente email — uniquement si le rôle le permet et adresse renseignée
        if ($notifiable->email && $this->sendEmail($notifiable)) {
            $canaux[] = QueuedEmailChannel::class;
        }

        return $canaux;
    }

    /** Surcharger pour désactiver l'email pour certains rôles */
    protected function sendEmail(object $notifiable): bool
    {
        // Par défaut : email pour tous sauf chauffeurs et attributaires
        return !in_array($notifiable->getRoleNames()->first(), ['chauffeur', 'attributaire']);
    }

    /** Données stockées en base pour le canal in-app */
    abstract public function toDatabase(object $notifiable): array;

    /** Email — utilisé par QueuedEmailChannel pour générer le contenu */
    public function toMail(object $notifiable): MailMessage
    {
        $data    = $this->toDatabase($notifiable);
        $appName = config('app.name', 'SysParc') ?: 'SysParc';

        $emoji = match($data['niveau'] ?? 'info') {
            'danger'  => '🔴',
            'warning' => '🟡',
            'success' => '🟢',
            default   => '🔵',
        };

        $mail = (new MailMessage)
            ->subject("{$emoji} {$data['titre']}")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line($data['message']);

        if (!empty($data['lien'])) {
            $mail->action("Voir dans {$appName}", url($data['lien']));
        }

        return $mail
            ->line('---')
            ->line("Cet email est envoyé automatiquement par {$appName}.")
            ->salutation("Cordialement, l'équipe {$appName}");
    }
}