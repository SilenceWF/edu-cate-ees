@extends('layouts.app', ['title' => __('admin.content.'.$type)])
@include('admin._nav')

@php
    $val = fn (string $field, string $locale) => old("$field.$locale", is_array($item->$field) ? ($item->$field[$locale] ?? '') : '');
    $multi = count($locales) > 1;
@endphp

@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.content') }}" class="inline-flex items-center gap-2 font-bold text-muted hover:text-ink">{!! icon_svg('arrow-left', 'size-5') !!}{{ __('ui.action.back') }}</a>
    <h1 class="mt-5 text-4xl">{{ $item->exists ? __('ui.action.edit') : __('ui.action.add') }}: {{ __('admin.content.single.'.$type) }}</h1>

    <form method="POST" enctype="multipart/form-data" class="mt-6 space-y-6"
          action="{{ $item->exists ? route('admin.content.update', [$type, $item->id]) : route('admin.content.store', $type) }}"
          @if ($type === 'modules') x-data="{ slides: @js($slides ?: [['title' => [], 'icon' => 'shield-check', 'summary' => [], 'bullets' => [], 'callout' => []]]), locales: @js($locales),
              add() { this.slides.push({ title: {}, icon: 'circle-check', summary: {}, bullets: {}, callout: {} }) },
              move(i, d) { const j = i + d; if (j < 0 || j >= this.slides.length) return; [this.slides[i], this.slides[j]] = [this.slides[j], this.slides[i]] } }" @endif>
        @csrf @if ($item->exists) @method('PUT') @endif

        @if ($type === 'policies')
            <div class="card grid gap-5 sm:grid-cols-3">
                <div><label class="label" for="code">{{ __('admin.content.code') }} *</label><input class="field" id="code" name="code" value="{{ old('code', $item->code) }}" required></div>
                <div><label class="label" for="version">{{ __('admin.cols.version') }} *</label><input class="field" id="version" name="version" value="{{ old('version', $item->version ?? '1') }}" required></div>
                <div><label class="label" for="effective_date">{{ __('admin.content.effective') }}</label><input class="field" id="effective_date" type="date" name="effective_date" value="{{ old('effective_date', $item->effective_date?->toDateString()) }}"></div>
                @if ($item->exists)<p class="rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-900 sm:col-span-3">{{ __('admin.content.version_hint') }}</p>@endif
                @foreach ($locales as $l)
                    <div class="sm:col-span-3"><label class="label" for="title_{{ $l }}">{{ __('admin.content.title') }} @if ($multi)<span class="text-muted uppercase">({{ $l }})</span>@endif</label><input class="field" id="title_{{ $l }}" name="title[{{ $l }}]" value="{{ $val('title', $l) }}"></div>
                @endforeach
                @foreach ($locales as $l)
                    <div class="sm:col-span-3"><label class="label" for="rules_{{ $l }}">{{ __('admin.content.key_rules') }} @if ($multi)<span class="text-muted uppercase">({{ $l }})</span>@endif</label>
                        <textarea class="field py-3" id="rules_{{ $l }}" name="rules[{{ $l }}]" rows="4">{{ old("rules.$l", $rules[$l] ?? '') }}</textarea></div>
                @endforeach
                <p class="-mt-3 text-sm text-muted sm:col-span-3">{{ __('admin.content.one_per_line') }}</p>
                <div class="sm:col-span-2"><label class="label" for="document_url">{{ __('admin.content.document_url') }}</label><input class="field" id="document_url" type="url" name="document_url" value="{{ old('document_url', $item->documentIsExternal() ? $item->document_url : '') }}" placeholder="https://…"></div>
                <div><label class="label" for="document_file">{{ __('admin.content.document_file') }}</label><input class="field py-2.5" id="document_file" type="file" name="document_file" accept="application/pdf">
                    @if ($item->hasDocument() && ! $item->documentIsExternal())<p class="mt-1 text-sm text-muted">{{ $item->document_url }}</p>@endif</div>
                <label class="flex items-center gap-3 font-bold sm:col-span-3"><input type="hidden" name="is_mandatory_all" value="0"><input type="checkbox" name="is_mandatory_all" value="1" class="size-6 accent-[var(--brand)]" @checked(old('is_mandatory_all', $item->is_mandatory_all ?? true))>{{ __('admin.content.mandatory_all') }}</label>
                <fieldset class="sm:col-span-3"><legend class="label">{{ __('admin.content.only_tracks') }}</legend><div class="flex flex-wrap gap-4">
                    @foreach ($tracks as $t)<label class="flex items-center gap-2"><input type="checkbox" name="track_keys[]" value="{{ $t->key }}" class="size-5 accent-[var(--brand)]" @checked(in_array($t->key, old('track_keys', $item->track_keys ?? [])))>{{ loc($t->name) }}</label>@endforeach
                </div></fieldset>
            </div>
        @elseif ($type === 'toolkit')
            <div class="card grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2"><label class="label" for="code">{{ __('admin.content.code') }} *</label><input class="field" id="code" name="code" value="{{ old('code', $item->code) }}" required maxlength="50"><p class="mt-1 text-sm text-muted">{{ __('admin.content.code_hint') }}</p></div>
                <div><label class="label" for="type">{{ __('admin.content.type') }} *</label><select class="field" id="type" name="type" required>
                    @foreach (\App\Models\ToolkitItem::TYPES as $t)<option value="{{ $t }}" @selected(old('type', $item->type ?? 'form') === $t)>{{ __('ui.toolkit.type_one.'.$t) }}</option>@endforeach</select></div>
                @foreach ($locales as $l)
                    <div class="sm:col-span-3"><label class="label" for="title_{{ $l }}">{{ __('admin.content.title') }} @if ($multi)<span class="text-muted uppercase">({{ $l }})</span>@endif</label><input class="field" id="title_{{ $l }}" name="title[{{ $l }}]" value="{{ $val('title', $l) }}"></div>
                @endforeach
                @foreach (['summary' => 2, 'when_to_use' => 3, 'roles_guidance' => 3, 'example_usage' => 4] as $field => $rows)
                    @foreach ($locales as $l)
                        <div class="sm:col-span-3"><label class="label" for="{{ $field }}_{{ $l }}">{{ __('admin.content.'.$field) }} @if ($multi)<span class="text-muted uppercase">({{ $l }})</span>@endif</label>
                            <textarea class="field py-3" id="{{ $field }}_{{ $l }}" name="{{ $field }}[{{ $l }}]" rows="{{ $rows }}">{{ $val($field, $l) }}</textarea></div>
                    @endforeach
                @endforeach
                <div class="sm:col-span-3"><label class="label" for="related_codes">{{ __('admin.content.related_codes') }}</label><input class="field" id="related_codes" name="related_codes" value="{{ old('related_codes', implode(', ', $item->related_codes ?? [])) }}"><p class="mt-1 text-sm text-muted">{{ __('admin.content.related_hint') }}</p></div>
                <div class="sm:col-span-3"><label class="label" for="template_file">{{ __('admin.content.template_file') }}</label>
                    <input class="field py-2.5" id="template_file" type="file" name="template_file" accept=".{{ implode(',.', \App\Models\ToolkitItem::TEMPLATE_EXTENSIONS) }}">
                    <p class="mt-1 text-sm text-muted">{{ __('admin.content.template_hint') }}</p>
                    @if ($item->hasFile())
                        <div class="mt-3 flex flex-wrap items-center gap-4 rounded-2xl bg-canvas p-3 text-sm">
                            <span class="flex items-center gap-2 font-bold">{!! icon_svg('file', 'size-4') !!}{{ $item->original_filename ?: $item->file_path }}</span>
                            <a class="font-bold hover:underline" href="{{ route('toolkit.download', $item) }}">{{ __('ui.action.download') }}</a>
                            <label class="flex items-center gap-2"><input type="checkbox" name="remove_file" value="1" class="size-5 accent-[var(--brand)]">{{ __('admin.content.remove_file') }}</label>
                        </div>
                    @endif
                </div>
            </div>
        @elseif ($type === 'modules')
            <div class="card grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2"><label class="label" for="key">{{ __('admin.content.key') }} *</label><input class="field font-mono" id="key" name="key" value="{{ old('key', $item->key) }}" @disabled($item->exists) required><p class="mt-1 text-sm text-muted">{{ __('admin.content.key_hint') }}</p></div>
                <div><label class="label" for="minutes">{{ __('admin.content.minutes') }} *</label><input class="field" id="minutes" type="number" min="1" name="estimated_minutes" value="{{ old('estimated_minutes', $item->estimated_minutes ?? 5) }}" required></div>
                @foreach ($locales as $l)
                    <div class="sm:col-span-3"><label class="label" for="title_{{ $l }}">{{ __('admin.content.title') }} @if ($multi)<span class="text-muted uppercase">({{ $l }})</span>@endif</label><input class="field" id="title_{{ $l }}" name="title[{{ $l }}]" value="{{ $val('title', $l) }}"></div>
                @endforeach
                @foreach ($locales as $l)
                    <div class="sm:col-span-3"><label class="label" for="subtitle_{{ $l }}">{{ __('admin.content.subtitle') }} @if ($multi)<span class="text-muted uppercase">({{ $l }})</span>@endif</label><input class="field" id="subtitle_{{ $l }}" name="subtitle[{{ $l }}]" value="{{ $val('subtitle', $l) }}"></div>
                @endforeach
                <fieldset class="sm:col-span-3"><legend class="label">{{ __('admin.content.tracks') }}</legend><div class="flex flex-wrap gap-4">
                    @foreach ($tracks as $t)<label class="flex items-center gap-2"><input type="checkbox" name="tracks[]" value="{{ $t->id }}" class="size-5 accent-[var(--brand)]" @checked(in_array($t->id, old('tracks', $item->exists ? $item->tracks->pluck('id')->all() : [])))>{{ loc($t->name) }}</label>@endforeach
                </div></fieldset>
            </div>

            <input type="hidden" name="slides_json" :value="JSON.stringify(slides)">
            <template x-for="(s, i) in slides" :key="i">
                <div class="card space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-xl">{{ __('admin.content.slide') }} <span x-text="i + 1"></span></h2>
                        <div class="flex gap-1">
                            <button type="button" class="btn-ghost btn-sm !px-3" @click="move(i, -1)" :disabled="i === 0" aria-label="{{ __('admin.content.move_up') }}">{!! icon_svg('arrow-up', 'size-4') !!}</button>
                            <button type="button" class="btn-ghost btn-sm !px-3" @click="move(i, 1)" :disabled="i === slides.length - 1" aria-label="{{ __('admin.content.move_down') }}">{!! icon_svg('arrow-down', 'size-4') !!}</button>
                            <button type="button" class="btn-ghost btn-sm !px-3 text-red-700" @click="slides.splice(i, 1)" :disabled="slides.length === 1" aria-label="{{ __('ui.action.delete') }}">{!! icon_svg('trash-2', 'size-4') !!}</button>
                        </div>
                    </div>
                    <div><label class="label">{{ __('admin.content.icon') }}</label><input class="field font-mono" x-model="s.icon" placeholder="shield-check"><p class="mt-1 text-sm text-muted">{{ __('admin.content.icon_hint') }}</p></div>
                    <template x-for="l in locales" :key="l">
                        <div class="grid gap-4 rounded-2xl bg-canvas p-4">
                            <p class="eyebrow" x-show="locales.length > 1" x-text="l"></p>
                            <div><label class="label">{{ __('admin.content.title') }}</label><input class="field" x-model="s.title[l]"></div>
                            <div><label class="label">{{ __('admin.content.summary') }}</label><textarea class="field py-3" rows="2" x-model="s.summary[l]"></textarea></div>
                            <div><label class="label">{{ __('admin.content.bullets') }}</label><textarea class="field py-3" rows="4" x-model="s.bullets[l]"></textarea></div>
                            <div><label class="label">{{ __('admin.content.callout') }}</label><input class="field" x-model="s.callout[l]"></div>
                        </div>
                    </template>
                </div>
            </template>
            <button type="button" class="btn-ghost w-full" @click="add()">{!! icon_svg('plus', 'size-5') !!}{{ __('admin.content.add_slide') }}</button>
        @else
            <div class="card grid gap-5">
                <div><label class="label" for="track_id">{{ __('admin.cols.track') }} *</label><select class="field" id="track_id" name="track_id" required>
                    @foreach ($tracks->whereNull('quiz_from') as $t)<option value="{{ $t->id }}" @selected(old('track_id', $item->track_id) == $t->id)>{{ loc($t->name) }}</option>@endforeach</select></div>
                @foreach ($locales as $l)
                    <div><label class="label" for="q_{{ $l }}">{{ __('admin.content.question') }} @if ($multi)<span class="text-muted uppercase">({{ $l }})</span>@endif</label><textarea class="field py-3" id="q_{{ $l }}" name="question_text[{{ $l }}]" rows="3">{{ $val('question_text', $l) }}</textarea></div>
                @endforeach
                @foreach (['A', 'B', 'C', 'D'] as $letter)
                    <fieldset class="rounded-2xl bg-canvas p-4"><legend class="float-left mr-3 flex size-9 items-center justify-center rounded-xl bg-header font-extrabold text-white">{{ $letter }}</legend>
                        <div class="grid gap-3">
                            @foreach ($locales as $l)<input class="field" name="options[{{ $letter }}][{{ $l }}]" value="{{ old("options.$letter.$l", is_array($item->options[$letter] ?? null) ? ($item->options[$letter][$l] ?? '') : ($l === setting('default_locale') ? ($item->options[$letter] ?? '') : '')) }}" placeholder="{{ __('admin.content.option') }} {{ $letter }}{{ $multi ? ' ('.strtoupper($l).')' : '' }}" aria-label="{{ __('admin.content.option') }} {{ $letter }} {{ $l }}">@endforeach
                        </div></fieldset>
                @endforeach
                <div><label class="label" for="correct_option">{{ __('admin.content.correct') }} *</label><select class="field !w-32" id="correct_option" name="correct_option">
                    @foreach (['A', 'B', 'C', 'D'] as $letter)<option @selected(old('correct_option', $item->correct_option) === $letter)>{{ $letter }}</option>@endforeach</select></div>
                @foreach ($locales as $l)
                    <div><label class="label" for="e_{{ $l }}">{{ __('admin.content.explanation') }} @if ($multi)<span class="text-muted uppercase">({{ $l }})</span>@endif</label><textarea class="field py-3" id="e_{{ $l }}" name="explanation[{{ $l }}]" rows="3">{{ $val('explanation', $l) }}</textarea></div>
                @endforeach
            </div>
        @endif

        <div class="card flex flex-wrap items-center justify-between gap-4">
            <label class="flex items-center gap-3 font-bold"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="size-6 accent-[var(--brand)]" @checked(old('is_active', $item->is_active ?? true))>{{ __('admin.content.active') }}</label>
            <button class="btn-primary">{!! icon_svg('save', 'size-5') !!}{{ __('ui.action.save') }}</button>
        </div>
    </form>
</div>
@endsection
