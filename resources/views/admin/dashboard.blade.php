@extends('layouts.app', ['title' => __('admin.nav.dashboard')])
@include('admin._nav')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">{{ loc(setting('company.admin_role_label')) }} · {{ __('ui.home.campaign', ['name' => $campaign->name]) }}</p>
        <h1 class="mt-2 text-4xl sm:text-5xl">{{ __('admin.dashboard.title') }}</h1>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($campaigns->count() > 1)
            <form method="GET"><select name="campaign" class="field !min-h-10 !w-auto text-sm font-bold" onchange="this.form.submit()" aria-label="{{ __('admin.settings.campaigns') }}">
                @foreach ($campaigns as $c)<option value="{{ $c->id }}" @selected($c->id === $campaign->id)>{{ $c->name }}{{ $c->is_current ? ' ●' : '' }}</option>@endforeach
            </select></form>
        @endif
        <a href="{{ route('admin.employees.create') }}" class="btn-ghost btn-sm">{!! icon_svg('user-plus', 'size-4') !!}{{ __('admin.employees.add') }}</a>
        <a href="{{ route('admin.exports.compliance') }}" class="btn-primary btn-sm">{!! icon_svg('download', 'size-4') !!}{{ __('admin.exports.compliance_short') }}</a>
    </div>
</div>

<section class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-5">
    @foreach ([
        ['total', $kpis['total'], 'users', false], ['policy_rate', $kpis['policy_rate'].'%', 'file-check-2', false],
        ['completion_rate', $kpis['completion_rate'].'%', 'badge-check', true], ['pending', $kpis['pending'], 'hourglass', false],
        ['sessions', $kpis['sessions'], 'presentation', false],
    ] as [$key, $value, $icon, $hero])
        <div @class(['rounded-3xl p-5 sm:p-6', 'bg-header text-white' => $hero, 'border border-line bg-white' => ! $hero, 'col-span-2 lg:col-span-1' => $key === 'sessions'])>
            <span @class(['flex size-10 items-center justify-center rounded-xl', 'bg-brand text-on-brand' => $hero, 'bg-canvas' => ! $hero])>{!! icon_svg($icon, 'size-5') !!}</span>
            <p class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $value }}</p>
            <p @class(['mt-1 text-sm font-bold', 'text-white/70' => $hero, 'text-muted' => ! $hero])>{{ __('admin.kpi.'.$key) }}</p>
        </div>
    @endforeach
</section>

<section class="mt-10">
    <h2 class="text-2xl">{{ __('admin.dashboard.groups') }}</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @foreach ($groups as $g)
            <a href="{{ route('admin.dashboard', ['group' => $g['group']->id, 'campaign' => $campaign->id]) }}#people" class="card !p-5 transition hover:border-ink">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="truncate text-lg font-extrabold">{{ loc($g['group']->name) }}</p>
                        <p class="text-sm text-muted">{{ loc($g['group']->track->name) }} · {{ __('admin.dashboard.done_of', ['done' => $g['complete'], 'total' => $g['total']]) }}</p>
                    </div>
                    <p class="text-3xl font-extrabold">{{ $g['percent'] }}%</p>
                </div>
                <div class="mt-3 flex h-3 overflow-hidden rounded-full bg-line">
                    <div class="bg-brand" style="width: {{ $g['percent'] }}%"></div>
                    <div class="bg-brand/30" style="width: {{ $g['total'] ? round($g['in_progress'] / $g['total'] * 100) : 0 }}%"></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($g['departments'] as $department) @include('partials.dept') @endforeach
                </div>
            </a>
        @endforeach
    </div>
</section>

<section class="mt-10" id="people">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl">{{ __('admin.dashboard.people') }} <span class="text-muted">({{ $rows->count() }})</span></h2>
    </div>
    <form method="GET" action="{{ route('admin.dashboard') }}#people" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <input type="hidden" name="campaign" value="{{ $campaign->id }}">
        <input class="field lg:col-span-2" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.dashboard.search') }}" aria-label="{{ __('admin.dashboard.search') }}">
        <select class="field" name="group" aria-label="{{ __('admin.cols.group') }}"><option value="">{{ __('admin.cols.group') }}</option>
            @foreach ($allGroups as $g)<option value="{{ $g->id }}" @selected(($filters['group'] ?? '') == $g->id)>{{ loc($g->name) }}</option>@endforeach</select>
        <select class="field" name="department" aria-label="{{ __('admin.cols.department') }}"><option value="">{{ __('admin.cols.department') }}</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(($filters['department'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach</select>
        <select class="field" name="location" aria-label="{{ __('admin.cols.location') }}"><option value="">{{ __('admin.cols.location') }}</option>
            @foreach ($locations as $l)<option @selected(($filters['location'] ?? '') === $l)>{{ $l }}</option>@endforeach</select>
        <select class="field" name="status" aria-label="{{ __('admin.cols.status') }}"><option value="">{{ __('admin.cols.status') }}</option>
            @foreach (['complete', 'in_progress', 'pending'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __('admin.status.'.$s) }}</option>@endforeach</select>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
            <button class="btn-dark btn-sm">{!! icon_svg('filter', 'size-4') !!}{{ __('ui.action.filter') }}</button>
            @if (array_filter($filters))<a href="{{ route('admin.dashboard', ['campaign' => $campaign->id]) }}#people" class="btn-ghost btn-sm">{{ __('ui.action.clear') }}</a>@endif
        </div>
    </form>

    <div class="card mt-4 overflow-x-auto !p-0">
        <table class="table-clean">
            <thead><tr>
                <th>{{ __('admin.cols.name') }}</th><th>{{ __('admin.cols.department') }}</th><th>{{ __('admin.cols.group') }}</th>
                <th class="text-center">{{ __('admin.cols.policies') }}</th><th class="text-center">{{ __('admin.cols.modules') }}</th><th class="text-center">{{ __('admin.cols.quiz') }}</th>
                <th>{{ __('admin.cols.status') }}</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($rows as $r)
                @php([$u, $p] = [$r['user'], $r['p']])
                <tr class="hover:bg-canvas/60">
                    <td><a href="{{ route('admin.employees.show', $u) }}" class="font-extrabold hover:underline">{{ $u->name }}</a><br><span class="text-xs text-muted">{{ $u->email ?? __('admin.employees.no_email') }}</span></td>
                    <td>@include('partials.dept', ['department' => $u->department])</td>
                    <td class="text-muted">{{ Str::limit(loc($u->group?->name), 28) }}</td>
                    @foreach ([$p['required_policies']->isEmpty() ? null : $p['policies_done'], $p['modules_done'], $p['quiz_passed']] as $ok)
                        <td class="text-center">
                            @if ($ok === null)<span class="text-muted">—</span>
                            @elseif ($ok)<span class="inline-flex text-emerald-600">{!! icon_svg('circle-check', 'size-5') !!}</span>
                            @else<span class="inline-flex text-slate-300">{!! icon_svg('circle', 'size-5') !!}</span>@endif
                        </td>
                    @endforeach
                    <td>@include('partials.status', ['status' => $p['status']])</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($p['status'] !== 'complete' && $u->email)
                            @if (! $canSendMail)
                                <a class="btn-ghost btn-sm !min-h-9 !px-2.5" title="{{ __('admin.reminder.send') }}" aria-label="{{ __('admin.reminder.send') }}: {{ $u->name }}" href="mailto:{{ $u->email }}?subject={{ rawurlencode(__('admin.reminder.subject', ['company' => setting('company.name')])) }}&body={{ rawurlencode(__('admin.reminder.body', ['name' => $u->name, 'url' => url('/'), 'company' => setting('company.name')])) }}">{!! icon_svg('mail', 'size-4') !!}</a>
                            @else
                                <form method="POST" action="{{ route('admin.employees.remind', $u) }}" class="inline">@csrf<button class="btn-ghost btn-sm !min-h-9 !px-2.5" title="{{ __('admin.reminder.send') }}" aria-label="{{ __('admin.reminder.send') }}: {{ $u->name }}">{!! icon_svg('mail', 'size-4') !!}</button></form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="!py-10 text-center text-muted">{{ __('admin.dashboard.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
