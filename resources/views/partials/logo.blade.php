@php($logoUrl = app(\App\Support\Logo::class)->url())
<span class="flex items-center gap-3">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="" class="{{ $size ?? 'h-10' }} w-auto max-w-44 object-contain">
    @endif
    <span class="text-xl font-extrabold tracking-tight">{{ setting('company.name', config('app.name')) }}</span>
</span>
