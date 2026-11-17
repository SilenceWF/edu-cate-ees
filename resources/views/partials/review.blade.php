{{-- Per-question review of a quiz attempt. Expects $review. --}}
<div class="space-y-4">
    @foreach ($review as $n => $r)
        <article @class(['card !p-6', 'border-2 !border-emerald-300' => $r['is_correct'], 'border-2 !border-red-300' => ! $r['is_correct']])>
            <div class="flex items-start gap-4">
                <span @class(['flex size-10 shrink-0 items-center justify-center rounded-xl', 'bg-emerald-100 text-emerald-700' => $r['is_correct'], 'bg-red-100 text-red-700' => ! $r['is_correct']])>{!! icon_svg($r['is_correct'] ? 'check' : 'x', 'size-6') !!}</span>
                <h3 class="text-lg leading-snug sm:text-xl">{{ $n + 1 }}. {{ $r['question'] }}</h3>
            </div>
            <ul class="mt-4 space-y-2">
                @foreach ($r['options'] as $letter => $text)
                    <li @class(['flex items-start gap-3 rounded-xl px-4 py-3 font-semibold',
                        'bg-emerald-50 text-emerald-900' => $letter === $r['correct'],
                        'bg-red-50 text-red-900 line-through decoration-red-300' => $letter === $r['selected'] && ! $r['is_correct'],
                        'text-muted' => $letter !== $r['correct'] && $letter !== $r['selected']])>
                        <span class="font-extrabold">{{ $letter }}</span><span class="flex-1">{{ $text }}</span>
                        @if ($letter === $r['correct'])<span class="chip bg-emerald-200 text-emerald-900 !py-0.5 no-underline">{{ __('ui.quiz.correct') }}</span>@endif
                    </li>
                @endforeach
            </ul>
            @if ($r['explanation'])
                <p class="mt-4 flex gap-3 rounded-xl bg-canvas p-4">{!! icon_svg('lightbulb', 'size-6 shrink-0 text-brand-dark') !!}<span>{{ $r['explanation'] }}</span></p>
            @endif
        </article>
    @endforeach
</div>
