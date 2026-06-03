<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<style>
  /* ── Reset ── */
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 11px;
    color: #1a1a1a;
    line-height: 1.5;
    padding: 6mm 10mm 0 10mm;
  }

  /* ── Page DomPDF ── */
  @@page {
    margin: 22mm 20mm 26mm 20mm;
  }

  /* ── Header 3 colonnes ── */
  .header {
    border-bottom: 3px solid #1B4F72;
    padding-bottom: 10px;
    margin-bottom: 18px;
    display: table;
    width: 100%;
  }
  .header-app     { display: table-cell; width: 28%; vertical-align: middle; }
  .header-company { display: table-cell; width: 44%; vertical-align: middle; text-align: center; border-left: 1px solid #ddd; border-right: 1px solid #ddd; padding: 0 10px; }
  .header-doc     { display: table-cell; width: 28%; vertical-align: middle; text-align: right; padding-left: 10px; }

  .app-title    { font-size: 14px; font-weight: bold; color: #1B4F72; letter-spacing: 1px; }
  .app-sub      { font-size: 8px; color: #999; margin-top: 3px; font-style: italic; }
  .company-name { font-size: 13px; font-weight: bold; color: #1B4F72; letter-spacing: 0.5px; }
  .company-meta { font-size: 8.5px; color: #555; margin-top: 3px; line-height: 1.7; }
  .doc-title {
    font-size: 11px;
    font-weight: bold;
    color: #FFFFFF;
    background: #1B4F72;
    padding: 5px 10px;
    border-radius: 4px;
    display: inline-block;
  }
  .doc-subtitle { font-size: 8.5px; color: #777; margin-top: 5px; line-height: 1.6; }

  /* ── Sections ── */
  .section        { margin-bottom: 16px; }
  .section-title  {
    background: #2E86C1;
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    padding: 5px 10px;
    margin-bottom: 8px;
    border-radius: 2px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* ── Tableaux ── */
  table { width: 100%; border-collapse: collapse; }
  th {
    background: #1B4F72;
    color: #fff;
    padding: 5px 8px;
    font-size: 10px;
    text-align: left;
    border: 1px solid #1B4F72;
  }
  td {
    padding: 5px 8px;
    border: 1px solid #d0d0d0;
    font-size: 10px;
    vertical-align: top;
  }
  .tr-even td { background: #EBF5FB; }
  .tr-odd  td { background: #FFFFFF; }
  tr td       { background: #FFFFFF; }
  tfoot td, tfoot th { border-top: 2px solid #1B4F72; }

  /* ── Grille ── */
  .grid       { display: table; width: 100%; }
  .col        { display: table-cell; padding-right: 10px; vertical-align: top; }
  .col:last-child { padding-right: 0; }
  .col-2 { width: 50%; }
  .col-3 { width: 33.33%; }
  .col-4 { width: 25%; }

  /* ── Champs formulaire ── */
  .field       { margin-bottom: 8px; }
  .field-label {
    font-size: 9px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 2px;
    font-weight: bold;
  }
  .field-box {
    border: 1px solid #aaa;
    padding: 4px 8px;
    min-height: 20px;
    border-radius: 2px;
    font-size: 11px;
  }

  /* ── Badges ── */
  .badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .badge-ok      { background: #d5f5e3; color: #1e8449; border: 1px solid #27ae60; }
  .badge-warning { background: #fef9e7; color: #9a7d0a; border: 1px solid #f39c12; }
  .badge-danger  { background: #fdedec; color: #922b21; border: 1px solid #e74c3c; }
  .badge-info    { background: #eaf4fb; color: #154360; border: 1px solid #3498db; }

  /* ── Checklist items ── */
  .check-grid  { display: table; width: 100%; }
  .check-row   { display: table-row; }
  .check-label,
  .check-val   { display: table-cell; padding: 3px 6px; border-bottom: 1px solid #eee; font-size: 10px; }
  .check-label { width: 62%; color: #333; }
  .check-val   { width: 38%; text-align: center; }

  /* ── Signatures ── */
  .signatures { display: table; width: 100%; margin-top: 18px; }
  .sig-cell {
    display: table-cell;
    width: 50%;
    border-top: 2px solid #1B4F72;
    padding-top: 6px;
    font-size: 10px;
    text-align: center;
  }
  .sig-label { font-weight: bold; color: #1B4F72; margin-bottom: 4px; }
  .sig-line  { border-bottom: 1px dashed #aaa; height: 40px; margin: 6px 20px; }

  /* ── Footer — pagination visible ── */
  .footer {
    position: fixed;
    bottom: -21mm;
    left: -10mm;
    right: -10mm;
    border-top: 1.5px solid #1B4F72;
    font-size: 8px;
    color: #666;
    padding: 4px 10mm;
    display: table;
    width: 100%;
    background: #fff;
  }
  .footer-left   { display: table-cell; text-align: left;   width: 50%; }
  .footer-center { display: table-cell; text-align: center; width: 20%; font-weight: bold; color: #1B4F72; }
  .footer-right  { display: table-cell; text-align: right;  width: 30%; }

  /* ── Pagination DomPDF (script inline) ── */
  .page-num {
    font-size: 8px;
    font-weight: bold;
    color: #1B4F72;
  }

  /* ── Utilitaires ── */
  .text-right   { text-align: right; }
  .text-center  { text-align: center; }
  .text-danger  { color: #e74c3c; }
  .text-success { color: #27ae60; }
  .bold         { font-weight: bold; }
  .mt-8         { margin-top: 8px; }
  .mb-8         { margin-bottom: 8px; }
</style>
</head>
<body>

{{-- ═══ Config entreprise + APP_NAME ═══ --}}
@php
  $config  = \App\Models\ConfigEntreprise::instance();
  $appName = config('app.name', 'Parc Auto');
@endphp

{{-- ═══ FOOTER avec pagination DomPDF ═══ --}}
<div class="footer">
  <div class="footer-left">{{ $config->nom }} &mdash; Document confidentiel</div>
  <div class="footer-center">
    <script type="text/php">
      if (isset($pdf)) {
        $font = $fontMetrics->getFont("DejaVu Sans", "bold");
        $pdf->page_text(
          297, 810,
          "Page {PAGE_NUM} / {PAGE_COUNT}",
          $font, 8,
          array(0.11, 0.31, 0.45)
        );
      }
    </script>
  </div>
  <div class="footer-right">{{ $appName }} &mdash; {{ now()->format('d/m/Y à H:i') }}</div>
</div>

{{-- ═══ HEADER : APP_NAME gauche | Entreprise centre | Date droite ═══ --}}
<div class="header">

  {{-- Gauche : Nom de l'application --}}
  <div class="header-app">
    <div class="app-title">{{ strtoupper($appName) }}</div>
    <div class="app-sub">Système de gestion de parc automobile</div>
  </div>

  {{-- Centre : Infos entreprise --}}
  <div class="header-company">
    @if($config->logo)
      <img src="{{ public_path('storage/' . $config->logo) }}"
           alt="{{ $config->nom }}"
           style="max-height:40px; max-width:120px; display:block; margin:0 auto 4px auto;" />
    @endif
    <div class="company-name">{{ strtoupper($config->nom) }}</div>
    <div class="company-meta">
      @if($config->ninea)   NINEA : {{ $config->ninea }}<br>@endif
      @if($config->rc)      RC : {{ $config->rc }}<br>@endif
      @if($config->adresse) {{ $config->adresse }}<br>@endif
      @if($config->telephone) Tél : {{ $config->telephone }}@endif
    </div>
  </div>

  {{-- Droite : Titre document + date --}}
  <div class="header-doc">
    <div class="doc-title">@yield('doc-title', 'DOCUMENT')</div>
    <div class="doc-subtitle">
      Généré le {{ now()->format('d/m/Y') }}<br>à {{ now()->format('H:i') }}
    </div>
  </div>

</div>

{{-- ═══ CONTENU SPÉCIFIQUE AU DOCUMENT ═══ --}}
@yield('content')

</body>
</html>