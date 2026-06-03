@extends('pdf.layout')

@section('doc-title', 'CHECKLIST HEBDOMADAIRE — SCOOTER')

@section('content')

@php
  $data = $checklist->data_json ?? [];
  function checkValS($val) {
    if (is_null($val) || $val === '') return '<span style="color:#bbb">—</span>';
    $v = strtolower((string)$val);
    if (in_array($v, ['bon','oui','ok','1'])) return '<span style="color:#27ae60;font-weight:bold">✓ ' . strtoupper($val) . '</span>';
    if (in_array($v, ['moyen']))              return '<span style="color:#e67e22;font-weight:bold">~ MOYEN</span>';
    if (in_array($v, ['mauvais','non','defaillant','absent'])) return '<span style="color:#e74c3c;font-weight:bold">✗ ' . strtoupper($val) . '</span>';
    return '<span>' . $val . '</span>';
  }
@endphp

{{-- En-tête véhicule --}}
<div class="section">
  <div class="section-title">Identification</div>
  <div class="grid">
    <div class="col col-3">
      <div class="field"><div class="field-label">Matricule</div>
        <div class="field-box bold" style="font-size:15px; color:#1B4F72;">{{ $checklist->vehicule?->immatriculation }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Marque / Modèle</div>
        <div class="field-box">{{ $checklist->vehicule?->marque }} {{ $checklist->vehicule?->modele }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Axe de livraison</div>
        <div class="field-box">{{ $checklist->vehicule?->affectationActive?->axeLivraison?->nom ?? '—' }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Date</div>
        <div class="field-box bold">{{ $checklist->date->format('d/m/Y') }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Kilométrage</div>
        <div class="field-box">{{ $checklist->kilometrage ? number_format($checklist->kilometrage, 0, ',', ' ') . ' km' : '—' }}</div>
      </div>
    </div>
    <div class="col col-3">
      <div class="field"><div class="field-label">Chauffeur / Livreur</div>
        <div class="field-box">{{ $checklist->chauffeur?->nom_complet ?? '—' }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Résultat global --}}
@if($checklist->resultat_global !== 'en_attente')
<div style="margin-bottom:10px;">
  @if($checklist->resultat_global === 'conforme')
    <span class="badge badge-ok" style="font-size:11px; padding:4px 12px;">✓ CONFORME</span>
  @else
    <span class="badge badge-danger" style="font-size:11px; padding:4px 12px;">
      ✗ NON CONFORME — {{ $checklist->nombre_non_conformites }} élément(s)
    </span>
  @endif
</div>
@endif

<div class="grid">

  {{-- Colonne 1 : Niveaux + Pneumatiques + Documents --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Niveaux</div>
      <div class="check-grid">
        @php $niveaux = $data['niveaux'] ?? []; @endphp
        @foreach(['huile_moteur' => 'Huile moteur', 'liquide_refroidissement' => 'Liquide refroid.', 'carburant' => 'Carburant'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValS($niveaux[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="section mt-8">
      <div class="section-title">Pneumatiques</div>
      <div class="check-grid">
        @php $pneus = $data['pneumatiques'] ?? []; @endphp
        @foreach(['pression_av' => 'Pression AV', 'pression_ar' => 'Pression AR', 'etat_pneu_av' => 'État AV', 'etat_pneu_ar' => 'État AR'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValS($pneus[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="section mt-8">
      <div class="section-title">Documents de bord</div>
      <div class="check-grid">
        @php $docs = $data['documents'] ?? []; @endphp
        @foreach(['carte_grise' => 'Carte grise', 'assurance' => 'Assurance', 'permis' => 'Permis conduire'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValS($docs[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Colonne 2 : Visibilité + ÉPI --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Visibilité</div>
      <div class="check-grid">
        @php $vis = $data['visibilite'] ?? []; @endphp
        @foreach(['retroviseur_g' => 'Rétroviseur G', 'retroviseur_d' => 'Rétroviseur D', 'plaque_imat' => 'Plaque immat.'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValS($vis[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="section mt-8">
      <div class="section-title">Équipements EPI</div>
      <div class="check-grid">
        @php $epi = $data['equipements_epi'] ?? []; @endphp
        @foreach(['casque' => 'Casque', 'masque' => 'Masque', 'chaussures_securite' => 'Chaussures sécu.', 'sac_isotherme' => 'Sac isotherme'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValS($epi[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Colonne 3 : Éclairage + Carrosserie --}}
  <div class="col col-3">
    <div class="section">
      <div class="section-title">Signalisation & Éclairage</div>
      <div class="check-grid">
        @php $ecl = $data['eclairage'] ?? []; @endphp
        @foreach(['phare_av' => 'Phare AV', 'feu_stop' => 'Feu stop', 'clignotant_avg' => 'Cligno. AVG', 'clignotant_avd' => 'Cligno. AVD', 'clignotant_arg' => 'Cligno. ARG', 'clignotant_ard' => 'Cligno. ARD'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValS($ecl[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="section mt-8">
      <div class="section-title">Carrosserie</div>
      <div class="check-grid">
        @php $car = $data['carrosserie'] ?? []; @endphp
        @foreach(['carenage_av_g' => 'Carénage AV G', 'carenage_av_d' => 'Carénage AV D', 'carenage_ar_g' => 'Carénage AR G', 'carenage_ar_d' => 'Carénage AR D', 'garde_boue_av' => 'Garde-boue AV', 'garde_boue_ar' => 'Garde-boue AR'] as $k => $l)
        <div class="check-row">
          <div class="check-label">{{ $l }}</div>
          <div class="check-val">{!! checkValS($car[$k] ?? null) !!}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

</div>

{{-- Non-conformités --}}
@if(!empty($checklist->non_conformites))
<div class="section">
  <div class="section-title" style="background:#e74c3c;">⚠ Non-conformités détectées</div>
  <table>
    <thead>
      <tr><th>Section</th><th>Élément</th><th>Valeur</th><th>Critique</th></tr>
    </thead>
    <tbody>
      @foreach($checklist->non_conformites as $nc)
      <tr>
        <td>{{ ucfirst(str_replace('_', ' ', $nc['section'])) }}</td>
        <td>{{ ucfirst(str_replace('_', ' ', $nc['item'])) }}</td>
        <td class="text-danger bold">{{ strtoupper($nc['valeur']) }}</td>
        <td class="text-center">{{ $nc['critique'] ? '⚠️ OUI' : '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- Observations --}}
@if($checklist->observations)
<div class="section">
  <div class="section-title">Observations</div>
  <div style="border:1px solid #ccc; padding:6px; min-height:28px; border-radius:3px; font-size:10px;">
    {{ $checklist->observations }}
  </div>
</div>
@endif

{{-- Signatures --}}
<div class="signatures">
  <div class="sig-cell" style="padding-right:20px;">
    <div class="sig-label">Chauffeur / Livreur</div>
    <div class="sig-line"></div>
    <div>{{ $checklist->chauffeur?->nom_complet ?? '____________________' }}</div>
  </div>
  <div class="sig-cell" style="padding-left:20px;">
    <div class="sig-label">Responsable de Parc</div>
    <div class="sig-line"></div>
    @if($checklist->validePar)
      <div>{{ $checklist->validePar->nom_complet }}</div>
      <div style="font-size:9px;color:#888;">Validé le {{ $checklist->valide_le?->format('d/m/Y H:i') }}</div>
    @else
      <div>____________________</div>
    @endif
  </div>
</div>

@endsection
