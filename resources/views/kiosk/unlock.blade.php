@extends('layouts.guest', ['title' => __('ui.nav.kiosk')])

@section('content')
<div class="mx-auto max-w-md">
    <div class="card !p-8 text-center sm:!p-10">
        <span class="mx-auto flex size-20 items-center justify-center rounded-3xl bg-brand text-on-brand">{!! icon_svg('tablet-smartphone', 'size-10') !!}</span>
        <h1 class="mt-6 text-3xl">{{ __('ui.kiosk.unlock_title') }}</h1>
        <p class="mt-3 text-muted">{{ __('ui.kiosk.unlock_lead') }}</p>
        <div class="mt-6 text-left">@include('partials.flash')</div>
        <form method="POST" action="{{ route('kiosk.unlock.store') }}" class="mt-2">
            @csrf
            <label class="sr-only" for="pin">PIN</label>
            <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="off" autofocus required
                   class="field !min-h-16 text-center text-3xl font-extrabold tracking-[0.5em]" placeholder="••••">
            <button class="btn-primary mt-5 w-full">{!! icon_svg('play', 'size-5') !!}{{ __('ui.kiosk.start') }}</button>
        </form>
        <a href="{{ route('home') }}" class="mt-5 inline-block font-bold text-muted hover:text-ink">{{ __('ui.action.back') }}</a>
    </div>
    @include('partials.kiosk-codes-form', ['class' => 'card mt-6 !p-8'])
</div>
@endsection
