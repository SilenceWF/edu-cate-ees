@extends('layouts.app', ['title' => __('ui.steps.quiz.title')])

@section('content')
<form method="POST" action="{{ route('quiz.submit') }}" x-data="quiz(@js($questions->pluck('id')->all()))" class="mx-auto max-w-3xl">
    @csrf
    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 font-bold text-muted hover:text-ink">{!! icon_svg('x', 'size-5') !!}{{ __('ui.action.close') }}</a>
        <p class="font-extrabold text-muted">{{ __('ui.quiz.question') }} <span x-text="i + 1"></span> / {{ $questions->count() }}</p>
    </div>
    <div class="mt-4 flex gap-1.5" aria-hidden="true">
        @foreach ($questions as $n => $q)
            <span class="h-2 flex-1 rounded-full transition-colors" :class="answers[{{ $q->id }}] ? 'bg-brand' : (i === {{ $n }} ? 'bg-ink/40' : 'bg-line')"></span>
        @endforeach
    </div>

    @foreach ($questions as $n => $q)
        <fieldset x-show="i === {{ $n }}" @if ($n > 0) x-cloak @endif class="card mt-6 !p-7 sm:!p-10">
            <legend class="sr-only">{{ __('ui.quiz.question') }} {{ $n + 1 }}</legend>
            <h1 class="text-2xl leading-snug sm:text-3xl">{{ loc($q->question_text) }}</h1>
            <div class="mt-6 space-y-3">
                @foreach ($q->localizedOptions() as $letter => $text)
                    <label class="flex cursor-pointer items-start gap-4 rounded-2xl border-2 p-4 text-lg font-semibold transition sm:p-5"
                           :class="answers[{{ $q->id }}] === '{{ $letter }}' ? 'border-brand bg-brand-soft' : 'border-line hover:border-ink'">
                        <input type="radio" class="sr-only" name="answers[{{ $q->id }}]" value="{{ $letter }}" @change="pick({{ $q->id }}, '{{ $letter }}')">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl font-extrabold"
                              :class="answers[{{ $q->id }}] === '{{ $letter }}' ? 'bg-brand text-on-brand' : 'bg-canvas'">{{ $letter }}</span>
                        <span class="pt-0.5">{{ $text }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach

    <div class="mt-6 flex items-center gap-3">
        <button type="button" class="btn-ghost" @click="prev()" :disabled="i === 0">{!! icon_svg('arrow-left', 'size-5') !!}<span class="hidden sm:inline">{{ __('ui.action.previous') }}</span></button>
        <button type="button" class="btn-primary flex-1" x-show="!last" @click="next()" :disabled="!answers[current]">{{ __('ui.action.next') }}{!! icon_svg('arrow-right', 'size-5') !!}</button>
        <button class="btn-primary flex-1" x-show="last" x-cloak :disabled="answered < ids.length">{!! icon_svg('send', 'size-5') !!}{{ __('ui.quiz.submit') }}</button>
    </div>
    <p class="mt-4 text-center text-sm text-muted">{{ __('ui.quiz.hint', ['threshold' => setting('quiz.pass_threshold', 80)]) }}</p>
</form>
@endsection
