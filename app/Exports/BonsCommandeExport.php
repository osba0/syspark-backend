<?php

namespace App\Exports;

use App\Models\BonCommande;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BonsCommandeExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private int  $annee,
        private ?int $agenceId = null,
    ) {}

    public function title(): string
    {
        return "Bons de Commande {$this->annee}";
    }

    public function collection()
    {
        return BonCommande::with(['fournisseur:id,nom', 'vehicule:id,immatriculation', 'agence:id,nom'])
            ->whereYear('date_commande', $this->annee)
            ->when($this->agenceId, fn ($q) => $q->where('agence_id', $this->agenceId))
            ->orderBy('date_commande')
            ->get();
    }

    public function headings(): array
    {
        return [
            'N° BC',
            'Date commande',
            'Fournisseur',
            'Véhicule',
            'Agence',
            'Montant HT',
            'TVA (%)',
            'Montant TTC',
            'Statut',
            'Livraison prévue',
            'Livraison réelle',
            'Observations',
        ];
    }

    public function map($bc): array
    {
        return [
            $bc->numero_bc,
            $bc->date_commande?->format('d/m/Y') ?? '',
            $bc->fournisseur?->nom ?? '—',
            $bc->vehicule?->immatriculation ?? '—',
            $bc->agence?->nom ?? '—',
            (float) $bc->montant_ht,
            (float) ($bc->tva ?? 0),
            (float) $bc->montant_ttc,
            ucfirst($bc->statut),
            $bc->date_livraison_prevue?->format('d/m/Y') ?? '—',
            $bc->date_livraison_reelle?->format('d/m/Y') ?? '—',
            $bc->observations ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A5F'],
                ],
            ],
        ];
    }
}
