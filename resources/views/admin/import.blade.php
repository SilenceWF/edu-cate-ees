@extends('layouts.app', ['title' => __('admin.nav.import')])
@include('admin._nav')

@section('content')
<h1 class="text-4xl sm:text-5xl">{{ __('admin.import.title') }}</h1>
<p class="mt-3 max-w-3xl text-lg text-muted">{{ __('admin.import.lead') }}</p>

@if (! $plan)
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.import.preview') }}" enctype="multipart/form-data" class="card">
            @csrf
            <span class="flex size-14 items-center justify-center rounded-2xl bg-brand text-on-brand">{!! icon_svg('file-spreadsheet', 'size-7') !!}</span>
            <h2 class="mt-4 text-2xl">{{ __('admin.import.upload') }}</h2>
            <input type="file" name="file" accept=".xlsx,.xls" required class="field mt-4 py-2.5">
            <button class="btn-primary mt-4">{!! icon_svg('eye', 'size-5') !!}{{ __('admin.import.preview') }}</button>
            <p class="mt-3 text-sm text-muted">{{ __('admin.import.safe') }}</p>
        </form>
        <div class="card">
            <h2 class="text-2xl">{{ __('admin.import.columns') }}</h2>
            <table class="table-clean mt-3">
                @foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col)
                    <tr><td class="w-10 font-mono font-extrabold">{{ $col }}</td><td>{{ __('admin.import.col.'.$col) }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>
@else
    <div class="mt-8 grid grid-cols-3 gap-4">
        <div class="card !p-5"><p class="text-4xl font-extrabold">{{ $plan['summary']['create'] }}</p><p class="text-sm font-bold text-muted">{{ __('admin.import.will_create') }}</p></div>
        <div class="card !p-5"><p class="text-4xl font-extrabold">{{ $plan['summary']['update'] }}</p><p class="text-sm font-bold text-muted">{{ __('admin.import.will_update') }}</p></div>
        <div class="card !p-5"><p class="text-4xl font-extrabold">{{ count($plan['summary']['new_groups']) }}</p><p class="text-sm font-bold text-muted">{{ __('admin.import.new_groups') }}</p></div>
    </div>
    @if ($plan['errors'])
        <div class="mt-4 rounded-2xl border-2 border-amber-200 bg-amber-50 p-5 font-semibold text-amber-900"><ul class="list-disc space-y-1 pl-5">@foreach ($plan['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <div class="mt-4 flex flex-wrap gap-3">
        <form method="POST" action="{{ route('admin.import.apply') }}">@csrf<button class="btn-primary">{!! icon_svg('check', 'size-5') !!}{{ __('admin.import.apply') }}</button></form>
        <a href="{{ route('admin.import') }}" class="btn-ghost">{{ __('ui.action.cancel') }}</a>
    </div>
    <div class="card mt-6 overflow-x-auto !p-0"><table class="table-clean">
        <thead><tr><th>#</th><th>{{ __('admin.cols.name') }}</th><th>Email</th><th>{{ __('admin.cols.group') }}</th><th>{{ __('admin.cols.department') }}</th><th>{{ __('admin.cols.track') }}</th><th>{{ __('admin.import.action') }}</th></tr></thead>
        <tbody>@foreach ($plan['rows'] as $row)
            <tr><td class="text-muted">{{ $row['line'] }}</td><td class="font-bold">{{ $row['name'] }}</td><td class="text-muted">{{ $row['email'] }}</td>
                <td>{{ $row['group'] }} @if ($row['new_group'])<span class="chip bg-amber-100 text-amber-900 !text-xs">{{ __('admin.import.new') }}</span>@endif</td>
                <td><span class="inline-flex items-center gap-2">@if ($row['color'])<span class="size-4 rounded-full border border-line" style="background: {{ $row['color'] }}"></span>@endif{{ $row['department'] }}</span></td>
                <td class="text-muted">{{ $row['track_key'] }}</td>
                <td><span @class(['chip !text-xs', 'bg-emerald-100 text-emerald-800' => $row['action'] === 'create', 'bg-slate-100 text-slate-700' => $row['action'] === 'update'])>{{ __('admin.import.'.$row['action']) }}</span></td></tr>
        @endforeach</tbody></table></div>
@endif
@endsection
