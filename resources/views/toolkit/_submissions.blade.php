@if ($rows->isEmpty())
    <p class="card mt-4 text-muted">{{ __('ui.toolkit.no_submissions') }}</p>
@else
    <div class="card mt-4 overflow-x-auto !p-0">
        <table class="table-clean">
            <thead><tr>
                <th>{{ __('ui.toolkit.cols.date') }}</th>
                @if ($showPerson)<th>{{ __('ui.toolkit.cols.person') }}</th>@endif
                <th>{{ __('ui.toolkit.cols.item') }}</th>
                <th>{{ __('ui.toolkit.cols.reference') }}</th>
                <th>{{ __('ui.toolkit.cols.status') }}</th>
                <th class="text-right">{{ __('ui.toolkit.cols.file') }}</th>
            </tr></thead>
            <tbody>
                @foreach ($rows as $s)
                    <tr>
                        <td class="whitespace-nowrap">{{ local_dt($s->submitted_at) }}</td>
                        @if ($showPerson)<td class="font-bold">{{ $s->user?->name }}</td>@endif
                        <td><span class="chip bg-header !text-xs whitespace-nowrap text-white">{{ $s->item?->code }}</span> <span class="font-bold">{{ loc($s->item?->title) }}</span></td>
                        <td class="text-muted">{{ $s->reference_note ?: '—' }}</td>
                        <td>
                            @if ($s->emailed_to)
                                <span class="chip bg-emerald-100 !text-xs text-emerald-800">{!! icon_svg('check', 'size-3.5') !!}{{ __('ui.toolkit.emailed', ['email' => $s->emailed_to]) }}</span>
                            @else
                                <span class="chip bg-canvas !text-xs text-muted">{!! icon_svg('archive', 'size-3.5') !!}{{ __('ui.toolkit.stored') }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a class="inline-flex max-w-56 items-center gap-1.5 font-bold hover:underline" href="{{ route('toolkit.submissions.download', $s) }}" title="{{ $s->original_filename }}">
                                {!! icon_svg('download', 'size-4 shrink-0') !!}<span class="truncate">{{ $s->original_filename }}</span>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
