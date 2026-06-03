@extends('pdf.layout')

@section('doc-title', 'FICHE VÉHICULE — ' . strtoupper($vehicule->immatriculation))

@section('content')

{{-- En-tête véhicule --}}
<div style="background:#1B4F72; color:#fff; padding:8px 12px; border-radius:4px; margin-bottom:14px; text-align:center; font-size:14px; font-weight:bold; letter-spacing:2px;">
  FICHE VÉHICULE — {{ $annee }}
</div>

{{-- Identité --}}
<div class="section">
  <div class="section-title">Identification du véhicule</div>
  <div class="grid">
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Immatriculation</div>
        <div class="field-box bold" style="font-size:15px; color:#1B4F72; letter-spacing:2px;">
          {{ $vehicule->immatriculation }}
        </div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Marque / Modèle</div>
        <div class="field-box">{{ $vehicule->marque }} {{ $vehicule->modele }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Année</div>
        <div class="field-box">{{ $vehicule->annee_mise_circulation ?? '—' }}</div>
      </div>
    </div>
  </div>
  <div class="grid">
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Type</div>
        <div class="field-box">{{ ucfirst(str_replace('_', ' ', $vehicule->type_vehicule ?? '—')) }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Carburant</div>
        <div class="field-box">{{ $vehicule->type_carburant ?? '—' }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Kilométrage actuel</div>
        <div class="field-box bold">{{ number_format($vehicule->kilometrage_actuel ?? 0, 0, ',', ' ') }} km</div>
      </div>
    </div>
  </div>
  <div class="grid">
    <div class="col col-3">
      <div class="field">
        <div class="field-label">N° châssis (VIN)</div>
        <div class="field-box">{{ $vehicule->numero_chassis ?? '—' }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Agence</div>
        <div class="field-box">{{ $vehicule->agence?->nom ?? '—' }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Statut</div>
        <div class="field-box bold" style="color:{{ $vehicule->statut === 'actif' ? '#1A7A2A' : '#B03030' }}">
          {{ strtoupper($vehicule->statut ?? '—') }}
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Chauffeur actuel --}}
@if($vehicule->affectationActive?->chauffeur)
<div class="section">
  <div class="section-title">Chauffeur / Attributaire</div>
  <div class="grid">
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Nom complet</div>
        <div class="field-box bold">
          {{ $vehicule->affectationActive->chauffeur->prenom }} {{ $vehicule->affectationActive->chauffeur->nom }}
        </div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Matricule</div>
        <div class="field-box">{{ $vehicule->affectationActive->chauffeur->matricule_interne ?? '—' }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Depuis le</div>
        <div class="field-box">{{ $vehicule->affectationActive->date_debut ? \Carbon\Carbon::parse($vehicule->affectationActive->date_debut)->format('d/m/Y') : '—' }}</div>
      </div>
    </div>
  </div>
</div>
@endif

{{-- Documents --}}
@if($vehicule->documentsVehicule->count() > 0)
<div class="section">
  <div class="section-title">Documents actifs</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>Type</th>
        <th>Référence</th>
        <th>Organisme</th>
        <th>Date émission</th>
        <th>Date expiration</th>
        <th>Statut</th>
      </tr>
    </thead>
    <tbody>
      @foreach($vehicule->documentsVehicule as $doc)
      <tr>
        <td>{{ ucfirst(str_replace('_', ' ', $doc->type_document)) }}</td>
        <td>{{ $doc->numero ?? '—' }}</td>
        <td>{{ $doc->organisme_emetteur ?? '—' }}</td>
        <td>{{ $doc->date_emission ? \Carbon\Carbon::parse($doc->date_emission)->format('d/m/Y') : '—' }}</td>
        <td style="{{ $doc->date_expiration && \Carbon\Carbon::parse($doc->date_expiration)->isPast() ? 'color:#B03030;font-weight:bold;' : '' }}">
          {{ $doc->date_expiration ? \Carbon\Carbon::parse($doc->date_expiration)->format('d/m/Y') : 'Illimitée' }}
        </td>
        <td style="{{ $doc->date_expiration && \Carbon\Carbon::parse($doc->date_expiration)->isPast() ? 'color:#B03030;' : 'color:#1A7A2A;' }}">
          {{ $doc->date_expiration && \Carbon\Carbon::parse($doc->date_expiration)->isPast() ? 'Expiré' : 'Valide' }}
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- Maintenances de l'année --}}
@if($vehicule->maintenances->count() > 0)
<div class="section">
  <div class="section-title">Interventions maintenance {{ $annee }}</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Titre</th>
        <th>Fournisseur</th>
        <th style="text-align:right">Montant TTC</th>
      </tr>
    </thead>
    <tbody>
      @php $totalMaintenance = 0; @endphp
      @foreach($vehicule->maintenances as $m)
      @php $totalMaintenance += $m->montant_ttc; @endphp
      <tr>
        <td>{{ \Carbon\Carbon::parse($m->date_travaux)->format('d/m/Y') }}</td>
        <td>{{ ucfirst(str_replace('_', ' ', $m->type_operation ?? '—')) }}</td>
        <td>{{ $m->titre }}</td>
        <td>{{ $m->fournisseur?->nom ?? '—' }}</td>
        <td style="text-align:right">{{ number_format($m->montant_ttc, 0, ',', ' ') }} F</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr style="background:#EBF5FB; font-weight:bold;">
        <td colspan="4" style="text-align:right">Total maintenance {{ $annee }}</td>
        <td style="text-align:right">{{ number_format($totalMaintenance, 0, ',', ' ') }} F</td>
      </tr>
    </tfoot>
  </table>
</div>
@endif

{{-- Carburant de l'année --}}
@if($vehicule->carburants->count() > 0)
<div class="section">
  <div class="section-title">Consommation carburant {{ $annee }}</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>Date</th>
        <th>Station</th>
        <th style="text-align:right">Litres</th>
        <th style="text-align:right">Montant</th>
        <th style="text-align:right">Km</th>
      </tr>
    </thead>
    <tbody>
      @php $totalLitres = 0; $totalCarburant = 0; @endphp
      @foreach($vehicule->carburants as $c)
      @php $totalLitres += $c->litres; $totalCarburant += $c->montant; @endphp
      <tr>
        <td>{{ \Carbon\Carbon::parse($c->date)->format('d/m/Y') }}</td>
        <td>{{ $c->station ?? '—' }}</td>
        <td style="text-align:right">{{ number_format($c->litres, 1, ',', ' ') }} L</td>
        <td style="text-align:right">{{ number_format($c->montant, 0, ',', ' ') }} F</td>
        <td style="text-align:right">{{ $c->kilometrage ? number_format($c->kilometrage, 0, ',', ' ') : '—' }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr style="background:#EBF5FB; font-weight:bold;">
        <td colspan="2" style="text-align:right">Total carburant {{ $annee }}</td>
        <td style="text-align:right">{{ number_format($totalLitres, 1, ',', ' ') }} L</td>
        <td style="text-align:right">{{ number_format($totalCarburant, 0, ',', ' ') }} F</td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>
@endif

{{-- Résumé TCO --}}
<div class="section">
  <div class="section-title">Résumé TCO {{ $annee }}</div>
  <div class="grid">
    @php
      $tcoMaintenance = $vehicule->maintenances->sum('montant_ttc');
      $tcoCarburant   = $vehicule->carburants->sum('montant');
      $tcoTotal       = $tcoMaintenance + $tcoCarburant;
    @endphp
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Maintenance</div>
        <div class="field-box bold">{{ number_format($tcoMaintenance, 0, ',', ' ') }} F CFA</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label">Carburant</div>
        <div class="field-box bold">{{ number_format($tcoCarburant, 0, ',', ' ') }} F CFA</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field">
        <div class="field-label" style="color:#1B4F72; font-weight:bold;">Total TCO {{ $annee }}</div>
        <div class="field-box bold" style="font-size:13px; color:#1B4F72;">
          {{ number_format($tcoTotal, 0, ',', ' ') }} F CFA
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Pied de page --}}
<div style="margin-top:20px; padding-top:8px; border-top:1px solid #ccc; font-size:9px; color:#888; text-align:center;">
  Document généré le {{ now()->format('d/m/Y à H:i') }} — Gestion Parc Auto
</div>

@endsection
