{{-- Printable list of kiosk employee codes for the supervisor. Shared by the kiosk start page and Audit exports. --}}
<form method="GET" action="{{ route('kiosk-codes') }}" class="{{ $class ?? 'card' }} flex flex-col text-left">
    <span class="flex size-14 items-center justify-center rounded-2xl bg-header text-white">{!! icon_svg('key-round', 'size-7') !!}</span>
    <h2 class="mt-4 text-2xl">{{ __('ui.kiosk.codes.title') }}</h2>
    <p class="mt-2 flex-1 text-muted">{{ __('ui.kiosk.codes.lead') }}</p>
    <label class="label mt-4" for="kc-department">{{ __('admin.cols.department') }}</label>
    <select class="field" id="kc-department" name="department">
        <option value="">{{ __('ui.kiosk.all_departments') }}</option>
        @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
    </select>
    <label class="mt-3 flex items-center gap-3 font-semibold"><input type="checkbox" name="pending" value="1" checked class="size-5 accent-[var(--brand)]">{{ __('ui.kiosk.codes.pending_only') }}</label>
    <button class="btn-dark mt-5">{!! icon_svg('download', 'size-5') !!}PDF (A4)</button>
    <p class="mt-3 flex items-start gap-2 text-sm text-muted">{!! icon_svg('shield-alert', 'size-4 mt-0.5 shrink-0') !!}{{ __('ui.kiosk.codes.confidential_hint') }}</p>
</form>
