@extends('layouts.guest', ['title' => __('ui.auth.title')])

@section('content')
<div class="grid items-center gap-10 lg:grid-cols-2">
    <div class="text-white">
        <p class="eyebrow !text-brand">{{ loc(setting('company.frameworks')) }}</p>
        <h1 class="mt-4 text-5xl leading-[1.05] sm:text-6xl">{{ __('ui.auth.hero') }}</h1>
        <p class="mt-6 max-w-md text-lg text-white/70">{{ __('ui.auth.lead') }}</p>
        <ol class="mt-8 space-y-3 text-lg font-bold">
            @foreach (['policies', 'modules', 'quiz'] as $i => $step)
                <li class="flex items-center gap-4">
                    <span class="flex size-10 items-center justify-center rounded-full bg-brand text-on-brand">{{ $i + 1 }}</span>
                    {{ __('ui.steps.'.$step.'.short') }}
                </li>
            @endforeach
        </ol>
    </div>

    <div class="card !p-8 sm:!p-10">
        <h2 class="text-3xl">{{ __('ui.auth.title') }}</h2>
        <div class="mt-6">@include('partials.flash')</div>

        @if ($microsoft)
            <a href="{{ route('auth.microsoft') }}" class="btn-dark w-full">
                <svg viewBox="0 0 21 21" class="size-5" aria-hidden="true"><path fill="#f25022" d="M0 0h10v10H0z"/><path fill="#7fba00" d="M11 0h10v10H11z"/><path fill="#00a4ef" d="M0 11h10v10H0z"/><path fill="#ffb900" d="M11 11h10v10H11z"/></svg>
                {{ __('ui.auth.microsoft') }}
            </a>
            <p class="mt-3 text-sm text-muted">{{ __('ui.auth.microsoft_hint') }}</p>
        @endif

        @if ($localAuth)
            @if ($microsoft)
                <div class="my-6 flex items-center gap-4 text-sm font-bold text-muted"><span class="h-px flex-1 bg-line"></span>{{ __('ui.auth.or') }}<span class="h-px flex-1 bg-line"></span></div>
            @endif
            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">{{ __('ui.auth.email') }}</label>
                    <input class="field" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div>
                    <label class="label" for="password">{{ __('ui.auth.password') }}</label>
                    <input class="field" id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                <button class="btn-primary w-full">{{ __('ui.auth.sign_in') }}</button>
            </form>
        @endif

        @unless ($microsoft || $localAuth)
            <p class="rounded-2xl bg-amber-50 p-4 font-semibold text-amber-900">{{ __('ui.auth.no_method') }}</p>
        @endunless

        <p class="mt-8 flex items-start gap-3 border-t border-line pt-6 text-sm text-muted">
            {!! icon_svg('tablet-smartphone', 'size-5 shrink-0') !!}<span>{{ __('ui.auth.kiosk_hint') }}</span>
        </p>
    </div>
</div>
@endsection
