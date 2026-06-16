<?php

namespace App\Console\Commands;

use App\Models\NotificationEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Message;

/**
 * Traite la file d'attente d'emails (table notification_emails).
 *
 * À exécuter via le scheduler toutes les minutes :
 *   $schedule->command('email-queue:process')->everyMinute();
 *
 * Reprend :
 *  - les emails 'pending' dont next_attempt_at est null ou passé
 *  - relance les emails 'failed' qui n'ont pas épuisé leurs tentatives
 *    (ceux-ci sont automatiquement repassés en 'pending' par marquerEchec())
 */
class ProcessEmailQueue extends Command
{
    protected $signature = 'email-queue:process {--limit=50 : Nombre maximum d\'emails à traiter par exécution}';

    protected $description = 'Envoie les emails en attente dans notification_emails (file de notifications)';

    public function handle(): int
    {
        $limite = (int) $this->option('limit');

        $emails = NotificationEmail::aPourEnvoi()
            ->orderBy('created_at')
            ->limit($limite)
            ->get();

        if ($emails->isEmpty()) {
            $this->info('Aucun email en attente.');
            return self::SUCCESS;
        }

        $this->info("Traitement de {$emails->count()} email(s)...");

        $envoyes = 0;
        $echecs  = 0;

        foreach ($emails as $email) {
            try {
                $this->envoyer($email);
                $email->marquerEnvoye();
                $envoyes++;
            } catch (\Throwable $e) {
                $email->marquerEchec($e->getMessage());
                $echecs++;

                Log::warning('Échec envoi email notification', [
                    'email_id' => $email->id,
                    'to'       => $email->to_email,
                    'subject'  => $email->subject,
                    'erreur'   => $e->getMessage(),
                    'tentative'=> $email->tentatives,
                ]);
            }
        }

        $this->info("✅ {$envoyes} envoyé(s) · ❌ {$echecs} échec(s)");

        return self::SUCCESS;
    }

    /**
     * Envoie un email brut via le mailer Laravel.
     * Utilise du HTML déjà rendu — pas de Mailable nécessaire.
     */
    private function envoyer(NotificationEmail $email): void
    {
        Mail::html($email->body_html, function (Message $message) use ($email) {
            $message->to($email->to_email, $email->to_name ?: null)
                    ->subject($email->subject);
        });
    }
}
