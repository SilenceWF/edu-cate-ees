@extends('layouts.app', ['title' => __('ui.steps.policies.title')])

@php($ids = $p['required_policies']->pluck('id')->all())

@section('content')
<a href="{{ route('home') }}" class="inline-flex items-center gap-2 font-bold text-muted hover:text-ink">{!! icon_svg('arrow-left', 'size-5') !!}{{ __('ui.action.back') }}</a>
<p class="eyebrow mt-6">{{ __('ui.home.step', ['n' => 1]) }}</p>
<h1 class="mt-2 text-4xl sm:text-5xl">{{ __('ui.steps.policies.title') }}</h1>
<p class="mt-4 max-w-2xl text-lg text-muted">{{ $p['policies_done'] ? __('ui.policies.done_lead', ['date' => local_dt($p['policies_signed_at'])]) : __('ui.policies.lead') }}</p>

<form method="POST" action="{{ route('policies.sign') }}" class="mt-8"
      x-data="{ acked: @js($p['policies_done'] ? $ids : array_map('intval', old('policies', $p['acknowledged_ids']))), all: @js($ids), declared: false,
                toggle(id) { this.acked.includes(id) ? this.acked = this.acked.filter(i => i !== id) : this.acked.push(id) },
                get ready() { return this.all.every(i => this.acked.includes(i)) } }">
    @csrf
    <div class="space-y-4">
        @foreach ($p['required_policies'] as $policy)
            <article class="card !p-0" x-data="{ open: false }" :class="acked.includes({{ $policy->id }}) && 'border-2 !border-emerald-400'">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
                    <button type="button" class="flex flex-1 items-start gap-4 text-left" @click="open = !open" :aria-expanded="open">
                        <span class="chip shrink-0 bg-header text-white">{{ $policy->code }}</span>
                        <span class="flex-1">
                            <span class="block text-xl font-extrabold">{{ loc($policy->title) }}</span>
                            <span class="text-sm text-muted">{{ __('ui.policies.version', ['version' => $policy->version]) }}@if ($policy->effective_date) · {{ __('ui.policies.effective', ['date' => $policy->effective_date->format('d/m/Y')]) }}@endif</span>
                        </span>
                        <span class="mt-1 text-muted transition" :class="open && 'rotate-180'">{!! icon_svg('chevron-down', 'size-6') !!}</span>
                    </button>
                    @unless ($p['policies_done'])
                        <input type="checkbox" class="sr-only" name="policies[]" value="{{ $policy->id }}" :checked="acked.includes({{ $policy->id }})" tabindex="-1" aria-hidden="true">
                        <button type="button" class="btn btn-sm !min-h-12 w-full shrink-0 sm:w-auto" @click="toggle({{ $policy->id }}); open = false"
                                :class="acked.includes({{ $policy->id }}) ? 'bg-emerald-100 text-emerald-800' : 'bg-brand text-on-brand'" :aria-pressed="acked.includes({{ $policy->id }})">
                            <span x-show="acked.includes({{ $policy->id }})" class="flex items-center gap-2">{!! icon_svg('check', 'size-5') !!}{{ __('ui.policies.acked') }}</span>
                            <span x-show="!acked.includes({{ $policy->id }})">{{ __('ui.policies.ack') }}</span>
                        </button>
                    @else
                        <span class="chip bg-emerald-100 text-emerald-800">{!! icon_svg('check', 'size-4') !!}{{ __('ui.policies.acked') }}</span>
                    @endunless
                </div>
                <div x-show="open" x-collapse x-cloak>
                    <div class="border-t border-line p-5 sm:p-6">
                        <p class="eyebrow">{{ __('ui.policies.key_rules') }}</p>
                        <ul class="mt-3 space-y-3 text-lg">
                            @foreach ($policy->key_rules ?? [] as $rule)
                                <li class="flex gap-3"><span class="mt-1 text-brand-dark">{!! icon_svg('circle-check', 'size-6') !!}</span><span>{{ loc($rule) }}</span></li>
                            @endforeach
                        </ul>
                        @if ($policy->hasDocument())
                            <a href="{{ route('policies.document', $policy) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm mt-5">{!! icon_svg('file-text', 'size-4') !!}{{ __('ui.policies.view_document') }}</a>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    @unless ($p['policies_done'])
        <div class="sticky bottom-4 mt-8 rounded-3xl bg-header p-6 text-white shadow-2xl sm:p-8">
            <p class="font-bold text-white/70" x-text="`${acked.filter(i => all.includes(i)).length} / ${all.length} {{ __('ui.policies.count_suffix') }}`"></p>
            <label class="mt-3 flex cursor-pointer items-start gap-4" :class="!ready && 'opacity-50'">
                <input type="checkbox" name="declaration" value="1" x-model="declared" :disabled="!ready" class="mt-1 size-7 shrink-0 rounded-lg accent-[var(--brand)]">
                <span class="text-lg leading-snug font-semibold">{{ loc(setting('declaration')) ?: __('ui.policies.default_declaration') }}</span>
            </label>
            <button class="btn-primary mt-6 w-full sm:w-auto" :disabled="!ready || !declared">{!! icon_svg('pen-line', 'size-5') !!}{{ __('ui.policies.submit') }}</button>
        </div>
    @endunless
</form>
@endsection
