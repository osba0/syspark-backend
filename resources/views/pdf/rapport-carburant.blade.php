@extends('pdf.layout')

@section('doc-title', 'RAPPORT CARBURANT ' . $annee)

@section('content')

@php
  $moisLabels = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
@endphp

{{-- KPIs globaux --}}
<div style="display:table; width:100%; margin-bottom:12px;">
  <div style="display:table-cell; padding:0 4px;">
    <div style="border:2px solid #2E86C1; border-radius:6px; padding:8px; text-align:center;">
      <div style="font-size:9px; color:#2E86C1; margin-bottom:2px; text-transform:uppercase; font-weight:bold;">Dotation totale</div>
      <div style="font-size:13px; font-weight:bold; color:#2E86C1;">{{ number_format($total_dote ?? 0, 0, ',', ' ') }}</div>
      <div style="font-size:8px; color:#aaa;">FCFA</div>
    </div>
  </div>
  <div style="display:table-cell; padding:0 4px;">
    <div style="border:2px solid #E74C3C; border-radius:6px; padding:8px; text-align:center;">
      <div style="font-size:9px; color:#E74C3C; margin-bottom:2px; text-transform:uppercase; font-weight:bold;">Consommation</div>
      <div style="font-size:13px; font-weight:bold; color:#E74C3C;">{{ number_format($total_consomme ?? 0, 0, ',', ' ') }}</div>
      <div style="font-size:8px; color:#aaa;">FCFA</div>
    </div>
  </div>
  <div style="display:table-cell; padding:0 4px;">
    @php $ecart = ($total_dote ?? 0) - ($total_consomme ?? 0); @endphp
    <div style="border:2px solid {{ $ecart >= 0 ? '#27AE60' : '#E74C3C' }}; border-radius:6px; padding:8px; text-align:center;">
      <div style="font-size:9px; color:#555; margin-bottom:2px; text-transform:uppercase; font-weight:bold;">Écart</div>
      <div style="font-size:13px; font-weight:bold; color:{{ $ecart >= 0 ? '#27AE60' : '#E74C3C' }};">
        {{ ($ecart >= 0 ? '+' : '') . number_format($ecart, 0, ',', ' ') }}
      </div>
      <div style="font-size:8px; color:#aaa;">FCFA</div>
    </div>
  </div>
  <div style="display:table-cell; padding:0 4px;">
    <div style="border:2px solid #F39C12; border-radius:6px; padding:8px; text-align:center;">
      <div style="font-size:9px; color:#F39C12; margin-bottom:2px; text-transform:uppercase; font-weight:bold;">Total litres</div>
      <div style="font-size:13px; font-weight:bold; color:#F39C12;">{{ number_format($total_litres ?? 0, 0, ',', ' ') }}</div>
      <div style="font-size:8px; color:#aaa;">Litres</div>
    </div>
  </div>
</div>

{{-- Situation mensuelle (tableau principal) --}}
<div class="section">
  <div class="section-title">Situation mensuelle {{ $annee }}</div>
  <table>
    <thead>
      <tr>
        <th>Mois</th>
        <th class="text-right">Dotation (FCFA)</th>
        <th class="text-right">Consommation (FCFA)</th>
        <th class="text-right">Écart (FCFA)</th>
        <th class="text-right">Taux (%)</th>
        <th class="text-right">Litres</th>
        <th class="text-right">Nb pleins</th>
      </tr>
    </thead>
    <tbody>
      @php
        $parMoisIndex = collect($par_mois ?? [])->keyBy('mois');
        $totDote = $totConso = $totLitres = $totNb = 0;
      @endphp
      @for($m = 1; $m <= 12; $m++)
        @php
          $row   = $parMoisIndex[$m] ?? null;
          $dote  = 0; // pas fourni par mois dans ce rapport — sera amélioré
          $conso = $row ? (float)$row['total_montant'] : 0;
          $litres= $row ? (float)$row['total_litres'] : 0;
          $nb    = $row ? (int)$row['nb_pleins'] : 0;
          $totConso  += $conso;
          $totLitres += $litres;
          $totNb     += $nb;
        @endphp
        <tr>
          <td class="bold">{{ $moisLabels[$m-1] }}</td>
          <td class="text-right">—</td>
          <td class="text-right">{{ $conso > 0 ? number_format($conso, 0, ',', ' ') : '—' }}</td>
          <td class="text-right">—</td>
          <td class="text-right">—</td>
          <td class="text-right">{{ $litres > 0 ? number_format($litres, 1, ',', ' ') : '—' }}</td>
          <td class="text-right">{{ $nb > 0 ? $nb : '—' }}</td>
        </tr>
      @endfor
      <tr style="background:#1B4F72;">
        <td class="bold" style="color:#fff;">TOTAL {{ $annee }}</td>
        <td class="text-right bold" style="color:#fff;">{{ number_format($total_dote ?? 0, 0, ',', ' ') }}</td>
        <td class="text-right bold" style="color:#fff;">{{ number_format($totConso, 0, ',', ' ') }}</td>
        <td class="text-right bold" style="color:#fff;">—</td>
        <td class="text-right bold" style="color:#fff;">—</td>
        <td class="text-right bold" style="color:#fff;">{{ number_format($totLitres, 1, ',', ' ') }}</td>
        <td class="text-right bold" style="color:#fff;">{{ $totNb }}</td>
      </tr>
    </tbody>
  </table>
</div>

{{-- Par véhicule --}}
<div class="section">
  <div class="section-title">Situation par véhicule</div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Immatriculation</th>
        <th>Marque / Modèle</th>
        <th class="text-right">Dotation (FCFA)</th>
        <th class="text-right">Consommation (FCFA)</th>
        <th class="text-right">Écart</th>
        <th class="text-right">Taux</th>
        <th class="text-right">Litres</th>
      </tr>
    </thead>
    <tbody>
      @foreach($par_vehicule ?? [] as $i => $v)
      @php $taux = $v['taux_conso'] ?? 0; @endphp
      <tr>
        <td class="text-center">{{ $i + 1 }}</td>
        <td class="bold" style="color:#1B4F72;">{{ $v['immatriculation'] }}</td>
        <td>{{ $v['marque_modele'] }}</td>
        <td class="text-right">{{ number_format($v['total_dote'] ?? 0, 0, ',', ' ') }}</td>
        <td class="text-right bold">{{ number_format($v['total_consomme'], 0, ',', ' ') }}</td>
        <td class="text-right {{ ($v['ecart'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
          {{ (($v['ecart'] ?? 0) >= 0 ? '+' : '') . number_format($v['ecart'] ?? 0, 0, ',', ' ') }}
        </td>
        <td class="text-right">
          <span style="color:{{ $taux >= 100 ? '#e74c3c' : ($taux >= 80 ? '#e67e22' : '#27ae60') }}; font-weight:bold;">
            {{ $taux }}%
          </span>
        </td>
        <td class="text-right">{{ number_format($v['total_litres'], 1, ',', ' ') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Par chauffeur --}}
@if(!empty($par_chauffeur))
<div class="section">
  <div class="section-title">Consommation par chauffeur (Top {{ count($par_chauffeur) }})</div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>Chauffeur</th>
        <th class="text-right">Consommation (FCFA)</th>
        <th class="text-right">Litres</th>
        <th class="text-right">Nb pleins</th>
      </tr>
    </thead>
    <tbody>
      @foreach($par_chauffeur as $i => $c)
      <tr>
        <td class="text-center">{{ $i + 1 }}</td>
        <td class="bold">{{ $c['nom'] }}</td>
        <td class="text-right bold">{{ number_format($c['total'], 0, ',', ' ') }}</td>
        <td class="text-right">{{ number_format($c['litres'], 1, ',', ' ') }}</td>
        <td class="text-right">{{ $c['nb'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

@endsection
