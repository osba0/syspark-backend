@extends('pdf.layout')

@section('doc-title', 'FICHE DE SIGNALEMENT')

@section('content')

{{-- Identité véhicule --}}
<div class="section">
  <div class="section-title">Identification du véhicule</div>
  <div class="grid">
    <div class="col col-4">
      <div class="field">
        <div class="field-label">Date</div>
        <div class="field-box bold">{{ $signalement->date_signalement->format('d/m/Y') }}</div>
      </div>
    </div>
    <div class="col col-4">
      <div class="field">
        <div class="field-label">Immatriculation</div>
        <div class="field-box bold" style="font-size:13px; color:#1B4F72;">
          {{ $signalement->vehicule?->immatriculation ?? '—' }}
        </div>
      </div>
    </div>
    <div class="col col-4">
      <div class="field">
        <div class="field-label">Marque / Modèle</div>
        <div class="field-box">{{ $signalement->vehicule?->marque }} {{ $signalement->vehicule?->modele }}</div>
      </div>
    </div>
    <div class="col col-4">
      <div class="field">
        <div class="field-label">Kilométrage</div>
        <div class="field-box">{{ $signalement->kilometrage ? number_format($signalement->kilometrage, 0, ',', ' ') . ' km' : '—' }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Gravité / Type --}}
<div class="section">
  <div class="grid">
    <div class="col col-2">
      <div class="field">
        <div class="field-label">Type de défaut</div>
        <div class="field-box">{{ ucfirst(str_replace('_', ' ', $signalement->type_defaut)) }}</div>
      </div>
    </div>
    <div class="col col-2">
      <div class="field">
        <div class="field-label">Gravité</div>
        <div class="field-box">
          @php
            $badgeClass = match($signalement->gravite) {
              'critique', 'haute' => 'badge-danger',
              'moyenne' => 'badge-warning',
              default => 'badge-info'
            };
          @endphp
          <span class="badge {{ $badgeClass }}">{{ strtoupper($signalement->gravite) }}</span>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Pneumatiques & Documents (reproduce la fiche papier) --}}
@php $etat = $signalement->etat_elements ?? []; @endphp

<div class="grid">
  {{-- Colonne gauche : Pneumatiques --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Pneumatiques</div>
      <div class="check-grid">
        @foreach(['pneu_av' => 'Pneu AV', 'pneu_ar' => 'Pneu AR'] as $key => $label)
        <div class="check-row">
          <div class="check-label">{{ $label }}</div>
          <div class="check-val">
            @php $val = $etat[$key] ?? null; @endphp
            @if($val === 'mauvais') <span class="check-bad">Mauvais</span>
            @elseif($val === 'moyen') <span class="check-warn">Moyen</span>
            @elseif($val === 'bon') <span class="check-ok">Bon</span>
            @else <span class="check-na">—</span>
            @endif
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Colonne centre : Documents --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Documents de bord</div>
      <div class="check-grid">
        @foreach(['carte_grise' => 'Carte grise', 'assurance' => 'Assurance', 'gilet' => 'Gilet', 'casque' => 'Casque'] as $key => $label)
        <div class="check-row">
          <div class="check-label">{{ $label }}</div>
          <div class="check-val">
            @php $val = $etat[$key] ?? null; @endphp
            @if($val === 'oui' || $val === true) <span class="check-ok">OUI</span>
            @elseif($val === 'non' || $val === false) <span class="check-bad">NON</span>
            @else <span class="check-na">—</span>
            @endif
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Colonne droite : Propreté --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Propreté</div>
      <div class="check-grid">
        @foreach(['carrosserie' => 'Carrosserie'] as $key => $label)
        <div class="check-row">
          <div class="check-label">{{ $label }}</div>
          <div class="check-val">
            @php $val = $etat[$key] ?? null; @endphp
            @if($val === 'ok') <span class="check-ok">OK</span>
            @elseif($val === 'non') <span class="check-bad">NON</span>
            @else <span class="check-na">—</span>
            @endif
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</div>

{{-- État carrosserie --}}
<div class="section">
  <div class="section-title">État carrosserie (éléments abîmés cochés)</div>
  <table>
    <thead>
      <tr>
        <th>Élément</th><th>État</th>
        <th>Élément</th><th>État</th>
        <th>Élément</th><th>État</th>
      </tr>
    </thead>
    <tbody>
      @php
        $carrosserie = [
          'retroviseur_g' => 'Rétroviseur G', 'retroviseur_d' => 'Rétroviseur D',
          'carenage_av_g' => 'Carénage AV G', 'carenage_av_d' => 'Carénage AV D',
          'carenage_ar_g' => 'Carénage AR G', 'carenage_ar_d' => 'Carénage AR D',
          'visiere' => 'Visière', 'carenage' => 'Carénage principal',
          'garde_boue_av' => 'Garde-boue AV', 'garde_boue_ar' => 'Garde-boue AR',
          'frein_av' => 'Frein AV', 'frein_ar' => 'Frein AR',
        ];
        $chunks = array_chunk($carrosserie, 3, true);
        // Reformater pour 3 colonnes par ligne
        $rows = [];
        $items = array_values($carrosserie);
        $keys  = array_keys($carrosserie);
        for ($i = 0; $i < count($items); $i += 3) {
          $rows[] = [
            [$keys[$i] ?? null, $items[$i] ?? null],
            [$keys[$i+1] ?? null, $items[$i+1] ?? null],
            [$keys[$i+2] ?? null, $items[$i+2] ?? null],
          ];
        }
      @endphp
      @foreach($rows as $row)
      <tr class="{{ $loop->even ? \'tr-even\' : \'tr-odd\' }}">
        @foreach($row as [$key, $label])
          <td>{{ $label ?? '' }}</td>
          <td class="text-center">
            @if($key && isset($etat[$key]))
              <span class="{{ $etat[$key] === 'mauvais' ? 'check-bad' : ($etat[$key] === 'moyen' ? 'check-warn' : 'check-ok') }}">
                {{ strtoupper($etat[$key]) }}
              </span>
            @else
              <span class="check-na">—</span>
            @endif
          </td>
        @endforeach
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Signalisation & éclairage --}}
<div class="section">
  <div class="section-title">Signalisation et éclairage</div>
  <table>
    <thead>
      <tr>
        <th>Élement</th><th>État</th>
        <th>Élement</th><th>État</th>
        <th>Élement</th><th>État</th>
      </tr>
    </thead>
    <tbody>
      @php
        $eclairage = [
          'phare_av_g' => 'Phare AV G', 'phare_av_d' => 'Phare AV D',
          'clignotant_avg' => 'Clignotant AVG', 'clignotant_avd' => 'Clignotant AVD',
          'clignotant_arg' => 'Clignotant ARG', 'clignotant_ard' => 'Clignotant ARD',
          'feu_stop' => 'Feu de stop',
        ];
        $eKeys = array_keys($eclairage); $eVals = array_values($eclairage);
        $eRows = [];
        for ($i = 0; $i < count($eVals); $i += 3) {
          $eRows[] = [
            [$eKeys[$i] ?? null, $eVals[$i] ?? null],
            [$eKeys[$i+1] ?? null, $eVals[$i+1] ?? null],
            [$eKeys[$i+2] ?? null, $eVals[$i+2] ?? null],
          ];
        }
      @endphp
      @foreach($eRows as $row)
      <tr class="{{ $loop->even ? \'tr-even\' : \'tr-odd\' }}">
        @foreach($row as [$key, $label])
          <td>{{ $label ?? '' }}</td>
          <td class="text-center">
            @if($key && isset($etat[$key]))
              @php $v = $etat[$key]; @endphp
              <span class="{{ in_array($v, ['defaillant', 'non', 'mauvais']) ? 'check-bad' : 'check-ok' }}">
                {{ $v === 'defaillant' ? 'Défaillant' : ($v === 'bon' ? 'Bon' : strtoupper($v)) }}
              </span>
            @else
              <span class="check-na">—</span>
            @endif
          </td>
        @endforeach
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Description --}}
<div class="section">
  <div class="section-title">Description du problème</div>
  <div style="border:1px solid #ccc; padding:8px; min-height:50px; border-radius:3px; font-size:11px;">
    <strong>{{ $signalement->titre }}</strong><br>
    {{ $signalement->description }}
  </div>
</div>

{{-- Signatures --}}
<div class="signatures">
  <div class="sig-cell" style="padding-right:20px;">
    <div class="sig-label">Le Chauffeur</div>
    <div class="sig-line"></div>
    <div>{{ $signalement->chauffeur?->nom_complet ?? '____________________' }}</div>
  </div>
  <div class="sig-cell" style="padding-left:20px;">
    <div class="sig-label">Le Responsable de Parc</div>
    <div class="sig-line"></div>
    <div>{{ $signalement->createdBy?->nom_complet ?? '____________________' }}</div>
  </div>
</div>

@endsection
