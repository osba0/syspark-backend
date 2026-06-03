@extends('pdf.layout')

@section('doc-title', 'BON DE COMMANDE')

@section('content')

{{-- Référence et statut --}}
<div style="display:table; width:100%; margin-bottom:14px;">
  <div style="display:table-cell; width:50%; vertical-align:middle;">
    <div style="font-size:22px; font-weight:bold; color:#1B4F72; letter-spacing:2px;">
      {{ $bc->numero_bc }}
    </div>
    <div style="font-size:10px; color:#666; margin-top:2px;">
      Date : {{ $bc->date_commande?->format('d/m/Y') }}
      @if($bc->date_livraison_prevue)
        &nbsp;·&nbsp; Livraison prévue : {{ $bc->date_livraison_prevue->format('d/m/Y') }}
      @endif
    </div>
  </div>
  <div style="display:table-cell; width:50%; text-align:right; vertical-align:middle;">
    @php
      $statusStyles = [
        'brouillon'  => 'background:#ecf0f1; color:#7f8c8d;',
        'soumis'     => 'background:#eaf4fb; color:#2E86C1;',
        'approuve'   => 'background:#d5f5e3; color:#1e8449;',
        'rejete'     => 'background:#fdedec; color:#922b21;',
        'execute'    => 'background:#d5f5e3; color:#1e8449;',
        'annule'     => 'background:#f9f9f9; color:#aaa;',
      ];
      $style = $statusStyles[$bc->statut] ?? '';
    @endphp
    <span style="padding:5px 14px; border-radius:14px; font-weight:bold; font-size:11px; {{ $style }}">
      {{ strtoupper($bc->statut) }}
    </span>
  </div>
</div>

{{-- Informations parties --}}
<div class="grid" style="margin-bottom:14px;">
  {{-- Émetteur --}}
  <div class="col col-2">
    <div style="border:1px solid #1B4F72; border-radius:4px; padding:8px;">
      <div style="font-size:9px; color:#1B4F72; font-weight:bold; text-transform:uppercase; margin-bottom:4px;">Émetteur</div>
      <div class="bold">{{ $bc->agence?->nom ?? 'Parc Automobile' }}</div>
      <div style="font-size:10px; color:#666;">{{ $bc->agence?->adresse ?? '' }}</div>
      <div style="font-size:10px; color:#666;">{{ $bc->agence?->ville ?? '' }}</div>
      @if($bc->agence?->telephone)
        <div style="font-size:10px;">Tél : {{ $bc->agence->telephone }}</div>
      @endif
      <div style="font-size:10px; margin-top:4px;">
        Créé par : <strong>{{ $bc->creePar?->nom_complet ?? '—' }}</strong>
      </div>
    </div>
  </div>

  {{-- Fournisseur --}}
  <div class="col col-2">
    <div style="border:1px solid #ccc; border-radius:4px; padding:8px; background:#f9f9f9;">
      <div style="font-size:9px; color:#555; font-weight:bold; text-transform:uppercase; margin-bottom:4px;">Fournisseur</div>
      @if($bc->fournisseur)
        <div class="bold" style="font-size:12px;">{{ $bc->fournisseur->nom }}</div>
        <div style="font-size:10px; color:#666;">{{ $bc->fournisseur->specialite ?? '' }}</div>
        <div style="font-size:10px;">{{ $bc->fournisseur->adresse ?? '' }}</div>
        <div style="font-size:10px;">{{ $bc->fournisseur->ville ?? '' }}</div>
        @if($bc->fournisseur->telephone)
          <div style="font-size:10px;">Tél : {{ $bc->fournisseur->telephone }}</div>
        @endif
        @if($bc->fournisseur->ninea)
          <div style="font-size:9px; color:#888;">NINEA : {{ $bc->fournisseur->ninea }}</div>
        @endif
      @else
        <div style="color:#aaa; font-style:italic;">Non spécifié</div>
      @endif
    </div>
  </div>
</div>

{{-- Véhicule concerné --}}
@if($bc->vehicule)
<div class="section">
  <div class="section-title">Véhicule concerné</div>
  <div class="grid">
    <div class="col col-3">
      <div class="field"><div class="field-label">Immatriculation</div>
        <div class="field-box bold" style="color:#1B4F72;">{{ $bc->vehicule->immatriculation }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Marque / Modèle</div>
        <div class="field-box">{{ $bc->vehicule->marque }} {{ $bc->vehicule->modele }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Kilométrage</div>
        <div class="field-box">{{ $bc->vehicule->kilometrage_actuel ? number_format($bc->vehicule->kilometrage_actuel, 0, ',', ' ') . ' km' : '—' }}</div>
      </div>
    </div>
  </div>
</div>
@endif

{{-- Lignes de commande --}}
<div class="section">
  <div class="section-title">Détail de la commande</div>
  <table>
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Désignation</th>
        <th style="width:10%; text-align:center;">Qté</th>
        <th style="width:10%; text-align:center;">Unité</th>
        <th style="width:15%; text-align:right;">Prix unitaire (FCFA)</th>
        <th style="width:15%; text-align:right;">Total HT (FCFA)</th>
      </tr>
    </thead>
    <tbody>
      @foreach($bc->lignes ?? [] as $i => $ligne)
      <tr class="{{ $loop->even ? \'tr-even\' : \'tr-odd\' }}">
        <td class="text-center">{{ $i + 1 }}</td>
        <td>{{ $ligne['description'] ?? '' }}</td>
        <td class="text-center">{{ number_format((float)($ligne['quantite'] ?? 0), 2, ',', ' ') }}</td>
        <td class="text-center">{{ $ligne['unite'] ?? 'U' }}</td>
        <td class="text-right">{{ number_format((float)($ligne['prix_unitaire'] ?? 0), 0, ',', ' ') }}</td>
        <td class="text-right bold">
          {{ number_format((float)($ligne['quantite'] ?? 1) * (float)($ligne['prix_unitaire'] ?? 0), 0, ',', ' ') }}
        </td>
      </tr>
      @endforeach

      {{-- Lignes vides si peu de lignes --}}
      @for ($j = count($bc->lignes ?? []); $j < 5; $j++)
      <tr>
        <td>{{ $j + 1 }}</td>
        <td></td><td></td><td></td><td></td><td></td>
      </tr>
      @endfor
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" class="text-right bold" style="background:#f0f0f0;">Montant HT</td>
        <td class="text-right bold" style="background:#f0f0f0;">{{ number_format($bc->montant_ht, 0, ',', ' ') }} FCFA</td>
      </tr>
      <tr>
        <td colspan="5" class="text-right" style="background:#f0f0f0;">TVA ({{ $bc->tva }}%)</td>
        <td class="text-right" style="background:#f0f0f0;">
          {{ number_format($bc->montant_ttc - $bc->montant_ht, 0, ',', ' ') }} FCFA
        </td>
      </tr>
      <tr>
        <td colspan="5" class="text-right bold" style="background:#1B4F72; color:#fff; font-size:12px;">
          MONTANT TOTAL TTC
        </td>
        <td class="text-right bold" style="background:#1B4F72; color:#fff; font-size:12px;">
          {{ number_format($bc->montant_ttc, 0, ',', ' ') }} FCFA
        </td>
      </tr>
    </tfoot>
  </table>
</div>

{{-- Arrêté en lettres --}}
<div style="border:1px solid #1B4F72; padding:6px 10px; border-radius:3px; margin-bottom:14px; font-size:10px;">
  <strong>Arrêté le présent bon de commande à la somme de :</strong>
  <span style="font-style:italic; color:#1B4F72;"> [{{ number_format($bc->montant_ttc, 0, ',', ' ') }} francs CFA TTC] </span>
</div>

{{-- Observations --}}
@if($bc->observations)
<div class="section">
  <div class="section-title">Observations</div>
  <div style="border:1px solid #ccc; padding:6px; min-height:25px; border-radius:3px; font-size:10px;">
    {{ $bc->observations }}
  </div>
</div>
@endif

{{-- Approbation --}}
@if($bc->approuve_par && $bc->statut === 'approuve')
<div style="border:1px solid #27ae60; background:#d5f5e3; padding:6px 10px; border-radius:3px; margin-bottom:14px; font-size:10px;">
  ✓ <strong>Approuvé</strong> par {{ $bc->approuvePar?->nom_complet }} le {{ $bc->approuve_le?->format('d/m/Y') }}
</div>
@endif
@if($bc->statut === 'rejete')
<div style="border:1px solid #e74c3c; background:#fdedec; padding:6px 10px; border-radius:3px; margin-bottom:14px; font-size:10px;">
  ✗ <strong>Rejeté</strong> — Motif : {{ $bc->motif_rejet }}
</div>
@endif

{{-- Signatures --}}
<div class="signatures">
  <div class="sig-cell" style="padding-right:20px;">
    <div class="sig-label">Le Responsable de Parc</div>
    <div class="sig-line"></div>
    <div>{{ $bc->creePar?->nom_complet ?? '____________________' }}</div>
  </div>
  <div class="sig-cell" style="padding-left:20px;">
    <div class="sig-label">Le Directeur / Approbateur</div>
    <div class="sig-line"></div>
    <div>{{ $bc->approuvePar?->nom_complet ?? '____________________' }}</div>
  </div>
</div>

@endsection
