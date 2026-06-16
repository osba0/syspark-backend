<?php

namespace App\Notifications\Channels;

use App\Models\NotificationEmail;
use Illuminate\Notifications\Notification;

/**
 * Canal de notification custom — au lieu d'envoyer l'email immédiatement
 * via le mailer, enregistre l'email dans la table notification_emails.
 *
 * Le cron email-queue:process traitera ensuite l'envoi réel.
 *
 * Utilisation : ajouter 'queued_email' dans via() de BaseNotification.
 */
class QueuedEmailChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toMail')) {
            return;
        }

        $mailMessage = $notification->toMail($notifiable);

        // Rendre le MailMessage en HTML via le moteur Markdown de Laravel
        // Constructeur: Markdown(Factory $view, array $options = [])
        $markdown = new \Illuminate\Mail\Markdown(
            app('view'),
            ['theme' => 'default']
        );

        $html = $markdown->render('notifications::email', $mailMessage->toArray());

        // Version texte basique (fallback)
        $text = $this->extraireTexte($mailMessage);

        NotificationEmail::create([
            'user_id'            => $notifiable->id ?? null,
            'to_email'           => $notifiable->routeNotificationFor('mail') ?? $notifiable->email,
            'to_name'            => trim(($notifiable->prenom ?? '') . ' ' . ($notifiable->name ?? '')),
            'subject'            => $mailMessage->subject ?? config('app.name'),
            'body_html'          => $html,
            'body_text'          => $text,
            'notification_type'  => get_class($notification),
            'notification_id'    => $notification->id ?? null,
            'statut'             => 'pending',
            'tentatives'         => 0,
            'max_tentatives'     => 3,
        ]);
    }

    /** Extrait un résumé texte du MailMessage pour le fallback */
    private function extraireTexte($mailMessage): string
    {
        $lines = [];

        if ($mailMessage->greeting) {
            $lines[] = $mailMessage->greeting;
        }

        foreach ($mailMessage->introLines as $line) {
            $lines[] = $line;
        }

        if (!empty($mailMessage->actionText) && !empty($mailMessage->actionUrl)) {
            $lines[] = "{$mailMessage->actionText} : {$mailMessage->actionUrl}";
        }

        foreach ($mailMessage->outroLines as $line) {
            $lines[] = $line;
        }

        if ($mailMessage->salutation) {
            $lines[] = $mailMessage->salutation;
        }

        return implode("\n\n", array_filter($lines));
    }
}