@extends('layouts.app', ['title' => $session->title])
@include('admin._nav')

@php($canEdit = auth()->user()->hasRole('admin'))

@section('content')
<a href="{{ route('admin.sessions.index') }}" class="inline-flex items-center gap-2 font-bold text-muted hover:text-ink">{!! icon_svg('arrow-left', 'size-5') !!}{{ __('ui.action.back') }}</a>
<div class="mt-5 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-4xl">{{ $session->title }}</h1>
        <p class="mt-2 text-muted">{{ $session->session_date->format('d/m/Y') }} · {{ $session->location ?: '—' }} · {{ $session->trainer_name }} · {{ loc($session->group?->name) ?: __('admin.sessions.everyone') }}</p>
        @if ($session->notes)<p class="mt-2 max-w-2xl">{{ $session->notes }}</p>@endif
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.exports.attendance', ['session' => $session->id]) }}" class="btn-ghost btn-sm">{!! icon_svg('printer', 'size-4') !!}{{ __('admin.exports.attendance_short') }}</a>
        @if ($canEdit)
            <form method="POST" action="{{ route('admin.sessions.destroy', $session) }}" onsubmit="return confirm(@js(__('admin.sessions.confirm_delete')))">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-700">{!! icon_svg('trash-2', 'size-4') !!}{{ __('ui.action.delete') }}</button></form>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('admin.sessions.attendance', $session) }}" class="mt-8" x-data="{ setAll(v) { $el.querySelectorAll('select').forEach(s => s.value = v) } }">
    @csrf @method('PUT')
    @if ($canEdit)
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm font-bold">
            <span class="text-muted">{{ __('admin.sessions.mark_all') }}:</span>
            @foreach (\App\Models\TrainingSession::ATTENDANCE_TYPES as $type)<button type="button" class="btn-ghost btn-sm !min-h-8" @click="setAll('{{ $type }}')">{{ __('admin.attendance.'.$type) }}</button>@endforeach
            <button type="button" class="btn-ghost btn-sm !min-h-8" @click="setAll('')">{{ __('admin.sessions.absent') }}</button>
        </div>
    @endif
    <div class="card overflow-x-auto !p-0"><table class="table-clean">
        <thead><tr><th>{{ __('admin.cols.name') }}</th><th>{{ __('admin.cols.department') }}</th><th>{{ __('admin.sessions.attendance') }}</th><th>{{ __('admin.sessions.recorded') }}</th></tr></thead>
        <tbody>@foreach ($people as $person)
            @php($a = $attendance->get($person->id))
            <tr><td class="font-bold">{{ $person->name }}</td><td>@include('partials.dept', ['department' => $person->department])</td>
                <td><select name="attendance[{{ $person->id }}]" class="field !min-h-10 !w-auto text-sm" @disabled(! $canEdit) aria-label="{{ __('admin.sessions.attendance') }} {{ $person->name }}">
                    <option value="">{{ __('admin.sessions.absent') }}</option>
                    @foreach (\App\Models\TrainingSession::ATTENDANCE_TYPES as $type)<option value="{{ $type }}" @selected($a?->attendance_type === $type)>{{ __('admin.attendance.'.$type) }}</option>@endforeach
                </select></td>
                <td class="text-muted">{{ $a ? local_dt($a->recorded_at) : '' }}</td></tr>
        @endforeach</tbody></table></div>
    @if ($canEdit)<button class="btn-primary mt-5">{!! icon_svg('save', 'size-5') !!}{{ __('admin.sessions.save') }}</button>@endif
</form>
@endsection
