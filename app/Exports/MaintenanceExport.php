<?php

namespace App\Exports;

use App\Models\Maintenance;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export Excel des maintenances
 * Reproduit l'onglet "ENTRET ET REP VEHIC LIVRAISON" du fichier RECUEIL_TRAVAUX_ET_STAT_DU_PARC.xlsx
 */
class MaintenanceExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(
        private int    $annee,
        private string $typeVehicule = 'livraison', // 'livraison' | 'administratif'
        private ?int   $agenceId = null
    ) {}

    public function title(): string
    {
        return $this->typeVehicule === 'livraison'
            ? 'ENTRET ET REP VEHIC LIVRAISON'
            : 'ENTRET ET REP VEHIC ADMINISTRAT';
    }

    public function query(): Builder
    {
        return Maintenance::with(['vehicule', 'chauffeur', 'fournisseur', 'agence', 'axeLivraison'])
            ->whereYear('date_travaux', $this->annee)
            ->where('statut', 'termine')
            ->when($this->agenceId, fn ($q) => $q->where('agence_id', $this->agenceId))
            ->when($this->typeVehicule !== 'all', fn ($q) => $q->whereHas('vehicule', fn ($v) =>
                $v->where('type_vehicule', $this->typeVehicule === 'livraison'
                    ? 'livraison'
                    : 'administratif')
            ))
            ->orderBy('date_travaux')
            ->orderBy('vehicule_id');
    }

    public function headings(): array
    {
        // Correspond exactement aux colonnes du fichier Excel existant
        return [
            'Dates travaux',
            'Matricules',
            'Kilométrages',
            'Agences',
            'Axes de livraison',
            $this->typeVehicule === 'livraison' ? 'Chauffeurs' : 'Attributaires',
            'Fournisseurs / Concessionnaires',
            'Type d\'opération',
            'Catégorie travaux',
            'Descriptions des travaux',
            'Fournitures / Main d\'œuvre',
            'Montant HT (FCFA)',
            'TVA (%)',
            'Montant TTC (FCFA)',
            'N° Facture',
        ];
    }

    public function map($maintenance): array
    {
        $acteur = $this->typeVehicule === 'livraison'
            ? ($maintenance->chauffeur?->nom_complet ?? '—')
            : ($maintenance->vehicule?->affectationActive?->attributaire?->nom_complet ?? '—');

        return [
            $maintenance->date_travaux?->format('d/m/Y'),
            $maintenance->vehicule?->immatriculation,
            $maintenance->kilometrage,
            $maintenance->agence?->nom,
            $maintenance->axeLivraison?->nom,
            $acteur,
            $maintenance->fournisseur?->nom,
            ucfirst($maintenance->type_operation),
            $maintenance->categorie_travaux,
            $maintenance->description_travaux,
            $maintenance->fournitures_mo,
            $maintenance->montant_ht,
            $maintenance->tva,
            $maintenance->montant_ttc,
            $maintenance->numero_facture,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // En-têtes : fond bleu foncé, texte blanc, gras
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B4F72']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = 'O'; // colonne O = 15ème

                // Alterner les couleurs de lignes
                for ($row = 2; $row <= $lastRow; $row++) {
                    $color = $row % 2 === 0 ? 'FFEBF5FB' : 'FFFFFFFF';
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($color);
                }

                // Ligne de total en bas
                $totalRow = $lastRow + 1;
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->setCellValue("L{$totalRow}", "=SUM(L2:L{$lastRow})");
                $sheet->setCellValue("N{$totalRow}", "=SUM(N2:N{$lastRow})");
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B4F72']],
                ]);

                // Format monétaire sur les colonnes montants
                $sheet->getStyle("L2:L{$totalRow}")->getNumberFormat()->setFormatCode('# ##0 "FCFA"');
                $sheet->getStyle("N2:N{$totalRow}")->getNumberFormat()->setFormatCode('# ##0 "FCFA"');

                // Figer la première ligne
                $sheet->freezePane('A2');

                // Filtres automatiques
                $sheet->setAutoFilter("A1:{$lastCol}1");

                // Titre de l'onglet
                $event->sheet->getSheetView()->setZoomScale(90);
            },
        ];
    }
}
