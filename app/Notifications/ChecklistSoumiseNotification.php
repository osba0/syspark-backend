<?php
namespace App\Notifications;

class ChecklistSoumiseNotification extends BaseNotification
{
    public function __construct(private readonly array $checklist) {}

    public function toDatabase(object $notifiable): array
    {
        $hasAnomalies = !empty($this->checklist['anomalies']);
        return [
            'event'   => 'checklist.soumise',
            'titre'   => $hasAnomalies ? "Checklist avec anomalies" : "Checklist soumise",
            'message' => "{$this->checklist['chauffeur']} a soumis une checklist pour {$this->checklist['immatriculation']}" .
                ($hasAnomalies ? " — {$this->checklist['anomalies']} anomalie(s) détectée(s)." : "."),
            'niveau'  => $hasAnomalies ? 'warning' : 'success',
            'lien'    => "/checklists/{$this->checklist['id']}",
            'meta'    => $this->checklist,
        ];
    }
}
