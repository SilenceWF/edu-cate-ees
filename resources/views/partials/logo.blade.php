{{-- The logo alone when one is set (it normally contains the name); otherwise the company name. --}}
@php($logoUrl = app(\App\Support\Logo::class)->url())
@php($companyName = setting('company.name', config('app.name')))
<span class="flex items-center gap-3">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="{{ $size ?? 'h-11' }} w-auto max-w-48 object-contain">
    @else
        <span class="text-xl font-extrabold tracking-tight">{{ $companyName }}</span>
    @endif
</span>
