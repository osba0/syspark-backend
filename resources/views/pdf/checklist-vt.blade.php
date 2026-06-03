@extends('pdf.layout')

@section('doc-title', 'CHECKLIST PRÉPARATION VISITE TECHNIQUE')

@section('content')

@php
  $data = $checklist->data_json ?? [];
  function checkValVT($val) {
    if (is_null($val) || $val === '') return '<span style="color:#bbb">—</span>';
    $v = strtolower((string)$val);
    if (in_array($v, ['bon','oui','ok','conforme','1'])) return '<span style="color:#27ae60;font-weight:bold">✓ OK</span>';
    if (in_array($v, ['moyen','acceptable']))             return '<span style="color:#e67e22;font-weight:bold">~ MOYEN</span>';
    if (in_array($v, ['mauvais','non','defaillant','non_conforme'])) return '<span style="color:#e74c3c;font-weight:bold">✗ DÉFAUT</span>';
    return '<span style="color:#555">' . $val . '</span>';
  }
@endphp

{{-- En-tête --}}
<div class="section">
  <div class="section-title">Identification du véhicule</div>
  <div class="grid">
    <div class="col col-3">
      <div class="field"><div class="field-label">Immatriculation</div>
        <div class="field-box bold" style="font-size:15px; color:#1B4F72;">{{ $checklist->vehicule?->immatriculation }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Marque / Modèle</div>
        <div class="field-box">{{ $checklist->vehicule?->marque }} {{ $checklist->vehicule?->modele }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Date de mise en circulation</div>
        <div class="field-box">{{ $checklist->vehicule?->date_mise_circulation?->format('d/m/Y') ?? '—' }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Date de contrôle</div>
        <div class="field-box bold">{{ $checklist->date->format('d/m/Y') }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Dernière VT</div>
        <div class="field-box">{{ $checklist->vehicule?->date_derniere_visite_tech?->format('d/m/Y') ?? '—' }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Kilométrage</div>
        <div class="field-box">{{ $checklist->kilometrage ? number_format($checklist->kilometrage, 0, ',', ' ') . ' km' : '—' }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Corps checklist VT en 2 colonnes --}}
<div class="grid">

  {{-- Colonne gauche --}}
  <div class="col col-2">

    <div class="section">
      <div class="section-title">Freinage</div>
      <div class="check-grid">
        @php $fr = $data['freinage'] ?? []; @endphp
        @foreach(['frein_service' => 'Frein de service', 'frein_stationnement' => 'Frein stationnement', 'disque_frein_av' => 'Disque frein AV', 'disque_frein_ar' => 'Disque frein AR', 'plaquettes_av' => 'Plaquettes AV'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValVT($fr[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="section mt-8">
      <div class="section-title">Direction & Suspension</div>
      <div class="check-grid">
        @php $ds = $data['direction_suspension'] ?? []; @endphp
        @foreach(['parallelisme' => 'Parallélisme', 'amortisseurs_av' => 'Amortisseurs AV', 'amortisseurs_ar' => 'Amortisseurs AR', 'rotules_direction' => 'Rotules direction'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValVT($ds[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="section mt-8">
      <div class="section-title">Moteur & Échappement</div>
      <div class="check-grid">
        @php $mo = $data['moteur_echappement'] ?? []; @endphp
        @foreach(['niveaux' => 'Niveaux (huile, eau, frein)', 'echappement' => 'Échappement', 'courroie' => 'Courroie distribution'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValVT($mo[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

  </div>

  {{-- Colonne droite --}}
  <div class="col col-2">

    <div class="section">
      <div class="section-title">Éclairage & Signalisation</div>
      <div class="check-grid">
        @php $ec = $data['eclairage_signalisation'] ?? []; @endphp
        @foreach(['phare_av_g' => 'Phare AV G', 'phare_av_d' => 'Phare AV D', 'feux_position' => 'Feux de position', 'feu_stop' => 'Feu stop', 'clignotants' => 'Clignotants (4)', 'feux_recul' => 'Feux de recul', 'feux_brouillard' => 'Feux brouillard'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValVT($ec[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="section mt-8">
      <div class="section-title">Pneumatiques</div>
      <div class="check-grid">
        @php $pn = $data['pneumatiques'] ?? []; @endphp
        @foreach(['pneu_av_g' => 'Pneu AV G', 'pneu_av_d' => 'Pneu AV D', 'pneu_ar_g' => 'Pneu AR G', 'pneu_ar_d' => 'Pneu AR D', 'roue_secours' => 'Roue de secours'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValVT($pn[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="section mt-8">
      <div class="section-title">Carrosserie & Vitrage</div>
      <div class="check-grid">
        @php $cv = $data['carrosserie_vitrage'] ?? []; @endphp
        @foreach(['pare_brise' => 'Pare-brise', 'vitres' => 'Vitres latérales', 'carrosserie' => 'Carrosserie', 'portes' => 'Portes & fermetures'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValVT($cv[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

  </div>
</div>

{{-- Résultat global --}}
<div style="border:2px solid {{ $checklist->resultat_global === 'conforme' ? '#27ae60' : '#e74c3c' }}; border-radius:6px; padding:8px 12px; margin:10px 0; text-align:center;">
  @if($checklist->resultat_global === 'conforme')
    <span style="font-size:13px; font-weight:bold; color:#27ae60;">✓ VÉHICULE PRÊT POUR LA VISITE TECHNIQUE</span>
  @elseif($checklist->resultat_global === 'non_conforme')
    <span style="font-size:13px; font-weight:bold; color:#e74c3c;">✗ VÉHICULE NON CONFORME — {{ $checklist->nombre_non_conformites }} point(s) à corriger avant la VT</span>
  @else
    <span style="font-size:12px; color:#888;">En attente de validation</span>
  @endif
</div>

{{-- Non-conformités si présentes --}}
@if(!empty($checklist->non_conformites))
<div class="section">
  <div class="section-title" style="background:#e74c3c;">Points à corriger avant la VT</div>
  <table>
    <thead>
      <tr><th>Section</th><th>Élément</th><th>État constaté</th><th>Critique</th></tr>
    </thead>
    <tbody>
      @foreach($checklist->non_conformites as $nc)
      <tr>
        <td>{{ ucfirst(str_replace('_', ' ', $nc['section'])) }}</td>
        <td class="bold">{{ ucfirst(str_replace('_', ' ', $nc['item'])) }}</td>
        <td class="text-danger bold">{{ strtoupper($nc['valeur']) }}</td>
        <td class="text-center">{{ $nc['critique'] ? '⚠️ OUI' : '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- Observations --}}
<div class="section">
  <div class="section-title">Observations & Travaux à effectuer</div>
  <div style="border:1px solid #ccc; padding:6px; min-height:40px; border-radius:3px; font-size:10px;">
    {{ $checklist->observations ?? '' }}
  </div>
</div>

{{-- Signatures --}}
<div class="signatures">
  <div class="sig-cell" style="padding-right:20px;">
    <div class="sig-label">Le Chauffeur</div>
    <div class="sig-line"></div>
    <div>{{ $checklist->chauffeur?->nom_complet ?? '____________________' }}</div>
  </div>
  <div class="sig-cell" style="padding-left:20px;">
    <div class="sig-label">Le Responsable de Parc</div>
    <div class="sig-line"></div>
    @if($checklist->validePar)
      <div>{{ $checklist->validePar->nom_complet }}</div>
      <div style="font-size:9px;color:#888;">Le {{ $checklist->valide_le?->format('d/m/Y') }}</div>
    @else
      <div>____________________</div>
    @endif
  </div>
</div>

@endsection
