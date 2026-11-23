@extends('layouts.app', ['title' => __('admin.nav.exports')])
@include('admin._nav')

@section('content')
<h1 class="text-4xl sm:text-5xl">{{ __('admin.exports.title') }}</h1>
<p class="mt-3 max-w-3xl text-lg text-muted">{{ __('admin.exports.lead', ['campaign' => $campaign->name]) }}</p>

<div class="mt-8 grid gap-6 md:grid-cols-2">
    <div class="card flex flex-col">
        <span class="flex size-14 items-center justify-center rounded-2xl bg-brand text-on-brand">{!! icon_svg('file-spreadsheet', 'size-7') !!}</span>
        <h2 class="mt-4 text-2xl">{{ __('admin.exports.compliance_title') }}</h2>
        <p class="mt-2 flex-1 text-muted">{{ __('admin.exports.compliance_lead') }}</p>
        <a href="{{ route('admin.exports.compliance') }}" class="btn-primary mt-5">{!! icon_svg('download', 'size-5') !!}Excel (.xlsx)</a>
    </div>
    <form method="GET" action="{{ route('admin.exports.attendance') }}" class="card flex flex-col">
        <span class="flex size-14 items-center justify-center rounded-2xl bg-header text-white">{!! icon_svg('printer', 'size-7') !!}</span>
        <h2 class="mt-4 text-2xl">{{ __('admin.exports.attendance_title') }}</h2>
        <p class="mt-2 flex-1 text-muted">{{ __('admin.exports.attendance_lead') }}</p>
        <label class="label mt-4" for="eg">{{ __('admin.cols.group') }}</label>
        <select class="field" id="eg" name="group"><option value="">{{ __('admin.sessions.everyone') }}</option>@foreach ($groups as $g)<option value="{{ $g->id }}">{{ loc($g->name) }}</option>@endforeach</select>
        @if ($sessions->isNotEmpty())
            <label class="label mt-3" for="es">{{ __('admin.exports.or_session') }}</label>
            <select class="field" id="es" name="session"><option value="">—</option>@foreach ($sessions as $s)<option value="{{ $s->id }}">{{ $s->session_date->format('d/m/Y') }} · {{ $s->title }}</option>@endforeach</select>
        @endif
        <button class="btn-dark mt-5">{!! icon_svg('download', 'size-5') !!}PDF (A4)</button>
    </form>
    <div class="card flex flex-col">
        <span class="flex size-14 items-center justify-center rounded-2xl bg-canvas">{!! icon_svg('scroll-text', 'size-7') !!}</span>
        <h2 class="mt-4 text-2xl">{{ __('admin.exports.audit_title') }}</h2>
        <p class="mt-2 flex-1 text-muted">{{ __('admin.exports.audit_lead') }}</p>
        <a href="{{ route('admin.exports.audit') }}" class="btn-ghost mt-5">{!! icon_svg('download', 'size-5') !!}Excel (.xlsx)</a>
    </div>
    @include('partials.kiosk-codes-form', ['departments' => $kioskDepartments])
</div>
@endsection
