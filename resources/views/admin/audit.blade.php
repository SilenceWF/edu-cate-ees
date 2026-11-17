@extends('layouts.app', ['title' => __('admin.nav.audit')])
@include('admin._nav')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-4xl sm:text-5xl">{{ __('admin.audit.title') }}</h1><p class="mt-3 max-w-3xl text-lg text-muted">{{ __('admin.audit.lead') }}</p></div>
    <form method="GET"><select name="action" class="field !w-auto" onchange="this.form.submit()" aria-label="{{ __('admin.audit.action') }}"><option value="">{{ __('admin.audit.all_actions') }}</option>
        @foreach ($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></form>
</div>
<div class="card mt-6 overflow-x-auto !p-0"><table class="table-clean">
    <thead><tr><th>#</th><th>{{ __('admin.audit.when') }}</th><th>{{ __('admin.audit.action') }}</th><th>{{ __('admin.audit.actor') }}</th><th>{{ __('admin.audit.target') }}</th><th>IP</th><th>{{ __('admin.audit.details') }}</th></tr></thead>
    <tbody>@foreach ($logs as $log)
        <tr><td class="text-muted">{{ $log->id }}</td><td class="whitespace-nowrap">{{ local_dt($log->created_at, 'd/m/Y H:i:s') }}</td><td><code class="rounded bg-canvas px-1.5 py-0.5 text-xs font-bold">{{ $log->action }}</code></td>
            <td>{{ $log->actor?->name }}</td><td>{{ $log->target?->name }}</td><td class="text-muted">{{ $log->ip_address }}</td>
            <td class="max-w-xs truncate text-xs text-muted" title="{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE) }}">{{ $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE) : '' }}</td></tr>
    @endforeach</tbody></table></div>
<div class="mt-5">{{ $logs->links() }}</div>
@endsection
