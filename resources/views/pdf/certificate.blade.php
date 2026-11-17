<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #17171a; margin: 0; }
    .frame { position: absolute; top: 12mm; left: 12mm; right: 12mm; bottom: 12mm; border: 1.2mm solid {{ setting('branding.colors.header', '#0F172A') }}; }
    .bar { background: {{ setting('branding.colors.header', '#0F172A') }}; color: #fff; padding: 7mm 12mm; }
    .bar table { width: 100%; } .bar td { vertical-align: middle; color: #fff; }
    .company { font-size: 15pt; font-weight: bold; }
    .content { padding: 9mm 18mm 0; text-align: center; }
    .eyebrow { font-size: 9pt; letter-spacing: 2px; color: #6b6f76; }
    h1 { font-size: 27pt; margin: 3mm 0 5mm; }
    .name { font-size: 25pt; font-weight: bold; margin: 3mm 0; padding-bottom: 3mm; border-bottom: 1mm solid {{ setting('branding.colors.primary', '#2563EB') }}; display: inline-block; }
    p { font-size: 11.5pt; line-height: 1.5; margin: 2mm 0; }
    .facts { width: 100%; margin-top: 7mm; border-collapse: collapse; }
    .facts td { width: 25%; border: 0.3mm solid #e6e3dc; padding: 3.5mm; font-size: 10pt; }
    .facts .k { display: block; font-size: 7.5pt; color: #6b6f76; letter-spacing: 1px; }
    .facts .v { font-weight: bold; font-size: 12pt; }
    .sign { width: 100%; margin-top: 9mm; } .sign td { width: 50%; font-size: 9.5pt; vertical-align: bottom; text-align: left; }
    .line { border-top: 0.3mm solid #17171a; width: 70mm; padding-top: 2mm; margin-top: 12mm; }
    .small { font-size: 8pt; color: #6b6f76; }
</style>
</head>
<body>
<div class="frame">
    <div class="bar">
        <table><tr>
            <td>@if ($logo)<img src="{{ $logo }}" style="height: 11mm; vertical-align: middle; margin-right: 4mm;">@endif<span class="company">{{ setting('company.legal_name', setting('company.name')) }}</span></td>
            <td style="text-align: right; font-size: 9pt;">{{ loc(setting('company.frameworks')) }}</td>
        </tr></table>
    </div>
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

        <table class="sign"><tr>
            <td>
                <div class="line"><strong>{{ setting('company.signatory.name') }}</strong><br>{{ loc(setting('company.signatory.title')) }}</div>
            </td>
            <td style="text-align: right;" class="small">
                {{ __('ui.certificate.reference') }}: {{ strtoupper(substr(hash('sha256', $user->id.'|'.$p['passed_attempt']->id.'|'.$p['passed_attempt']->completed_at), 0, 12)) }}<br>
                {{ __('ui.certificate.issued') }}: {{ local_dt(now()) }}
                @if ($p['supervised'])<br>{{ __('ui.certificate.supervised') }}@endif
            </td>
        </tr></table>
    </div>
</div>
</body>
</html>
