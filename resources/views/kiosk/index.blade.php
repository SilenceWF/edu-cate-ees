@extends('layouts.kiosk', ['title' => __('ui.nav.kiosk')])

@section('content')
<div x-data="kiosk(@js(['workers' => $workers, 'departments' => $departments, 'urls' => ['verify' => route('kiosk.verify'), 'questions' => route('kiosk.questions'), 'submit' => route('kiosk.submit')], 't' => __('ui.kiosk.js')]))"
     class="flex min-h-screen flex-col">

    <header class="flex items-center justify-between bg-header px-5 py-4 text-white sm:px-8">
        @include('partials.logo')
        <div class="flex items-center gap-3">
            <span class="hidden font-bold text-white/60 sm:inline" x-show="worker" x-text="worker?.name"></span>
            <button type="button" class="btn btn-sm bg-white/10 text-white" x-show="screen !== 'pick'" x-cloak @click="reset()">{!! icon_svg('rotate-ccw', 'size-4') !!}{{ __('ui.kiosk.restart') }}</button>
            <button type="button" class="btn btn-sm bg-white/10 text-white" @click="exitOpen = true" aria-label="{{ __('ui.kiosk.exit') }}">{!! icon_svg('lock', 'size-4') !!}</button>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col px-5 py-8 sm:px-8 sm:py-12">

        {{-- 1. Who are you? --}}
        <section x-show="screen === 'pick'">
            <p class="eyebrow">{{ __('ui.kiosk.step', ['n' => 1]) }}</p>
            <h1 class="mt-2 text-4xl sm:text-6xl">{{ __('ui.kiosk.pick_title') }}</h1>
            <div class="mt-6 flex flex-wrap gap-2" x-show="departments.length > 1">
                <button type="button" class="chip !px-5 !py-3 !text-base" :class="department === null ? 'bg-header text-white' : 'bg-white border-2 border-line'" @click="department = null">{{ __('ui.kiosk.all_departments') }}</button>
                <template x-for="d in departments" :key="d.id">
                    <button type="button" class="chip !px-5 !py-3 !text-base border-2" @click="department = d.id"
                            :style="department === d.id ? `background:${d.color};color:${d.text};border-color:${d.color}` : `border-color:${d.color};background:#fff`" x-text="d.name"></button>
                </template>
            </div>
            <div class="relative mt-5">
                <span class="pointer-events-none absolute top-1/2 left-5 -translate-y-1/2 text-muted">{!! icon_svg('search', 'size-7') !!}</span>
                <input type="search" x-model="search" class="field !min-h-18 !rounded-2xl !pl-16 text-2xl font-bold" placeholder="{{ __('ui.kiosk.search') }}" autocomplete="off">
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <template x-for="w in filtered" :key="w.id">
                    <button type="button" class="card flex items-center gap-4 !p-5 text-left text-xl font-extrabold transition hover:border-ink active:scale-[0.99]" @click="choose(w)">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-soft text-lg" x-text="initials(w.name)"></span>
                        <span x-text="w.name"></span>
                    </button>
                </template>
            </div>
            <p class="mt-8 text-center text-lg text-muted" x-show="filtered.length === 0">{{ __('ui.kiosk.no_results') }}</p>
        </section>

        {{-- 2. Employee code --}}
        <section x-show="screen === 'code'" x-cloak class="mx-auto w-full max-w-md text-center">
            <p class="eyebrow">{{ __('ui.kiosk.step', ['n' => 2]) }}</p>
            <h1 class="mt-2 text-4xl" x-text="worker?.name"></h1>
            <p class="mt-3 text-xl text-muted">{{ __('ui.kiosk.code_lead') }}</p>
            <div class="mt-6 flex min-h-20 items-center justify-center rounded-2xl border-2 border-line bg-white text-5xl font-extrabold tracking-[0.3em]" x-text="code ? '•'.repeat(code.length) : ''" aria-live="polite"></div>
            <p class="mt-3 min-h-7 font-bold text-red-700" x-text="error" role="alert"></p>
            <div class="mt-2 grid grid-cols-3 gap-3">
                <template x-for="k in ['1','2','3','4','5','6','7','8','9','⌫','0','✓']" :key="k">
                    <button type="button" class="btn !min-h-20 !text-3xl" @click="key(k)" :disabled="busy"
                            :class="k === '✓' ? 'bg-brand text-on-brand' : 'bg-white border-2 border-line'" x-text="k"></button>
                </template>
            </div>
            <button type="button" class="mt-6 font-bold text-muted" @click="reset()">{{ __('ui.kiosk.not_me') }}</button>
        </section>

        {{-- 3. Policies (only for tracks that require them) --}}
        <section x-show="screen === 'policies'" x-cloak>
            <p class="eyebrow">{{ __('ui.steps.policies.title') }}</p>
            <h1 class="mt-2 text-4xl sm:text-5xl">{{ __('ui.kiosk.policies_title') }}</h1>
            <div class="mt-6 space-y-4">
                <template x-for="p in content.policies" :key="p.code">
                    <article class="card !p-6">
                        <p class="flex items-center gap-3 text-xl font-extrabold"><span class="chip bg-header text-white" x-text="p.code"></span><span x-text="p.title"></span></p>
                        <ul class="mt-3 list-disc space-y-1 pl-6 text-lg"><template x-for="r in p.rules"><li x-text="r"></li></template></ul>
                    </article>
                </template>
            </div>
            <label class="mt-6 flex cursor-pointer items-start gap-4 rounded-3xl bg-header p-6 text-white">
                <input type="checkbox" x-model="declared" class="mt-1 size-8 shrink-0 accent-[var(--brand)]">
                <span class="text-xl font-semibold" x-text="content.declaration"></span>
            </label>
            <button type="button" class="btn-primary mt-6 w-full !min-h-20 !text-2xl" :disabled="!declared" @click="screen = 'slides'">{{ __('ui.action.next') }}{!! icon_svg('arrow-right', 'size-7') !!}</button>
        </section>

        {{-- 4. Rules --}}
        <section x-show="screen === 'slides'" x-cloak class="flex flex-1 flex-col">
            <div class="flex gap-2" aria-hidden="true">
                <template x-for="(s, n) in content.slides" :key="n"><span class="h-3 flex-1 rounded-full" :class="slide >= n ? 'bg-brand' : 'bg-line'"></span></template>
            </div>
            <template x-if="content.slides[slide]">
                <article class="card mt-6 flex-1 !p-8 sm:!p-12">
                    <p class="eyebrow" x-text="content.slides[slide].module"></p>
                    <span class="mt-5 flex size-28 items-center justify-center rounded-[2rem] bg-brand text-on-brand [&_svg]:size-16" x-html="content.slides[slide].icon"></span>
                    <h1 class="mt-6 text-4xl sm:text-6xl" x-text="content.slides[slide].title"></h1>
                    <p class="mt-5 text-2xl text-muted" x-text="content.slides[slide].summary"></p>
                    <ul class="mt-6 space-y-3 text-2xl font-semibold">
                        <template x-for="b in content.slides[slide].bullets"><li class="flex gap-4"><span class="text-brand-dark">●</span><span x-text="b"></span></li></template>
                    </ul>
                    <p class="mt-8 rounded-2xl bg-header p-6 text-2xl font-bold text-white" x-show="content.slides[slide].callout" x-text="content.slides[slide].callout"></p>
                </article>
            </template>
            <div class="mt-6 flex gap-3">
                <button type="button" class="btn-ghost !min-h-20 !px-8" @click="slide--" :disabled="slide === 0" aria-label="{{ __('ui.action.previous') }}">{!! icon_svg('arrow-left', 'size-7') !!}</button>
                <button type="button" class="btn-primary flex-1 !min-h-20 !text-2xl" @click="nextSlide()">
                    <span x-text="slide === content.slides.length - 1 ? t.to_quiz : t.next"></span>{!! icon_svg('arrow-right', 'size-7') !!}
                </button>
            </div>
        </section>

        {{-- 5. Questions --}}
        <section x-show="screen === 'quiz'" x-cloak class="flex flex-1 flex-col">
            <p class="eyebrow"><span x-text="t.question"></span> <span x-text="q + 1"></span> / <span x-text="content.questions.length"></span></p>
            <template x-if="content.questions[q]">
                <div class="flex flex-1 flex-col">
                    <h1 class="mt-3 text-3xl leading-tight sm:text-4xl" x-text="content.questions[q].text"></h1>
                    <div class="mt-6 space-y-3">
                        <template x-for="(text, letter) in content.questions[q].options" :key="letter">
                            <button type="button" class="flex w-full items-start gap-5 rounded-3xl border-4 bg-white p-5 text-left text-2xl font-bold transition sm:p-6"
                                    :class="answers[content.questions[q].id] === letter ? 'border-brand bg-brand-soft' : 'border-line'" @click="answers[content.questions[q].id] = letter">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl" :class="answers[content.questions[q].id] === letter ? 'bg-brand text-on-brand' : 'bg-canvas'" x-text="letter"></span>
                                <span class="pt-1.5" x-text="text"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
            <div class="mt-6 flex gap-3">
                <button type="button" class="btn-ghost !min-h-20 !px-8" @click="q--" :disabled="q === 0" aria-label="{{ __('ui.action.previous') }}">{!! icon_svg('arrow-left', 'size-7') !!}</button>
                <button type="button" class="btn-primary flex-1 !min-h-20 !text-2xl" :disabled="!content.questions[q] || !answers[content.questions[q].id] || busy" @click="nextQuestion()">
                    <span x-text="q === content.questions.length - 1 ? t.sign : t.next"></span>
                </button>
            </div>
            <p class="mt-3 min-h-7 text-center font-bold text-red-700" x-text="error" role="alert"></p>
        </section>

        {{-- 6. Result --}}
        <section x-show="screen === 'result'" x-cloak>
            <template x-if="result">
                <div>
                    <div class="rounded-3xl p-10 text-center sm:p-14" :class="result.passed ? 'bg-header text-white' : 'card'">
                        <span class="mx-auto flex size-28 items-center justify-center rounded-full" :class="result.passed ? 'bg-brand text-on-brand' : 'bg-red-100 text-red-700'">
                            <span x-show="result.passed">{!! icon_svg('check', 'size-16') !!}</span><span x-show="!result.passed">{!! icon_svg('rotate-ccw', 'size-14') !!}</span>
                        </span>
                        <p class="mt-6 text-7xl font-extrabold" x-text="result.percentage + '%'"></p>
                        <h1 class="mt-4 text-4xl sm:text-5xl" x-text="result.passed ? t.passed.replace(':name', worker?.name.split(' ')[0] ?? '') : t.failed"></h1>
                        <p class="mt-4 text-2xl opacity-80" x-show="result.passed" x-text="t.signed"></p>
                        <p class="mt-6 text-lg font-bold opacity-60" x-show="result.passed"><span x-text="t.next_worker"></span> <span x-text="countdown"></span>…</p>
                        <button type="button" class="btn-primary mt-8 !min-h-20 !px-10 !text-2xl" x-show="!result.passed" @click="retry()" :disabled="busy">{!! icon_svg('rotate-ccw', 'size-7') !!}<span x-text="t.retry"></span></button>
                    </div>
                    <div class="mt-6 space-y-4" x-show="!result.passed">
                        <template x-for="(r, n) in result.review.filter(r => !r.is_correct)" :key="n">
                            <article class="card border-2 !border-red-300 !p-6">
                                <h3 class="text-2xl" x-text="r.question"></h3>
                                <p class="mt-3 rounded-xl bg-emerald-50 p-4 text-xl font-bold text-emerald-900"><span x-text="r.correct"></span>. <span x-text="r.options[r.correct]"></span></p>
                                <p class="mt-3 text-xl text-muted" x-text="r.explanation"></p>
                            </article>
                        </template>
                    </div>
                </div>
            </template>
        </section>

        {{-- Already done --}}
        <section x-show="screen === 'already'" x-cloak class="card text-center !p-12">
            <span class="mx-auto flex size-24 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">{!! icon_svg('check', 'size-14') !!}</span>
            <h1 class="mt-6 text-4xl" x-text="t.already"></h1>
            <button type="button" class="btn-primary mt-8 !min-h-20 !px-10 !text-2xl" @click="reset()" x-text="t.next_person"></button>
        </section>
    </main>

    {{-- Exit (PIN protected) --}}
    <div x-show="exitOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-5" @keydown.escape.window="exitOpen = false">
        <form method="POST" action="{{ route('kiosk.exit') }}" class="card w-full max-w-sm text-center" @click.outside="exitOpen = false">
            @csrf
            <h2 class="text-2xl">{{ __('ui.kiosk.exit') }}</h2>
            <p class="mt-2 text-muted">{{ __('ui.kiosk.exit_lead') }}</p>
            @error('pin')<p class="mt-3 font-bold text-red-700">{{ $message }}</p>@enderror
            <input name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="off" required class="field mt-4 !min-h-16 text-center text-3xl font-extrabold tracking-[0.5em]" placeholder="••••" aria-label="PIN">
            <div class="mt-5 flex gap-3">
                <button type="button" class="btn-ghost flex-1" @click="exitOpen = false">{{ __('ui.action.cancel') }}</button>
                <button class="btn-dark flex-1">{{ __('ui.kiosk.exit_confirm') }}</button>
            </div>
        </form>
    </div>
</div>
@if ($errors->has('pin'))
    <script>document.addEventListener('alpine:initialized', () => { Alpine.$data(document.querySelector('[x-data]')).exitOpen = true; });</script>
@endif
@endsection
