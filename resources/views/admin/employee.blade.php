@extends('layouts.app', ['title' => $user->name])
@include('admin._nav')

@section('content')
<a href="{{ route('admin.dashboard') }}#people" class="inline-flex items-center gap-2 font-bold text-muted hover:text-ink">{!! icon_svg('arrow-left', 'size-5') !!}{{ __('ui.action.back') }}</a>
<div class="mt-5 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-4xl">{{ $user->name }}</h1>
        <p class="mt-2 flex flex-wrap items-center gap-2 text-muted">
            @include('partials.dept', ['department' => $user->department])
            <span>{{ loc($user->group?->name) }}</span>·<span>{{ loc($p['track']?->name) }}</span>·<span>{{ __('admin.roles.'.$user->role) }}</span>
            @unless ($user->is_active)<span class="chip bg-red-100 text-red-800 !text-xs">{{ __('admin.employees.inactive') }}</span>@endunless
        </p>
    </div>
    <div class="flex gap-2">
        @if ($p['status'] === 'complete')<a href="{{ route('admin.employees.certificate', $user) }}" class="btn-ghost btn-sm">{!! icon_svg('award', 'size-4') !!}{{ __('admin.employees.certificate') }}</a>@endif
        <a href="{{ route('admin.employees.edit', $user) }}" class="btn-dark btn-sm">{!! icon_svg('pencil', 'size-4') !!}{{ __('ui.action.edit') }}</a>
    </div>
</div>

<div class="mt-8 grid gap-4 lg:grid-cols-3">
    <div class="card !p-6"><p class="eyebrow">Email</p><p class="mt-1 font-bold break-all">{{ $user->email ?? '—' }}</p>
        <p class="eyebrow mt-4">{{ __('admin.cols.employee_code') }}</p><p class="mt-1 font-mono text-lg font-bold">{{ $user->employee_code ?? '—' }}</p>
        <p class="eyebrow mt-4">{{ __('admin.employees.last_login') }}</p><p class="mt-1 font-bold">{{ local_dt($user->last_login_at) }}</p></div>
    <div class="card !p-6 lg:col-span-2">
        <div class="flex items-center justify-between"><p class="eyebrow">{{ __('ui.home.campaign', ['name' => $campaign->name]) }}</p>@include('partials.status', ['status' => $p['status']])</div>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div><dt class="text-sm text-muted">{{ __('admin.cols.policies') }}</dt><dd class="text-lg font-extrabold">{{ $p['required_policies']->isEmpty() ? __('admin.exports.not_required') : count($p['acknowledged_ids']).' / '.$p['required_policies']->count() }}</dd><dd class="text-sm text-muted">{{ $p['policies_signed_at'] ? local_dt($p['policies_signed_at']).' · '.$p['policies_ip'] : '' }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('admin.cols.modules') }}</dt><dd class="text-lg font-extrabold">{{ count(array_intersect($p['completed_module_ids'], $p['modules']->pluck('id')->all())) }} / {{ $p['modules']->count() }}</dd><dd class="text-sm text-muted">{{ $p['modules_done_at'] ? local_dt($p['modules_done_at']) : '' }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('admin.cols.quiz') }}</dt><dd class="text-lg font-extrabold">{{ $p['best_attempt'] ? (int) round($p['best_attempt']->percentage).'%' : '—' }}</dd><dd class="text-sm text-muted">{{ trans_choice('admin.employees.attempts', $p['attempts']->count(), ['count' => $p['attempts']->count()]) }}</dd></div>
        </dl>
    </div>
</div>

@if ($p['attempts']->isNotEmpty())
    <h2 class="mt-10 text-2xl">{{ __('admin.employees.attempt_history') }}</h2>
    <div class="card mt-4 overflow-x-auto !p-0"><table class="table-clean">
        <thead><tr><th>{{ __('admin.audit.when') }}</th><th>{{ __('admin.cols.score') }}</th><th>{{ __('admin.cols.result') }}</th><th>IP</th><th>{{ __('admin.cols.supervised') }}</th></tr></thead>
        <tbody>@foreach ($p['attempts']->sortByDesc('completed_at') as $a)
            <tr><td>{{ local_dt($a->completed_at) }}</td><td class="font-bold">{{ $a->score }}/{{ $a->total_questions }} ({{ (int) round($a->percentage) }}%)</td>
                <td>@include('partials.status', ['status' => $a->passed ? 'complete' : 'pending'])</td><td class="text-muted">{{ $a->ip_address }}</td><td>{{ $a->supervised_by_user_id ? __('admin.yes') : '' }}</td></tr>
        @endforeach</tbody></table></div>
@endif

@if ($p['acknowledgments']->isNotEmpty())
    <h2 class="mt-10 text-2xl">{{ __('admin.employees.ack_history') }}</h2>
    <div class="card mt-4 overflow-x-auto !p-0"><table class="table-clean">
        <thead><tr><th>{{ __('admin.cols.policy') }}</th><th>{{ __('admin.cols.version') }}</th><th>{{ __('admin.audit.when') }}</th><th>IP</th></tr></thead>
        <tbody>@foreach ($p['acknowledgments']->sortBy('acknowledged_at') as $a)
            @php($policy = $p['required_policies']->firstWhere('id', $a->policy_id) ?? \App\Models\Policy::find($a->policy_id))
            <tr><td><span class="font-bold">{{ $policy?->code }}</span> {{ loc($policy?->title) }}</td><td>{{ $a->policy_version }}</td><td>{{ local_dt($a->acknowledged_at) }}</td><td class="text-muted">{{ $a->ip_address }}</td></tr>
        @endforeach</tbody></table></div>
@endif
@endsection
