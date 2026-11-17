@if (session('status'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border-2 border-emerald-200 bg-emerald-50 px-5 py-4 font-semibold text-emerald-900" role="status">
        {!! icon_svg('circle-check', 'size-6 shrink-0') !!}<span>{{ session('status') }}</span>
    </div>
@endif
@if ($errors->any())
    <div class="mb-6 rounded-2xl border-2 border-red-200 bg-red-50 px-5 py-4 font-semibold text-red-900" role="alert">
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li class="flex items-start gap-3">{!! icon_svg('triangle-alert', 'size-6 shrink-0') !!}<span>{{ $error }}</span></li>
            @endforeach
        </ul>
    </div>
@endif
