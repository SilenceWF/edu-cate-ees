@extends('layouts.app', ['title' => __('ui.nav.my_training')])

@php
    $firstName = explode(' ', trim(auth()->user()->name))[0];
    $steps = [
        ['key' => 'policies', 'done' => $p['policies_done'], 'locked' => false, 'skip' => $p['required_policies']->isEmpty(),
            'href' => route('policies.index'), 'icon' => 'file-check-2',
            'meta' => trans_choice('ui.steps.policies.meta', $p['required_policies']->count(), ['done' => count($p['acknowledged_ids']), 'count' => $p['required_policies']->count()])],
        ['key' => 'modules', 'done' => $p['modules_done'], 'locked' => false, 'skip' => false,
            'href' => $nextModule ? route('modules.show', $nextModule) : ($p['modules']->first() ? route('modules.show', $p['modules']->first()) : '#'), 'icon' => 'graduation-cap',
            'meta' => __('ui.steps.modules.meta', ['done' => count(array_intersect($p['completed_module_ids'], $p['modules']->pluck('id')->all())), 'count' => $p['modules']->count(), 'minutes' => $p['modules']->sum('estimated_minutes')])],
        ['key' => 'quiz', 'done' => $p['quiz_passed'], 'locked' => ! $p['quiz_unlocked'], 'skip' => false,
            'href' => route('quiz.show'), 'icon' => 'badge-check',
            'meta' => $p['best_attempt'] ? __('ui.steps.quiz.best', ['score' => (int) round($p['best_attempt']->percentage)]) : __('ui.steps.quiz.meta', ['count' => $p['track']?->quiz_question_count, 'threshold' => setting('quiz.pass_threshold', 80)])],
    ];
    $current = collect($steps)->first(fn ($s) => ! $s['done'] && ! $s['skip']);
@endphp

@section('content')
@if (! $p['track'])
    <div class="card text-center">
        <h1 class="text-3xl">{{ __('ui.home.no_track_title') }}</h1>
        <p class="mt-3 text-lg text-muted">{{ __('ui.home.no_track_body', ['email' => setting('company.contact_email')]) }}</p>
    </div>
@else
    <section class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">{{ __('ui.home.campaign', ['name' => $campaign->name]) }} · {{ loc($p['track']->name) }}</p>
            <h1 class="mt-3 text-4xl sm:text-6xl">
                @if ($p['status'] === 'complete') {{ __('ui.home.done_title', ['name' => $firstName]) }} @else {{ __('ui.home.hello', ['name' => $firstName]) }} @endif
            </h1>
            <p class="mt-4 max-w-2xl text-lg text-muted sm:text-xl">
                {{ $p['status'] === 'complete' ? __('ui.home.done_lead') : __('ui.home.lead') }}
            </p>
        </div>
        <div class="w-full shrink-0 lg:w-72">
            <div class="flex items-baseline justify-between font-extrabold">
                <span>{{ __('ui.home.progress') }}</span><span class="text-3xl">{{ $p['percent'] }}%</span>
            </div>
            <div class="mt-2 h-4 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $p['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-brand transition-all" style="width: {{ $p['percent'] }}%"></div>
            </div>
        </div>
    </section>

    @if ($p['status'] === 'complete')
        <section class="mt-10 flex flex-col items-start gap-6 rounded-3xl bg-header p-8 text-white sm:flex-row sm:items-center sm:p-10">
            <span class="flex size-20 shrink-0 items-center justify-center rounded-3xl bg-brand text-on-brand">{!! icon_svg('award', 'size-10') !!}</span>
            <div class="flex-1">
                <h2 class="text-3xl">{{ __('ui.certificate.ready') }}</h2>
                <p class="mt-1 text-white/70">{{ __('ui.certificate.ready_lead', ['score' => (int) round($p['best_attempt']->percentage), 'date' => local_dt($p['passed_attempt']->completed_at, 'd/m/Y')]) }}</p>
            </div>
            <a href="{{ route('certificate') }}" class="btn-primary w-full sm:w-auto">{!! icon_svg('download', 'size-5') !!}{{ __('ui.certificate.download') }}</a>
        </section>
    @endif

    <section class="mt-10 grid gap-5 lg:grid-cols-3">
        @foreach ($steps as $i => $s)
            @php($isCurrent = $current && $current['key'] === $s['key'])
            <article @class(['card flex flex-col', 'border-2 !border-brand shadow-xl shadow-brand/10' => $isCurrent && ! $s['locked'], 'opacity-60' => $s['locked']])>
                <div class="flex items-center justify-between">
                    <span @class(['flex size-14 items-center justify-center rounded-2xl text-2xl font-extrabold', 'bg-emerald-100 text-emerald-700' => $s['done'], 'bg-brand text-on-brand' => ! $s['done'] && $isCurrent, 'bg-canvas text-muted' => ! $s['done'] && ! $isCurrent])>
                        @if ($s['done']) {!! icon_svg('check', 'size-7') !!} @elseif ($s['locked']) {!! icon_svg('lock', 'size-6') !!} @else {{ $i + 1 }} @endif
                    </span>
                    <span @class(['chip', 'bg-emerald-100 text-emerald-800' => $s['done'], 'bg-brand-soft text-ink' => ! $s['done'] && ! $s['locked'], 'bg-canvas text-muted' => $s['locked']])>
                        {{ $s['done'] ? __('ui.state.done') : ($s['locked'] ? __('ui.state.locked') : ($isCurrent ? __('ui.state.next') : __('ui.state.todo'))) }}
                    </span>
                </div>
                <p class="eyebrow mt-6">{{ __('ui.home.step', ['n' => $i + 1]) }}</p>
                <h2 class="mt-1 text-2xl">{{ __('ui.steps.'.$s['key'].'.title') }}</h2>
                <p class="mt-2 flex-1 text-muted">{{ $s['skip'] ? __('ui.steps.policies.not_required') : __('ui.steps.'.$s['key'].'.body') }}</p>
                <p class="mt-4 text-sm font-bold">{{ $s['skip'] ? '' : $s['meta'] }}</p>
                @if ($s['locked'])
                    <span class="btn-ghost mt-5 w-full !cursor-not-allowed">{!! icon_svg('lock', 'size-5') !!}{{ __('ui.steps.quiz.locked') }}</span>
                @elseif ($s['skip'])
                @elseif ($s['done'])
                    @if ($s['key'] !== 'quiz')
                        <a href="{{ $s['href'] }}" class="btn-ghost mt-5 w-full">{{ __('ui.action.review') }}</a>
                    @endif
                @else
                    <a href="{{ $s['href'] }}" class="{{ $isCurrent ? 'btn-primary' : 'btn-ghost' }} mt-5 w-full">{{ __('ui.action.'.($isCurrent ? 'start' : 'open')) }}{!! icon_svg('arrow-right', 'size-5') !!}</a>
                @endif
            </article>
        @endforeach
    </section>

    @if ($p['modules']->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-2xl">{{ __('ui.modules.heading') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($p['modules'] as $m)
                    @php($mDone = in_array($m->id, $p['completed_module_ids']))
                    <a href="{{ route('modules.show', $m) }}" class="card flex items-center gap-4 !p-5 transition hover:border-ink">
                        <span @class(['flex size-12 shrink-0 items-center justify-center rounded-2xl', 'bg-emerald-100 text-emerald-700' => $mDone, 'bg-brand-soft' => ! $mDone])>{!! icon_svg($mDone ? 'check' : 'book-open', 'size-6') !!}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-lg font-extrabold">{{ loc($m->title) }}</span>
                            <span class="text-sm text-muted">{{ __('ui.modules.meta', ['slides' => count($m->slides), 'minutes' => $m->estimated_minutes]) }}</span>
                        </span>
                        {!! icon_svg('chevron-right', 'size-5 text-muted') !!}
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endif
@endsection
