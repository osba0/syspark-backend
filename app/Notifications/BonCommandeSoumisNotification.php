<?php

namespace App\Notifications;

use App\Models\BonCommande;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BonCommandeSoumisNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public BonCommande $bonCommande) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bc = $this->bonCommande;

        return (new MailMessage)
            ->subject("Bon de commande {$bc->numero_bc} en attente d'approbation")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line("Un bon de commande a été soumis et attend votre approbation.")
            ->line("**Numéro :** {$bc->numero_bc}")
            ->line("**Fournisseur :** " . ($bc->fournisseur?->nom ?? 'Non spécifié'))
            ->line("**Montant TTC :** " . number_format($bc->montant_ttc, 0, ',', ' ') . " FCFA")
            ->line("**Date commande :** " . $bc->date_commande?->format('d/m/Y'))
            ->action('Voir le bon de commande', url("/bons-commande/{$bc->id}"))
            ->line('Merci de traiter cette demande dans les meilleurs délais.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'bon_commande_soumis',
            'bc_id'       => $this->bonCommande->id,
            'numero_bc'   => $this->bonCommande->numero_bc,
            'montant_ttc' => $this->bonCommande->montant_ttc,
            'message'     => "BC {$this->bonCommande->numero_bc} en attente d'approbation",
        ];
    }
}
