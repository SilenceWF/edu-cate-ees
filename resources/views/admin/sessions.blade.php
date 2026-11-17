@extends('layouts.app', ['title' => __('admin.nav.sessions')])
@include('admin._nav')

@section('content')
<h1 class="text-4xl sm:text-5xl">{{ __('admin.sessions.title') }}</h1>
<p class="mt-3 max-w-3xl text-lg text-muted">{{ __('admin.sessions.lead') }}</p>

<div class="mt-8 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-x-auto !p-0"><table class="table-clean">
            <thead><tr><th>{{ __('admin.sessions.date') }}</th><th>{{ __('admin.sessions.name') }}</th><th>{{ __('admin.cols.group') }}</th><th>{{ __('admin.cols.location') }}</th><th class="text-right">{{ __('admin.sessions.attended') }}</th></tr></thead>
            <tbody>@forelse ($sessions as $s)
                <tr class="hover:bg-canvas/60"><td class="whitespace-nowrap">{{ $s->session_date->format('d/m/Y') }}</td>
                    <td><a class="font-extrabold hover:underline" href="{{ route('admin.sessions.show', $s) }}">{{ $s->title }}</a><br><span class="text-xs text-muted">{{ $s->trainer_name }}</span></td>
                    <td class="text-muted">{{ Str::limit(loc($s->group?->name) ?: __('admin.sessions.everyone'), 30) }}</td><td class="text-muted">{{ $s->location }}</td><td class="text-right font-bold">{{ $s->attendances_count }}</td></tr>
            @empty
                <tr><td colspan="5" class="!py-10 text-center text-muted">{{ __('admin.sessions.empty') }}</td></tr>
            @endforelse</tbody></table></div>
    </div>
    @if (auth()->user()->hasRole('admin'))
        <form method="POST" action="{{ route('admin.sessions.store') }}" class="card space-y-4 !p-6">
            @csrf
            <h2 class="text-xl">{{ __('admin.sessions.new') }}</h2>
            <div><label class="label" for="title">{{ __('admin.sessions.name') }} *</label><input class="field" id="title" name="title" value="{{ old('title') }}" required></div>
            <div><label class="label" for="session_date">{{ __('admin.sessions.date') }} *</label><input class="field" id="session_date" type="date" name="session_date" value="{{ old('session_date', now()->toDateString()) }}" required></div>
            <div><label class="label" for="g">{{ __('admin.cols.group') }}</label><select class="field" id="g" name="training_group_id"><option value="">{{ __('admin.sessions.everyone') }}</option>
                @foreach ($groups as $g)<option value="{{ $g->id }}">{{ loc($g->name) }}</option>@endforeach</select></div>
            <div><label class="label" for="location">{{ __('admin.cols.location') }}</label><input class="field" id="location" name="location" list="locations" value="{{ old('location') }}">
                <datalist id="locations">@foreach (setting('locations', []) as $l)<option value="{{ $l }}">@endforeach</datalist></div>
            <div><label class="label" for="trainer">{{ __('admin.sessions.trainer') }}</label><input class="field" id="trainer" name="trainer_name" value="{{ old('trainer_name', setting('company.trainer')) }}"></div>
            <div><label class="label" for="notes">{{ __('admin.sessions.notes') }}</label><textarea class="field py-3" id="notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
            <button class="btn-primary w-full">{!! icon_svg('plus', 'size-5') !!}{{ __('admin.sessions.create') }}</button>
        </form>
    @endif
</div>
@endsection
