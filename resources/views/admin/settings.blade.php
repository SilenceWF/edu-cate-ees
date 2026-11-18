@extends('layouts.app', ['title' => __('admin.nav.settings')])
@include('admin._nav')

@section('content')
<h1 class="text-4xl sm:text-5xl">{{ __('admin.nav.settings') }}</h1>

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section class="card">
        <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('calendar-range', 'size-6') !!}{{ __('admin.settings.campaigns') }}</h2>
        <p class="mt-2 text-muted">{{ __('admin.settings.campaigns_lead') }}</p>
        <ul class="mt-4 divide-y divide-line">
            @foreach ($campaigns as $c)
                <li class="flex items-center justify-between gap-3 py-3">
                    <span><span class="font-extrabold">{{ $c->name }}</span> <span class="text-sm text-muted">{{ $c->starts_on?->format('d/m/Y') }}{{ $c->ends_on ? ' – '.$c->ends_on->format('d/m/Y') : '' }}</span></span>
                    @if ($c->is_current)<span class="chip bg-emerald-100 text-emerald-800 !text-xs">{{ __('admin.settings.current') }}</span>
                    @else<form method="POST" action="{{ route('admin.settings.campaign') }}">@csrf<button name="make_current" value="{{ $c->id }}" class="btn-ghost btn-sm !min-h-8">{{ __('admin.settings.make_current') }}</button></form>@endif
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.settings.campaign') }}" class="mt-4 grid gap-3 border-t border-line pt-5 sm:grid-cols-3" onsubmit="return confirm(@js(__('admin.settings.confirm_campaign')))">
            @csrf
            <div class="sm:col-span-3"><label class="label" for="cname">{{ __('admin.settings.new_campaign') }}</label><input class="field" id="cname" name="name" placeholder="2027" required></div>
            <div><label class="label" for="cs">{{ __('admin.settings.starts') }}</label><input class="field" id="cs" type="date" name="starts_on"></div>
            <div><label class="label" for="ce">{{ __('admin.settings.ends') }}</label><input class="field" id="ce" type="date" name="ends_on"></div>
            <div class="flex items-end"><button class="btn-dark w-full">{{ __('admin.settings.start_campaign') }}</button></div>
        </form>
    </section>

    <div class="space-y-6">
        <section class="card">
            <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('tablet-smartphone', 'size-6') !!}{{ __('admin.settings.kiosk_pin') }}</h2>
            <p class="mt-2 text-muted">{{ __('admin.settings.kiosk_pin_lead') }}</p>
            <form method="POST" action="{{ route('admin.settings.kiosk-pin') }}" class="mt-4 grid gap-3 sm:grid-cols-3">
                @csrf @method('PUT')
                <input class="field" type="password" inputmode="numeric" name="pin" placeholder="{{ __('admin.settings.new_pin') }}" aria-label="{{ __('admin.settings.new_pin') }}" required autocomplete="off">
                <input class="field" type="password" inputmode="numeric" name="pin_confirmation" placeholder="{{ __('admin.settings.repeat_pin') }}" aria-label="{{ __('admin.settings.repeat_pin') }}" required autocomplete="off">
                <button class="btn-dark">{{ __('ui.action.save') }}</button>
            </form>
        </section>
        <section class="card">
            <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('badge-check', 'size-6') !!}{{ __('admin.settings.threshold') }}</h2>
            <form method="POST" action="{{ route('admin.settings.quiz') }}" class="mt-4 flex gap-3">
                @csrf @method('PUT')
                <div class="relative w-32"><input class="field pr-10" type="number" min="1" max="100" name="pass_threshold" value="{{ setting('quiz.pass_threshold', 80) }}" aria-label="{{ __('admin.settings.threshold') }}" required><span class="absolute top-1/2 right-4 -translate-y-1/2 font-bold text-muted">%</span></div>
                <button class="btn-dark">{{ __('ui.action.save') }}</button>
            </form>
        </section>
    </div>

    <section class="card">
        <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('image', 'size-6') !!}{{ __('admin.logo.title') }}</h2>
        <p class="mt-2 text-muted">{{ __('admin.logo.lead') }}</p>
        <div class="mt-4 flex items-center gap-4 rounded-2xl bg-header p-5 text-white">
            @if ($logoUrl)<img src="{{ $logoUrl }}" alt="" class="h-12 w-auto max-w-48 object-contain">@else<span class="text-sm text-white/60">{{ __('admin.logo.none') }}</span>@endif
            <span class="text-xl font-extrabold">{{ setting('company.name') }}</span>
        </div>
        @error('logo')<p class="mt-3 rounded-xl bg-red-50 p-3 font-semibold text-red-900">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('admin.settings.logo') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap gap-3">
            @csrf
            <input type="file" name="logo" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" required class="field flex-1 py-2.5" aria-label="{{ __('admin.logo.title') }}">
            <button class="btn-dark">{!! icon_svg('upload', 'size-5') !!}{{ __('admin.logo.upload') }}</button>
        </form>
        <p class="mt-2 text-sm text-muted">{{ __('admin.logo.hint') }}</p>
        @if ($logoUrl)
            <form method="POST" action="{{ route('admin.settings.logo.remove') }}" class="mt-3" onsubmit="return confirm(@js(__('admin.logo.confirm_remove')))">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-700">{!! icon_svg('trash-2', 'size-4') !!}{{ __('admin.logo.remove') }}</button></form>
        @endif
    </section>

    <section class="card">
        <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('package', 'size-6') !!}{{ __('admin.settings.pack') }}</h2>
        <p class="mt-2 text-muted">{{ __('admin.settings.pack_lead') }}</p>
        <p class="mt-3 text-sm font-bold">{{ setting('pack.slug') }} · v{{ setting('pack.version') }} · {{ local_dt(setting('pack.installed_at')) }}</p>
        <form method="POST" action="{{ route('admin.settings.pack') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap gap-3">
            @csrf
            <input type="file" name="pack" accept=".zip" required class="field flex-1 py-2.5" aria-label="{{ __('admin.settings.pack') }}">
            <button class="btn-dark">{!! icon_svg('upload', 'size-5') !!}{{ __('admin.settings.pack_upload') }}</button>
        </form>
        <a href="{{ route('admin.settings.pack.export') }}" class="btn-ghost mt-3">{!! icon_svg('download', 'size-5') !!}{{ __('admin.settings.pack_export') }}</a>
    </section>

    <section class="card">
        <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('shield-check', 'size-6') !!}{{ __('admin.settings.integrity') }}</h2>
        <p class="mt-2 text-muted">{{ __('admin.settings.integrity_lead') }}</p>
        @if ($brokenLink)
            <p class="mt-4 rounded-2xl bg-red-50 p-4 font-bold text-red-900">{{ __('admin.settings.integrity_broken', ['id' => $brokenLink]) }}</p>
        @else
            <p class="mt-4 flex items-center gap-3 rounded-2xl bg-emerald-50 p-4 font-bold text-emerald-900">{!! icon_svg('circle-check', 'size-6') !!}{{ __('admin.settings.integrity_ok') }}</p>
        @endif
    </section>

    <section class="card lg:col-span-2">
        <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('server', 'size-6') !!}{{ __('admin.system.title') }}</h2>
        <p class="mt-2 text-muted">{{ __('admin.system.lead') }}</p>
        <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($system as $row)
                <li @class(['flex items-start gap-3 rounded-xl p-3', 'bg-emerald-50 text-emerald-900' => $row['ok'], 'bg-red-50 text-red-900' => ! $row['ok'] && empty($row['optional']), 'bg-amber-50 text-amber-900' => ! $row['ok'] && ! empty($row['optional'])])>
                    {!! icon_svg($row['ok'] ? 'circle-check' : (empty($row['optional']) ? 'circle-x' : 'circle-alert'), 'size-5 shrink-0 mt-0.5') !!}
                    <span class="min-w-0"><span class="block font-bold">{{ $row['label'] }}</span>@if ($row['detail'])<span class="block text-sm break-all opacity-80">{{ $row['detail'] }}</span>@endif</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
