<?php

namespace App\Exports;

use App\Models\Carburant;
use App\Models\DotationCarburant;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel multi-onglets du carburant
 * Reproduit l'onglet "STAT CARBURANT" du fichier RECUEIL_TRAVAUX_ET_STAT_DU_PARC.xlsx
 */
class StatCarburantExport implements WithMultipleSheets
{
    public function __construct(
        private int  $annee,
        private ?int $agenceId = null
    ) {}

    public function sheets(): array
    {
        return [
            new StatCarburantMoisSheet($this->annee, $this->agenceId),
            new StatCarburantVehiculeSheet($this->annee, $this->agenceId),
            new StatCarburantChauffeurSheet($this->annee, $this->agenceId),
        ];
    }
}

// ── Onglet 1 : Situation mensuelle ────────────────────────────────────────

class StatCarburantMoisSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    private const MOIS = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

    public function __construct(private int $annee, private ?int $agenceId) {}

    public function title(): string { return 'Situation mensuelle'; }

    public function headings(): array
    {
        return ['Mois', 'Dotation (FCFA)', 'Consommation (FCFA)', 'Écart (FCFA)', 'Taux (%)', 'Litres', 'Nb pleins'];
    }

    public function array(): array
    {
        $parMois = DB::table('carburants')
            ->whereYear('date', $this->annee)
            ->when($this->agenceId, fn ($q) => $q->where('agence_id', $this->agenceId))
            ->selectRaw('MONTH(date) as mois, SUM(montant) as conso, SUM(litres) as litres, COUNT(*) as nb')
            ->groupBy('mois')->get()->keyBy('mois');

        $dotations = DB::table('dotations_carburant')
            ->where('annee', $this->annee)
            ->when($this->agenceId, fn ($q) => $q->where('agence_id', $this->agenceId))
            ->selectRaw('mois, SUM(montant_dote) as dote')
            ->groupBy('mois')->get()->keyBy('mois');

        $rows = [];
        $totalDote = $totalConso = $totalLitres = $totalNb = 0;

        for ($m = 1; $m <= 12; $m++) {
            $dote  = (float)($dotations[$m]?->dote ?? 0);
            $conso = (float)($parMois[$m]?->conso ?? 0);
            $ecart = $dote - $conso;
            $taux  = $dote > 0 ? round($conso / $dote * 100, 1) : 0;

            $totalDote  += $dote;
            $totalConso += $conso;
            $totalLitres += (float)($parMois[$m]?->litres ?? 0);
            $totalNb     += (int)($parMois[$m]?->nb ?? 0);

            $rows[] = [
                self::MOIS[$m - 1],
                $dote,
                $conso,
                $ecart,
                $taux,
                round((float)($parMois[$m]?->litres ?? 0), 2),
                (int)($parMois[$m]?->nb ?? 0),
            ];
        }

        // Ligne total
        $rows[] = [
            'TOTAL ' . $this->annee,
            $totalDote,
            $totalConso,
            $totalDote - $totalConso,
            $totalDote > 0 ? round($totalConso / $totalDote * 100, 1) : 0,
            round($totalLitres, 2),
            $totalNb,
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B4F72']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last  = $sheet->getHighestRow();

                // Style ligne total
                $sheet->getStyle("A{$last}:G{$last}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B4F72']],
                ]);

                // Format monétaire
                $sheet->getStyle("B2:D{$last}")->getNumberFormat()->setFormatCode('# ##0');
                $sheet->getStyle("E2:E{$last}")->getNumberFormat()->setFormatCode('0.0"%"');

                // Alterner lignes
                for ($r = 2; $r < $last; $r++) {
                    $color = $r % 2 === 0 ? 'FFEBF5FB' : 'FFFFFFFF';
                    $sheet->getStyle("A{$r}:G{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
                }

                $sheet->setAutoFilter('A1:G1');
                $sheet->freezePane('A2');
            },
        ];
    }
}

// ── Onglet 2 : Par véhicule ───────────────────────────────────────────────

class StatCarburantVehiculeSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(private int $annee, private ?int $agenceId) {}

    public function title(): string { return 'Par véhicule'; }

    public function headings(): array
    {
        return ['Immatriculation', 'Marque', 'Modèle', 'Dotation (FCFA)', 'Consommation (FCFA)', 'Écart (FCFA)', 'Taux (%)', 'Litres', 'Nb pleins'];
    }

    public function array(): array
    {
        return DB::table('carburants as c')
            ->join('vehicules as v', 'c.vehicule_id', '=', 'v.id')
            ->leftJoin(
                DB::raw('(SELECT vehicule_id, SUM(montant_dote) as dote FROM dotations_carburant WHERE annee = ' . $this->annee . ' GROUP BY vehicule_id) as d'),
                'd.vehicule_id', '=', 'c.vehicule_id'
            )
            ->whereYear('c.date', $this->annee)
            ->when($this->agenceId, fn ($q) => $q->where('c.agence_id', $this->agenceId))
            ->select(
                'v.immatriculation', 'v.marque', 'v.modele',
                DB::raw('COALESCE(MAX(d.dote), 0) as dotation'),
                DB::raw('SUM(c.montant) as consommation'),
                DB::raw('SUM(c.litres) as litres'),
                DB::raw('COUNT(c.id) as nb_pleins')
            )
            ->groupBy('c.vehicule_id', 'v.immatriculation', 'v.marque', 'v.modele')
            ->orderByDesc('consommation')
            ->get()
            ->map(fn ($v) => [
                $v->immatriculation,
                $v->marque,
                $v->modele,
                round((float)$v->dotation, 2),
                round((float)$v->consommation, 2),
                round((float)$v->dotation - (float)$v->consommation, 2),
                $v->dotation > 0 ? round((float)$v->consommation / (float)$v->dotation * 100, 1) : 0,
                round((float)$v->litres, 2),
                (int)$v->nb_pleins,
            ])
            ->toArray();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B4F72']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last  = $sheet->getHighestRow();
                for ($r = 2; $r <= $last; $r++) {
                    $color = $r % 2 === 0 ? 'FFEBF5FB' : 'FFFFFFFF';
                    $sheet->getStyle("A{$r}:I{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
                }
                $sheet->getStyle("D2:F{$last}")->getNumberFormat()->setFormatCode('# ##0');
                $sheet->setAutoFilter('A1:I1');
                $sheet->freezePane('A2');
            },
        ];
    }
}

// ── Onglet 3 : Par chauffeur ──────────────────────────────────────────────

class StatCarburantChauffeurSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(private int $annee, private ?int $agenceId) {}

    public function title(): string { return 'Par chauffeur'; }

    public function headings(): array
    {
        return ['Chauffeur', 'Agence', 'Consommation (FCFA)', 'Litres', 'Nb pleins'];
    }

    public function array(): array
    {
        return DB::table('carburants as c')
            ->join('chauffeurs as ch', 'c.chauffeur_id', '=', 'ch.id')
            ->join('agences as ag', 'c.agence_id', '=', 'ag.id')
            ->whereYear('c.date', $this->annee)
            ->when($this->agenceId, fn ($q) => $q->where('c.agence_id', $this->agenceId))
            ->select(
                DB::raw("CONCAT(ch.prenom, ' ', ch.nom) as chauffeur"),
                'ag.nom as agence',
                DB::raw('SUM(c.montant) as total'),
                DB::raw('SUM(c.litres) as litres'),
                DB::raw('COUNT(c.id) as nb')
            )
            ->whereNotNull('c.chauffeur_id')
            ->groupBy('c.chauffeur_id', 'ch.prenom', 'ch.nom', 'ag.nom')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($c) => [
                $c->chauffeur, $c->agence,
                round((float)$c->total, 2), round((float)$c->litres, 2), (int)$c->nb,
            ])
            ->toArray();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B4F72']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last  = $sheet->getHighestRow();
                for ($r = 2; $r <= $last; $r++) {
                    $color = $r % 2 === 0 ? 'FFEBF5FB' : 'FFFFFFFF';
                    $sheet->getStyle("A{$r}:E{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
                }
                $sheet->getStyle("C2:C{$last}")->getNumberFormat()->setFormatCode('# ##0');
                $sheet->setAutoFilter('A1:E1');
                $sheet->freezePane('A2');
            },
        ];
    }
}
