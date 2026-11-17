<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 12mm 12mm 14mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #17171a; }
    .head { width: 100%; border-bottom: 0.8mm solid {{ setting('branding.colors.header', '#0F172A') }}; padding-bottom: 3mm; margin-bottom: 4mm; }
    .head td { vertical-align: top; }
    h1 { font-size: 15pt; margin: 0 0 1mm; }
    .meta { width: 100%; margin-bottom: 4mm; border-collapse: collapse; }
    .meta td { border: 0.2mm solid #c9c5bc; padding: 2mm 3mm; width: 25%; }
    .k { display: block; font-size: 6.5pt; color: #6b6f76; letter-spacing: 0.5px; }
    table.list { width: 100%; border-collapse: collapse; }
    table.list th { background: {{ setting('branding.colors.header', '#0F172A') }}; color: #fff; font-size: 7.5pt; text-align: left; padding: 2mm; }
    table.list td { border: 0.2mm solid #c9c5bc; padding: 2mm; height: 8.5mm; }
    tr { page-break-inside: avoid; }
    .foot { margin-top: 8mm; width: 100%; } .foot td { width: 50%; font-size: 8pt; }
    .line { border-top: 0.2mm solid #17171a; width: 70mm; margin-top: 12mm; padding-top: 1.5mm; }
</style>
</head>
<body>
<table class="head"><tr>
    <td>@if ($logo)<img src="{{ $logo }}" style="height: 10mm; float: left; margin-right: 4mm;">@endif
        <h1>{{ __('admin.sheet.title') }}</h1>{{ setting('company.legal_name', setting('company.name')) }}</td>
    <td style="text-align: right; font-size: 8pt; color: #6b6f76;">{{ loc(setting('company.frameworks')) }}<br>{{ __('ui.home.campaign', ['name' => $campaign->name]) }}</td>
</tr></table>

<table class="meta"><tr>
    <td><span class="k">{{ upper($session ? __('admin.sessions.name') : __('admin.cols.group')) }}</span><strong>{{ $session?->title ?? (loc($group?->name) ?: __('admin.sessions.everyone')) }}</strong></td>
    <td><span class="k">{{ upper(__('admin.sessions.date')) }}</span><strong>{{ $session ? $session->session_date->format('d/m/Y') : '____ / ____ / ________' }}</strong></td>
    <td><span class="k">{{ upper(__('admin.cols.location')) }}</span><strong>{{ $session?->location ?: '________________' }}</strong></td>
    <td><span class="k">{{ upper(__('admin.sessions.trainer')) }}</span><strong>{{ $session?->trainer_name ?: setting('company.trainer') }}</strong></td>
</tr></table>

<table class="list">
    <thead><tr>
        <th style="width: 7mm;">#</th><th style="width: 58mm;">{{ upper(__('admin.cols.name')) }}</th><th style="width: 52mm;">{{ __('admin.cols.department') }}</th>
        <th style="width: 30mm;">{{ __('admin.sheet.digital') }}</th><th style="width: 34mm;">{{ __('admin.sheet.completed') }}</th><th>{{ __('admin.sheet.signature') }}</th>
    </tr></thead>
    <tbody>
    @foreach ($rows as $i => $r)
        <tr>
            <td>{{ $i + 1 }}</td><td><strong>{{ $r['user']->name }}</strong></td><td>{{ $r['user']->department?->name }}</td>
            <td>{{ __('admin.status.'.$r['p']['status']) }}@if ($attendance->has($r['user']->id))<br><span style="font-size: 6.5pt;">{{ __('admin.attendance.'.$attendance[$r['user']->id]->attendance_type) }}</span>@endif</td>
            <td>{{ $r['p']['passed_attempt'] ? local_dt($r['p']['passed_attempt']->completed_at).' ('.(int) round($r['p']['best_attempt']->percentage).'%)' : '' }}</td>
            <td></td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="foot"><tr>
    <td><div class="line">{{ __('admin.sessions.trainer') }}: {{ $session?->trainer_name ?: setting('company.trainer') }}</div></td>
    <td style="text-align: right; color: #6b6f76;">{{ __('admin.sheet.printed', ['date' => local_dt(now())]) }}</td>
</tr></table>
</body>
</html>
