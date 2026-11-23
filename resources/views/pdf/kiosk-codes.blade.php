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
    .warn { border: 0.4mm solid #b91c1c; background: #fef2f2; color: #7f1d1d; padding: 2.5mm 3mm; margin-bottom: 4mm; font-size: 8.5pt; }
    .warn strong { letter-spacing: 1px; }
    .meta { width: 100%; margin-bottom: 4mm; border-collapse: collapse; }
    .meta td { border: 0.2mm solid #c9c5bc; padding: 2mm 3mm; width: 33%; }
    .k { display: block; font-size: 6.5pt; color: #6b6f76; letter-spacing: 0.5px; }
    table.list { width: 100%; border-collapse: collapse; }
    table.list th { background: {{ setting('branding.colors.header', '#0F172A') }}; color: #fff; font-size: 7.5pt; text-align: left; padding: 2mm; }
    table.list td { border: 0.2mm solid #c9c5bc; padding: 2mm; height: 7mm; }
    .code { font-family: 'DejaVu Sans Mono', monospace; font-size: 13pt; font-weight: bold; letter-spacing: 2px; }
    .done { color: #047857; } .muted { color: #6b6f76; }
    tr { page-break-inside: avoid; }
    .foot { margin-top: 6mm; width: 100%; font-size: 8pt; color: #6b6f76; }
</style>
</head>
<body>
<table class="head"><tr>
    <td>@if ($logo)<img src="{{ $logo }}" style="height: 10mm; float: left; margin-right: 4mm;">@endif
        <h1>{{ __('ui.kiosk.codes.title') }}</h1>{{ setting('company.legal_name', setting('company.name')) }}</td>
    <td style="text-align: right; font-size: 8pt; color: #6b6f76;">{{ loc(setting('company.frameworks')) }}<br>{{ __('ui.home.campaign', ['name' => $campaign->name]) }}</td>
</tr></table>

<div class="warn"><strong>{{ upper(__('ui.kiosk.codes.confidential')) }}</strong> · {{ __('ui.kiosk.codes.confidential_text') }}</div>

<table class="meta"><tr>
    <td><span class="k">{{ upper(__('admin.cols.department')) }}</span><strong>{{ $department?->name ?? __('ui.kiosk.all_departments') }}</strong></td>
    <td><span class="k">{{ upper(__('ui.kiosk.codes.people')) }}</span><strong>{{ $rows->count() }}</strong>{{ $pendingOnly ? ' · '.__('ui.kiosk.codes.pending_only') : '' }}</td>
    <td><span class="k">{{ upper(__('ui.kiosk.codes.printed_by')) }}</span><strong>{{ $printedBy->name }}</strong><br>{{ local_dt(now()) }}</td>
</tr></table>

<table class="list">
    <thead><tr>
        <th style="width: 8mm;">#</th><th>{{ upper(__('admin.cols.name')) }}</th><th style="width: 50mm;">{{ __('admin.cols.department') }}</th>
        <th style="width: 34mm;">{{ __('ui.kiosk.codes.code') }}</th><th style="width: 28mm;">{{ __('ui.kiosk.codes.status') }}</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $i => $r)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $r['user']->name }}</strong></td>
            <td>{{ $r['user']->department?->name }}</td>
            <td>@if ($r['user']->employee_code)<span class="code">{{ $r['user']->employee_code }}</span>@else<span class="muted">{{ __('ui.kiosk.codes.no_code') }}</span>@endif</td>
            <td class="{{ $r['done'] ? 'done' : '' }}">{{ $r['done'] ? __('ui.state.done') : __('ui.state.todo') }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">{{ __('ui.kiosk.codes.empty') }}</td></tr>
    @endforelse
    </tbody>
</table>

<p class="foot">{{ __('ui.kiosk.codes.footer') }}</p>
</body>
</html>
