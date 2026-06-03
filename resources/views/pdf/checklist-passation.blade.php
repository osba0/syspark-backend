@extends('pdf.layout')

@section('doc-title', 'FICHE DE PASSATION DE VÉHICULE')

@section('content')

@php
  $data = $checklist->data_json ?? [];
  $affectation = $checklist->vehicule?->affectationActive;
  function checkValP($val) {
    if (is_null($val) || $val === '') return '<span style="color:#bbb">—</span>';
    $v = strtolower((string)$val);
    if (in_array($v, ['bon','oui','ok','1'])) return '<span style="color:#27ae60;font-weight:bold">✓ BON</span>';
    if (in_array($v, ['moyen']))              return '<span style="color:#e67e22;font-weight:bold">~ MOYEN</span>';
    if (in_array($v, ['mauvais','non','defaillant'])) return '<span style="color:#e74c3c;font-weight:bold">✗ MAUVAIS</span>';
    return '<span>' . $val . '</span>';
  }
@endphp

{{-- Bandeau passation --}}
<div style="background:#1B4F72; color:#fff; padding:8px 12px; border-radius:4px; margin-bottom:14px; text-align:center; font-size:12px; font-weight:bold;">
  ÉTAT DES LIEUX CONTRADICTOIRE — PASSATION DE VÉHICULE
</div>

{{-- Identité véhicule --}}
<div class="section">
  <div class="section-title">Véhicule concerné</div>
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
      <div class="field"><div class="field-label">Date de passation</div>
        <div class="field-box bold">{{ $checklist->date->format('d/m/Y') }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Kilométrage</div>
        <div class="field-box bold">{{ $checklist->kilometrage ? number_format($checklist->kilometrage, 0, ',', ' ') . ' km' : '—' }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Chauffeurs --}}
<div class="section">
  <div class="section-title">Chauffeurs concernés</div>
  <div class="grid">
    <div class="col col-2">
      <div style="border:2px solid #e74c3c; border-radius:6px; padding:8px; background:#fdedec;">
        <div style="font-size:9px; color:#e74c3c; font-weight:bold; text-transform:uppercase; margin-bottom:4px;">Chauffeur sortant</div>
        <div class="bold" style="font-size:12px;">{{ $checklist->chauffeur?->nom_complet ?? '____________________' }}</div>
        <div style="font-size:10px; color:#666;">{{ $checklist->chauffeur?->telephone ?? '' }}</div>
      </div>
    </div>
    <div class="col col-2">
      <div style="border:2px solid #27ae60; border-radius:6px; padding:8px; background:#d5f5e3;">
        <div style="font-size:9px; color:#27ae60; font-weight:bold; text-transform:uppercase; margin-bottom:4px;">Chauffeur entrant</div>
        <div class="bold" style="font-size:12px;">{{ $affectation?->chauffeur?->nom_complet ?? '____________________' }}</div>
        <div style="font-size:10px; color:#666;">{{ $affectation?->chauffeur?->telephone ?? '' }}</div>
      </div>
    </div>
  </div>
</div>

{{-- État des éléments --}}
<div class="grid">

  {{-- Col 1 : Niveaux + Pneus --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Niveaux</div>
      <div class="check-grid">
        @php $niv = $data['niveaux'] ?? []; @endphp
        @foreach(['huile_moteur' => 'Huile moteur', 'liquide_refroidissement' => 'Liq. refroid.', 'liquide_frein' => 'Liq. frein', 'carburant' => 'Carburant'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValP($niv[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
    <div class="section mt-8">
      <div class="section-title">Pneumatiques</div>
      <div class="check-grid">
        @php $pn = $data['pneumatiques'] ?? []; @endphp
        @foreach(['pression_av' => 'Pression AV', 'pression_ar' => 'Pression AR', 'etat_pneu_av' => 'État AV', 'etat_pneu_ar' => 'État AR'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValP($pn[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Col 2 : Documents + Matériels --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Documents de bord</div>
      <div class="check-grid">
        @php $docs = $data['documents'] ?? []; @endphp
        @foreach(['carte_grise' => 'Carte grise', 'assurance' => 'Assurance', 'permis' => 'Permis conducteur'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValP($docs[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
    <div class="section mt-8">
      <div class="section-title">Matériels de sécurité</div>
      <div class="check-grid">
        @php $mat = $data['materiels'] ?? []; @endphp
        @foreach(['cric' => 'Cric', 'roue_secours' => 'Roue secours', 'triangle' => 'Triangle', 'extincteur' => 'Extincteur', 'cle_roue' => 'Clé à roue'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValP($mat[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Col 3 : Éclairage + État général --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Éclairage</div>
      <div class="check-grid">
        @php $ecl = $data['eclairage'] ?? []; @endphp
        @foreach(['phare_av_g' => 'Phare AV G', 'phare_av_d' => 'Phare AV D', 'feu_stop' => 'Feu stop', 'clignotant_avg' => 'Cligno. AVG', 'clignotant_arg' => 'Cligno. ARG'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValP($ecl[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
    <div class="section mt-8">
      <div class="section-title">État général</div>
      <div class="check-grid">
        @php $eg = $data['etat_general'] ?? []; @endphp
        @foreach(['etat_interieur' => 'Intérieur', 'etat_exterieur' => 'Extérieur'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValP($eg[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

</div>

{{-- Remarques --}}
<div class="section">
  <div class="section-title">Remarques / Réserves</div>
  <div style="border:1px solid #ccc; padding:6px; min-height:40px; border-radius:3px; font-size:10px;">
    {{ ($data['etat_general']['remarques'] ?? '') ?: ($checklist->observations ?? '') }}
  </div>
</div>

{{-- Note réglementaire --}}
<div style="border:1px solid #1B4F72; background:#eaf4fb; padding:6px 10px; border-radius:4px; margin-bottom:14px; font-size:10px; color:#1B4F72;">
  <strong>Important :</strong> Ce document fait foi de l'état du véhicule au moment de la passation.
  Toute anomalie non signalée sera à la charge du chauffeur entrant.
</div>

{{-- Double signature --}}
<div class="signatures" style="margin-top:10px;">
  <div class="sig-cell" style="padding-right:20px;">
    <div class="sig-label" style="color:#e74c3c;">Chauffeur sortant</div>
    <div style="font-size:9px;color:#666; margin-bottom:4px;">(Je certifie remettre le véhicule dans l'état décrit)</div>
    <div class="sig-line"></div>
    <div>{{ $checklist->chauffeur?->nom_complet ?? '____________________' }}</div>
  </div>
  <div class="sig-cell" style="padding-left:20px;">
    <div class="sig-label" style="color:#27ae60;">Chauffeur entrant</div>
    <div style="font-size:9px;color:#666; margin-bottom:4px;">(Je certifie avoir reçu le véhicule dans l'état décrit)</div>
    <div class="sig-line"></div>
    <div>{{ $affectation?->chauffeur?->nom_complet ?? '____________________' }}</div>
  </div>
</div>
<div style="text-align:center; margin-top:10px;">
  <div class="sig-label">Responsable de Parc</div>
  <div style="border-bottom:1px dashed #aaa; height:40px; margin:6px auto; width:250px;"></div>
  <div>{{ $checklist->validePar?->nom_complet ?? '____________________' }}</div>
</div>

@endsection
