@extends('layouts.app', ['title' => __('admin.nav.content')])
@include('admin._nav')

@php($canEdit = auth()->user()->hasRole('admin'))

@section('content')
<h1 class="text-4xl sm:text-5xl">{{ __('admin.content.heading') }}</h1>
<p class="mt-3 max-w-3xl text-lg text-muted">{{ __('admin.content.lead') }}</p>

@foreach (['policies' => 'file-check-2', 'modules' => 'graduation-cap', 'questions' => 'circle-help', 'toolkit' => 'briefcase-business'] as $type => $icon)
    <section class="mt-10">
        <div class="flex items-center justify-between gap-4">
            <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg($icon, 'size-6') !!}{{ __('admin.content.'.$type) }}</h2>
            @if ($canEdit)<a href="{{ route('admin.content.create', $type) }}" class="btn-dark btn-sm">{!! icon_svg('plus', 'size-4') !!}{{ __('ui.action.add') }}</a>@endif
        </div>
        <div class="card mt-4 overflow-x-auto !p-0"><table class="table-clean">
            @if ($type === 'policies')
                <thead><tr><th>{{ __('admin.cols.policy') }}</th><th>{{ __('admin.cols.version') }}</th><th>{{ __('admin.content.effective') }}</th><th>{{ __('admin.content.applies') }}</th><th>{{ __('admin.content.document') }}</th><th></th></tr></thead>
                <tbody>@foreach ($policies as $p)
                    <tr @class(['opacity-50' => ! $p->is_active])><td><span class="chip bg-header text-white !text-xs">{{ $p->code }}</span> <span class="font-bold">{{ loc($p->title) }}</span></td><td>{{ $p->version }}</td><td>{{ $p->effective_date?->format('d/m/Y') }}</td>
                        <td class="text-muted">{{ $p->is_mandatory_all ? __('admin.content.everyone') : implode(', ', $p->track_keys ?? []) }}</td><td>{{ $p->hasDocument() ? '✓' : '—' }}</td>
                        <td class="text-right">@if ($canEdit)<a class="font-bold hover:underline" href="{{ route('admin.content.edit', ['policies', $p->id]) }}">{{ __('ui.action.edit') }}</a>@endif</td></tr>
                @endforeach</tbody>
            @elseif ($type === 'modules')
                <thead><tr><th>{{ __('admin.content.module') }}</th><th>{{ __('admin.content.slides') }}</th><th>{{ __('admin.content.minutes') }}</th><th>{{ __('admin.content.tracks') }}</th><th></th></tr></thead>
                <tbody>@foreach ($modules as $m)
                    <tr @class(['opacity-50' => ! $m->is_active])><td class="font-bold">{{ loc($m->title) }}<br><span class="font-mono text-xs font-normal text-muted">{{ $m->key }}</span></td><td>{{ count($m->slides ?? []) }}</td><td>{{ $m->estimated_minutes }}′</td>
                        <td class="text-muted">{{ $m->tracks->map(fn ($t) => loc($t->name))->implode(', ') }}</td>
                        <td class="text-right">@if ($canEdit)<a class="font-bold hover:underline" href="{{ route('admin.content.edit', ['modules', $m->id]) }}">{{ __('ui.action.edit') }}</a>@endif</td></tr>
                @endforeach</tbody>
            @elseif ($type === 'toolkit')
                <thead><tr><th>{{ __('admin.content.title') }}</th><th>{{ __('admin.content.type') }}</th><th>{{ __('admin.content.file') }}</th><th>{{ __('admin.content.related') }}</th><th>{{ __('admin.content.submissions') }}</th><th></th></tr></thead>
                <tbody>@forelse ($toolkit as $t)
                    <tr @class(['opacity-50' => ! $t->is_active])><td><span class="chip bg-header text-white !text-xs">{{ $t->code }}</span> <span class="font-bold">{{ loc($t->title) }}</span></td>
                        <td class="text-muted">{{ __('ui.toolkit.type_one.'.$t->type) }}</td>
                        <td>@if ($t->hasFile())<span class="font-mono text-xs uppercase">.{{ $t->extension() }}</span>@else — @endif</td>
                        <td class="text-muted">{{ implode(', ', $t->related_codes ?? []) ?: '—' }}</td>
                        <td>{{ $t->submissions_count ?: '—' }}</td>
                        <td class="text-right">@if ($canEdit)<a class="font-bold hover:underline" href="{{ route('admin.content.edit', ['toolkit', $t->id]) }}">{{ __('ui.action.edit') }}</a>@endif</td></tr>
                @empty
                    <tr><td colspan="6" class="text-muted">{{ __('ui.toolkit.empty') }}</td></tr>
                @endforelse</tbody>
            @else
                <thead><tr><th>{{ __('admin.content.question') }}</th><th>{{ __('admin.cols.track') }}</th><th>{{ __('admin.content.correct') }}</th><th></th></tr></thead>
                <tbody>@foreach ($tracks as $t) @foreach ($questions->get($t->id, []) as $q)
                    <tr @class(['opacity-50' => ! $q->is_active])><td class="max-w-xl">{{ Str::limit(loc($q->question_text), 110) }}</td><td class="text-muted">{{ loc($t->name) }}</td><td class="font-bold">{{ $q->correct_option }}</td>
                        <td class="text-right">@if ($canEdit)<a class="font-bold hover:underline" href="{{ route('admin.content.edit', ['questions', $q->id]) }}">{{ __('ui.action.edit') }}</a>@endif</td></tr>
                @endforeach @endforeach</tbody>
            @endif
        </table></div>
        @if ($type === 'questions')
            <p class="mt-3 text-sm text-muted">@foreach ($tracks as $t){{ loc($t->name) }}: {{ __('admin.content.draws', ['n' => $t->quiz_question_count, 'pool' => $t->questionPool()->count()]) }}@if ($t->quiz_from) ({{ __('admin.content.shared_with', ['track' => $t->quiz_from]) }})@endif @unless ($loop->last) · @endunless @endforeach</p>
        @endif
    </section>
@endforeach
@endsection
