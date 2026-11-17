@extends('layouts.guest', ['title' => __('pack.setup.title')])

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="card !p-8 sm:!p-10">
        <p class="eyebrow">{{ config('app.name') }}</p>
        <h1 class="mt-2 text-4xl">{{ __('pack.setup.title') }}</h1>
        <p class="mt-3 text-lg text-muted">{{ __('pack.setup.lead') }}</p>
        <div class="mt-6">@include('partials.flash')</div>

        <form method="POST" action="{{ route('setup.install') }}" enctype="multipart/form-data" class="rounded-2xl border-2 border-line p-6">
            @csrf
            <h2 class="text-xl">{{ __('pack.setup.upload_title') }}</h2>
            <p class="mt-1 text-muted">{{ __('pack.setup.upload_lead') }}</p>
            <input type="file" name="pack" accept=".zip" required class="field mt-4 py-2.5">
            <button class="btn-primary mt-4">{!! icon_svg('upload', 'size-5') !!}{{ __('pack.setup.upload') }}</button>
        </form>

        @if ($hasDemo)
            <form method="POST" action="{{ route('setup.install') }}" class="mt-4 rounded-2xl border-2 border-line p-6">
                @csrf
                <h2 class="text-xl">{{ __('pack.setup.demo_title') }}</h2>
                <p class="mt-1 text-muted">{{ __('pack.setup.demo_lead') }}</p>
                <button class="btn-ghost mt-4">{!! icon_svg('play', 'size-5') !!}{{ __('pack.setup.demo') }}</button>
            </form>
        @endif
        <p class="mt-6 text-sm text-muted">{{ __('pack.setup.cli') }} <code class="rounded bg-canvas px-1.5 py-0.5">php artisan pack:install packs/&lt;folder&gt;</code></p>
    </div>
</div>
@endsection
