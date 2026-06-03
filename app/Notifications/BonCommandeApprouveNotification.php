<?php

namespace App\Notifications;

use App\Models\BonCommande;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BonCommandeApprouveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BonCommande $bonCommande,
        public string $action // 'approuve' | 'rejete'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bc      = $this->bonCommande;
        $estApprv = $this->action === 'approuve';
        $sujet   = $estApprv
            ? "Bon de commande {$bc->numero_bc} approuvé ✓"
            : "Bon de commande {$bc->numero_bc} rejeté ✗";

        $mail = (new MailMessage)
            ->subject($sujet)
            ->greeting("Bonjour {$notifiable->prenom},");

        if ($estApprv) {
            $mail->line("Votre bon de commande **{$bc->numero_bc}** a été **approuvé**.")
                ->line("**Montant TTC :** " . number_format($bc->montant_ttc, 0, ',', ' ') . " FCFA")
                ->line("Vous pouvez maintenant procéder à la commande auprès du fournisseur.")
                ->action('Voir le bon de commande', url("/bons-commande/{$bc->id}"));
        } else {
            $mail->line("Votre bon de commande **{$bc->numero_bc}** a été **rejeté**.")
                ->line("**Motif :** " . ($bc->motif_rejet ?? 'Non précisé'))
                ->line("Vous pouvez le modifier et le soumettre à nouveau.")
                ->action('Modifier le bon de commande', url("/bons-commande/{$bc->id}/edit"));
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'bon_commande_' . $this->action,
            'bc_id'       => $this->bonCommande->id,
            'numero_bc'   => $this->bonCommande->numero_bc,
            'action'      => $this->action,
            'message'     => "BC {$this->bonCommande->numero_bc} " . ($this->action === 'approuve' ? 'approuvé' : 'rejeté'),
        ];
    }
}
