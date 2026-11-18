<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
@php
    $header = setting('branding.colors.header', '#0F172A');
    $accent = setting('branding.colors.primary', '#2563EB');
    $legal = setting('company.legal_name', setting('company.name'));
@endphp
<style>
    /*
     * A4 landscape = 297 × 210 mm. Every region is placed absolutely inside the 12 mm frame,
     * so long company names, titles or framework references wrap inside their own box
     * instead of pushing the signature block off the page.
     */
    @page { margin: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #17171a; margin: 0; }
    .frame { position: absolute; top: 12mm; left: 12mm; width: 270.6mm; height: 183.6mm; border: 1.2mm solid {{ $header }}; } /* outer size 273 × 186 mm */
    .bar { position: absolute; top: 12mm; left: 12mm; width: 273mm; height: 30mm; background: {{ $header }}; }
    .bar table { width: 100%; border-collapse: collapse; }
    .bar td { height: 30mm; vertical-align: middle; color: #fff; padding: 0 10mm; }
    .legal { font-size: 12pt; font-weight: bold; line-height: 1.3; }
    .legal-solo { font-size: 15pt; }
    .frameworks { font-size: 7.5pt; line-height: 1.45; color: #fff; opacity: 0.85; }

    .content { position: absolute; top: 50mm; left: 26mm; width: 245mm; text-align: center; }
    .eyebrow { font-size: 8.5pt; letter-spacing: 2px; color: #6b6f76; }
    h1 { font-size: 24pt; margin: 2.5mm 0 4mm; line-height: 1.2; }
    p { font-size: 11pt; line-height: 1.45; margin: 1.5mm 0; }
    .name { font-size: 22pt; font-weight: bold; margin: 2mm 0 3mm; padding-bottom: 2.5mm; border-bottom: 1mm solid {{ $accent }}; display: inline-block; }
    .facts { width: 100%; margin-top: 6mm; border-collapse: collapse; }
    .facts td { width: 25%; border: 0.3mm solid #e6e3dc; padding: 3mm; font-size: 10pt; text-align: left; vertical-align: top; }
    .facts .k { display: block; font-size: 7pt; color: #6b6f76; letter-spacing: 1px; margin-bottom: 1mm; }
    .facts .v { font-weight: bold; font-size: 11pt; }

    .footer { position: absolute; bottom: 19mm; left: 26mm; width: 245mm; }
    .footer table { width: 100%; border-collapse: collapse; }
    .footer td { vertical-align: bottom; font-size: 9pt; line-height: 1.4; }
    .line { border-top: 0.3mm solid #17171a; padding-top: 2mm; width: 95mm; }
    .small { font-size: 7.5pt; color: #6b6f76; line-height: 1.5; text-align: right; }
</style>
</head>
<body>
<div class="bar">
    <table><tr>
        @if ($logo)
            <td style="width: 60mm;"><img src="{{ $logo }}" style="max-height: 14mm; max-width: 55mm;"></td>
            <td style="text-align: right;">
                <div class="legal">{{ $legal }}</div>
                <div class="frameworks">{{ loc(setting('company.frameworks')) }}</div>
            </td>
        @else
            <td><div class="legal legal-solo">{{ $legal }}</div></td>
            <td style="text-align: right; width: 120mm;"><div class="frameworks">{{ loc(setting('company.frameworks')) }}</div></td>
        @endif
    </tr></table>
</div>
<div class="frame"></div>

<div class="content">
    <div class="eyebrow">{{ upper(__('ui.certificate.eyebrow')) }}</div>
    <h1>{{ __('ui.certificate.title') }}</h1>
    <p>{{ __('ui.certificate.certifies') }}</p>
    <div class="name">{{ $user->name }}</div>
    <p>{{ __('ui.certificate.body', ['track' => loc($p['track']->name), 'campaign' => $campaign->name]) }}</p>

    <table class="facts"><tr>
        <td><span class="k">{{ upper(__('ui.certificate.department')) }}</span><span class="v">{{ $user->department?->name ?? '—' }}</span></td>
        <td><span class="k">{{ upper(__('ui.certificate.date')) }}</span><span class="v">{{ local_dt($p['passed_attempt']->completed_at, 'd/m/Y') }}</span></td>
        <td><span class="k">{{ upper(__('ui.certificate.score')) }}</span><span class="v">{{ (int) round($p['best_attempt']->percentage) }}%</span></td>
        <td><span class="k">{{ upper(__('ui.certificate.modules')) }}</span><span class="v">{{ $p['modules']->count() }}</span></td>
    </tr></table>
</div>

<div class="footer">
    <table><tr>
        <td style="width: 55%;">
            <div class="line"><strong>{{ setting('company.signatory.name') }}</strong><br>{{ loc(setting('company.signatory.title')) }}</div>
        </td>
        <td class="small">
            {{ __('ui.certificate.reference') }}: {{ strtoupper(substr(hash('sha256', $user->id.'|'.$p['passed_attempt']->id.'|'.$p['passed_attempt']->completed_at), 0, 12)) }}<br>
            {{ __('ui.certificate.issued') }}: {{ local_dt(now()) }}
            @if ($p['supervised'])<br>{{ __('ui.certificate.supervised') }}@endif
        </td>
    </tr></table>
</div>
</body>
</html>
