<?php

namespace App\Exports;

use App\Models\Vehicule;
use App\Models\Chauffeur;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export complet du parc en multi-onglets
 * Onglets : Véhicules | Chauffeurs | Affectations actives
 */
class ParcGlobalExport implements WithMultipleSheets
{
    public function __construct(private ?int $agenceId = null) {}

    public function sheets(): array
    {
        return [
            new VehiculesSheet($this->agenceId),
            new ChauffeursSheet($this->agenceId),
        ];
    }
}

// ── Onglet véhicules ──────────────────────────────────────────────────────

class VehiculesSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(private ?int $agenceId) {}
    public function title(): string { return 'Véhicules'; }

    public function query()
    {
        return Vehicule::with(['agence', 'affectationActive.chauffeur', 'affectationActive.axeLivraison'])
            ->when($this->agenceId, fn ($q) => $q->where('agence_id', $this->agenceId))
            ->orderBy('immatriculation');
    }

    public function headings(): array
    {
        return [
            'Immatriculation', 'Marque', 'Modèle', 'Type', 'Catégorie',
            'Année fab.', 'Énergie', 'Agence', 'Statut',
            'Km actuel', 'Prochain entretien km', 'Prochain entretien date',
            'Prochaine VT', 'Expiration assurance',
            'Chauffeur actuel', 'Axe de livraison',
            'Carte carburant',
        ];
    }

    public function map($v): array
    {
        return [
            $v->immatriculation,
            $v->marque,
            $v->modele,
            ucfirst($v->type_vehicule),
            $v->categorie,
            $v->annee_fabrication,
            $v->energie,
            $v->agence?->nom,
            ucfirst(str_replace('_', ' ', $v->statut)),
            $v->kilometrage_actuel,
            $v->prochain_entretien_km,
            $v->prochain_entretien_date?->format('d/m/Y'),
            $v->date_prochaine_visite_tech?->format('d/m/Y'),
            $v->date_expiration_assurance?->format('d/m/Y'),
            $v->affectationActive?->chauffeur?->nom_complet,
            $v->affectationActive?->axeLivraison?->nom,
            $v->numero_carte_carburant,
        ];
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
                    $sheet->getStyle("A{$r}:Q{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
                }
                $sheet->setAutoFilter('A1:Q1');
                $sheet->freezePane('B2');
            },
        ];
    }
}

// ── Onglet chauffeurs ─────────────────────────────────────────────────────

class ChauffeursSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(private ?int $agenceId) {}
    public function title(): string { return 'Chauffeurs'; }

    public function query()
    {
        return Chauffeur::with(['agence', 'affectationActive.vehicule'])
            ->when($this->agenceId, fn ($q) => $q->where('agence_id', $this->agenceId))
            ->orderBy('nom');
    }

    public function headings(): array
    {
        return [
            'Nom', 'Prénom', 'Téléphone', 'Agence', 'Statut',
            'N° Permis', 'Catégorie permis', 'Expiration permis',
            'Véhicule actuel', 'Date embauche',
        ];
    }

    public function map($c): array
    {
        return [
            $c->nom, $c->prenom, $c->telephone, $c->agence?->nom,
            ucfirst($c->statut),
            $c->numero_permis, $c->categorie_permis,
            $c->date_expiration_permis?->format('d/m/Y'),
            $c->affectationActive?->vehicule?->immatriculation,
            $c->date_embauche?->format('d/m/Y'),
        ];
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
                    $sheet->getStyle("A{$r}:J{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
                }
                $sheet->setAutoFilter('A1:J1');
                $sheet->freezePane('B2');
            },
        ];
    }
}
