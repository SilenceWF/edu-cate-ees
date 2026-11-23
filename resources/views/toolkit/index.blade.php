@extends('layouts.app', ['title' => __('ui.toolkit.title')])

@php
    $typeIcons = ['form' => 'file-spreadsheet', 'procedure' => 'workflow', 'guide' => 'presentation'];
    $typeBadges = ['form' => 'bg-brand-soft text-ink', 'procedure' => 'bg-sky-100 text-sky-900', 'guide' => 'bg-violet-100 text-violet-900'];
    $titles = $items->mapWithKeys(fn ($i) => [$i->code => loc($i->title)]);
    $anchor = fn ($i) => 'item-'.$i->id;
    $openId = (int) old('toolkit_item_id');
@endphp

@section('content')
<div x-data="toolkit(@js(['type' => $type, 'q' => $q, 'items' => $items->map(fn ($i) => ['id' => $i->id, 'type' => $i->type, 'text' => $i->searchText()])->values()]))">
    <p class="eyebrow">{{ __('ui.toolkit.eyebrow') }}</p>
    <h1 class="mt-3 text-4xl sm:text-5xl">{{ __('ui.toolkit.title') }}</h1>
    <p class="mt-4 max-w-3xl text-lg text-muted">{{ __('ui.toolkit.lead') }}</p>

    @if (session('toolkit_mailto') || session('toolkit_mail_error'))
        <div class="mt-6 flex flex-col gap-4 rounded-2xl border-2 border-amber-200 bg-amber-50 px-5 py-4 text-amber-950 sm:flex-row sm:items-center" role="status">
            <span class="shrink-0">{!! icon_svg('mail-warning', 'size-6') !!}</span>
            <div class="flex-1">
                <p class="font-extrabold">{{ __('ui.toolkit.mailto_title') }}</p>
                <p class="mt-1 text-sm">{{ __('ui.toolkit.mailto_lead') }}</p>
                @if (session('toolkit_mail_error'))<p class="mt-1 text-sm font-semibold">{{ __('ui.toolkit.mailto_failed', ['error' => Str::limit(session('toolkit_mail_error'), 200)]) }}</p>@endif
            </div>
            @if (session('toolkit_mailto'))
                <a href="{{ session('toolkit_mailto') }}" class="btn-dark btn-sm shrink-0">{!! icon_svg('mail', 'size-4') !!}{{ __('ui.toolkit.mailto_open') }}</a>
            @endif
        </div>
    @endif

    @if ($items->isEmpty())
        <div class="card mt-10 text-center">
            <span class="mx-auto flex size-16 items-center justify-center rounded-3xl bg-brand-soft">{!! icon_svg('briefcase-business', 'size-8') !!}</span>
            <h2 class="mt-5 text-2xl">{{ __('ui.toolkit.empty') }}</h2>
            @if (auth()->user()->hasRole('admin'))
                <p class="mt-2 text-muted">{{ __('ui.toolkit.empty_admin') }}</p>
                <a href="{{ route('admin.content.create', 'toolkit') }}" class="btn-primary mt-6">{!! icon_svg('plus', 'size-5') !!}{{ __('ui.action.add') }}</a>
            @endif
        </div>
    @else
        {{-- Filter cards: one per type. Clicking again (or "All") shows everything. --}}
        <section class="mt-10 grid grid-cols-1 gap-4 md:grid-cols-3" aria-label="{{ __('ui.action.filter') }}">
            @foreach ($typeIcons as $t => $icon)
                <a href="{{ route('toolkit.index', ['type' => $t]) }}" class="card flex items-start gap-4 !p-5 text-left transition hover:border-ink"
                   :class="type === @js($t) && 'border-2 !border-brand shadow-xl shadow-brand/10'" :aria-pressed="type === @js($t)"
                   @click.prevent="pick(@js($t))">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-ink transition" :class="type === @js($t) && '!bg-brand !text-on-brand'">{!! icon_svg($icon, 'size-6') !!}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-3">
                            <span class="text-lg leading-tight font-extrabold">{{ __('ui.toolkit.types.'.$t) }}</span>
                            <span class="chip shrink-0 bg-header !px-2.5 !text-xs text-white">{{ $counts[$t] }}</span>
                        </span>
                        <span class="mt-1 block text-sm text-muted">{{ __('ui.toolkit.type_hints.'.$t) }}</span>
                    </span>
                </a>
            @endforeach
        </section>

        <form method="GET" action="{{ route('toolkit.index') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent role="search">
            <input type="hidden" name="type" :value="type" value="{{ $type }}">
            <label class="relative flex-1">
                <span class="sr-only">{{ __('ui.toolkit.search') }}</span>
                <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-muted">{!! icon_svg('search', 'size-5') !!}</span>
                <input type="search" name="q" value="{{ $q }}" class="field !pl-12" placeholder="{{ __('ui.toolkit.search') }}" x-model="q" @input.debounce.200ms="sync()">
            </label>
            <button type="button" class="btn-ghost btn-sm !min-h-12 shrink-0" :class="!type && !q.trim() && '!border-ink'" @click="reset()">
                {!! icon_svg('layout-grid', 'size-4') !!}{{ __('ui.toolkit.all') }} <span class="text-muted">({{ $items->count() }})</span>
            </button>
        </form>

        <div class="mt-6 space-y-4">
            @foreach ($items as $item)
                @php($hasGuidance = filled(loc($item->when_to_use)) || filled(loc($item->roles_guidance)) || filled(loc($item->example_usage)) || ! empty($item->related_codes))
                <article id="{{ $anchor($item) }}" class="card scroll-mt-6 !p-0 transition" data-code="{{ $item->code }}"
                         x-data="{ open: false, flash: false, submitting: @js($openId === $item->id) }"
                         x-show="visible({{ $item->id }})" @style(['display: none' => ! in_array($item->id, $visibleIds, true)])
                         :class="flash && 'ring-4 ring-brand'"
                         @toolkit-jump.window="if ($event.detail === @js($item->code)) { open = true; flash = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' })); setTimeout(() => flash = false, 2200) }">
                    <div class="flex flex-col gap-4 p-5 sm:p-6 lg:flex-row lg:items-center">
                        <button type="button" class="flex flex-1 flex-col gap-3 text-left sm:flex-row sm:items-start sm:gap-4" @click="open = !open" :aria-expanded="open" aria-controls="{{ $anchor($item) }}-body">
                            <span class="flex shrink-0 items-center gap-2 sm:w-32 sm:flex-col sm:items-start">
                                <span class="chip bg-header whitespace-nowrap text-white">{{ $item->code }}</span>
                                <span class="chip {{ $typeBadges[$item->type] ?? 'bg-canvas' }} !py-0.5 !text-xs whitespace-nowrap">{!! icon_svg($typeIcons[$item->type] ?? 'file', 'size-3.5') !!}{{ __('ui.toolkit.type_one.'.$item->type) }}</span>
                                <span class="ml-auto text-muted transition sm:hidden" :class="open && 'rotate-180'">{!! icon_svg('chevron-down', 'size-6') !!}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xl font-extrabold">{{ loc($item->title) }}</span>
                                @if (filled(loc($item->summary)))<span class="mt-1 block text-muted">{{ loc($item->summary) }}</span>@endif
                            </span>
                            <span class="mt-1 hidden text-muted transition sm:block" :class="open && 'rotate-180'">{!! icon_svg('chevron-down', 'size-6') !!}</span>
                        </button>
                        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                            @if ($item->hasFile())
                                <a href="{{ route('toolkit.download', $item) }}" class="btn-ghost btn-sm w-full sm:w-auto" title="{{ __('ui.toolkit.file_meta', ['ext' => strtoupper($item->extension())]) }}">
                                    {!! icon_svg('file-down', 'size-4') !!}{{ __('ui.toolkit.download.'.$item->type) }} <span class="text-xs text-muted uppercase">.{{ $item->extension() }}</span>
                                </a>
                            @endif
                            @if ($item->acceptsSubmissions())
                                <button type="button" class="btn btn-sm w-full bg-brand text-on-brand sm:w-auto" @click="submitting = true">{!! icon_svg('send', 'size-4') !!}{{ __('ui.toolkit.submit') }}</button>
                            @endif
                        </div>
                    </div>

                    <div id="{{ $anchor($item) }}-body" x-show="open" x-collapse x-cloak>
                        <div class="border-t border-line p-5 sm:p-6">
                            @if ($hasGuidance)
                                <div class="grid gap-4 md:grid-cols-3">
                                    @foreach (['when_to_use' => ['calendar-clock', 'when_to_use'], 'roles_guidance' => ['users', 'roles'], 'example_usage' => ['lightbulb', 'example']] as $field => [$icon, $label])
                                        @if (filled(loc($item->$field)))
                                            <section class="rounded-2xl bg-canvas p-4">
                                                <p class="eyebrow flex items-center gap-2 !text-xs">{!! icon_svg($icon, 'size-4') !!}{{ __('ui.toolkit.'.$label) }}</p>
                                                <p class="mt-2 whitespace-pre-line">{{ loc($item->$field) }}</p>
                                            </section>
                                        @endif
                                    @endforeach
                                </div>
                                @if (! empty($item->related_codes))
                                    <div class="mt-5">
                                        <p class="eyebrow flex items-center gap-2 !text-xs">{!! icon_svg('link', 'size-4') !!}{{ __('ui.toolkit.related') }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($item->related_codes as $code)
                                                @if (isset($titles[$code]))
                                                    <a href="#{{ $anchor($items->firstWhere('code', $code)) }}" class="chip border border-line bg-white transition hover:border-ink" @click.prevent="jump(@js($code))">
                                                        {!! icon_svg('arrow-up-right', 'size-4') !!}<span class="font-extrabold">{{ $code }}</span><span class="font-semibold text-muted">{{ Str::limit($titles[$code], 60) }}</span>
                                                    </a>
                                                @else
                                                    <span class="chip bg-canvas text-muted">{{ $code }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                            @unless ($item->hasFile())
                                <p @class(['text-sm text-muted', 'mt-5' => $hasGuidance])>{{ __('ui.toolkit.no_file') }}</p>
                            @endunless
                        </div>
                    </div>

                    @if ($item->acceptsSubmissions())
                        <div x-show="submitting" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center" @keydown.escape.window="submitting = false" @click.self="submitting = false" role="dialog" aria-modal="true" aria-labelledby="{{ $anchor($item) }}-submit-title">
                            <form method="POST" action="{{ route('toolkit.submit', $item) }}" enctype="multipart/form-data" class="card max-h-[90vh] w-full max-w-lg overflow-y-auto shadow-2xl">
                                @csrf
                                <input type="hidden" name="toolkit_item_id" value="{{ $item->id }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <span class="chip bg-header text-white">{{ $item->code }}</span>
                                        <h2 id="{{ $anchor($item) }}-submit-title" class="mt-3 text-2xl">{{ __('ui.toolkit.submit_form.title') }}</h2>
                                        <p class="mt-1 text-muted">{{ loc($item->title) }}</p>
                                    </div>
                                    <button type="button" class="rounded-xl p-2 text-muted hover:text-ink" @click="submitting = false" aria-label="{{ __('ui.action.close') }}">{!! icon_svg('x', 'size-6') !!}</button>
                                </div>
                                <div class="mt-6 space-y-5">
                                    <div>
                                        <label class="label" for="file-{{ $item->id }}">{{ __('ui.toolkit.submit_form.file') }} *</label>
                                        <input class="field py-2.5" id="file-{{ $item->id }}" type="file" name="file" required accept=".docx,.xlsx,.pdf,.zip,.doc,.xls">
                                        <p class="mt-1 text-sm text-muted">{{ __('ui.toolkit.submit_form.file_hint') }}</p>
                                        @if ($openId === $item->id) @error('file')<p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>@enderror @endif
                                    </div>
                                    <div>
                                        <label class="label" for="note-{{ $item->id }}">{{ __('ui.toolkit.submit_form.note') }}</label>
                                        <input class="field" id="note-{{ $item->id }}" name="reference_note" maxlength="255" value="{{ $openId === $item->id ? old('reference_note') : '' }}" placeholder="{{ __('ui.toolkit.submit_form.note_hint') }}">
                                        @if ($openId === $item->id) @error('reference_note')<p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>@enderror @endif
                                    </div>
                                    <p class="flex gap-3 rounded-2xl bg-canvas p-4 text-sm">
                                        <span class="shrink-0 text-muted">{!! icon_svg($canSend && $recipient ? 'send' : 'archive', 'size-5') !!}</span>
                                        <span>
                                            @if ($recipient && $canSend) {{ __('ui.toolkit.submit_form.auto', ['email' => $recipient]) }}
                                            @elseif ($recipient) {{ __('ui.toolkit.submit_form.manual', ['email' => $recipient]) }}
                                                <a class="mt-1 block font-bold underline" href="mailto:{{ $recipient }}?subject={{ rawurlencode(__('ui.toolkit.mailto_subject', ['code' => $item->code, 'title' => loc($item->title)])) }}">{{ __('ui.toolkit.submit_form.mailto') }}</a>
                                            @else {{ __('ui.toolkit.submit_form.store_only') }}
                                            @endif
                                        </span>
                                    </p>
                                </div>
                                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <button type="button" class="btn-ghost btn-sm" @click="submitting = false">{{ __('ui.action.cancel') }}</button>
                                    <button class="btn-primary btn-sm">{!! icon_svg('send', 'size-4') !!}{{ __('ui.toolkit.submit_form.send') }}</button>
                                </div>
                            </form>
                        </div>
                    @endif
                </article>
            @endforeach

            <div class="card text-center text-muted" x-show="visibleCount === 0" @style(['display: none' => $visibleIds !== []]) x-cloak>
                <p class="font-bold">{{ __('ui.toolkit.no_results') }}</p>
                <button type="button" class="btn-ghost btn-sm mt-4" @click="reset()">{{ __('ui.toolkit.all') }}</button>
            </div>
        </div>
    @endif

    {{-- Submission history: your own, and for HR / admins everyone's. --}}
    <section id="submissions" class="mt-14 scroll-mt-6">
        <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('history', 'size-6') !!}{{ __('ui.toolkit.my_submissions') }}</h2>
        @include('toolkit._submissions', ['rows' => $mySubmissions, 'showPerson' => false])
    </section>

    @if ($allSubmissions !== null)
        <section class="mt-10">
            <h2 class="flex items-center gap-3 text-2xl">{!! icon_svg('inbox', 'size-6') !!}{{ __('ui.toolkit.all_submissions') }}</h2>
            <p class="mt-1 text-muted">{{ __('ui.toolkit.all_submissions_lead') }}</p>
            @include('toolkit._submissions', ['rows' => $allSubmissions, 'showPerson' => true])
        </section>
    @endif
</div>
@endsection
