@extends('layouts.app', ['title' => __('ui.quiz.result')])

@section('content')
<div class="mx-auto max-w-3xl">
    <section @class(['rounded-3xl p-8 text-center sm:p-12', 'bg-header text-white' => $attempt->passed, 'card' => ! $attempt->passed])>
        <span @class(['mx-auto flex size-24 items-center justify-center rounded-full', 'bg-brand text-on-brand' => $attempt->passed, 'bg-red-100 text-red-700' => ! $attempt->passed])>{!! icon_svg($attempt->passed ? 'party-popper' : 'rotate-ccw', 'size-12') !!}</span>
        <p class="mt-6 text-7xl font-extrabold tracking-tight">{{ (int) round($attempt->percentage) }}%</p>
        <p class="mt-1 font-bold opacity-70">{{ __('ui.quiz.score', ['score' => $attempt->score, 'total' => $attempt->total_questions]) }}</p>
        <h1 class="mt-6 text-3xl sm:text-4xl">{{ $attempt->passed ? __('ui.quiz.passed_title') : __('ui.quiz.failed_title') }}</h1>
        <p class="mx-auto mt-3 max-w-lg text-lg opacity-80">{{ $attempt->passed ? __('ui.quiz.passed_lead') : __('ui.quiz.failed_lead', ['threshold' => $threshold]) }}</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            @if ($attempt->passed)
                <a href="{{ route('certificate') }}" class="btn-primary">{!! icon_svg('download', 'size-5') !!}{{ __('ui.certificate.download') }}</a>
                <a href="{{ route('home') }}" class="btn border-2 border-white/30 text-white hover:border-white">{{ __('ui.action.home') }}</a>
            @else
                <a href="{{ route('quiz.show') }}" class="btn-primary">{!! icon_svg('rotate-ccw', 'size-5') !!}{{ __('ui.quiz.retry') }}</a>
                <a href="{{ route('home') }}" class="btn-ghost">{{ __('ui.action.home') }}</a>
            @endif
        </div>
    </section>

    <h2 class="mt-10 mb-4 text-2xl">{{ __('ui.quiz.review') }}</h2>
    @include('partials.review')
</div>
@endsection
