@extends('layouts.app', ['title' => loc($module->title)])

@section('content')
<div x-data="deck({{ count($slides) }})" class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 font-bold text-muted hover:text-ink">{!! icon_svg('x', 'size-5') !!}{{ __('ui.action.close') }}</a>
        <p class="font-extrabold text-muted"><span x-text="i + 1"></span> / {{ count($slides) }}</p>
    </div>
    <div class="mt-4 flex gap-1.5" aria-hidden="true">
        @foreach ($slides as $n => $slide)
            <span class="h-2 flex-1 rounded-full transition-colors" :class="i >= {{ $n }} ? 'bg-brand' : 'bg-line'"></span>
        @endforeach
    </div>

    <p class="eyebrow mt-8">{{ __('ui.modules.position', ['n' => $position, 'total' => $total]) }}@if ($module->subtitle) · {{ loc($module->subtitle) }}@endif</p>
    <p class="mt-1 text-lg font-extrabold">{{ loc($module->title) }}</p>

    @foreach ($slides as $n => $slide)
        <article x-show="i === {{ $n }}" @if ($n > 0) x-cloak @endif class="card mt-5 !p-7 sm:!p-10">
            <span class="flex size-20 items-center justify-center rounded-3xl bg-brand text-on-brand">{!! $slide['icon'] !!}</span>
            <h1 class="mt-6 text-3xl sm:text-5xl">{{ $slide['title'] }}</h1>
            @if ($slide['summary'])
                <p class="mt-4 text-xl text-muted">{{ $slide['summary'] }}</p>
            @endif
            @if ($slide['bullets'])
                <ul class="mt-6 space-y-4 text-lg sm:text-xl">
                    @foreach ($slide['bullets'] as $bullet)
                        <li class="flex gap-4"><span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-soft">{!! icon_svg('check', 'size-5') !!}</span><span class="font-semibold">{{ $bullet }}</span></li>
                    @endforeach
                </ul>
            @endif
            @if ($slide['callout'])
                <p class="mt-8 flex gap-4 rounded-2xl bg-header p-5 text-lg font-bold text-white">{!! icon_svg('lightbulb', 'size-7 shrink-0 text-brand') !!}<span>{{ $slide['callout'] }}</span></p>
            @endif
        </article>
    @endforeach

    <div class="mt-6 flex items-center gap-3">
        <button type="button" class="btn-ghost" @click="prev()" :disabled="i === 0">{!! icon_svg('arrow-left', 'size-5') !!}<span class="hidden sm:inline">{{ __('ui.action.previous') }}</span></button>
        <button type="button" class="btn-primary flex-1" x-show="!last" @click="next()">{{ __('ui.action.next') }}{!! icon_svg('arrow-right', 'size-5') !!}</button>
        <form method="POST" action="{{ route('modules.complete', $module) }}" class="flex-1" x-show="last" x-cloak>
            @csrf
            <button class="btn-primary w-full">{!! icon_svg('check', 'size-5') !!}{{ $done ? __('ui.modules.continue') : __('ui.modules.finish') }}</button>
        </form>
    </div>
</div>
@endsection
